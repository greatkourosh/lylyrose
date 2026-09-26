#!/usr/bin/env bash
# migrate-from-remote.sh — Pull the public data of https://lylyrose.vegacodex.ir
# into this local WooCommerce so the local site matches (products, media, categories,
# brands, pages). Requires the docker stack to be running (lylyrose-wp / lylyrose-db).
#
# Usage: docker/migrate-from-remote.sh
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WP_CONTAINER="${WP_CONTAINER:-lylyrose-wp}"
LIB_REMOTE="/tmp/migrate-lib.php"

echo "==> Ensuring stack is up"
docker compose -f "$HERE/../docker-compose.yml" up -d --wait wordpress 2>/dev/null || docker compose up -d wordpress

echo "==> Verifying WP container and WP-CLI availability"
if ! docker exec "$WP_CONTAINER" test -f wp-load.php; then
  echo "ERR: $WP_CONTAINER has no wp-load.php (is WordPress provisioned?)" >&2
  exit 1
fi

echo "==> Copying migration library into container"
docker cp "$HERE/migrate-lib.php" "$WP_CONTAINER:$LIB_REMOTE"

run_phase() {
  echo "==> Phase: $1"
  docker exec "$WP_CONTAINER" php "$LIB_REMOTE" "$1" --allow-root
}

# Idempotent bootstrap: terms + brand map first, then products (media + price).
run_phase terms
run_phase brandmap
run_phase products
run_phase pages
run_phase links

echo "==> Rewriting remote URLs to local"
LOCAL_URL="http://localhost:${WORDPRESS_PORT:-8020}"
docker exec "$WP_CONTAINER" php /tmp/wp-cli.phar search-replace 'https://lylyrose.vegacodex.ir' "$LOCAL_URL" --all-tables --precise --recurse-objects --skip-columns=guid --allow-root >/dev/null 2>&1 || true
docker exec "$WP_CONTAINER" php /tmp/wp-cli.phar search-replace 'http://lylyrose.vegacodex.ir' "$LOCAL_URL" --all-tables --precise --recurse-objects --skip-columns=guid --allow-root >/dev/null 2>&1 || true

echo "==> Flush + cache clean"
docker exec "$WP_CONTAINER" php /tmp/wp-cli.phar rewrite flush --hard --allow-root >/dev/null 2>&1 || true
docker exec "$WP_CONTAINER" php /tmp/wp-cli.phar cache flush --allow-root >/dev/null 2>&1 || true
docker exec "$WP_CONTAINER" php /tmp/wp-cli.phar wc tool run install_pages --user=0 --allow-root >/dev/null 2>&1 || true

echo "==> Summary"
docker exec "$WP_CONTAINER" php -r '
require "/var/www/html/wp-load.php";
function n($q){ global $wpdb; return (int)$wpdb->get_var($q); }
echo "products:  " . n("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=\"product\" AND post_status=\"publish\"") . "\n";
echo "pages:     " . n("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=\"page\" AND post_status=\"publish\"") . "\n";
echo "media:     " . n("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=\"attachment\"") . "\n";
echo "categories:" . n("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy=\"product_cat\"") . "\n";
echo "brands:    " . n("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy=\"pa_brand\"") . "\n";
' 2>/dev/null

echo "==> Done. Browse http://localhost:${WORDPRESS_PORT:-8020}"
