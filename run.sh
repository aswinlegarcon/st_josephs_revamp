#!/usr/bin/env bash
# One-command launcher for the St. Joseph's dynamic-home-page demo.
#   ./run.sh          → build (first time), start containers, seed DB, print URLs
#   ./run.sh stop     → stop containers
#   ./run.sh reset    → stop and DELETE the database volume (fresh start next run)
set -euo pipefail
cd "$(dirname "$0")"

case "${1:-up}" in
  stop)
    docker compose down
    exit 0
    ;;
  reset)
    docker compose down -v
    echo "Database volume removed. Next ./run.sh starts fresh."
    exit 0
    ;;
esac

mkdir -p public_html/media
chmod 777 public_html/media 2>/dev/null || true

echo "==> Building/starting containers (first run downloads images; takes a few minutes)…"
docker compose up -d --build

echo "==> Waiting for the database (MySQL) to be ready…"
for i in $(seq 1 90); do
  if docker compose exec -T db mysqladmin ping -h127.0.0.1 -uroot -prootpw --silent >/dev/null 2>&1; then break; fi
  sleep 1
done

echo "==> Seeding content (idempotent — safe to re-run)…"
docker compose exec -T web php /var/www/database/seed.php

cat <<'EOF'

  ✔ Ready!

  Public site :  http://localhost:8090/
  Admin panel :  http://localhost:8090/admin/   (user: admin  ·  password: admin123)

  Log in → press "Edit this page" on the Home page → pencil/camera/＋ controls appear.
  Stop with:  ./run.sh stop
EOF
