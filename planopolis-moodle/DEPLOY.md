# Hosting Planopolis on a VPS

Deployment for a Debian or Ubuntu VPS, sized for up to ~200 participants taking the
quiz at the same time (2 vCPU / 4 GB RAM).

The stack that ends up running:

```
internet --> :80/:443  Caddy (proxy, HTTPS)  --> moodle:80  --> db:5432
                                                  cron
```

Only Caddy is reachable from the internet. Moodle is published on `127.0.0.1:8080`
for local checks, and PostgreSQL is not published at all.

---

## 1. Get the project onto the server

The project lives at <https://github.com/zan4yov/Planopolis-2026>. Clone it on the VPS:

```bash
ssh root@<vps-ip>
apt-get update && apt-get install -y git
git clone https://github.com/zan4yov/Planopolis-2026.git /opt/planopolis
cd /opt/planopolis/planopolis-moodle
```

Cloning is the preferred route over `scp`, because every later client revision is then
a `git pull` instead of another copy, and because `.env` is not tracked -- a clone can
never carry your laptop's development passwords onto the server.

## 2. Deploy

```bash
ssh root@<vps-ip>
cd /opt/planopolis/planopolis-moodle
chmod +x deploy.sh manage.sh
sudo ./deploy.sh
```

The script installs Docker, adds a 2 GB swap file, opens only ports 22/80/443 in the
firewall, generates the database and Super Admin passwords, builds the image and waits
until Moodle answers. First run takes roughly 5–15 minutes, mostly downloading Moodle.

It finishes by printing the address, username and password. **Save the password** —
it is also in `/opt/planopolis/planopolis-moodle/.env`, which is `chmod 600`.

Running `./deploy.sh` again is safe: it keeps your existing `.env` and upgrades in place.

## 3. Check it

Open `http://<vps-ip>` and log in as the Super Admin, then:

- *Planopolis panel* appears in the top menu
- *Register participants* accepts the Excel template
- Create one test participant and take the quiz as them

## 4. Day-to-day

```bash
./manage.sh status          # containers, disk, memory
./manage.sh logs moodle     # follow a container's log
./manage.sh backup          # database dump + uploaded files, into ./backups
./manage.sh restore backups/db_2026-10-05_1430.dump
./manage.sh update          # pull the latest revision, then rebuild (backs up first)
./manage.sh rebuild         # rebuild without pulling (after a rollback)
```

Back up before and after the event, and copy the files off the server:

```bash
scp root@<vps-ip>:/opt/planopolis/planopolis-moodle/backups/\* ./
```

## 5. Applying a client revision

The loop once the site is live:

```bash
# on your machine
git add -A && git commit -m "Adjust scoring per client feedback" && git push

# on the VPS
cd /opt/planopolis/planopolis-moodle
./manage.sh update
```

`./manage.sh update` backs up the database and files first, pulls the new revision with
`--ff-only`, rebuilds the image and restarts. Moodle runs any plugin upgrade itself on
start; follow it with `./manage.sh logs moodle`.

Things worth knowing about this loop:

- **Never edit files directly on the VPS.** `git pull --ff-only` refuses to merge, so a
  local edit there stops the next update until you undo it with `git checkout -- <file>`.
  Change things on your machine, push, pull.
- **`.env` is yours alone.** It is untracked, so a pull never overwrites your passwords
  or the site address.
- **Bump the plugin version for schema changes.** If a revision touches
  `plugin/local_planopolis/db/`, raise `$plugin->version` in
  `plugin/local_planopolis/version.php`, or Moodle will not run the upgrade.
- **Rehearse risky revisions.** Close to the event, test a change on a second VPS (or
  locally) before updating the live one.
- **Revisions during the event are not worth the risk.** If one cannot wait, take a
  backup with `./manage.sh backup` immediately beforehand.

To go back to the previous revision:

```bash
git log --oneline -5          # find the commit that worked
git checkout <commit>         # detached HEAD
./manage.sh rebuild           # rebuild without pulling
```

Use `rebuild`, not `update`, here: a detached HEAD has nothing to pull and `update`
would stop. Return to the latest revision with `git checkout main && ./manage.sh update`.

If a revision corrupted data rather than code, restore the database instead:
`./manage.sh restore backups/db_<stamp>.dump`.

---

## About HTTPS

Your current setup serves plain **HTTP on the IP address**. That works, but everything
travels unencrypted, including the Super Admin password you type on exam day and every
participant password. Anyone sharing a network with a user — a venue's Wi-Fi, a campus
LAN — can read them, and browsers show "Not secure" on the login page.

You do not need to buy a domain to fix this. Two options:

**A free hostname that maps to your IP.** `sslip.io` resolves any
`<your-ip>.sslip.io` to that IP, and Caddy can get a real Let's Encrypt certificate
for it:

```bash
sudo SITE_ADDRESS=203.0.113.45.sslip.io ./deploy.sh      # your IP, then .sslip.io
```

The site is then `https://203.0.113.45.sslip.io`. The trade-off: it depends on the
public sslip.io DNS service staying up. If it were down during the exam, participants
could not resolve the name — though the raw IP over HTTP would still work as a fallback.

**Your own domain** — the robust choice, and an `.my.id` domain costs very little.
Point an A record at the VPS, wait for DNS, then:

```bash
./manage.sh enable-https quiz.example.com
```

That checks DNS resolves to this server before touching anything, then redeploys with
automatic certificate renewal.

Either way, set the address **before** the event. Moodle stores absolute URLs in page
content, so changing it after participants and questions are loaded means running
`./manage.sh fix-urls` and re-checking the site.

---

## Capacity

The tuning in `docker-compose.prod.yml` and `docker/apache-mpm.conf` assumes 4 GB RAM:
40 Apache workers, 150 PostgreSQL connections. Under a burst, requests queue instead of
exhausting memory — slow beats crashed.

**On 1 vCPU, lower the worker count.** PHP here is CPU-bound, so 40 workers on a
single core thrash rather than serve: set `MaxRequestWorkers` to about 16 in
`docker/apache-mpm.conf`, and treat 2 vCPU as the real minimum for 150-200
concurrent participants.

For 300–500 participants, move to 4 vCPU / 8 GB and raise `MaxRequestWorkers` to ~80
and `shared_buffers` to 2GB. Whatever the number, **run a rehearsal** with your real
participant count before the event; the README's *Exam day* section covers this.

## Troubleshooting

| Symptom | Check |
|---|---|
| `./deploy.sh` ends with "has not answered yet" | `./manage.sh logs moodle` — usually still installing, or `DB_PASS` contains `$` |
| Browser cannot connect | `./manage.sh status`, then `ufw status`, and your provider's own firewall / security group |
| HTTPS certificate fails | DNS must point at this server and port 80 must be reachable; `./manage.sh logs caddy` |
| `server block without any key` from Caddy | `CADDY_ADDRESS` in `.env` is empty. It must be `:80` or a hostname; re-run `sudo ./deploy.sh` to set it |
| Logged-in pages look broken | `MOODLE_URL` in `.env` must exactly match what you type in the browser |
| Site slow under load | `./manage.sh status` for memory; raise `MaxRequestWorkers` only if RAM allows |
