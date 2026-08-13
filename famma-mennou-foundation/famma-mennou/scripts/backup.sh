#!/usr/bin/env bash
# FAMMA — local backup (DB + uploads). Run before major changes.
set -euo pipefail
# Voir setup.sh : empêche Git Bash de réécrire les chemins POSIX passés à docker.
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'
cd "$(dirname "$0")/.."
if [ -f .env ]; then set -a; . ./.env; set +a; fi
STAMP=$(date +%Y%m%d-%H%M%S)
DEST="backups/${STAMP}"
mkdir -p "${DEST}"
echo "==> Dumping database..."
docker compose exec -T db sh -c "exec mariadb-dump -u\"${DB_USER}\" -p\"${DB_PASSWORD}\" \"${DB_NAME}\"" > "${DEST}/db.sql"
echo "==> Archiving uploads..."
docker compose exec -T -u 33:33 wpcli sh -c "cd /var/www/html/wp-content && tar czf - uploads 2>/dev/null" > "${DEST}/uploads.tar.gz" || true
echo "==> Backup written to ${DEST}"
echo "    NOTE: backups may contain customer data — never commit them."
