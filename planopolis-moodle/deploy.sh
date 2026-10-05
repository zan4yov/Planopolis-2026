#!/usr/bin/env bash
# One-command deployment of the Planopolis quiz platform on a fresh Debian/Ubuntu VPS.
#
#   sudo ./deploy.sh                      HTTP on this server's public IP
#   sudo SITE_ADDRESS=quiz.example.com ./deploy.sh      HTTPS (DNS must already point here)
#
# Safe to run again: it never overwrites an existing .env, and Moodle upgrades in place.
set -euo pipefail

cd "$(dirname "$(readlink -f "$0")")"
log()  { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m!   %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31mERROR: %s\033[0m\n' "$*" >&2; exit 1; }

[ "$(id -u)" = 0 ] || die "Run with sudo: sudo ./deploy.sh"
[ -f docker-compose.yml ] || die "Run this from the planopolis-moodle folder."

# A password with upper case, lower case, a digit and a symbol, and no characters
# that the shell, .env parsing or Moodle's own escaping would mangle.
genpass() { printf 'Pl%s-%s9' "$(openssl rand -hex 4)" "$(openssl rand -base64 12 | tr -dc 'A-Za-z0-9' | head -c 10)"; }
# Update a key in .env, appending it when the file predates that setting.
setkv() {
    if grep -qE "^$1=" .env; then
        sed -i "s|^$1=.*|$1=$2|" .env
    else
        printf '%s=%s\n' "$1" "$2" >> .env
    fi
}
log "Checking the server"
. /etc/os-release 2>/dev/null || die "Cannot identify the OS."
case "${ID:-}${ID_LIKE:-}" in *debian*|*ubuntu*) ;; *) die "This script supports Debian/Ubuntu. Yours: ${PRETTY_NAME:-unknown}" ;; esac
case "${ID:-}" in
    debian|ubuntu) DOCKER_DISTRO="$ID" ;;
    # A derivative (Mint, Pop!_OS, ...) must use its upstream's Docker repository.
    *) case "${ID_LIKE:-}" in *ubuntu*) DOCKER_DISTRO=ubuntu ;; *) DOCKER_DISTRO=debian ;; esac ;;
esac
TOTAL_MB=$(awk '/MemTotal/{print int($2/1024)}' /proc/meminfo)
echo "    ${PRETTY_NAME}, $(nproc) vCPU, ${TOTAL_MB} MB RAM"
[ "$TOTAL_MB" -ge 3500 ] || warn "Under 4 GB RAM. Fine for testing; expect trouble with 200 participants at once."

log "Installing prerequisites"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq ca-certificates curl gnupg openssl
echo "    curl, openssl ready"

log "Installing Docker"
if ! command -v docker >/dev/null 2>&1; then
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL "https://download.docker.com/linux/${DOCKER_DISTRO}/gpg" -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/${DOCKER_DISTRO} ${VERSION_CODENAME} stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update -qq
    apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    systemctl enable --now docker
    echo "    installed $(docker --version)"
else
    echo "    already present: $(docker --version)"
fi
docker compose version >/dev/null 2>&1 || die "The Docker Compose plugin is missing."

log "Adding swap (protects against an out-of-memory crash mid-exam)"
if [ "$(swapon --show --noheadings | wc -l)" -eq 0 ]; then
    fallocate -l 2G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=2048 status=none
    chmod 600 /swapfile && mkswap -q /swapfile && swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
    echo "    2 GB swap file active"
else
    echo "    swap already configured"
fi

log "Configuring the firewall"
if ! command -v ufw >/dev/null 2>&1; then DEBIAN_FRONTEND=noninteractive apt-get install -y -qq ufw; fi
SSH_PORT=$(awk '/^[[:space:]]*Port[[:space:]]+[0-9]+/{print $2; exit}' /etc/ssh/sshd_config 2>/dev/null || true)
SSH_PORT="${SSH_PORT:-22}"
ufw allow "${SSH_PORT}/tcp" >/dev/null
ufw allow 80/tcp    >/dev/null
ufw allow 443/tcp   >/dev/null
ufw allow 443/udp   >/dev/null
ufw --force enable  >/dev/null
echo "    open: ${SSH_PORT} (SSH), 80 (HTTP), 443 (HTTPS). Everything else is blocked."
warn "Docker publishes ports past ufw, so .env pins Moodle itself to 127.0.0.1 instead."

log "Writing configuration"
PUBLIC_IP="${PUBLIC_IP:-$(curl -fsS --max-time 10 https://api.ipify.org || true)}"
[ -n "$PUBLIC_IP" ] || die "Could not detect the public IP. Re-run with: sudo PUBLIC_IP=1.2.3.4 ./deploy.sh"
SITE_ADDRESS="${SITE_ADDRESS:-}"

