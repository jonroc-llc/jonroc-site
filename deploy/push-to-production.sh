#!/bin/bash
# ============================================================
# Jonroc: Push staging (jonroc.dev) → production (jonroc.com)
# ============================================================
# Run on the server as jonrocd26:
#   bash ~/deploy/push-to-production.sh
#
# Or triggered via the web deploy button at jonroc.dev/deploy/
# ============================================================

set -e

# ── CONFIG ──────────────────────────────────────────────────
STAGING_DIR="/home/jonrocd26/public_html"
PRODUCTION_DIR="jonroc6@localhost:/home/jonroc6/public_html"
DEPLOY_KEY="/home/jonrocd26/.ssh/deploy_key"
LOG_FILE="/home/jonrocd26/logs/deploy-production.log"
# ────────────────────────────────────────────────────────────

mkdir -p "$(dirname "$LOG_FILE")"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

echo "[$TIMESTAMP] Starting push to production..." | tee -a "$LOG_FILE"

# Verify SSH access to production
if ! ssh -i "$DEPLOY_KEY" -o StrictHostKeyChecking=no jonroc6@localhost "test -d /home/jonroc6/public_html" 2>/dev/null; then
  echo "[$TIMESTAMP] ERROR: Cannot reach production directory via SSH. Check deploy key authorization." | tee -a "$LOG_FILE"
  exit 1
fi

# Sync staging → production
rsync -az --delete \
  -e "ssh -i $DEPLOY_KEY -o StrictHostKeyChecking=no -p 22" \
  --exclude 'cgi-bin' \
  --exclude '.htaccess.bak' \
  --exclude 'Outside_Content' \
  --exclude 'deploy' \
  "$STAGING_DIR/" \
  "$PRODUCTION_DIR/"

# Fix permissions via SSH — suPHP rejects PHP in group-writable dirs/files
ssh -i "$DEPLOY_KEY" -o StrictHostKeyChecking=no jonroc6@localhost \
  'find ~/public_html -type d | xargs chmod 755 2>/dev/null; find ~/public_html -type f | xargs chmod 644 2>/dev/null; echo done'

echo "[$TIMESTAMP] ✅ Push complete. jonroc.com is now up to date." | tee -a "$LOG_FILE"
