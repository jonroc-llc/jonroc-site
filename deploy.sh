#!/bin/bash
# Deploy all built files to production via SSH
set -e

REMOTE="jonroc.dev"
REMOTE_PATH="~/public_html"
DIST="dist"

echo "🔨 Building..."
npm run build

echo "🚀 Deploying HTML pages..."
find "$DIST" -name "index.html" | while read f; do
  remote_file="$REMOTE_PATH/${f#$DIST/}"
  remote_dir=$(dirname "$remote_file")
  ssh "$REMOTE" "mkdir -p $remote_dir"
  scp "$f" "$REMOTE:$remote_file"
done

echo "🎨 Deploying CSS..."
NEW_CSS=$(ls dist/_astro/*.css | head -1 | xargs basename)
scp dist/_astro/*.css "$REMOTE:$REMOTE_PATH/_astro/"
# Remove stale CSS bundles
ssh "$REMOTE" "cd $REMOTE_PATH/_astro && ls *.css | grep -v '$NEW_CSS' | xargs rm -f 2>/dev/null || true"

echo "📜 Deploying JS..."
ls dist/_astro/*.js 2>/dev/null && scp dist/_astro/*.js "$REMOTE:$REMOTE_PATH/_astro/" || true

echo "🗺️  Deploying sitemap + robots..."
scp dist/sitemap-index.xml "$REMOTE:$REMOTE_PATH/sitemap-index.xml" 2>/dev/null || true
scp dist/sitemap-0.xml "$REMOTE:$REMOTE_PATH/sitemap-0.xml" 2>/dev/null || true
scp dist/robots.txt "$REMOTE:$REMOTE_PATH/robots.txt"

echo "✅ Deploy complete."
