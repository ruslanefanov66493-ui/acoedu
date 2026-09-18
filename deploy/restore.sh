#!/usr/bin/env bash
# Usage: ./deploy/restore.sh backups/20260908-120000
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${1:-}"
if [[ -z "$SRC" || ! -d "$SRC" ]]; then
  echo "Usage: $0 backups/<stamp>" >&2
  exit 1
fi

docker exec -i aco-mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD"' < "$SRC/mysql-all.sql"

if [[ -f "$SRC/moodledata.tar.gz" ]]; then
  docker run --rm \
    -v aco_moodle_moodledata:/data \
    -v "$(cd "$SRC" && pwd)":/backup \
    alpine sh -c 'rm -rf /data/*; tar xzf /backup/moodledata.tar.gz -C /data'
fi

if [[ -f "$SRC/wordpress-uploads.tar.gz" ]]; then
  tar xzf "$SRC/wordpress-uploads.tar.gz" -C "$ROOT/wordpress/wp-content"
fi

docker exec aco-moodle php /var/www/html/admin/cli/purge_caches.php || true
echo "Restored from $SRC"
