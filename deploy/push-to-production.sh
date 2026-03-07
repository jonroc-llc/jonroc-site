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
PRODUCTION_DIR="/home/jonrocd26/public_html_jonroc"   # ← UPDATE THIS PATH
LOG_FILE="/home/jonrocd26/logs/deploy-production.log"
# ────────────────────────────────────────────────────────────

mkdir -p "$(dirname "$LOG_FILE")"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

echo "[$TIMESTAMP] Starting push to production..." | tee -a "$LOG_FILE"

# Verify production dir exists
if [ ! -d "$PRODUCTION_DIR" ]; then
  echo "[$TIMESTAMP] ERROR: Production directory not found: $PRODUCTION_DIR" | tee -a "$LOG_FILE"
  exit 1
fi

# Sync staging → production
rsync -az --delete \
  --exclude 'cgi-bin' \
  --exclude '.htaccess.bak' \
  "$STAGING_DIR/" \
  "$PRODUCTION_DIR/"

echo "[$TIMESTAMP] ✅ Push complete. jonroc.com is now up to date." | tee -a "$LOG_FILE"
