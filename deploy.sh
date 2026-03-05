#!/bin/bash
# Deploy all built files to production via SSH
set -e

REMOTE="jonroc.dev"
DEST="~/public_html"

echo "🔨 Building..."
npm run build

echo "🎨 Deploying CSS (new bundle first)..."
scp dist/_astro/*.css "$REMOTE:$DEST/_astro/"

echo "📜 Deploying JS..."
ls dist/_astro/*.js 2>/dev/null && scp dist/_astro/*.js "$REMOTE:$DEST/_astro/" || true

echo "🖼️  Deploying static assets..."
scp dist/robots.txt "$REMOTE:$DEST/robots.txt"
scp dist/sitemap-index.xml "$REMOTE:$DEST/sitemap-index.xml" 2>/dev/null || true
scp dist/sitemap-0.xml "$REMOTE:$DEST/sitemap-0.xml" 2>/dev/null || true

echo "🚀 Deploying HTML pages..."
scp dist/index.html                                                          "$REMOTE:$DEST/index.html"
scp dist/about/index.html                                                    "$REMOTE:$DEST/about/index.html"
scp dist/contact/index.html                                                  "$REMOTE:$DEST/contact/index.html"
scp dist/portfolio/index.html                                                "$REMOTE:$DEST/portfolio/index.html"
scp dist/blog/index.html                                                     "$REMOTE:$DEST/blog/index.html"
scp dist/blog/blog-01-ai-construction-project-management/index.html         "$REMOTE:$DEST/blog/blog-01-ai-construction-project-management/index.html"
scp dist/blog/blog-02-event-rental-automation/index.html                    "$REMOTE:$DEST/blog/blog-02-event-rental-automation/index.html"
scp dist/industries/construction/index.html                                  "$REMOTE:$DEST/industries/construction/index.html"
scp dist/industries/events/index.html                                        "$REMOTE:$DEST/industries/events/index.html"
scp dist/industries/rental/index.html                                        "$REMOTE:$DEST/industries/rental/index.html"

echo "🧹 Cleaning stale CSS bundles..."
NEW_CSS=$(ls dist/_astro/*.css | xargs -I{} basename {})
ssh "$REMOTE" "cd $DEST/_astro && ls *.css | grep -v '$NEW_CSS' | xargs rm -f 2>/dev/null || true"

echo "✅ Deploy complete."