if [ -f .env ]; then
    # "scp -r" copies the development .env along with everything else. Using it
    # would silently deploy the placeholder passwords from .env.example.
    if grep -qE '^(DB_PASS|ADMIN_PASS)=.*CHANGE' .env; then
        die ".env still holds the placeholder passwords from .env.example.
       This is the development file, copied here by mistake.
       Delete it and run again so fresh passwords are generated:
         rm .env && sudo ./deploy.sh"
    fi
    chmod 600 .env
    echo "    .env already exists – keeping your passwords untouched."
    # Keep the public address in step with how the site is actually reached.
    if [ -n "$SITE_ADDRESS" ]; then WANT_URL="https://${SITE_ADDRESS}"; else WANT_URL="http://${PUBLIC_IP}"; fi
    CUR_URL=$(grep -E '^MOODLE_URL=' .env | cut -d= -f2- || true)
    if [ "$CUR_URL" != "$WANT_URL" ]; then
        warn "MOODLE_URL changes from '${CUR_URL}' to '${WANT_URL}'."
        warn "Moodle stores absolute links, so run ./manage.sh fix-urls after this if the site was already in use."
    fi
    SET_URL="$WANT_URL"
else
    [ -f .env.production.example ] || die ".env.production.example is missing."
    cp .env.production.example .env
    chmod 600 .env
    DB_PASS="$(genpass)"; ADMIN_PASS="$(genpass)"
    setkv DB_PASS "${DB_PASS}"
    setkv ADMIN_PASS "${ADMIN_PASS}"
    if [ -n "$SITE_ADDRESS" ]; then SET_URL="https://${SITE_ADDRESS}"; else SET_URL="http://${PUBLIC_IP}"; fi
    echo "    generated fresh database and Super Admin passwords"
fi

if [ -n "$SITE_ADDRESS" ]; then
    setkv SITE_ADDRESS "${SITE_ADDRESS}"
    setkv MOODLE_SSLPROXY "true"
    setkv MOODLE_REVERSEPROXY "true"
else
    setkv SITE_ADDRESS ""
    setkv MOODLE_SSLPROXY "false"
    setkv MOODLE_REVERSEPROXY "true"
fi
setkv MOODLE_URL "${SET_URL}"
grep -q '^HTTP_PORT=127.0.0.1:' .env || setkv HTTP_PORT "127.0.0.1:8080"

log "Checking the proxy configuration"
# Fail here rather than after the old proxy has already been replaced.
docker run --rm -e SITE_ADDRESS="$SITE_ADDRESS" -e ACME_EMAIL="x@example.com"     -v "$(pwd)/docker/Caddyfile:/etc/caddy/Caddyfile:ro"     caddy:2-alpine caddy validate --config /etc/caddy/Caddyfile     || die "docker/Caddyfile is not valid - fix it before deploying (the site was not touched)."
echo "    Caddyfile is valid"

log "Building and starting (first run downloads Moodle – several minutes)"
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

log "Waiting for Moodle to finish installing"
for i in $(seq 1 90); do
    CODE=$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "http://127.0.0.1:8080/login/index.php" || true)
    case "$CODE" in
        200|303|302) log "The site is up."; READY=1; break ;;
    esac
    printf '    still installing (%s/90, last status %s)\r' "$i" "${CODE:-none}"
    sleep 10
done
echo
if [ -z "${READY:-}" ]; then
    warn "Moodle has not answered yet. It may still be installing. Watch it with:"
    warn "  docker compose -f docker-compose.yml -f docker-compose.prod.yml logs -f moodle"
    exit 1
fi

printf '\n\033[1;32m================ Planopolis is live ================\033[0m\n'
printf '  Address:   %s\n' "$SET_URL"
printf '  Username:  %s\n' "$(grep -E '^ADMIN_USER=' .env | cut -d= -f2-)"
printf '  Password:  %s\n' "$(grep -E '^ADMIN_PASS=' .env | cut -d= -f2-)"
printf '\n  These are stored in .env (chmod 600). Save them in your password manager.\n'
if [ -z "$SITE_ADDRESS" ]; then
printf '\n\033[1;33m  HTTP only: passwords travel unencrypted over the network.\n'
printf '  Turn on HTTPS once you have a hostname:\n'
printf '    sudo SITE_ADDRESS=quiz.example.com ./deploy.sh\n'
printf '  See DEPLOY.md for a free hostname that needs no domain purchase.\033[0m\n'
fi
printf '\n  Next: ./manage.sh backup before the event, and read the "Exam day" section of README.md\n\n'
