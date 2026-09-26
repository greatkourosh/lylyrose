#!/bin/sh
# Quick post-update smoke check: every plugin still loads, the store's key pages
# still answer, and nothing fatal is logged. Run after each plugin update.
set -u
fail=0

docker exec lylyrose-wp php -r '
define("WP_USE_THEMES", false);
require "/var/www/html/wp-load.php";
$active = (array) get_option("active_plugins", []);
foreach ($active as $p) {
    $f = WP_PLUGIN_DIR . "/" . $p;
    if (!file_exists($f)) { echo "MISSING: $p\n"; continue; }
}
echo "active plugins: " . count($active) . "\n";
' > /tmp/smoke-plugins.txt 2>/tmp/smoke-plugins.err

if grep -q "MISSING" /tmp/smoke-plugins.txt; then
  echo "SMOKE FAIL: missing plugin files"
  grep "MISSING" /tmp/smoke-plugins.txt
  fail=1
fi
cat /tmp/smoke-plugins.txt | head -3

# A fatal during plugin load shows up as a PHP error on any page.
for p in "/" "/shop/" "/cart/"; do
  code=$(curl -s -o /dev/null -w '%{http_code}' -m 90 "http://localhost:8020$p" 2>/dev/null)
  if [ "$code" != "200" ]; then
    echo "SMOKE FAIL: $p returned $code"
    fail=1
  else
    echo "  $p -> $code"
  fi
done

if docker exec lylyrose-wp sh -c 'tail -200 /var/log/apache2/error.log 2>/dev/null | grep -ci "fatal error"' 2>/dev/null | grep -qv '^0$'; then
  echo "SMOKE FAIL: fatal error in apache log"
  docker exec lylyrose-wp sh -c 'tail -200 /var/log/apache2/error.log | grep -i "fatal error" | tail -3'
  fail=1
fi

[ $fail -eq 0 ] && echo "SMOKE OK" || echo "SMOKE FAILED"
exit $fail
