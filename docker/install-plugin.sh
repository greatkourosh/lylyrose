#!/usr/bin/env bash
# Install + activate one WordPress plugin by slug, then smoke-test the site.
# Usage: bash docker/install-plugin.sh <slug> [main-file.php]
set -uo pipefail
export MSYS_NO_PATHCONV=1

SLUG="${1:?usage: install-plugin.sh <slug> [main-file]}"
MAIN="${2:-}"
WP=/var/www/html/wp-content/plugins

echo "--- downloading $SLUG"
docker exec lylyrose-wp sh -c "cd /tmp && curl -sL -o $SLUG.zip https://downloads.wordpress.org/plugin/$SLUG.latest-stable.zip && php -r '\$z=new ZipArchive(); if(\$z->open(\"/tmp/$SLUG.zip\")!==TRUE){fwrite(STDERR,\"bad zip\n\");exit(1);} \$z->extractTo(\"$WP\"); \$z->close(); echo \"extracted\n\";'" || { echo "DOWNLOAD FAILED: $SLUG"; exit 1; }

if [ -z "$MAIN" ]; then
  MAIN=$(docker exec lylyrose-wp sh -c "grep -rl 'Plugin Name' $WP/$SLUG/*.php 2>/dev/null | head -1 | xargs -r basename")
fi
[ -z "$MAIN" ] && { echo "NO MAIN FILE FOUND for $SLUG"; exit 1; }
REL="$SLUG/$MAIN"
echo "--- main file: $REL"

# PHP lint the plugin before activating (avoid wp-jalali-style fatals)
LINT_FAIL=0
for f in $(docker exec lylyrose-wp sh -c "find $WP/$SLUG -name '*.php' -not -path '*/vendor/*'"); do
  docker exec lylyrose-wp php -l "$f" >/dev/null 2>&1 || { LINT_FAIL=1; docker exec lylyrose-wp php -l "$f" 2>&1 | head -1; }
done
if [ "$LINT_FAIL" = "1" ]; then
  echo "PHP 8.2 INCOMPATIBLE — removing $SLUG, NOT activating"
  docker exec lylyrose-wp rm -rf "$WP/$SLUG"
  exit 2
fi
echo "--- php 8.2 lint OK"

# Activate via WP API (safe serialization)
docker exec lylyrose-wp php -d error_reporting=E_ALL -r '
require("/var/www/html/wp-load.php");
$plugin = $argv[1];
$active = get_option("active_plugins", []);
if (!in_array($plugin, $active, true)) {
    $active[] = $plugin;
    update_option("active_plugins", $active);
}
var_dump(in_array($plugin, get_option("active_plugins", []), true));
' "$REL" 2>&1 | tail -1

# Smoke test: homepage must return 200 and not fatal
sleep 2
TESTHTML="$(mktemp)"
CODE=$(curl -s -o "$TESTHTML" -w "%{http_code}" --max-time 20 http://localhost:8020/)
FATAL=$(grep -c "Fatal error\|Parse error" "$TESTHTML" 2>/dev/null)
[ -z "$FATAL" ] && FATAL=0
case "$FATAL" in ''|*[!0-9]*) FATAL=0;; esac
rm -f "$TESTHTML"
SHOP=$(curl -s -o /dev/null -w "%{http_code}" --max-time 20 http://localhost:8020/shop/)
echo "--- home=$CODE shop=$SHOP fatals=$FATAL"

if [ "$CODE" = "200" ] && [ "$FATAL" = "0" ] && [ "$SHOP" = "200" ]; then
  echo "SUCCESS: $SLUG active and site healthy"
  exit 0
else
  echo "SITE BROKEN after activating $SLUG — deactivating"
  docker exec lylyrose-wp php -r '
require("/var/www/html/wp-load.php");
$plugin = $argv[1];
$active = get_option("active_plugins", []);
$active = array_values(array_diff($active, [$plugin]));
update_option("active_plugins", $active);
echo "deactivated\n";
' "$REL" 2>&1 | tail -1
  exit 3
fi
