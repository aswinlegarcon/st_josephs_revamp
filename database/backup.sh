#!/bin/sh
# X2 — site backups: nightly database dump + weekly media/ archive,
# 14-day retention, written to a NON-web directory.
#
# Production (MilesWeb mPanel → Cron Jobs), one nightly entry, e.g. 01:30:
#     /bin/sh /home/<account>/database/backup.sh
# Dev drill (run inside Docker; mysqldump lives in the db container):
#     docker compose exec -T db sh -c 'exec mysqldump ...'  — see DEPLOY.md §Backups.
#
# DB credentials are read from config/config.php (above the webroot) via PHP,
# so nothing secret ever appears in the crontab or in `ps` output for long.
# The admin dashboard reads this directory for its status-only widget (SEC-20:
# there is deliberately NO download endpoint for backups).

set -eu

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)          # …/database
REPO_ROOT=$(dirname "$SCRIPT_DIR")                 # repo / account root
BACKUP_DIR=${BACKUP_DIR:-"$REPO_ROOT/backups"}     # NON-web (never under public_html)
PHP_BIN=${PHP_BIN:-php}
STAMP=$(date +%Y%m%d-%H%M)

case "$BACKUP_DIR" in
  */public_html*) echo "refusing: BACKUP_DIR is inside the webroot" >&2; exit 1;;
esac
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR" 2>/dev/null || true

# ---- read DB creds from config/config.php (env vars win, same as the app) --
eval "$("$PHP_BIN" -d display_errors=0 -r '
  define("SJ_PUBLIC_ROOT", $argv[1] . "/public_html");
  $cfg = require $argv[1] . "/config/config.php";
  $db = $cfg["db"];
  foreach (["host","port","name","user","pass"] as $k) {
      $env = "DB_" . strtoupper($k);
      $v = getenv($env) !== false ? getenv($env) : $db[$k];
      printf("%s=%s\n", $env, escapeshellarg($v));
  }' "$REPO_ROOT")"

# ---- nightly: database dump (schema + data, single transaction) ------------
DUMP="$BACKUP_DIR/db-$STAMP.sql.gz"
MYSQL_PWD="$DB_PASS" mysqldump \
  --single-transaction --quick --routines --triggers --no-tablespaces \
  -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" | gzip > "$DUMP"
echo "db dump: $DUMP ($(du -h "$DUMP" | cut -f1))"

# ---- weekly (Sunday): media/ archive (uploads + generated renditions) ------
if [ "$(date +%u)" = "7" ] && [ -d "$REPO_ROOT/public_html/media" ]; then
    ARCH="$BACKUP_DIR/media-$STAMP.tar.gz"
    tar -czf "$ARCH" -C "$REPO_ROOT/public_html" media
    echo "media archive: $ARCH ($(du -h "$ARCH" | cut -f1))"
fi

# ---- retention: 14 days ----------------------------------------------------
find "$BACKUP_DIR" -name 'db-*.sql.gz'    -mtime +14 -delete
find "$BACKUP_DIR" -name 'media-*.tar.gz' -mtime +14 -delete
echo "retention pruned (>14 days)"
echo "backup OK"
