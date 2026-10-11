#!/usr/bin/env bash
# Copy the project to the VPS without secrets, caches, or Obsidian.
# Usage: VPS=root@1.2.3.4 ./deploy/rsync-to-vps.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VPS="${VPS:?Set VPS=user@ip}"
DEST="${DEST:-/opt/aco}"

rsync -azP --delete \
  --exclude '.git/' \
  --include '.env.example' \
  --exclude '.env' \
  --exclude '.env.*' \
  --exclude 'wordpress/wp-config.php' \
  --exclude 'moodle/config.php' \
  --exclude 'certs/' \
  --exclude 'backups/' \
  --exclude 'docker-compose.yml' \
  --exclude 'docker-compose.tls.yml' \
  --exclude 'docker-compose.prod.yml' \
  --exclude 'nginx/prod/bbb.conf' \
  --exclude 'wordpress/wp-admin/' \
  --exclude 'wordpress/wp-includes/' \
  --exclude 'wordpress/wp-*.php' \
  --exclude 'wordpress/xmlrpc.php' \
  --exclude 'wordpress/index.php' \
  --exclude 'wordpress/readme.html' \
  --exclude 'wordpress/license.txt' \
  --exclude 'moodledata/' \
  --exclude 'Obsidian/' \
  --exclude '.tmp-*/' \
  --exclude '**/node_modules/' \
  --exclude 'wordpress/wp-content/cache/' \
  --exclude 'wordpress/wp-content/languages/' \
  --exclude 'wordpress/wp-content/uploads/' \
  --exclude 'meet/jitsi/' \
  --exclude 'meet/config/' \
  --exclude 'moodle/aco_seed_python.php' \
  --exclude 'moodle/eb_demo_attempt.php' \
  --exclude 'moodle/eb_seed_content.php' \
  --exclude 'wordpress/_blog_seed/' \
  --exclude 'frontend/' \
  --exclude '.DS_Store' \
  "$ROOT/" "$VPS:$DEST/"

echo "Synced to $VPS:$DEST"
echo "On the server: cp .env.example .env && cp moodle/config-dist.php moodle/config.php"
