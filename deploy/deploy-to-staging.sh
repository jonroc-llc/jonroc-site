#!/bin/bash
# Deploy Astro build from Alice VPS → jonroc.dev staging
# Usage: bash deploy/deploy-to-staging.sh (run from jonroc-site root)

rsync -az --delete \
  --exclude 'contact-handler.php' \
  --exclude 'cgi-bin' \
  --exclude '.htaccess' \
  --exclude 'deploy' \
  dist/ \
  jonroc.dev:~/public_html/
