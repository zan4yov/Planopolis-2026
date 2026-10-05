#!/usr/bin/env bash
# Day-to-day operation of the deployed Planopolis platform.
#   ./manage.sh status | logs | restart | update | backup | restore <file.tgz> | fix-urls | enable-https <host>
set -euo pipefail
cd "$(dirname "$(readlink -f "$0")")"
DC="docker compose -f docker-compose.yml -f docker-compose.prod.yml"
BACKUP_DIR="${BACKUP_DIR:-./backups}"
log() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
die() { printf '\033[1;31mERROR: %s\033[0m\n' "$*" >&2; exit 1; }
[ -f .env ] || die "No .env found. Run sudo ./deploy.sh first."
# Read values directly: sourcing .env would break on a password containing '#'.
envval() { grep -E "^$1=" .env | head -1 | cut -d= -f2-; }
DB_USER="$(envval DB_USER)"; DB_NAME="$(envval DB_NAME)"; MOODLE_URL="$(envval MOODLE_URL)"

case "${1:-}" in
  status)
    $DC ps
    log "Disk"; df -h . | tail -1
    log "Memory"; free -h | head -2
    ;;

  logs)
    $DC logs -f --tail=100 "${2:-}"
    ;;

  restart)
    $DC restart; log "Restarted."
    ;;

  update)
    # Rebuilds the image (picks up plugin changes) and runs Moodle's upgrade.
    log "Backing up first"; "$0" backup
    $DC up -d --build
    log "Done. Watch the upgrade with: ./manage.sh logs moodle"
    ;;

  backup)
    # Everything that matters lives in the database and the moodledata volume.
    mkdir -p "$BACKUP_DIR"
    STAMP=$(date +%F_%H%M)
    log "Dumping the database"
    $DC exec -T db pg_dump -U "$DB_USER" -Fc "$DB_NAME" > "$BACKUP_DIR/db_$STAMP.dump"
    log "Archiving uploaded files"
    $DC run --rm --no-deps -v "$(pwd)/$BACKUP_DIR:/backup" --entrypoint sh moodle \
        -c "tar czf /backup/moodledata_$STAMP.tgz -C /var/moodledata ." 
    log "Saved in $BACKUP_DIR:"
    ls -lh "$BACKUP_DIR" | grep "$STAMP"
    printf '\nCopy these off the server as well:\n  scp root@<ip>:%s/\*_%s.\* ./\n' "$(pwd)/$BACKUP_DIR" "$STAMP"
    ;;

  restore)
    DUMP="${2:-}"
    [ -f "$DUMP" ] || die "Usage: ./manage.sh restore backups/db_YYYY-MM-DD_HHMM.dump"
    printf 'This REPLACES the current database with %s.\nType RESTORE to continue: ' "$DUMP"
    read -r ANS; [ "$ANS" = "RESTORE" ] || die "Cancelled."
    $DC stop moodle cron
    cat "$DUMP" | $DC exec -T db pg_restore -U "$DB_USER" -d "$DB_NAME" --clean --if-exists
    $DC start moodle cron
    log "Restored. Check the site before announcing it."
    ;;

  fix-urls)
    # Moodle stores absolute URLs in content; run this after the address changes.
    log "Rewriting stored links to $MOODLE_URL"
    $DC exec -T moodle php /var/www/moodle/admin/cli/fix_course_sortorder.php >/dev/null 2>&1 || true
    $DC exec -T moodle php /var/www/moodle/admin/cli/purge_caches.php
    log "Caches purged. If old links persist, see Moodle's 'Site relocation' docs."
    ;;

  enable-https)
    HOST="${2:-}"
    [ -n "$HOST" ] || die "Usage: ./manage.sh enable-https quiz.example.com"
    RESOLVED=$(getent hosts "$HOST" | awk '{print $1}' | head -1 || true)
    MYIP=$(curl -fsS --max-time 10 https://api.ipify.org || true)
    [ -n "$RESOLVED" ] || die "$HOST does not resolve yet. Add the DNS A record first and wait."
    if [ "$RESOLVED" != "$MYIP" ]; then
        printf '\033[1;33m!   %s resolves to %s but this server is %s.\n' "$HOST" "$RESOLVED" "$MYIP"
        printf '    Let'\''s Encrypt will fail unless it points here. Continue anyway? [y/N] \033[0m'
        read -r A; [ "$A" = y ] || die "Cancelled."
    fi
    sudo SITE_ADDRESS="$HOST" ./deploy.sh
    ;;

  *)
    sed -n '2,4p' "$0"
    exit 1
    ;;
esac
