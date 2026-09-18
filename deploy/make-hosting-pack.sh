#!/usr/bin/env bash
# Pack our layer only: themes, format, mu-plugin, prod compose/nginx, deploy docs.
# Not a second Moodle/WordPress clone.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAMP="$(date +%Y%m%d)"
OUT="${BACKUP_DIR:-$ROOT/backups}/hosting-pack-$STAMP"

rm -rf "$OUT"
mkdir -p "$OUT"

copy_path() {
  local rel="$1"
  local src="$ROOT/$rel"
  local dest="$OUT/$rel"
  if [[ ! -e "$src" ]]; then
    echo "skip missing: $rel" >&2
    return 0
  fi
  mkdir -p "$(dirname "$dest")"
  if [[ -d "$src" ]]; then
    rsync -a \
      --exclude 'node_modules/' \
      --exclude '.DS_Store' \
      --exclude '*.log' \
      "$src/" "$dest/"
  else
    cp -a "$src" "$dest"
  fi
}

copy_path wordpress/wp-content/themes/aco-theme
copy_path moodle/theme/learnhub
copy_path moodle/course/format/aco
copy_path moodle/auth/edwiserbridge/login.php
copy_path wordpress/wp-content/mu-plugins
copy_path nginx/prod
copy_path nginx/bootstrap
copy_path mysql/init-prod
copy_path deploy
copy_path .env.example
copy_path docker-compose.prod.yml
copy_path moodle/config-dist.php
copy_path docs/yookassa-precheck.md
copy_path docs/pochta-i-zayavki.md
copy_path deploy/HOSTING.md

cp -a "$ROOT/deploy/HOSTING.md" "$OUT/HOSTING.md"

cat > "$OUT/MANIFEST.txt" <<EOF
hosting-pack-$STAMP
Our code on top of official WordPress / Moodle cores.

Includes:
- wordpress/wp-content/themes/aco-theme
- moodle/theme/learnhub
- moodle/course/format/aco
- moodle/auth/edwiserbridge/login.php (SSO back-button fix)
- wordpress/wp-content/mu-plugins
- docker-compose.prod.yml, nginx/prod, mysql/init-prod
- .env.example, moodle/config-dist.php, deploy/, docs

Does not include:
- WordPress / Moodle core, plugins vendor trees, node_modules
- Mailpit, seed/QA scripts, Obsidian
- uploads, moodledata, .env, wp-config.php, moodle/config.php

Install cores from git clone of the repo or official distros, then overlay this pack.
Follow HOSTING.md
EOF

echo "Hosting pack → $OUT"
find "$OUT" -type f | wc -l | awk '{print "files:", $1}'
