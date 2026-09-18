#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="${BACKUP_DIR:-$ROOT/backups}/$STAMP"
mkdir -p "$OUT"

echo "Backup → $OUT"

docker exec aco-mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --all-databases --single-transaction --quick' \
  > "$OUT/mysql-all.sql"

docker run --rm \
  -v aco_moodle_moodledata:/data:ro \
  -v "$OUT":/backup \
  alpine tar czf /backup/moodledata.tar.gz -C /data .

tar czf "$OUT/wordpress-uploads.tar.gz" -C "$ROOT/wordpress/wp-content" uploads || true

echo "Done: $OUT"
