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

## 1. Copy the project to the server

From your machine, in `Planopolis - 2/`:

```bash
scp -r planopolis-moodle root@<vps-ip>:/opt/planopolis
```

No `scp`? Use `rsync -av --exclude .env planopolis-moodle/ root@<vps-ip>:/opt/planopolis/`,
or clone it from your Git repository on the server.

> Do not copy a `.env` from your laptop. The deploy script writes a fresh one with
> newly generated passwords; the local file still holds development values.

## 2. Deploy

```bash
ssh root@<vps-ip>
cd /opt/planopolis
chmod +x deploy.sh manage.sh
sudo ./deploy.sh
```

The script installs Docker, adds a 2 GB swap file, opens only ports 22/80/443 in the
firewall, generates the database and Super Admin passwords, builds the image and waits
until Moodle answers. First run takes roughly 5–15 minutes, mostly downloading Moodle.

It finishes by printing the address, username and password. **Save the password** —
it is also in `/opt/planopolis/.env`, which is `chmod 600`.

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
./manage.sh update          # rebuild after changing the plugin (backs up first)
```

Back up before and after the event, and copy the files off the server:

```bash
scp root@<vps-ip>:/opt/planopolis/backups/\* ./
```

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

For 300–500 participants, move to 4 vCPU / 8 GB and raise `MaxRequestWorkers` to ~80
and `shared_buffers` to 2GB. Whatever the number, **run a rehearsal** with your real
participant count before the event; the README's *Exam day* section covers this.

## Troubleshooting

| Symptom | Check |
|---|---|
| `./deploy.sh` ends with "has not answered yet" | `./manage.sh logs moodle` — usually still installing, or `DB_PASS` contains `$` |
| Browser cannot connect | `./manage.sh status`, then `ufw status`, and your provider's own firewall / security group |
| HTTPS certificate fails | DNS must point at this server and port 80 must be reachable; `./manage.sh logs caddy` |
| Logged-in pages look broken | `MOODLE_URL` in `.env` must exactly match what you type in the browser |
| Site slow under load | `./manage.sh status` for memory; raise `MaxRequestWorkers` only if RAM allows |
