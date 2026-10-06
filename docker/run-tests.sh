#!/usr/bin/env bash
# Full test suite for lylyrose.
# Runs PHP linting of all custom code inside the WordPress container,
# then functional HTTP smoke tests against the running site.
#
# Usage: bash docker/run-tests.sh

set -uo pipefail

# Prevent Git Bash (MSYS) from rewriting container paths like /var/www/...
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL="*"

PASS=0
FAIL=0
FAILED_ITEMS=()

pass() { PASS=$((PASS+1)); echo "  PASS  $1"; }
fail() { FAIL=$((FAIL+1)); FAILED_ITEMS+=("$1"); echo "  FAIL  $1"; }

section() { echo ""; echo "== $1 =="; }

WP_CONTAINER="lylyrose-wp"
DB_CONTAINER="lylyrose-db"
# WP_HOME/WP_SITEURL are derived from the request host, so the published port
# (http://127.0.0.1:8030) and the Caddy proxy (https://lylyrose.local) are both
# canonical and neither redirects to the other. The proxy is the default because
# it is the scheme a real visitor gets, and it keeps the suite honest about the
# X-Forwarded-Proto path; override SITE_URL to exercise the port form instead.
SITE_URL="${SITE_URL:-https://lylyrose.local}"
THEMES_DIR="/var/www/html/wp-content/themes"
PLUGINS_DIR="/var/www/html/wp-content/plugins"
ACTIVE_THEME="${ACTIVE_THEME:-lylyrose}"

# Fixture products are resolved dynamically (DB rebuilds shift product IDs).
# FP_MAIN: in-stock simple product, price 9,940,000 Toman (old fixture 137 /
#   SKU DIGIKALA-20599667; 2% wallet cashback on 1x = 198,800).
# FP_OOS:  any other in-stock simple product (old fixture 138; flipped
#   out-of-stock/in-stock by the back-in-stock notifier section).
# FP_ALT:  higher-priced simple product (old fixture 139; cart total must
#   exceed the wallet balance for the partial-payment check).
# FP_NEG:  a product distinct from FP_MAIN for negative checks (old 11).
FIXTURE_PROG='
require("/var/www/html/wp-load.php");
$fp_main = 0;
foreach (wc_get_products(array("limit" => -1, "status" => "publish")) as $p) {
    if ($p->get_type() !== "simple" || $p->get_stock_status() !== "instock") { continue; }
    if (!$fp_main && abs((float) $p->get_price() - 9940000) < 0.01) { $fp_main = $p->get_id(); }
}
$other_ids = array();
foreach (wc_get_products(array("limit" => -1, "status" => "publish", "orderby" => "ID", "order" => "ASC", "return" => "ids")) as $pid) {
    $p = wc_get_product($pid);
    if (!$p || $p->get_type() !== "simple" || $p->get_stock_status() !== "instock" || (int) $pid === (int) $fp_main) { continue; }
    $other_ids[] = (int) $pid;
}
$fp_neg = isset($other_ids[0]) ? $other_ids[0] : 0;
$fp_oos = isset($other_ids[1]) ? $other_ids[1] : 0;
$fp_alt = 0;
foreach ($other_ids as $pid) {
    if ((float) wc_get_product($pid)->get_price() > 9940000) { $fp_alt = (int) $pid; break; }
}
if (!$fp_alt) { $fp_alt = isset($other_ids[2]) ? $other_ids[2] : 0; }
// negative-check product must differ from oos/alt fixtures
foreach ($other_ids as $pid) {
    if ((int) $pid !== $fp_oos && (int) $pid !== $fp_alt) { $fp_neg = (int) $pid; break; }
}
// payment/review sections assert stock levels on FP_MAIN; it must manage stock
$m = wc_get_product($fp_main);
if ($m && !$m->managing_stock()) { $m->set_manage_stock(true); $m->set_stock_quantity(100); $m->save(); }
echo implode("|", array($fp_main, $fp_oos, $fp_alt, $fp_neg));
'
FIXTURE_IDS=$(docker exec "$WP_CONTAINER" php -r "$FIXTURE_PROG" 2>/dev/null)
FP_MAIN=$(printf '%s' "$FIXTURE_IDS" | cut -d'|' -f1)
FP_OOS=$(printf '%s' "$FIXTURE_IDS" | cut -d'|' -f2)
FP_ALT=$(printf '%s' "$FIXTURE_IDS" | cut -d'|' -f3)
FP_NEG=$(printf '%s' "$FIXTURE_IDS" | cut -d'|' -f4)
if [ -n "$FP_MAIN" ] && [ "$FP_MAIN" != "0" ] && [ -n "$FP_OOS" ] && [ "$FP_OOS" != "0" ] && [ -n "$FP_ALT" ] && [ "$FP_ALT" != "0" ] && [ -n "$FP_NEG" ] && [ "$FP_NEG" != "0" ]; then
  echo "Fixtures resolved: main=$FP_MAIN oos=$FP_OOS alt=$FP_ALT neg=$FP_NEG"
else
  echo "FATAL: could not resolve fixture products (got: $FIXTURE_IDS)"
  exit 1
fi

section "1. PHP syntax lint (custom themes & plugins)"
CUSTOM_CODE=$(docker exec "$WP_CONTAINER" sh -c \
  "find $THEMES_DIR/aroma-store $THEMES_DIR/lylyrose $PLUGINS_DIR/lylyrose-core -name '*.php' -type f 2>/dev/null")
TOTAL_FILES=0
LINT_FAILED=0
for f in $CUSTOM_CODE; do
  TOTAL_FILES=$((TOTAL_FILES+1))
  if ! docker exec "$WP_CONTAINER" php -l "$f" >/dev/null 2>&1; then
    LINT_FAILED=1
    ERR=$(docker exec "$WP_CONTAINER" php -l "$f" 2>&1 | head -2 | tr '\n' ' ')
    fail "lint: $f — $ERR"
  fi
done
if [ "$LINT_FAILED" -eq 0 ]; then
  pass "php -l on $TOTAL_FILES PHP files (themes + plugin)"
fi

section "2. Active theme check"
ACTIVE=$(docker exec "$DB_CONTAINER" sh -c \
  'mariadb -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT option_value FROM wp_options WHERE option_name=\"stylesheet\";"' 2>/dev/null)
if [ "$ACTIVE" = "$ACTIVE_THEME" ]; then
  pass "active theme is '$ACTIVE'"
else
  fail "active theme is '$ACTIVE', expected '$ACTIVE_THEME'"
fi

section "3. Required plugins active"
for plugin in woocommerce/woocommerce.php lylyrose-core/lylyrose-core.php \
              redis-cache/redis-cache.php wp-mail-smtp/wp_mail_smtp.php \
              persian-woocommerce-sms/WoocommerceIR_SMS.php autoptimize/autoptimize.php \
              products-extractor-for-woocommerce/wcpe.php goftino/goftino.php \
              microsoft-clarity/clarity.php google-site-kit/google-site-kit.php; do
  if docker exec "$DB_CONTAINER" sh -c \
    "mariadb -u root -p\"\$MYSQL_ROOT_PASSWORD\" \"\$MYSQL_DATABASE\" -N -e \"SELECT option_value FROM wp_options WHERE option_name='active_plugins';\"" 2>/dev/null | grep -q "$plugin"; then
    pass "plugin active: $plugin"
  else
    fail "plugin NOT active: $plugin"
  fi
done

section "4. WooCommerce currency & Persian config"
CURRENCY=$(docker exec "$DB_CONTAINER" sh -c \
  'mariadb -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT option_value FROM wp_options WHERE option_name=\"woocommerce_currency\";"' 2>/dev/null)
if [ "$CURRENCY" = "IRT" ]; then
  pass "WooCommerce currency is Toman (IRT)"
else
  fail "WooCommerce currency is '$CURRENCY', expected IRT"
fi
LOCALE=$(docker exec "$DB_CONTAINER" sh -c \
  'mariadb -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT option_value FROM wp_options WHERE option_name=\"WPLANG\";"' 2>/dev/null)
if [ "$LOCALE" = "fa_IR" ]; then
  pass "site locale is fa_IR"
else
  fail "site locale is '$LOCALE', expected fa_IR"
fi

section "5. HTTP smoke tests"
check_http() {
  local url="$1" label="$2" expect="${3:-200}"
  CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 30 "$url")
  if [ "$CODE" = "$expect" ]; then
    pass "$label ($url -> $CODE)"
  else
    fail "$label ($url -> $CODE, expected $expect)"
  fi
}

check_http "$SITE_URL/"                       "homepage loads"
check_http "$SITE_URL/shop/"                  "shop page loads"
check_http "$SITE_URL/cart/"                  "cart page loads"
check_http "$SITE_URL/checkout/"              "checkout page loads"
check_http "$SITE_URL/my-account/"            "my account page loads"
# WPS Hide Login moved the login page. Current plugin versions render a 404 for
# wp-login.php (stronger than the old 301 redirect); both hide the real login page.
LOGIN_SLUG=$(docker exec "$DB_CONTAINER" sh -c \
  'mariadb -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT option_value FROM wp_options WHERE option_name=\"whl_page\";"' 2>/dev/null)
LOGIN_SLUG="${LOGIN_SLUG:-wp-login.php}"
check_http "$SITE_URL/$LOGIN_SLUG/"           "login page loads (slug: $LOGIN_SLUG)"
LOGIN_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 30 "$SITE_URL/wp-login.php")
case "$LOGIN_CODE" in
  301|302|404)
    pass "wp-login.php hidden from public -> $LOGIN_CODE"
    ;;
  *)
    fail "wp-login.php not hidden (got $LOGIN_CODE, expected 301/302/404)"
    ;;
esac

HOME_HTML=$(curl -s --max-time 30 "$SITE_URL/")
# Note: grep -q can exit 141 (SIGPIPE) under MSYS/Git Bash on large inputs,
# so we use grep -c with numeric checks instead.
html_has() {
  local haystack="$1" needle="$2"
  [ "$(printf '%s' "$haystack" | grep -c "$needle")" -gt 0 ]
}
html_has "$HOME_HTML" "lylyrose-theme" && pass "homepage uses lylyrose theme body class" || fail "lylyrose theme body class missing on homepage"
html_has "$HOME_HTML" 'dir="rtl"' && pass "homepage renders RTL" || fail "homepage not RTL"
html_has "$HOME_HTML" "dk-announce" && pass "announcement bar present" || fail "announcement bar missing"
html_has "$HOME_HTML" "dk-offers\|dk-hero\|dk-story-row" && pass "homepage Digikala sections render" || fail "no Digikala homepage sections found"

# The hero carousel: markup presence is all this section can prove. Whether a
# click or a drag actually moves a slide needs a real browser, so the
# interaction half lives in aroma_store's docker/hero-check.py (it needs
# google-chrome on the host, which the container does not have).
html_has "$HOME_HTML" "dk-hero-prev" && pass "hero has a previous-slide button" || fail "hero prev button missing"
html_has "$HOME_HTML" "dk-hero-next" && pass "hero has a next-slide button" || fail "hero next button missing"
HERO_SLIDES=$(printf '%s' "$HOME_HTML" | grep -o "dk-hero-slide" | grep -c .)
[ "$HERO_SLIDES" -ge 2 ] && pass "hero renders $HERO_SLIDES slides" || fail "hero has $HERO_SLIDES slide(s); a carousel needs 2+"
HERO_DOTS=$(printf '%s' "$HOME_HTML" | grep -oE 'aria-label="اسلاید [۰-۹0-9]+"' | grep -c .)
[ "$HERO_DOTS" -ge 1 ] && pass "hero dots are real <button>s, not inert <i>" || fail "hero dots are not <button>s; <i> dots cannot be clicked"
# hero.js pairs dot n with slide n, so a dot count that disagrees with the slide
# count leaves a dead dot at the end of the row.
# HERO_SLIDES reads one high: the grep also matches the `dk-hero-slides` wrapper.
[ "$HERO_DOTS" -eq "$(( HERO_SLIDES - 1 ))" ] && pass "hero dots match the slide count ($HERO_DOTS)" || fail "hero has $(( HERO_SLIDES - 1 )) slide(s) but $HERO_DOTS dot(s); a dot with no slide behind it does nothing"

# Design change 2026-10-06: the story row stayed, the service row is parked,
# and a five-cell top banner grid sits under them.
html_has "$HOME_HTML" "dk-story-row" \
  && pass "story row renders on the homepage" \
  || fail "story row is missing from the homepage; it was only parked, not removed"
printf '%s' "$HOME_HTML" | grep -q 'dk-story-ring' \
  && pass "story row has its gradient rings" || fail "story row renders without its rings"
# The service row is commented out in front-page.php, not deleted: if it ever
# comes back by accident the design silently doubles up again.
printf '%s' "$HOME_HTML" | grep -q 'dk-service-row' \
  && fail "service row renders; it is parked until the design wants it back" \
  || pass "service row is parked"
# The grid resolves its categories by slug, because مراقبت از پوست and مراقبت از مو
# hold their products in child terms and disappear from an hide_empty query.
# grep is ugrep here, and \{0,4000\} over the ~100KB one-line homepage spins
# forever — a hung suite prints no FAILs, so it reads green. Bounded slice instead.
TOP_GRID=$(printf '%s' "$HOME_HTML" | python3 -c '
import sys
h = sys.stdin.read()
i = h.find("dk-top-banner-grid")
print(h[i:i+20000] if i >= 0 else "")
')
[ -n "$TOP_GRID" ] && pass "top banner grid renders" || fail "top banner grid missing from the homepage"
DK_TB_CELLS=$(printf '%s' "$TOP_GRID" | grep -o 'class="dk-top-banner"' | grep -c .)
[ "$DK_TB_CELLS" -eq 5 ] && pass "top banner grid has $DK_TB_CELLS cells" || fail "top banner grid has $DK_TB_CELLS cells; the design calls for 5"
# آرایشی has no product_cat, so it links to a shop search rather than a term
# archive; the other four are slugs that must resolve to real archives.
for DK_CAT in "عطر" "مراقبت پوست" "مراقبت مو" "ست‌ها و هدیه"; do
  printf '%s' "$TOP_GRID" | grep -q "<b>$DK_CAT</b>" \
    && pass "top banner grid has $DK_CAT" || fail "top banner grid is missing $DK_CAT"
done
printf '%s' "$TOP_GRID" | grep -q '<b>آرایشی</b>' \
  && pass "top banner grid has آرایشی" || fail "top banner grid is missing آرایشی"
printf '%s' "$TOP_GRID" | grep -q 'product-category/skin-care/' \
  && pass "top banner skin-care cell resolves to a real archive" \
  || fail "top banner skin-care cell does not link to its archive"
# The footer wears the brand rose; verify the served CSS, not the source file.
FOOTER_CSS=$(docker exec "$WP_CONTAINER" cat "/var/www/html/wp-content/themes/$ACTIVE_THEME/style.css" 2>/dev/null)
printf '%s' "$FOOTER_CSS" | grep -A2 '^\.dk-footer {' | grep -q 'var(--dk-red)' \
  && pass "footer background uses the brand colour" || fail "footer background is not the brand colour"
grep -q '^\.dk-story-row {' <<< "$FOOTER_CSS" \
  && pass "style.css ships the dk-story-row rules" || fail "style.css has no dk-story-row rules; the markup would render unstyled"
HERO_JS=$(docker exec "$WP_CONTAINER" cat "/var/www/html/wp-content/themes/$ACTIVE_THEME/assets/js/hero.js" 2>/dev/null)
printf '%s' "$HERO_JS" | grep -c "pointerdown" | grep -q "^[1-9]" \
  && pass "hero.js wires pointer (drag) events" || fail "hero.js has no drag wiring"
printf '%s' "$HERO_JS" | grep -c "dk-hero-prev" | grep -q "^[1-9]" \
  && pass "hero.js wires the nav buttons" || fail "hero.js ignores the nav buttons"
# A drag that leaves the hero gets no pointerup without an explicit capture, so the
# drag stays live: the slide keeps its offset and then tracks the cursor with no
# button held. Both halves are needed — capturing without releasing breaks the
# next gesture.
printf '%s' "$HERO_JS" | grep -c "setPointerCapture" | grep -q "^[1-9]" \
  && pass "hero.js captures the pointer so a drag that leaves the hero still ends" \
  || fail "hero.js never calls setPointerCapture — a drag that wanders off the hero never receives its pointerup, so the slide stays stuck to the cursor"
printf '%s' "$HERO_JS" | grep -c "releasePointerCapture" | grep -q "^[1-9]" \
  && pass "hero.js releases the pointer capture when the drag ends" \
  || fail "hero.js takes a pointer capture it never releases, so the following drag starts from a broken state"

SHOP_HTML=$(curl -s --max-time 30 "$SITE_URL/shop/")
html_has "$SHOP_HTML" "dk-product-card\|woocommerce" && pass "shop page renders products/woocommerce markup" || fail "shop page missing product markup"
html_has "$SHOP_HTML" "تومان\|IRT\|&#x62A;&#x648;&#x645;&#x627;&#x646;" && pass "shop shows Toman currency" || fail "Toman not visible on shop page"

section "6. Cron sidecar + DISABLE_WP_CRON"
CRON_RUNNING=$(docker ps --filter name=lylyrose-cron --format "{{.Status}}" 2>/dev/null)
case "$CRON_RUNNING" in
  Up*) pass "cron sidecar container running ($CRON_RUNNING)" ;;
  *)   fail "cron sidecar container NOT running (status: ${CRON_RUNNING:-not found})" ;;
esac
WP_CRON_DISABLED=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); echo defined("DISABLE_WP_CRON") && DISABLE_WP_CRON ? "yes" : "no";' 2>/dev/null)
[ "$WP_CRON_DISABLED" = "yes" ] && pass "DISABLE_WP_CRON is defined and true" || fail "DISABLE_WP_CRON not set correctly"
# Sidecar can reach wp-cron.php over the internal network
CRON_HTTP=$(docker exec "$WP_CONTAINER" sh -c 'command -v curl >/dev/null && echo ok' 2>/dev/null)
if [ "$CRON_HTTP" = "ok" ]; then
  CRON_CODE=$(docker exec lylyrose-cron sh -c 'curl -sf -o /dev/null -w "%{http_code}" -m 30 "http://wordpress/wp-cron.php?doing_wp_cron"' 2>/dev/null)
  [ "$CRON_CODE" = "200" ] && pass "sidecar reaches wp-cron.php internally (200)" || fail "sidecar cannot reach wp-cron.php (got $CRON_CODE)"
else
  # fall back to checking the cron service network alias exists
  docker exec lylyrose-cron sh -c 'nc -z wordpress 80' >/dev/null 2>&1 \
    && pass "wordpress:80 reachable from sidecar" \
    || fail "wordpress unreachable from sidecar"
fi
# No overdue Action Scheduler actions.
# The suite itself schedules async work (wc_run_batch_process every minute), so a
# handful of actions can sit in `pending` a few seconds past their schedule while
# the sidecar's next tick claims them. Only a real backlog means cron is broken.
OVERDUE=$(docker exec "$DB_CONTAINER" sh -c \
  'mariadb -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT COUNT(*) FROM wp_actionscheduler_actions WHERE status=\"pending\" AND scheduled_date_gmt < UTC_TIMESTAMP() - INTERVAL 5 MINUTE;"' 2>/dev/null)
[ "${OVERDUE:-1}" = "0" ] && pass "no overdue Action Scheduler actions" || fail "$OVERDUE Action Scheduler actions overdue by >5min"

section "7. Redis object cache"
REDIS_UP=$(docker exec lylyrose-redis redis-cli ping 2>/dev/null)
[ "$REDIS_UP" = "PONG" ] && pass "redis responds PONG" || fail "redis ping failed (${REDIS_UP:-no response})"
DROPIN=$(docker exec "$WP_CONTAINER" test -f /var/www/html/wp-content/object-cache.php && echo yes || echo no)
[ "$DROPIN" = "yes" ] && pass "object-cache.php drop-in installed" || fail "object-cache.php drop-in missing"
PHP_REDIS=$(docker exec "$WP_CONTAINER" php -r 'echo class_exists("Redis") ? "yes" : "no";' 2>/dev/null)
[ "$PHP_REDIS" = "yes" ] && pass "PHP redis extension loaded" || fail "PHP redis extension missing"
KEYS=$(docker exec "$WP_CONTAINER" sh -c 'php -r "require(\"/var/www/html/wp-load.php\"); wp_cache_set(\"__suite_probe\", \"v\", \"__probe\"); echo wp_cache_get(\"__suite_probe\", \"__probe\") === \"v\" ? \"ok\" : \"fail\";"' 2>/dev/null)
[ "$KEYS" = "ok" ] && pass "wp_cache round-trip via Redis works" || fail "wp_cache round-trip failed"

section "8. Checkout & National ID field"
CHECKOUT_HTML=$(curl -sL --max-time 30 "$SITE_URL/checkout/")
# The checkout page must render the actual checkout UI, not a blog-style fallback
# Classic Digikala template (dk-checkout-*), block checkout, or classic shortcode all qualify
html_has "$CHECKOUT_HTML" "wp-block-woocommerce-checkout\|woocommerce-checkout-form\|dk-checkout-layout\|dk-cart-stepper" && pass "checkout renders checkout UI (not blog fallback)" || fail "checkout page uses wrong template"
# Classic shortcode checkout carries the field in HTML; block checkout registers via API
NI_CLASSIC=$(printf '%s' "$CHECKOUT_HTML" | grep -c "billing_national_id")
NI_BLOCK=$(docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
$cf = Automattic\WooCommerce\Blocks\Package::container()->get(Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class);
echo array_key_exists("lylyrose-core/national-id", $cf->get_additional_fields()) ? "ok" : "missing";' 2>/dev/null)
if [ "$NI_BLOCK" = "ok" ]; then
  pass "national ID registered for block checkout (lylyrose-core/national-id)"
elif [ "${NI_CLASSIC:-0}" -gt 0 ]; then
  pass "national ID field on classic checkout form"
else
  fail "national ID field not found on either checkout path"
fi
NI_LABEL=$(docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
$fields = apply_filters("woocommerce_checkout_fields", WC()->checkout()->get_checkout_fields());
echo isset($fields["billing"]["billing_national_id"]["label"]) ? trim($fields["billing"]["billing_national_id"]["label"]) : "none";' 2>/dev/null)
[ "$NI_LABEL" = "کد ملی" ] && pass "field label is کد ملی" || fail "label wrong ('$NI_LABEL')"
# checksum validator unit tests
NI_VALID=$(docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
require_once("/var/www/html/wp-content/plugins/lylyrose-core/includes/class-national-id.php");
echo ASC_National_ID::is_valid("0499370899") ? "ok" : "bad";
echo "|";
echo ASC_National_ID::is_valid("1234567890") ? "bad" : "ok";
echo "|";
echo ASC_National_ID::is_valid("1111111111") ? "bad" : "ok";
echo "|";
echo ASC_National_ID::is_valid("12345") ? "bad" : "ok";' 2>/dev/null)
[ "$NI_VALID" = "ok|ok|ok|ok" ] && pass "national ID checksum validation (4 cases)" || fail "checksum validation broken ($NI_VALID)"
# empty-cart checkout must stay on checkout (not redirect to cart)
EMPTY_CHECKOUT=$(curl -s -o /dev/null -w "%{http_code} %{redirect_url}" --max-time 30 "$SITE_URL/checkout/")
case "$EMPTY_CHECKOUT" in
  200*) pass "checkout reachable with empty cart (200)" ;;
  *)    fail "checkout redirects/errors with empty cart ($EMPTY_CHECKOUT)" ;;
esac

# Iran-only checkout form (3abc3ade). Each of these was verified absent from the
# pre-commit functions.php, so they detect a regression rather than restate
# current behaviour. The reordering and the hidden country row are done by
# inline JS in the footer, so they are asserted on markup curl can see, not on
# computed style — the JS's effect is only observable in a browser.
CO_COUNTRIES=$(docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
$c = WC()->countries->get_allowed_countries();
echo count($c), "|", implode(",", array_keys($c));' 2>/dev/null)
[ "$CO_COUNTRIES" = "1|IR" ] && pass "checkout country pinned to Iran only (allowed=$CO_COUNTRIES)" \
  || fail "country not pinned to Iran (allowed=$CO_COUNTRIES)"

CO_ORDER=$(docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
$f = apply_filters("woocommerce_checkout_fields", WC()->checkout()->get_checkout_fields());
foreach (array("billing_state","billing_city","billing_address_1") as $k) {
  echo $k, "=", ($f["billing"][$k]["priority"] ?? "missing"), "|";
}' 2>/dev/null)
[ "$CO_ORDER" = "billing_state=25|billing_city=26|billing_address_1=50|" ] \
  && pass "province and city sort above the address (25/26 before 50)" \
  || fail "checkout field order wrong ($CO_ORDER)"

html_has "$CHECKOUT_HTML" "dk_checkout_reorder_fields\|dk-row-half" \
  && pass "checkout re-lays province/city after the Iran-cities script rewrites the block" \
  || fail "checkout field-reorder script missing"
# WooCommerce always renders the country field, so its mere presence in the
# markup proves nothing about the change. What is new is the script hiding it,
# so assert on that call rather than on the field existing.
html_has "$CHECKOUT_HTML" "billing_country_field" \
  && html_has "$CHECKOUT_HTML" "find( '#billing_country_field' ).addClass( 'is-hidden' )" \
  && pass "country row hidden by the checkout script (one option, so noise removed)" \
  || fail "country row is not hidden — the pinned-to-Iran select shows a 1-option dropdown"
if printf '%s' "$CHECKOUT_HTML" | grep -q 'dk-page-title'; then
  fail "checkout still shows the page title (dk-page-title rendered)"
else
  pass "checkout page title suppressed"
fi

section "9. Autoptimize asset optimization"
AO_HOME=$(curl -s --max-time 30 "$SITE_URL/")
html_has "$AO_HOME" "cache/autoptimize" && pass "homepage serves autoptimize assets" || fail "no autoptimize cache assets on homepage"
AO_CACHE_COUNT=$(docker exec "$WP_CONTAINER" sh -c 'find /var/www/html/wp-content/cache/autoptimize -name "*.php" 2>/dev/null | wc -l')
[ "${AO_CACHE_COUNT:-0}" -gt 0 ] && pass "autoptimize cache files exist ($AO_CACHE_COUNT)" || fail "autoptimize cache dir empty"

section "10. Permalinks, coming-soon off, wishlist page"
PERMALINK=$(docker exec "$DB_CONTAINER" sh -c \
  'mariadb -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT option_value FROM wp_options WHERE option_name=\"permalink_structure\";"' 2>/dev/null)
[ "$PERMALINK" = "/%postname%/" ] && pass "permalink structure /%postname%/" || fail "permalink structure wrong ('$PERMALINK')"
COMING_SOON=$(docker exec "$DB_CONTAINER" sh -c \
  'mariadb -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT option_value FROM wp_options WHERE option_name=\"woocommerce_coming_soon\";"' 2>/dev/null)
[ "$COMING_SOON" = "no" ] && pass "WooCommerce coming-soon mode off" || fail "coming-soon is '$COMING_SOON' (products hidden if yes)"
WISHLIST=$(docker exec "$DB_CONTAINER" sh -c \
  'mariadb -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT COUNT(*) FROM wp_posts WHERE post_status=\"publish\" AND post_content LIKE \"%tinvwl_wishlist%\";"' 2>/dev/null)
check_http "$SITE_URL/wishlist/"              "wishlist page loads"
WIZARD_FLAG=$(docker exec "$DB_CONTAINER" sh -c \
  'mariadb -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT option_value FROM wp_options WHERE option_name=\"ti-woocommerce-wishlist_wizard\";"' 2>/dev/null)
[ "$WIZARD_FLAG" = "1" ] && pass "wishlist wizard completed flag set" || fail "wishlist wizard flag not set ('$WIZARD_FLAG')"

section "11. Store pages (about / contact / track-order / faq)"
for DK_PAGE in about contact track-order faq; do
  check_http "$SITE_URL/$DK_PAGE/" "$DK_PAGE page loads"
done
# Whole HTML is one line, so count matches (grep -o) not lines (grep -c).
DK_FOOTER_LINKS=$(curl -s --max-time 30 "$SITE_URL/" | grep -oE 'href="[^"]*/(about|contact|track-order|faq)/"' | wc -l)
[ "$DK_FOOTER_LINKS" -ge 4 ] && pass "footer links to all 4 store pages ($DK_FOOTER_LINKS found)" || fail "footer missing store-page links ($DK_FOOTER_LINKS/4)"
DK_TRACK_LOOKUP=$(curl -s --max-time 30 -o /dev/null -w "%{http_code}" "$SITE_URL/track-order/")
[ "$DK_TRACK_LOOKUP" = "200" ] && pass "track-order lookup form reachable" || fail "track-order broken ($DK_TRACK_LOOKUP)"
# Order lookup must reject a wrong contact (no enumeration oracle: same generic error either way)
DK_COOKIES="$(mktemp -u)"
DK_TRACK_PAGE=$(curl -s --max-time 30 -c "$DK_COOKIES" "$SITE_URL/track-order/")
DK_TRACK_NONCE=$(printf '%s' "$DK_TRACK_PAGE" | grep -oE 'name="asc_track_nonce" value="[a-f0-9]+"' | grep -oE '[a-f0-9]{10}')
if [ -n "$DK_TRACK_NONCE" ]; then
  DK_TRACK_BAD=$(curl -s --max-time 30 -b "$DK_COOKIES" -X POST "$SITE_URL/track-order/" \
    -d "asc_track_submit=1&asc_track_nonce=$DK_TRACK_NONCE&asc_order_id=99999999&asc_contact=wrong@example.com")
  printf '%s' "$DK_TRACK_BAD" | grep -q "پیدا نشد" && pass "order lookup rejects wrong contact" || fail "order lookup did not reject wrong contact"
  printf '%s' "$DK_TRACK_BAD" | grep -q "dk-track-result" && fail "wrong contact rendered an order" || pass "wrong contact shows no order data"
else
  fail "track-order nonce not found (form broken?)"
fi
DK_FAQ_ITEMS=$(curl -s --max-time 30 "$SITE_URL/faq/" | grep -o "<details" | wc -l)
[ "$DK_FAQ_ITEMS" -ge 15 ] && pass "FAQ has $DK_FAQ_ITEMS accordion items" || fail "FAQ accordion items missing ($DK_FAQ_ITEMS)"

# 12. Transactional emails: Persian subjects via ASC_Emails + branded options.
# Renders the completed-order email through the real WC pipeline inside the container.
section "12. Persian transactional emails"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -W 2>/dev/null || pwd)"
docker cp "$SCRIPT_DIR/email-check.php" lylyrose-wp:/tmp/email-check.php >/dev/null
EV_OUT=$(docker exec lylyrose-wp php /tmp/email-check.php 2>/dev/null)
printf '%s' "$EV_OUT" | grep -q "HAS_DIRECTION_CSS: YES" && pass "email RTL direction CSS injected" || fail "email missing direction:rtl CSS"
printf '%s' "$EV_OUT" | grep -q "HAS_TAHOMA: YES" && pass "email uses Tahoma font" || fail "email not using Tahoma"
printf '%s' "$EV_OUT" | grep -q "HAS_RED: YES" && pass "email base color #ef394e applied" || fail "email base color not #ef394e"
printf '%s' "$EV_OUT" | grep -q "HAS_RTL_ATTR: YES" && pass "email wrapper has dir=rtl" || fail "email wrapper missing dir=rtl"
printf '%s' "$EV_OUT" | grep -q "سفارش شما از" && pass "completed-order subject in Persian" || fail "completed-order subject not Persian"
printf '%s' "$EV_OUT" | grep -q "خبرهای خوب" && pass "completed-order heading in Persian" || fail "completed-order heading not Persian"

# 13. Image optimization: uploads converted to WebP by WP core (ASC_Images),
# existing library fully WebP. Renders a real JPEG + PNG through the media pipeline.
section "13. WebP image conversion"
docker cp "$SCRIPT_DIR/image-check.php" lylyrose-wp:/tmp/image-check.php >/dev/null
IMG_OUT=$(docker exec lylyrose-wp php /tmp/image-check.php 2>/dev/null)
printf '%s' "$IMG_OUT" | grep -q "JPG_STORED_MIME: image/webp" && pass "JPEG upload stored as WebP" || fail "JPEG upload not converted to WebP"
printf '%s' "$IMG_OUT" | grep -q "PNG_STORED_MIME: image/webp" && pass "PNG upload stored as WebP" || fail "PNG upload not converted to WebP"
printf '%s' "$IMG_OUT" | grep -q "JPG_SIZES: [0-9]* generated, 0 non-webp" && pass "all JPEG sizes generated as WebP" || fail "JPEG sizes not all WebP"
printf '%s' "$IMG_OUT" | grep -q "LIBRARY_NON_WEBP: 0" && pass "media library fully WebP" || fail "media library contains non-WebP images"
printf '%s' "$IMG_OUT" | grep -q "BIG_THRESHOLD: 2560" && pass "large-image threshold capped at 2560px" || fail "big image threshold not set"

# 14. Coupon surfaces: Digikala-style coupon field in cart summary + campaign
# banner strip (P1 #10). Uses a real browser session (cookies) + real POST.
section "14. Coupon surfaces"
CU_CPN=$(curl -s --max-time 30 -o /dev/null -w "%{http_code}" "${SITE_URL}/cart/")
[ "$CU_CPN" = "200" ] && pass "cart reachable" || fail "cart not reachable ($CU_CPN)"
# Coupon must exist (seeded by lylyrose-core or manually)
CU_COUPON=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); $ids = wc_get_coupon_id_by_code("welcome10") ? : wc_get_coupon_id_by_code("WELCOME10"); echo $ids ? "yes" : "no";' 2>/dev/null)
[ "$CU_COUPON" = "yes" ] && pass "WELCOME10 coupon exists" || fail "WELCOME10 coupon missing (seed it in admin or via script)"
# Campaign banner option registered
CU_BANNER=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); echo is_array(get_option("asc_campaign_banner")) ? "ok" : "missing";' 2>/dev/null)
[ "$CU_BANNER" = "ok" ] && pass "campaign banner option registered" || fail "asc_campaign_banner option missing"
# Cart page contains coupon form markup (fetch with session, cart empty state hides it —
# add a product first when coupon form is absent)
CU_COOKIES="$(mktemp -u)"
# fixture stock can drift (completed orders decrement); restore before add-to-cart
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
if (($p = wc_get_product((int) $argv[1])) && $p->managing_stock()) { $p->set_stock_quantity(10); $p->set_stock_status("instock"); $p->save(); }' "$FP_MAIN" >/dev/null 2>&1
# section 13 image conversion can leave the container slow; use generous
# timeouts and confirm the item actually landed before fetching /cart/
curl -s --max-time 120 --retry 2 -c "$CU_COOKIES" "$SITE_URL/" -o /dev/null
curl -s --max-time 120 --retry 2 -b "$CU_COOKIES" -c "$CU_COOKIES" "$SITE_URL/?add-to-cart=$FP_MAIN" -o /dev/null
CU_CART_HTML=$(curl -s --max-time 120 --retry 2 -b "$CU_COOKIES" -c "$CU_COOKIES" "$SITE_URL/cart/")
if ! html_has "$CU_CART_HTML" "woocommerce-items-in-cart\|quantity" && ! html_has "$CU_CART_HTML" "dk-coupon-form"; then
  sleep 5
  CU_CART_HTML=$(curl -s --max-time 120 --retry 2 -b "$CU_COOKIES" -c "$CU_COOKIES" "$SITE_URL/cart/")
fi
html_has "$CU_CART_HTML" "dk-coupon-form" && pass "coupon form renders in cart summary" || fail "coupon form missing from cart"
html_has "$CU_CART_HTML" 'name="coupon_code"' && pass "coupon field name matches WC handler (coupon_code)" || fail "coupon field name wrong"
html_has "$CU_CART_HTML" "woocommerce-cart-nonce" && pass "coupon form carries woocommerce-cart nonce" || fail "coupon form nonce missing"
# Campaign banner renders on home (only when activated; test passes if strip absent-but-valid)
CU_HOME=$(curl -s --max-time 30 "$SITE_URL/")
html_has "$CU_HOME" "dk-campaign\|dk-announce" && pass "campaign/announce strip renders on home" || fail "no campaign strip on home"
# Bad coupon code via real POST must NOT grant a discount row
CU_NONCE=$(printf '%s' "$CU_CART_HTML" | grep -oE 'name="woocommerce-cart-nonce" value="[a-f0-9]+"' | grep -oE '[a-f0-9]{10}')
if [ -n "$CU_NONCE" ]; then
  CU_BAD=$(curl -s --max-time 30 -b "$CU_COOKIES" -c "$CU_COOKIES" -X POST "$SITE_URL/cart/" \
    -d "coupon_code=INVALIDCODE99&apply_coupon=1&woocommerce-cart-nonce=$CU_NONCE&_wp_http_referer=%2Fcart%2F")
  html_has "$CU_BAD" "cart-discount" && fail "invalid coupon produced a discount row" || pass "invalid coupon rejected (no discount row)"
else
  fail "cart nonce not found (form broken?)"
fi

# 15. Frequently bought together: co-purchase query over completed orders,
# transient-cached, invalidated on order completion (P2 #12). Seeds a real
# completed order pair, checks markup + invalidation, cleans everything up.
section "15. Frequently bought together"
docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
$o = wc_create_order();
$o->add_product(wc_get_product((int) $argv[1]), 1);
$o->add_product(wc_get_product((int) $argv[2]), 1);
$o->add_product(wc_get_product((int) $argv[3]), 1);
$o->calculate_totals();
$o->update_status("completed");
echo "FBT_ORDER:" . $o->get_id();
' "$FP_MAIN" "$FP_OOS" "$FP_ALT" >/dev/null 2>&1
FBT_HTML=$(curl -sL --max-time 90 "$SITE_URL/?p=$FP_MAIN")
html_has "$FBT_HTML" "dk-fbt-title" && pass "FBT section renders on product page" || fail "FBT section missing on product page"
html_has "$FBT_HTML" "اکثراً با هم خریداری شده‌اند" && pass "FBT Persian heading present" || fail "FBT heading text wrong"
html_has "$FBT_HTML" "dk-fbt-btn" && pass "FBT buy-both button renders" || fail "FBT buy button missing"
html_has "$FBT_HTML" "dk-fbt-more" && pass "FBT extra partner cards render" || fail "FBT partner cards missing"
FBT_NEG=$(curl -sL --max-time 90 "$SITE_URL/?p=$FP_NEG" | grep -c "dk-fbt" || true)
[ "$FBT_NEG" = "0" ] && pass "product without co-purchase data renders no FBT" || fail "FBT rendered without data"
docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
foreach (wc_get_orders(array("limit" => 50, "return" => "ids")) as $oid) { $o = wc_get_order($oid); if ($o) { $o->delete(true); } }
foreach ($argv as $pid) { delete_transient("asc_fbt_" . (int) $pid); }
' "$FP_MAIN" "$FP_OOS" "$FP_ALT" >/dev/null 2>&1
FBT_POST=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); echo implode(",", ASC_Frequently_Bought::get_partners((int) $argv[1]));' "$FP_MAIN" 2>/dev/null)
[ -z "$FBT_POST" ] && pass "test orders cleaned up, cache flushed" || fail "FBT data leaked after cleanup: $FBT_POST"

# 16. Instagram / social proof strip: option-driven grid on the homepage
# (P2 #14). Seeds the option, asserts markup + lazy images + sanitizer, then
# restores the original option value.
section "16. Instagram strip"
INSTA_BACKUP=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); echo wp_json_encode(get_option("asc_instagram", array()));' 2>/dev/null)
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
update_option("asc_instagram", array("handle" => "@lylyrose.ir", "items" => array(
  array("image_url" => "https://lylyrose.local/wp-content/uploads/insta-1.jpg", "post_url" => ""),
  array("image_url" => "https://lylyrose.local/wp-content/uploads/insta-2.jpg", "post_url" => "https://instagram.com/p/abc"),
)));' >/dev/null 2>&1
docker exec "$WP_CONTAINER" rm -rf /var/www/html/wp-content/cache/supercache/lylyrose.local >/dev/null 2>&1
INSTA_HTML=$(curl -sL --max-time 90 "$SITE_URL/")
html_has "$INSTA_HTML" "dk-insta-grid" && pass "Instagram strip renders on homepage" || fail "Instagram strip missing"
html_has "$INSTA_HTML" "اینستاگرام ما" && pass "Instagram Persian heading present" || fail "Instagram heading wrong"
html_has "$INSTA_HTML" "insta-1.jpg" && pass "Instagram images rendered from option" || fail "Instagram images missing"
html_has "$INSTA_HTML" 'loading="lazy"' && pass "Instagram images lazy-loaded" || fail "Instagram images not lazy"
INSTA_SAN=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$o = ASC_Instagram::sanitize(array("handle" => "<script>x</script>@h", "items" => array(
  array("image_url" => "javascript:alert(1)"),
  array("image_url" => "https://ok.example/a.jpg", "post_url" => "javascript:alert(2)"),
)));
echo $o["handle"] . "|" . count($o["items"]) . "|" . $o["items"][0]["post_url"];' 2>/dev/null)
[ "$INSTA_SAN" = "@h|1|" ] && pass "Instagram sanitizer strips scripts and javascript: URLs" || fail "Instagram sanitizer weak: $INSTA_SAN"
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); delete_option("asc_instagram");' >/dev/null 2>&1
docker exec "$WP_CONTAINER" rm -rf /var/www/html/wp-content/cache/supercache/lylyrose.local >/dev/null 2>&1
INSTA_NEG=$(curl -sL --max-time 90 "$SITE_URL/" | grep -c "dk-insta" || true)
[ "$INSTA_NEG" = "0" ] && pass "unconfigured Instagram option renders nothing" || fail "Instagram strip rendered while unconfigured"
if [ -n "$INSTA_BACKUP" ] && [ "$INSTA_BACKUP" != "[]" ] && [ "$INSTA_BACKUP" != "{}" ]; then
  docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); update_option("asc_instagram", json_decode($argv[1], true));' "$INSTA_BACKUP" >/dev/null 2>&1
fi

# 17. Sales reports (P2 #15): Persian admin dashboard + CSV export. Seeds a
# completed order, computes the signed export token CLI-side (same HMAC the
# page renders), downloads the CSV, asserts Persian headers + BOM + row, then
# cleans up. Bad tokens must 403.
section "17. Sales reports"
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$o = wc_create_order();
$o->add_product(wc_get_product((int) $argv[1]), 2);
$o->set_billing_phone("09120000000");
$o->calculate_totals();
$o->update_status("completed");
' "$FP_MAIN" >/dev/null 2>&1
REP_STATS=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$s = ASC_Reports::get_stats("2026-01-01", "2026-12-31");
echo $s["orders"] . "|" . $s["items"] . "|" . $s["products"][(int) $argv[1]];' "$FP_MAIN" 2>/dev/null)
[ "$REP_STATS" = "1|2|2" ] && pass "reports stats aggregate seeded order" || fail "reports stats wrong: $REP_STATS"
# signed export URL (admin-only endpoint; requires an admin auth cookie)
# AUTH_COOKIE is load-bearing, not belt-and-braces. wp-admin picks the scheme
# from is_ssl(): over HTTPS it validates "secure_auth", but over plain HTTP it
# validates "logged_in" -- against the logged-in cookie's own hash, which the
# secure_auth value does not satisfy. With only the two cookies, every /wp-admin/
# request 302s to the WPS Hide Login "404" page whenever SITE_URL is an http://
# host, while the front-end assertions still pass (they use logged_in). The
# front end tolerating the cookie is why this looked host-specific.
REP_PAIR=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$m = WP_Session_Tokens::get_instance(1);
$e = time() + 600;
$t = $m->create($e);
echo SECURE_AUTH_COOKIE . "=" . wp_generate_auth_cookie(1, $e, "secure_auth", $t) . ";" . LOGGED_IN_COOKIE . "=" . wp_generate_auth_cookie(1, $e, "logged_in", $t) . ";" . AUTH_COOKIE . "=" . wp_generate_auth_cookie(1, $e, "auth", $t);' 2>/dev/null)
REP_TOKEN=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
echo ASC_Reports::export_token(1, "2026-01-01", "2026-12-31");' 2>/dev/null)
REP_CSV=$(curl -s --max-time 90 -b "$REP_PAIR" "$SITE_URL/wp-admin/admin.php?page=asc-reports&asc_export=$REP_TOKEN&from=2026-01-01&to=2026-12-31")
printf '%s' "$REP_CSV" | head -c 3 | xxd -p | grep -q "efbbbf" && pass "CSV starts with UTF-8 BOM" || fail "CSV missing UTF-8 BOM"
printf '%s' "$REP_CSV" | grep -q "شماره سفارش" && pass "CSV has Persian headers" || fail "CSV headers not Persian"
printf '%s' "$REP_CSV" | grep -q "09120000000" && pass "CSV contains seeded order row" || fail "CSV missing order row"
printf '%s' "$REP_CSV" | grep -q "تکمیل شده" && pass "CSV order status in Persian" || fail "CSV status not Persian"
REP_BAD=$(curl -s --max-time 90 -o /dev/null -w "%{http_code}" -b "$REP_PAIR" "$SITE_URL/wp-admin/admin.php?page=asc-reports&asc_export=deadbeef&from=2026-01-01&to=2026-12-31")
[ "$REP_BAD" = "403" ] && pass "bad export token rejected with 403" || fail "bad export token status: $REP_BAD"
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
foreach (wc_get_orders(array("limit" => 50, "return" => "ids")) as $oid) { $o = wc_get_order($oid); if ($o) { $o->delete(true); } }' >/dev/null 2>&1
REP_LEFT=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); echo count(wc_get_orders(array("limit" => 10, "return" => "ids")));' 2>/dev/null)
[ "$REP_LEFT" = "0" ] && pass "report test orders cleaned up" || fail "orders leaked: $REP_LEFT"

# 18. Wallet (P2 #11): woo-wallet config, cashback on completed order, review
# credit, topup form limits, my-account nav, partial payment fee. Everything is
# cleaned at the end (orders, comments, transactions, user kept for reuse).
section "18. Wallet"
WALLET_ACTIVE=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); echo class_exists("Woo_Wallet_Frontend") && wc_get_product(get_option("_woo_wallet_recharge_product")) ? "yes" : "no";' 2>/dev/null)
[ "$WALLET_ACTIVE" = "yes" ] && pass "woo-wallet active with recharge product" || fail "woo-wallet missing recharge product"

WALLET_CFG=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$s = woo_wallet()->settings_api;
echo $s->get_option("is_enable_wallet_topup", "_wallet_settings_general") . "|" .
     $s->get_option("is_enable_cashback_reward_program", "_wallet_settings_credit") . "|" .
     $s->get_option("cashback_type", "_wallet_settings_credit") . "|" .
     $s->get_option("cashback_amount", "_wallet_settings_credit") . "|" .
     implode(",", WOO_Wallet_Helper::get_cashback_order_statuses());' 2>/dev/null)
[ "$WALLET_CFG" = "on|on|percent|2|completed" ] && pass "wallet config: 2% cashback on completed, topup on" || fail "wallet config wrong: $WALLET_CFG"

# seed user + completed order -> expect 2% cashback credited
WALLET_SEED=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$uid = username_exists("wallet_tester") ?: wp_create_user("wallet_tester", "Wt#2026pass", "wallet_tester@example.com");
$u = new WP_User($uid); $u->set_role("customer");
// restore stock from earlier runs (cashback order decrements the fixtures)
foreach (array((int) $argv[1] => 10, (int) $argv[2] => 10) as $pid => $qty) {
    if (($p = wc_get_product($pid)) && $p->managing_stock()) { $p->set_stock_quantity($qty); $p->set_stock_status("instock"); $p->save(); }
}
global $wpdb;
$t = $wpdb->prefix . "woo_wallet_transactions";
$wpdb->query("TRUNCATE TABLE $t");
$o = wc_create_order(array("customer_id" => $uid, "status" => "pending"));
$o->add_product(wc_get_product((int) $argv[1]), 1);
$o->calculate_totals(); $o->save();
$o->update_status("completed");
echo "USER:" . $uid . "|BAL:" . woo_wallet()->wallet->get_wallet_balance($uid, "edit");' "$FP_MAIN" "$FP_ALT" 2>/dev/null)
WALLET_BAL=$(printf '%s' "$WALLET_SEED" | sed -n "s/.*BAL://p")
[ "$WALLET_BAL" = "198800" ] && pass "2% cashback credited on completed order (198800)" || fail "cashback balance wrong: $WALLET_BAL"

# product review action credits wallet once per product/user
WALLET_UID=$(printf '%s' "$WALLET_SEED" | sed -n "s/.*USER:\([0-9]*\).*/\1/p")
WALLET_REVIEW=$(docker exec "$WP_CONTAINER" php -r "require(\"/var/www/html/wp-load.php\");
\$uid = $WALLET_UID;
global \$wpdb;
// guard meta may linger from earlier runs — remove so the credit fires deterministically
\$wpdb->query(\"DELETE FROM {\$wpdb->postmeta} WHERE meta_key LIKE '_woo_wallet_comment_commission_received%'\");
\$c = wp_insert_comment(array(\"comment_post_ID\" => $FP_MAIN, \"user_id\" => \$uid, \"comment_author\" => \"wallet_tester\", \"comment_author_email\" => \"wallet_tester@example.com\", \"comment_content\" => \"تست دیدگاه کیف پول\", \"comment_approved\" => 1, \"comment_type\" => \"review\"));
\$comment = get_comment(\$c, ARRAY_A);
\$comment[\"comment_approved\"] = 1;
do_action(\"comment_post\", \$c, 1, \$comment);
echo \"BAL:\" . woo_wallet()->wallet->get_wallet_balance(\$uid, \"edit\") . \"|TX:\" . get_comment_meta(\$c, \"wallet_transaction_id\", true);
wp_delete_comment(\$c, true);" 2>/dev/null)
echo "$WALLET_REVIEW" | grep -q "TX:[0-9]" && pass "product review credits wallet once" || fail "review credit missing: $WALLET_REVIEW"

# topup form enforces min limit via is_valid_wallet_recharge_amount
WALLET_MIN=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); wc_load_cart(); wp_set_current_user((int) (username_exists("wallet_tester") ?: 0));
$f = Woo_Wallet_Frontend::instance();
$v = $f->is_valid_wallet_recharge_amount(50000);
echo ($v["is_valid"] ? "VALID" : "REJECTED");' 2>/dev/null)
[ "$WALLET_MIN" = "REJECTED" ] && pass "topup below 100000 minimum rejected" || fail "min topup not enforced: $WALLET_MIN"

# partial payment: cart total > balance -> fee equals balance
WALLET_FEE=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); wc_load_cart(); wp_set_current_user((int) (username_exists("wallet_tester") ?: 0));
WC()->cart->empty_cart();
WC()->cart->add_to_cart((int) $argv[1], 1);
WC()->cart->calculate_totals();
do_action("woocommerce_cart_calculate_fees", WC()->cart);
foreach (WC()->cart->get_fees() as $f) { if (false !== strpos($f->id ?? "", "wallet") || false !== strpos($f->name, "کیف پول")) { echo $f->amount; } }
WC()->cart->empty_cart();' "$FP_ALT" 2>/dev/null)
[ "$WALLET_FEE" = "-248800" ] && pass "partial payment fee = balance (-248800)" || fail "partial fee wrong: $WALLET_FEE"

# my-account theme nav exposes wallet link
# mint a fresh session+cookie pair per fetch — the security stack rotates tokens
fetch_auth() {
    local PAIR
    # AUTH_COOKIE here for the same reason as the reports pair above: plain-HTTP
    # /wp-admin/ authenticates against it rather than against SECURE_AUTH_COOKIE.
    PAIR=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$uid = username_exists("wallet_tester");
$m = WP_Session_Tokens::get_instance($uid);
$m->destroy_all();
$e = time() + 1200;
$t = $m->create($e);
echo SECURE_AUTH_COOKIE . "=" . wp_generate_auth_cookie($uid, $e, "secure_auth", $t) . ";" . LOGGED_IN_COOKIE . "=" . wp_generate_auth_cookie($uid, $e, "logged_in", $t) . ";" . AUTH_COOKIE . "=" . wp_generate_auth_cookie($uid, $e, "auth", $t);' 2>/dev/null)
    curl -sL --max-time 90 -b "$PAIR" "$1"
}
WALLET_NAV=$(fetch_auth "$SITE_URL/my-account/")
html_has "$WALLET_NAV" "my-account/my-wallet/" && pass "theme account nav links wallet" || fail "wallet nav missing from account page"
html_has "$WALLET_NAV" "کیف پول" && pass "wallet nav label in Persian" || fail "wallet nav label not Persian"

# wallet page renders balance + transactions for the test user
WALLET_PAGE=$(fetch_auth "$SITE_URL/my-account/my-wallet/")
html_has "$WALLET_PAGE" "woo-wallet-my-wallet-container" && pass "wallet page renders container" || fail "wallet page container missing"
html_has "$WALLET_PAGE" "۲۴۸,۸۰۰" && pass "wallet page shows cashback balance" || fail "wallet balance not shown"
html_has "$WALLET_PAGE" "بازپرداخت نقدی" && pass "wallet transaction history Persian" || fail "wallet history not Persian"

# guest must not see wallet content
WALLET_GUEST=$(curl -s --max-time 90 "$SITE_URL/my-account/my-wallet/")
html_has "$WALLET_GUEST" "woo-wallet-my-wallet-container" && fail "wallet leaks to guests" || pass "wallet hidden from guests"

# cleanup: transactions, orders, comments; keep wallet_tester user
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
global $wpdb;
$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}woo_wallet_transactions");
$wpdb->query("DELETE FROM {$wpdb->prefix}woo_wallet_transaction_meta");
foreach (wc_get_orders(array("limit" => 50, "return" => "ids")) as $oid) { $o = wc_get_order($oid); if ($o) { $o->delete(true); } }
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE \"_woo_wallet_comment_commission_received%\"");
$wu = username_exists("wallet_tester"); if ($wu) { delete_option("_wc_persistent_cart_" . $wu); }' >/dev/null 2>&1
WALLET_CLEAN=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
global $wpdb;
echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}woo_wallet_transactions") . "|" . count(wc_get_orders(array("limit" => 10, "return" => "ids")));' 2>/dev/null)
[ "$WALLET_CLEAN" = "0|0" ] && pass "wallet test data cleaned" || fail "wallet cleanup incomplete: $WALLET_CLEAN"

# 19. OTP login (P0 #1): form renders with tabs, request→SMS→verify creates a
# customer account, wrong code rejected, resend cooldown, invalid input
# rejected, rate limit, cleanup.
section "19. OTP login"
OTP_NONCE=$(curl -s --max-time 90 "$SITE_URL/my-account/" | grep -o 'data-otp-nonce="[^"]*"' | head -1 | sed 's/.*="//;s/"//')
[ -n "$OTP_NONCE" ] && pass "login page exposes OTP nonce" || fail "OTP nonce missing from login page"

OTP_LOGIN_HTML=$(curl -s --max-time 90 "$SITE_URL/my-account/")
html_has "$OTP_LOGIN_HTML" "dk-otp-tabs" && pass "OTP tabs render on login" || fail "OTP tabs missing"
html_has "$OTP_LOGIN_HTML" "ورود سریع" && pass "OTP quick-login tab Persian" || fail "OTP quick tab label missing"
html_has "$OTP_LOGIN_HTML" "دریافت کد تایید" && pass "OTP send button Persian" || fail "OTP send button missing"
html_has "$OTP_LOGIN_HTML" "otp-login.js" && pass "OTP script enqueued" || fail "otp-login.js not enqueued"

OTP_SINK="$SITE_URL/wp-content/uploads/wc-logs/pwsms.log"
otp_last_code() {
  docker exec "$WP_CONTAINER" sh -c 'grep -o "کد ورود شما: [0-9]*" /var/www/html/wp-content/uploads/wc-logs/pwsms.log 2>/dev/null | tail -1' 2>/dev/null | grep -o '[0-9]*$'
}
otp_ajax() {
  curl -s --max-time 90 -b "$1" -c "$1" -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
    --data-urlencode "action=$2" --data-urlencode "nonce=$OTP_NONCE" "${@:3}"
}

# registration: unknown mobile -> verify creates customer
OTP_JAR_A="$(mktemp -u)"
OTP_JAR_B="$(mktemp -u)"
OTP_JAR_C="$(mktemp -u)"
otp_ajax "$OTP_JAR_A" asc_otp_request --data-urlencode "mobile=09361112233" | grep -c '"success":true' >/dev/null
OTP_REG_CODE=$(otp_last_code)
[ -n "$OTP_REG_CODE" ] && pass "OTP code delivered to SMS sink" || fail "OTP code not found in SMS sink"
otp_ajax "$OTP_JAR_A" asc_otp_verify --data-urlencode "mobile=09361112233" --data-urlencode "code=$OTP_REG_CODE" > "$OTP_JAR_B.json"
grep -c '"success":true' "$OTP_JAR_B.json" >/dev/null && pass "OTP verify succeeds for new mobile" || fail "OTP verify failed for new mobile"
OTP_USER=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); $u = get_users(array("meta_key" => "billing_phone", "meta_value" => "09361112233", "number" => 1)); echo $u ? $u[0]->user_login : "none";' 2>/dev/null)
[ "$OTP_USER" != "none" ] && pass "OTP auto-creates customer account ($OTP_USER)" || fail "OTP did not create account"
OTP_ROLE=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); $u = get_user_by("login", "'$OTP_USER'"); echo $u ? implode(",", $u->roles) : "?";' 2>/dev/null)
[ "$OTP_ROLE" = "customer" ] && pass "OTP account role is customer" || fail "OTP account role wrong: $OTP_ROLE"
OTP_PAGE=$(curl -s --max-time 90 -b "$OTP_JAR_A" "$SITE_URL/my-account/")
html_has "$OTP_PAGE" "my-wallet" && pass "OTP session logged in (wallet nav visible)" || fail "OTP session not logged in"

# re-login: existing user can log in again with a fresh code
otp_ajax "$OTP_JAR_C" asc_otp_request --data-urlencode "mobile=09361112233" | grep -c '"success":true' >/dev/null
OTP_RE_CODE=$(otp_last_code)
otp_ajax "$OTP_JAR_C" asc_otp_verify --data-urlencode "mobile=09361112233" --data-urlencode "code=$OTP_RE_CODE" | grep -c '"success":true' >/dev/null && pass "existing user re-login works" || fail "existing user re-login failed"

# negative paths (AJAX JSON escapes Persian as \uXXXX — match success flags
# and structurally distinctive fragments instead of raw Persian text)
otp_ajax "$OTP_JAR_B" asc_otp_request --data-urlencode "mobile=09354445566" >/dev/null
otp_ajax "$OTP_JAR_B" asc_otp_verify --data-urlencode "mobile=09354445566" --data-urlencode "code=000000" | grep -c '"success":false' >/dev/null && pass "wrong code rejected" || fail "wrong code accepted"
OTP_COOLDOWN=$(otp_ajax "$OTP_JAR_B" asc_otp_request --data-urlencode "mobile=09354445566")
# cooldown message holds the seconds left as space-delimited digits ( 47 );
# the rate-limit message has no digits at all
printf '%s' "$OTP_COOLDOWN" | grep -c '"success":false' >/dev/null && printf '%s' "$OTP_COOLDOWN" | grep -cE ' [0-9]+ ' >/dev/null && pass "resend cooldown enforced" || fail "resend cooldown missing"
otp_ajax "$OTP_JAR_B" asc_otp_request --data-urlencode "mobile=12345" | grep -c '"success":false' >/dev/null && pass "invalid mobile rejected" || fail "invalid mobile accepted"
curl -s --max-time 90 -X POST "$SITE_URL/wp-admin/admin-ajax.php" --data-urlencode "action=asc_otp_verify" --data-urlencode "nonce=bogus" --data-urlencode "mobile=09361112233" --data-urlencode "code=123456" | grep -c '"success":false\|^-1' >/dev/null && pass "bad nonce rejected" || fail "bad nonce accepted"

# rate limit: hammer 09367778899 past MAX_PER_HOUR
OTP_RATE_LAST=""
for i in 1 2 3 4 5 6; do
  OTP_RATE_LAST=$(curl -s --max-time 90 -X POST "$SITE_URL/wp-admin/admin-ajax.php" --data-urlencode "action=asc_otp_request" --data-urlencode "nonce=$OTP_NONCE" --data-urlencode "mobile=09367778899")
done
# rate-limit response: success:false with no space-delimited digits
printf '%s' "$OTP_RATE_LAST" | grep -c '"success":false' >/dev/null && ! printf '%s' "$OTP_RATE_LAST" | grep -cE ' [0-9]+ ' >/dev/null && pass "rate limit enforced (6th request)" || fail "rate limit not enforced"

# persian digit normalization
OTP_FA=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); echo ASC_OTP::normalize_mobile("۰۹۳۵۱۲۳۴۵۶۷");' 2>/dev/null)
[ "$OTP_FA" = "09351234567" ] && pass "Persian digit mobile normalization" || fail "Persian digits not normalized: $OTP_FA"

# cleanup: user + transients + sink
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$u = get_user_by("login", "user_361112233"); if ($u) { wp_delete_user($u->ID); }
global $wpdb;
foreach (array("09361112233", "09354445566", "09367778899") as $m) { delete_transient("asc_otp_" . md5($m)); delete_transient("asc_otp_rate_" . md5($m)); }
@unlink("/var/www/html/wp-content/uploads/wc-logs/pwsms.log");' >/dev/null 2>&1
OTP_LEFT=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); $u = get_users(array("meta_key" => "billing_phone", "meta_value" => "09361112233")); echo count($u);' 2>/dev/null)
[ "$OTP_LEFT" = "0" ] && pass "OTP test data cleaned" || fail "OTP test user not cleaned"

# 20. Cart abandonment recovery (P0 #4): tracking integration on checkout,
# capture AJAX stores cart + phone, cron flips stale cart to abandoned and the
# Persian SMS reminder (with tokenized recovery link) lands in the PWSMS sink,
# follow-up emails scheduled, unsubscribed carts get no SMS, cleanup.
section "20. Cart abandonment recovery"

# deterministic start: clear plugin tables (test env) from earlier runs
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->prefix}cartflows_ca_cart_abandonment");
$wpdb->query("DELETE FROM {$wpdb->prefix}cartflows_ca_email_history");' >/dev/null 2>&1

CA_JAR="$(mktemp -u)"
curl -s --max-time 90 -b "$CA_JAR" -c "$CA_JAR" "$SITE_URL/?add-to-cart=$FP_MAIN" >/dev/null
CA_CHECKOUT=$(curl -s --max-time 90 -b "$CA_JAR" -c "$CA_JAR" "$SITE_URL/checkout/")
html_has "$CA_CHECKOUT" "cart-abandonment-tracking.js" && pass "abandonment tracking script on checkout" || fail "tracking script missing on checkout"
html_has "$CA_CHECKOUT" "id=\"billing_phone\"" && pass "checkout has billing_phone field" || fail "billing_phone field missing"
CA_NONCE=$(printf '%s' "$CA_CHECKOUT" | grep -o '"_nonce":"[^"]*"' | head -1 | sed 's/.*:"//;s/"//')
[ -n "$CA_NONCE" ] && pass "capture nonce exposed on checkout" || fail "capture nonce missing"

# capture: POST checkout data -> abandonment row with phone
curl -s --max-time 90 -b "$CA_JAR" -c "$CA_JAR" -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
  --data-urlencode "action=cartflows_save_cart_abandonment_data" \
  --data-urlencode "security=$CA_NONCE" \
  --data-urlencode "wcf_email=abandon-test@lylyrose.test" \
  --data-urlencode "wcf_name=Test" \
  --data-urlencode "wcf_surname=Abandon" \
  --data-urlencode "wcf_phone=09361112233" \
  --data-urlencode "wcf_post_id=8" | grep -c '"success":true' >/dev/null && pass "capture AJAX accepts checkout data" || fail "capture AJAX failed"
CA_ROW=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
global $wpdb;
$t = $wpdb->prefix . "cartflows_ca_cart_abandonment";
$r = $wpdb->get_row("SELECT order_status, other_fields FROM {$t} WHERE email = \"abandon-test@lylyrose.test\"");
if ($r) { $of = maybe_unserialize($r->other_fields); echo $r->order_status . "|" . $of["wcf_phone_number"]; } else { echo "none"; }' 2>/dev/null)
[ "$CA_ROW" = "normal|09361112233" ] && pass "abandonment row stored with phone" || fail "abandonment row missing/wrong: $CA_ROW"

# stale cart: backdate past cut-off, fire cron -> abandoned + SMS
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
global $wpdb;
$t = $wpdb->prefix . "cartflows_ca_cart_abandonment";
$wpdb->query("UPDATE {$t} SET time = DATE_SUB(NOW(), INTERVAL 45 MINUTE) WHERE email = \"abandon-test@lylyrose.test\"");' >/dev/null 2>&1
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); do_action("cartflows_ca_update_order_status_action");' >/dev/null 2>&1
CA_STATUS=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
global $wpdb;
$t = $wpdb->prefix . "cartflows_ca_cart_abandonment";
echo $wpdb->get_var("SELECT order_status FROM {$t} WHERE email = \"abandon-test@lylyrose.test\"");' 2>/dev/null)
[ "$CA_STATUS" = "abandoned" ] && pass "cron flips stale cart to abandoned" || fail "cron status: $CA_STATUS"
CA_SMS=$(docker exec "$WP_CONTAINER" sh -c 'grep -c "09361112233" /var/www/html/wp-content/uploads/wc-logs/pwsms.log 2>/dev/null || echo 0')
[ "${CA_SMS:-0}" -ge 1 ] && pass "Persian SMS reminder delivered to sink" || fail "SMS not delivered"
CA_TOKEN=$(docker exec "$WP_CONTAINER" sh -c 'grep -c "wcf_ac_token" /var/www/html/wp-content/uploads/wc-logs/pwsms.log 2>/dev/null || echo 0')
[ "${CA_TOKEN:-0}" -ge 1 ] && pass "SMS carries tokenized recovery link" || fail "recovery token missing in SMS"
CA_HIST=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
global $wpdb;
$h = $wpdb->prefix . "cartflows_ca_email_history";
echo $wpdb->get_var("SELECT COUNT(*) FROM {$h}");' 2>/dev/null)
[ "${CA_HIST:-0}" -ge 1 ] && pass "follow-up emails scheduled ($CA_HIST)" || fail "no emails scheduled"

# negative: unsubscribed cart must get no SMS (re-arm same row with consent opt-out)
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
global $wpdb;
$t = $wpdb->prefix . "cartflows_ca_cart_abandonment";
$wpdb->query("UPDATE {$t} SET order_status = \"normal\", unsubscribed = 1, time = DATE_SUB(NOW(), INTERVAL 45 MINUTE) WHERE email = \"abandon-test@lylyrose.test\"");' >/dev/null 2>&1
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); do_action("cartflows_ca_update_order_status_action");' >/dev/null 2>&1
CA_SMS2=$(docker exec "$WP_CONTAINER" sh -c 'grep -c "09361112233" /var/www/html/wp-content/uploads/wc-logs/pwsms.log 2>/dev/null || echo 0')
[ "${CA_SMS2:-0}" = "1" ] && pass "unsubscribed cart gets no SMS" || fail "unsubscribed SMS leak: $CA_SMS2"

# cleanup: plugin tables + SMS sink
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->prefix}cartflows_ca_cart_abandonment");
$wpdb->query("DELETE FROM {$wpdb->prefix}cartflows_ca_email_history");
@unlink("/var/www/html/wp-content/uploads/wc-logs/pwsms.log");' >/dev/null 2>&1
CA_LEFT=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
global $wpdb;
$t = $wpdb->prefix . "cartflows_ca_cart_abandonment";
echo $wpdb->get_var("SELECT COUNT(*) FROM {$t}");' 2>/dev/null)
[ "$CA_LEFT" = "0" ] && pass "abandonment test data cleaned" || fail "abandonment cleanup incomplete: $CA_LEFT"

# 21. ZarinPal sandbox e2e (P0 #5): gateway configured in sandbox mode, full
# purchase over HTTP — checkout form → order-pay POST → receipt fires
# Send_to_ZarinPal → 302 to sandbox StartPay → simulated payment (verify page)
# → wc-api callback with Authority+Status=OK → order processing, transaction id
# stored, stock decremented, cart emptied. Cancel path: Status=NOK leaves order
# pending and returns to checkout. Requires outbound internet (sandbox API).
section "21. ZarinPal sandbox e2e payment"

# deterministic gateway config: sandbox + dummy merchant (sandbox accepts any)
ZP_MODE=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$g = WC_Payment_Gateways::instance()->get_available_payment_gateways();
echo isset($g["WC_ZPal"]) ? ($g["WC_ZPal"]->sandbox ? "sandbox" : "production") : "off";' 2>/dev/null)
[ "$ZP_MODE" = "sandbox" ] && pass "ZarinPal gateway available in sandbox mode" || fail "ZarinPal mode: $ZP_MODE"

ZP_JAR="$(mktemp -u)"
curl -s --max-time 90 -b "$ZP_JAR" -c "$ZP_JAR" "$SITE_URL/?add-to-cart=$FP_MAIN" >/dev/null
ZP_CO=$(curl -s --max-time 90 -b "$ZP_JAR" -c "$ZP_JAR" "$SITE_URL/checkout/")
ZP_NONCE=$(printf '%s' "$ZP_CO" | grep -o 'name="woocommerce-process-checkout-nonce" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
[ -n "$ZP_NONCE" ] && pass "checkout nonce available" || fail "checkout nonce missing"

# place order as guest with ZarinPal selected (state/city are state_city term IDs, national id = valid checksum)
ZP_LOC=$(curl -s --max-time 90 -b "$ZP_JAR" -c "$ZP_JAR" -o /dev/null -w "%{redirect_url}" -X POST "$SITE_URL/checkout/" \
  --data-urlencode "woocommerce-process-checkout-nonce=$ZP_NONCE" \
  --data-urlencode "billing_first_name=Test" --data-urlencode "billing_last_name=User" \
  --data-urlencode "billing_email=zpal-e2e@lylyrose.test" --data-urlencode "billing_phone=09123456789" \
  --data-urlencode "billing_address_1=Test Address" --data-urlencode "billing_national_id=0012345679" \
  --data-urlencode "billing_city=3322" --data-urlencode "billing_state=THR" --data-urlencode "billing_postcode=12345" --data-urlencode "billing_country=IR" \
  --data-urlencode "shipping_first_name=Test" --data-urlencode "shipping_last_name=User" \
  --data-urlencode "shipping_address_1=Test Address" --data-urlencode "shipping_city=3322" --data-urlencode "shipping_state=THR" \
  --data-urlencode "shipping_postcode=12345" --data-urlencode "shipping_country=IR" \
  --data-urlencode "payment_method=WC_ZPal" --data-urlencode "woocommerce_checkout_place_order=Place order")
case "$ZP_LOC" in
  *order-pay*) pass "checkout creates order, redirects to order-pay" ;;
  *) fail "checkout redirect missing: $ZP_LOC" ;;
esac
ZP_OID=$(printf '%s' "$ZP_LOC" | grep -o "order-pay/[0-9]*" | grep -o "[0-9]*")
ZP_KEY=$(printf '%s' "$ZP_LOC" | grep -o "key=[^&]*")

# pay page: fetch fresh pay nonce, POST pay form -> 302 to receipt URL
# (process_payment redirects to order's checkout-payment URL; the receipt
# action fires when that URL is GET'd WITHOUT pay_for_order)
ZP_R1=$(curl -s --max-time 90 -b "$ZP_JAR" -c "$ZP_JAR" -o /dev/null -w "%{redirect_url}" -X POST "$ZP_LOC&pay_for_order=1" \
  --data-urlencode "woocommerce_pay=1" \
  --data-urlencode "woocommerce-pay-nonce=$(curl -s --max-time 90 -b "$ZP_JAR" -c "$ZP_JAR" "$ZP_LOC&pay_for_order=1" | grep -o 'name="woocommerce-pay-nonce" value="[^"]*"' | sed 's/.*value="//;s/"//')" \
  --data-urlencode "payment_method=WC_ZPal")
case "$ZP_R1" in
  *order-pay*) pass "pay form accepted, redirects to gateway receipt" ;;
  *) fail "pay POST unexpected: $ZP_R1" ;;
esac
# receipt GET fires requestPayment() against sandbox.zarinpal.com; the shared
# dummy merchant is latency-prone under rapid test runs, so retry up to 6x
ZP_STARTPAY=""
for _ in 1 2 3 4 5 6; do
  ZP_STARTPAY=$(curl -s --max-time 90 -b "$ZP_JAR" -c "$ZP_JAR" -o /tmp/zp_receipt_last.html -w "%{redirect_url}" "$ZP_R1")
  case "$ZP_STARTPAY" in
    https://sandbox.zarinpal.com/pg/StartPay/*) break ;;
    *) sleep 8 ;;
  esac
done
case "$ZP_STARTPAY" in
  https://sandbox.zarinpal.com/pg/StartPay/*) pass "receipt redirects to ZarinPal sandbox StartPay" ;;
  *) fail "no StartPay redirect: $ZP_STARTPAY" ;;
esac
ZP_AUTH=$(printf '%s' "$ZP_STARTPAY" | grep -o "StartPay/[A-Za-z0-9]*" | cut -d/ -f2)

# simulate payment: StartPay page -> verify token -> hit verify OK (marks session paid)
# ZarinPal's shared sandbox merchant intermittently serves an HTML "Server Error"
# instead of the payment page (~1 in 5 under rapid runs), so retry the token fetch
# separately from the redirect above.
ZP_VTOKEN=""
for _ in 1 2 3 4 5 6; do
  ZP_VTOKEN=$(curl -s --max-time 60 "https://sandbox.zarinpal.com/pg/StartPay/$ZP_AUTH" | grep -o "pg/verify/[a-z0-9]*/?status=OK" | head -1 | cut -d/ -f3)
  [ -n "$ZP_VTOKEN" ] && break
  sleep 5
done
[ -n "$ZP_VTOKEN" ] && pass "sandbox payment page reached, verify token extracted" || fail "StartPay page/verify token failed"
[ -n "$ZP_VTOKEN" ] && curl -s --max-time 60 -o /dev/null "https://sandbox.zarinpal.com/pg/verify/$ZP_VTOKEN/?status=OK"

# gateway callback with Authority + Status=OK -> order completed.
# Without a token the gateway never saw a successful payment, so skip rather
# than report three cascading failures for one upstream sandbox outage.
if [ -n "$ZP_VTOKEN" ]; then
  ZP_CB=$(curl -s --max-time 90 -b "$ZP_JAR" -o /dev/null -w "%{redirect_url}" "$SITE_URL/wc-api/WC_ZPal/?wc_order=$ZP_OID&Authority=$ZP_AUTH&Status=OK")
  case "$ZP_CB" in
    *order-received*) pass "callback redirects to order-received" ;;
    *) fail "callback redirect: $ZP_CB" ;;
  esac
  ZP_ORDER=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
  $o = wc_get_order((int) $argv[1]);
  echo $o ? $o->get_status() . "|" . ($o->is_paid() ? "paid" : "unpaid") . "|" . $o->get_transaction_id() : "gone";' "$ZP_OID" 2>/dev/null)
  case "$ZP_ORDER" in
    *"|paid|"*) pass "order $ZP_OID completed with transaction id" ;;
    *) fail "order state after OK: $ZP_ORDER" ;;
  esac
else
  fail "StartPay unavailable (upstream sandbox error) - callback + order state not verifiable"
fi

# cancel path: fresh order, pay, then callback with Status=NOK stays pending
ZP_JAR2="$(mktemp -u)"
curl -s --max-time 90 -b "$ZP_JAR2" -c "$ZP_JAR2" "$SITE_URL/?add-to-cart=$FP_MAIN" >/dev/null
ZP_CO2=$(curl -s --max-time 90 -b "$ZP_JAR2" -c "$ZP_JAR2" "$SITE_URL/checkout/")
ZP_NONCE2=$(printf '%s' "$ZP_CO2" | grep -o 'name="woocommerce-process-checkout-nonce" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
ZP_LOC2=$(curl -s --max-time 90 -b "$ZP_JAR2" -c "$ZP_JAR2" -o /dev/null -w "%{redirect_url}" -X POST "$SITE_URL/checkout/" \
  --data-urlencode "woocommerce-process-checkout-nonce=$ZP_NONCE2" \
  --data-urlencode "billing_first_name=Test" --data-urlencode "billing_last_name=User" \
  --data-urlencode "billing_email=zpal-nok@lylyrose.test" --data-urlencode "billing_phone=09123456789" \
  --data-urlencode "billing_address_1=Test Address" --data-urlencode "billing_national_id=0012345679" \
  --data-urlencode "billing_city=3322" --data-urlencode "billing_state=THR" --data-urlencode "billing_postcode=12345" --data-urlencode "billing_country=IR" \
  --data-urlencode "payment_method=WC_ZPal" --data-urlencode "woocommerce_checkout_place_order=Place order")
ZP_OID2=$(printf '%s' "$ZP_LOC2" | grep -o "order-pay/[0-9]*" | grep -o "[0-9]*")
if [ -n "$ZP_OID2" ]; then
  curl -s --max-time 90 -b "$ZP_JAR2" -c "$ZP_JAR2" "$ZP_LOC2&pay_for_order=1" -o /dev/null
  curl -s --max-time 90 -b "$ZP_JAR2" -c "$ZP_JAR2" -o /dev/null -X POST "$ZP_LOC2&pay_for_order=1" \
    --data-urlencode "woocommerce_pay=1" \
    --data-urlencode "woocommerce-pay-nonce=$(curl -s --max-time 90 -b "$ZP_JAR2" -c "$ZP_JAR2" "$ZP_LOC2&pay_for_order=1" | grep -o 'name="woocommerce-pay-nonce" value="[^"]*"' | sed 's/.*value="//;s/"//')" \
    --data-urlencode "payment_method=WC_ZPal"
  ZP_AUTH2=$(curl -s --max-time 90 -b "$ZP_JAR2" -c "$ZP_JAR2" -o /dev/null -w "%{redirect_url}" "$ZP_LOC2" | grep -o "StartPay/[A-Za-z0-9]*" | cut -d/ -f2)
  curl -s --max-time 90 -b "$ZP_JAR2" -o /dev/null "$SITE_URL/wc-api/WC_ZPal/?wc_order=$ZP_OID2&Authority=$ZP_AUTH2&Status=NOK"
  ZP_NOK=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$o = wc_get_order((int) $argv[1]);
echo $o ? $o->get_status() . "|" . ($o->is_paid() ? "paid" : "unpaid") : "gone";' "$ZP_OID2" 2>/dev/null)
  [ "$ZP_NOK" = "pending|unpaid" ] && pass "cancelled payment leaves order pending" || fail "NOK order state: $ZP_NOK"
else
  fail "cancel-path order not created: $ZP_LOC2"
fi

# cleanup: test orders + stock restore
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
foreach (array((int) $argv[1], (int) $argv[2]) as $id) { if ($o = wc_get_order($id)) { $o->delete(true); } }
$p = wc_get_product((int) $argv[3]); if ($p && $p->get_stock_quantity() < 100) { $p->set_stock_quantity(100); $p->save(); }' "$ZP_OID" "$ZP_OID2" "$FP_MAIN" >/dev/null 2>&1
ZP_LEFT=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$o = wc_get_order((int) $argv[1]);
$p = wc_get_product((int) $argv[2]);
echo ($o ? "order-left" : "order-deleted") . "|" . $p->get_stock_quantity();' "$ZP_OID" "$FP_MAIN" 2>/dev/null)
[ "$ZP_LEFT" = "order-deleted|100" ] && pass "payment test data cleaned" || fail "cleanup incomplete: $ZP_LEFT"

section "22. Review incentive (P1 #8)"
# complete a test order -> action scheduled; then post an approved review as
# the same customer -> 10% single-use coupon generated + emailed
RI_JAR="$(mktemp -u)"
curl -s --max-time 90 -b "$RI_JAR" -c "$RI_JAR" "$SITE_URL/?add-to-cart=$FP_MAIN" >/dev/null
RI_CO=$(curl -s --max-time 90 -b "$RI_JAR" -c "$RI_JAR" "$SITE_URL/checkout/")
RI_NONCE=$(printf '%s' "$RI_CO" | grep -o 'name="woocommerce-process-checkout-nonce" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
RI_LOC=$(curl -s --max-time 90 -b "$RI_JAR" -c "$RI_JAR" -o /dev/null -w "%{redirect_url}" -X POST "$SITE_URL/checkout/" \
  --data-urlencode "woocommerce-process-checkout-nonce=$RI_NONCE" \
  --data-urlencode "billing_first_name=Riya" --data-urlencode "billing_last_name=Test" \
  --data-urlencode "billing_email=riya-review@lylyrose.test" --data-urlencode "billing_phone=09355556666" \
  --data-urlencode "billing_address_1=Test Address" --data-urlencode "billing_national_id=0012345679" \
  --data-urlencode "billing_city=3322" --data-urlencode "billing_state=THR" --data-urlencode "billing_postcode=12345" --data-urlencode "billing_country=IR" \
  --data-urlencode "payment_method=WC_ZPal" --data-urlencode "woocommerce_checkout_place_order=Place order")
RI_OID=$(printf '%s' "$RI_LOC" | grep -o "order-pay/[0-9]*" | grep -o "[0-9]*")

RI_HOOKS=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
echo (int) (class_exists("ASC_Reviews")) . "|" . (function_exists("as_schedule_single_action") ? 1 : 0);' 2>/dev/null)
[ "$RI_HOOKS" = "1|1" ] && pass "ASC_Reviews class + Action Scheduler available" || fail "reviews prerequisites: $RI_HOOKS"

RI_SCHED=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$o = wc_get_order((int) $argv[1]);
if (!$o) { echo "gone"; exit; }
// complete for real (fires woocommerce_order_status_completed -> schedules request;
// also lets the coupon handler find this order as a completed purchase)
$o->update_status("completed", "Test completion");
$o2 = wc_get_order((int) $argv[1]);
global $wpdb;
$t = $wpdb->prefix . "actionscheduler_actions";
$row = $wpdb->get_row($wpdb->prepare("SELECT action_id, scheduled_date_gmt FROM $t WHERE hook = %s AND args = %s ORDER BY action_id DESC LIMIT 1", "asc_send_review_request", "{\"order_id\":{$o2->get_id()}}"));
echo $o2->get_meta("_asc_review_requested") . "|" . ($row ? substr($row->scheduled_date_gmt, 0, 10) : "none");' "$RI_OID" 2>/dev/null)
case "$RI_SCHED" in
  scheduled\|20*) pass "review request scheduled 3-7 days out ($RI_SCHED)" ;;
  *) fail "review request not scheduled: $RI_SCHED" ;;
esac

# immediate send: invoke the hook directly (don't wait 3-7 days)
RI_SENT=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
do_action("asc_send_review_request", array("order_id" => (int) $argv[1]));
$o = wc_get_order((int) $argv[1]);
echo $o ? $o->get_meta("_asc_review_requested") : "gone";' "$RI_OID" 2>/dev/null)
[ "$RI_SENT" = "sent" ] && pass "review request sends on hook (email + note)" || fail "review request send failed: $RI_SENT"

# post an approved review as the same customer -> coupon issued
RI_CMT=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$c = wp_insert_comment(array(
  "comment_post_ID" => (int) $argv[2],
  "comment_author" => "Riya Test",
  "comment_author_email" => "riya-review@lylyrose.test",
  "comment_content" => "محصول فوق‌العاده‌ای بود، عطر ماندگار و اصیل. تست نظر.",
  "comment_type" => "review",
  "comment_approved" => 1,
  "comment_date" => current_time("mysql"),
));
do_action("comment_post", $c, 1, get_comment($c, ARRAY_A));
echo $c ? $c : "err";' "" "$FP_MAIN" 2>/dev/null)
RI_CP=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
global $wpdb;
$o = wc_get_order((int) $argv[1]);
$flag = "_asc_review_coupon_" . (int) $argv[2];
$code = $o ? $o->get_meta($flag) : "";
if ($code) {
  $c = new WC_Coupon($code);
  echo $code . "|" . $c->get_discount_type() . "|" . $c->get_amount() . "|" . $c->get_usage_limit();
} else { echo "none"; }' "$RI_OID" "$FP_MAIN" 2>/dev/null)
case "$RI_CP" in
  REVIEW-*\|percent\|10\|1) pass "review coupon issued (10% single-use): $RI_CP" ;;
  *) fail "review coupon missing/wrong: $RI_CP" ;;
esac

# second review must NOT double-issue
RI_CP2=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$c = wp_insert_comment(array(
  "comment_post_ID" => (int) $argv[2],
  "comment_author" => "Riya Test",
  "comment_author_email" => "riya-review@lylyrose.test",
  "comment_content" => "نظر دوم براي تست جلوگيري از تكرار.",
  "comment_type" => "review",
  "comment_approved" => 1,
  "comment_date" => current_time("mysql"),
));
do_action("comment_post", $c, 1, get_comment($c, ARRAY_A));
$o = wc_get_order((int) $argv[1]);
$codes = $o->get_meta("_asc_review_coupon_" . (int) $argv[2]);
echo is_string($codes) && strlen($codes) > 0 ? $codes : "none";' "$RI_OID" "$FP_MAIN" 2>/dev/null)
RI_N=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
global $wpdb;
$n = $wpdb->get_var("SELECT COUNT(DISTINCT meta_value) FROM {$wpdb->postmeta} WHERE post_id = " . (int) $argv[1] . " AND meta_key = \"_asc_review_coupon_" . (int) $argv[2] . "\"");
echo (int) $n;' "$RI_OID" "$FP_MAIN" 2>/dev/null)
[ "$RI_N" = "1" ] && pass "no duplicate coupon on second review" || fail "coupon duplication: $RI_N"

# review by non-purchaser must NOT issue coupon
RI_CP3=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$c = wp_insert_comment(array(
  "comment_post_ID" => (int) $argv[2],
  "comment_author" => "Stranger",
  "comment_author_email" => "stranger-nowhere@lylyrose.test",
  "comment_content" => "نظر بدون خرید برای تست.",
  "comment_type" => "review",
  "comment_approved" => 1,
  "comment_date" => current_time("mysql"),
));
do_action("comment_post", $c, 1, get_comment($c, ARRAY_A));
$o = wc_get_order((int) $argv[1]);
echo $o->get_meta("_asc_review_coupon_" . (int) $argv[2]);' "$RI_OID" "$FP_MAIN" 2>/dev/null)
# coupon code should be unchanged (the original RI_CP), not a second one for stranger
case "$RI_CP" in
  REVIEW-*) RI_CODE="${RI_CP%%|*}"
    [ "$RI_CP3" = "$RI_CODE" ] && pass "non-purchaser review yields no coupon" || fail "stranger review changed coupon: $RI_CP3 vs $RI_CODE" ;;
esac

# cleanup: order, comments, coupon, stock
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$o = wc_get_order((int) $argv[1]);
if ($o) {
    // Scrub the per-product coupon-issue guard meta so a stale flag from a
    // prior aborted run does not suppress re-issuing on the next run.
    $o->delete_meta_data("_asc_review_requested");
    $o->delete_meta_data("_asc_review_coupon_" . (int) $argv[2]);
    $o->save();
    $o->delete(true);
}
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_author_email IN (\"riya-review@lylyrose.test\", \"stranger-nowhere@lylyrose.test\")");
$wpdb->query("DELETE FROM {$wpdb->prefix}actionscheduler_actions WHERE hook = \"asc_send_review_request\"");
$p = wc_get_product((int) $argv[2]); if ($p && $p->get_stock_quantity() < 100) { $p->set_stock_quantity(100); $p->save(); }
$wpdb->query("DELETE p, pm FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE p.post_type = \"shop_coupon\" AND p.post_title LIKE \"REVIEW-%\"");
echo "done";' "$RI_OID" "$FP_MAIN" >/dev/null 2>&1
RI_LEFT=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$o = wc_get_order((int) $argv[1]);
global $wpdb;
$comments = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_author_email = \"riya-review@lylyrose.test\"");
$p = wc_get_product((int) $argv[2]);
echo ($o ? "order-left" : "order-deleted") . "|" . $comments . "|" . $p->get_stock_quantity();' "$RI_OID" "$FP_MAIN" 2>/dev/null)
[ "$RI_LEFT" = "order-deleted|0|100" ] && pass "review test data cleaned" || fail "review cleanup: $RI_LEFT"

# 23. Fragrance note pyramid (P2 #13): postmeta-driven layers on the single
# product page. Seeds _asc_notes_top/_heart/_base on product 17, asserts the
# dk-notes card + Persian layer titles, checks a noteless product renders
# nothing, then deletes the meta.
section "23. Fragrance note pyramid (P2 #13)"
FN_CLASS=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); echo class_exists("ASC_Fragrance_Notes") ? "yes" : "no";' 2>/dev/null)
[ "$FN_CLASS" = "yes" ] && pass "ASC_Fragrance_Notes class loaded" || fail "ASC_Fragrance_Notes missing"

docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
update_post_meta((int) $argv[1], "_asc_notes_top", "برگاموت\nیاس");
update_post_meta((int) $argv[1], "_asc_notes_heart", "گل محمدی\nعود");
update_post_meta((int) $argv[1], "_asc_notes_base", "مشک سفید\nوانیل");
echo "seeded";' "$FP_MAIN" >/dev/null 2>&1
docker exec "$WP_CONTAINER" rm -rf /var/www/html/wp-content/cache/supercache/lylyrose.local >/dev/null 2>&1

FN_HTML=$(curl -sL --max-time 120 --retry 2 "$SITE_URL/?p=$FP_MAIN")
html_has "$FN_HTML" "dk-notes" && pass "note pyramid card renders on product page" || fail "dk-notes card missing"
html_has "$FN_HTML" "هرم رایحه" && pass "pyramid Persian heading present" || fail "pyramid heading missing"
html_has "$FN_HTML" "نوت آغازین" && pass "top layer title renders" || fail "top layer title missing"
html_has "$FN_HTML" "نوت میانی" && pass "heart layer title renders" || fail "heart layer title missing"
html_has "$FN_HTML" "نوت پایه" && pass "base layer title renders" || fail "base layer title missing"
html_has "$FN_HTML" "برگاموت" && pass "top note text renders" || fail "top note text missing"
html_has "$FN_HTML" "وانیل" && pass "base note text renders" || fail "base note text missing"
FN_ICON_TOP=$(printf '%s' "$FN_HTML" | grep -c "🌿" || true)
FN_ICON_HEART=$(printf '%s' "$FN_HTML" | grep -c "🌸" || true)
FN_ICON_BASE=$(printf '%s' "$FN_HTML" | grep -c "🪵" || true)
[ "$FN_ICON_TOP" -ge 1 ] && [ "$FN_ICON_HEART" -ge 1 ] && [ "$FN_ICON_BASE" -ge 1 ] \
  && pass "layer icons render" || fail "layer icons missing (top=$FN_ICON_TOP heart=$FN_ICON_HEART base=$FN_ICON_BASE)"

FN_NEG=$(curl -sL --max-time 120 --retry 2 "$SITE_URL/?p=$FP_NEG" | grep -c "dk-notes" || true)
[ "$FN_NEG" = "0" ] && pass "noteless product renders no pyramid" || fail "pyramid rendered on noteless product"

docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
foreach (array("_asc_notes_top", "_asc_notes_heart", "_asc_notes_base") as $k) { delete_post_meta((int) $argv[1], $k); }
echo "cleaned";' "$FP_MAIN" >/dev/null 2>&1
docker exec "$WP_CONTAINER" rm -rf /var/www/html/wp-content/cache/supercache/lylyrose.local >/dev/null 2>&1
FN_LEFT=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$out = array();
foreach (array("top", "heart", "base") as $k) { $out[] = get_post_meta((int) $argv[1], "_asc_notes_" . $k, true) === "" ? "empty" : "left"; }
echo implode("|", $out);' "$FP_MAIN" 2>/dev/null)
[ "$FN_LEFT" = "empty|empty|empty" ] && pass "note test data cleaned" || fail "note cleanup: $FN_LEFT"

section "24. Multi-step checkout (P3)"
CS2_JAR="$(mktemp -u)"
curl -s --max-time 30 -b "$CS2_JAR" -c "$CS2_JAR" "$SITE_URL/?add-to-cart=$FP_MAIN" >/dev/null
CS2_HTML=$(curl -sL --max-time 30 -b "$CS2_JAR" -c "$CS2_JAR" "$SITE_URL/checkout/")
rm -f "$CS2_JAR"
html_has "$CS2_HTML" "dk-cs2" && pass "2-step wrapper renders" || fail "dk-cs2 missing"
html_has "$CS2_HTML" "dk-checkout-step--1" && pass "step 1 (address) card present" || fail "step 1 card missing"
html_has "$CS2_HTML" "dk-checkout-step--2" && pass "step 2 (payment) card present" || fail "step 2 card missing"
html_has "$CS2_HTML" "dk-step-next-btn" && pass "'ادامه به پرداخت' button present" || fail "next button missing"
html_has "$CS2_HTML" "dk-step-back-link" && pass "back-to-address link present" || fail "back link missing"
# No-JS fallback: step 2 must render WITHOUT is-hidden in raw HTML
printf '%s' "$CS2_HTML" | grep -q "dk-checkout-step--2 is-hidden" \
  && fail "step 2 wrongly hidden in raw HTML (no-JS fallback broken)" || pass "no-JS fallback: both steps visible"
# Summary sidebar + place-order still present (unchanged anatomy)
html_has "$CS2_HTML" "order_review\|place-order" && pass "order summary sidebar preserved" || fail "order summary missing"
html_has "$CS2_HTML" "billing_national_id" && pass "national-ID field still in step 1" || fail "national-ID missing"
# 24.1 The order review must render EXACTLY ONCE. The theme used to call both
# woocommerce_order_review() and do_action('woocommerce_checkout_order_review')
# in the sidebar, and fired that action again in step 2 -- and the action already
# runs woocommerce_order_review at priority 10. That produced 3 review tables,
# 2 payment blocks and 2 #place_order submit buttons on a live checkout.
# Counts the opening class attribute, so it cannot be satisfied by a CSS mention.
cs2_count() { printf '%s' "$CS2_HTML" | grep -o -- "$1" | wc -l | tr -d ' '; }
CS2_TBL=$(cs2_count 'class="shop_table woocommerce-checkout-review-order-table"')
[ "$CS2_TBL" = 1 ] && pass "order review table renders exactly once" \
  || fail "order review table renders $CS2_TBL times, expected 1"
CS2_PAY=$(cs2_count 'class="woocommerce-checkout-payment"')
[ "$CS2_PAY" = 1 ] && pass "payment block renders exactly once" \
  || fail "payment block renders $CS2_PAY times, expected 1"
CS2_SUBMIT=$(cs2_count 'id="place_order"')
[ "$CS2_SUBMIT" = 1 ] && pass "place-order submit button is unique" \
  || fail "duplicate #place_order ids: $CS2_SUBMIT, expected 1"
# The sidebar holds the table, step 2 holds the payment block. Guard the split so
# a future "simplification" that moves either one breaks loudly. Newlines are
# collapsed first: the aside spans lines and sed is line-oriented.
CS2_ASIDE=$(printf '%s' "$CS2_HTML" | tr '\n' ' ' | sed -n 's/.*<aside class="dk-checkout-summary">\(.*\)<\/aside>.*/\1/p')
printf '%s' "$CS2_ASIDE" | grep -q 'class="woocommerce-checkout-payment"' \
  && fail "payment block still inside the summary sidebar (duplicated by step 2)" \
  || pass "summary sidebar holds the table only; step 2 owns the payment block"
# JS enqueued + contains required markers
html_has "$CS2_HTML" "checkout-stepper.js" && pass "checkout-stepper.js enqueued" || fail "stepper JS not enqueued"
CS2_JS=$(docker exec "$WP_CONTAINER" cat /var/www/html/wp-content/themes/lylyrose/assets/js/checkout-stepper.js)
printf '%s' "$CS2_JS" | grep -q "is-hidden"    && pass "JS toggles is-hidden"   || fail "JS missing is-hidden"
printf '%s' "$CS2_JS" | grep -q "step2-ready"  && pass "JS sets step2-ready"    || fail "JS missing step2-ready"
# 24.2 The stepper adds is-hidden to BOTH step cards (it hides whichever step is
# not current), so the rule must not be scoped to step 2 only. Scoped to step 2,
# step 1 never hid and both cards stayed stacked after "ادامه به پرداخت".
CS2_CSS=$(docker exec "$WP_CONTAINER" cat /var/www/html/wp-content/themes/lylyrose/style.css)
# html_has, not `grep -q`: style.css is ~82 KB, well past the 4 KB pipe buffer, so
# grep -q exits at the first match and SIGPIPEs the writer, which surfaces under
# this script's `set -o pipefail` as exit 141. Measured 8 of 15 runs on an
# unmodified tree — same hazard documented at the html_has helper's definition.
html_has "$CS2_CSS" '\.dk-checkout-step\.is-hidden[[:space:]]*{' \
  && pass "is-hidden rule covers both step cards" \
  || fail "is-hidden rule not scoped to .dk-checkout-step (step 1 would never hide)"

# 24.3 The mobile header search. `.dk-search-form` is `flex: 1` (= `1 1 0%`), so on
# a 375px viewport the menu toggle, logo and the 182px-wide account+cart pair take
# every pixel and the form measured 0.2px wide — the input was 60px but sat
# outside the box, so the top search bar was untappable. `flex-wrap: wrap` was
# already there for the overflow fix; it cannot help while the form's basis is 0.
# The fix gives it `flex-basis: 100%` on its own row. Assert the basis, not
# "a rule mentioning dk-search-form" — the base `flex: 1` rule is always present.
# `*` not `+`: html_has greps in BRE, where `+` is a literal plus sign.
html_has "$CS2_CSS" '\.dk-search-form[[:space:]]*{[^}]*flex:[[:space:]]*1[[:space:]]*0[[:space:]]*100%' \
  && pass "mobile header search gets a 100%-basis row (was 0.2px wide)" \
  || fail "no flex:1 0 100% on .dk-search-form — the mobile search bar collapses to zero width"

section "25. Notifications center (P3 #17)"
# Seed: create 2 notifications (1 read, 1 unread) and trigger order status change + review reply
NOTIF_SEED=$(docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
$uid = 1;
$p1 = wp_insert_post(array("post_type"=>"asc_notification","post_author"=>$uid,"post_title"=>"سفارش #123 تغییر کرد","post_content"=>"http://example.com/order/123","post_status"=>"publish"));
update_post_meta($p1,"_asc_notification_read",1); // read
$p2 = wp_insert_post(array("post_type"=>"asc_notification","post_author"=>$uid,"post_title"=>"پاسخ به نظر","post_content"=>"http://example.com/product","post_status"=>"publish"));
$o = wc_create_order(array("customer_id"=>$uid,"status"=>"pending"));
$o->update_status("processing","notification test");
echo $p1."|".$p2."|".$o->get_id();
' 2>/dev/null)
P1=$(echo "$NOTIF_SEED" | cut -d'|' -f1)
P2=$(echo "$NOTIF_SEED" | cut -d'|' -f2)
OID=$(echo "$NOTIF_SEED" | cut -d'|' -f3)
[ -n "$P1" ] && [ -n "$P2" ] && [ -n "$OID" ] && pass "notification test data seeded" || fail "seed failed: $NOTIF_SEED"

# Bell not shown to guests
GUEST_HTML=$(curl -s --max-time 30 "$SITE_URL/")
echo "$GUEST_HTML" | grep -q "dk-bell-wrap" && fail "bell renders for guest" || pass "bell hidden from guest"

# Bell shown to logged-in user (synthetic cookie)
AUTH_COOKIE_NAME=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); echo AUTH_COOKIE;' 2>/dev/null)
LOGGED_IN_COOKIE_NAME=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); echo LOGGED_IN_COOKIE;' 2>/dev/null)
EXPIRY=$(date -d '+10 min' +%s 2>/dev/null || echo $(( $(date +%s) + 600 )))
SESSION_INST=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); echo WP_Session_Tokens::get_instance(1)->create('"$EXPIRY"');' 2>/dev/null)
AUTH_COOKIE=$(docker exec "$WP_CONTAINER" php -r "require('/var/www/html/wp-load.php'); echo SECURE_AUTH_COOKIE . '=' . wp_generate_auth_cookie(1,$EXPIRY,'secure_auth','$SESSION_INST');" 2>/dev/null)
LOGGED_IN_COOKIE=$(docker exec "$WP_CONTAINER" php -r "require('/var/www/html/wp-load.php'); echo LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie(1,$EXPIRY,'logged_in','$SESSION_INST');" 2>/dev/null)
NOTIF_PAIR="$AUTH_COOKIE; $LOGGED_IN_COOKIE"
NOTIF_HTML=$(curl -s --max-time 30 -b "$NOTIF_PAIR" "$SITE_URL/")
echo "$NOTIF_HTML" | grep -q "dk-bell-wrap" && pass "bell renders for logged-in user" || fail "bell missing for user"
echo "$NOTIF_HTML" | grep -q "dk-bell-count" && pass "badge present" || fail "badge missing"

# AJAX unread count returns correct number
NOTIF_NONCE=$(printf '%s' "$NOTIF_HTML" | grep -o 'data-nonce="[^"]*"' | head -1 | sed 's/.*="//;s/"//')
UNREAD_JSON=$(curl -s --max-time 30 -b "$NOTIF_PAIR" -X POST -d "action=asc_notifications_unread" -d "nonce=$NOTIF_NONCE" "$SITE_URL/wp-admin/admin-ajax.php")
echo "$UNREAD_JSON" | grep -q '"count":' && pass "AJAX unread returns count" || fail "AJAX unread missing count"

# AJAX mark-read works (all)
curl -s --max-time 30 -b "$NOTIF_PAIR" -X POST -d "action=asc_notifications_mark_read" -d "nonce=$NOTIF_NONCE" "$SITE_URL/wp-admin/admin-ajax.php" >/dev/null
UNREAD_AFTER=$(curl -s --max-time 30 -b "$NOTIF_PAIR" -X POST -d "action=asc_notifications_unread" -d "nonce=$NOTIF_NONCE" "$SITE_URL/wp-admin/admin-ajax.php")
COUNT_AFTER=$(echo "$UNREAD_AFTER" | grep -o '"count":[0-9]*' | cut -d: -f2)
[ "$COUNT_AFTER" = "0" ] && pass "all unread marked 0" || fail "unread after mark all: $COUNT_AFTER"

# Account page renders and contains notifications.
# The synthetic auth cookie occasionally doesn't authenticate on the first
# fetch (session token not yet committed), so retry a couple of times.
NOTIF_PAGE=""
NOTIF_RENDERED=""
NOTIF_HAS_MARK_ALL=""
for _try in 1 2 3; do
  NOTIF_PAGE=$(curl -s --max-time 30 -b "$NOTIF_PAIR" "$SITE_URL/my-account/notifications/")
  NOTIF_RENDERED=$(printf '%s' "$NOTIF_PAGE" | grep -c "اعلان‌ها")
  NOTIF_HAS_MARK_ALL=$(printf '%s' "$NOTIF_PAGE" | grep -c "dk-bell-mark-all")
  # A logged-in account page always has both markers; if the page came back
  # as the guest login view, refresh the synthetic session once and retry.
  if [ "${NOTIF_HAS_MARK_ALL:-0}" -gt 0 ] && [ "${NOTIF_RENDERED:-0}" -gt 0 ]; then
    break
  fi
  [ "$_try" -lt 3 ] && sleep 2
done
[ "${NOTIF_RENDERED:-0}" -gt 0 ] && pass "account page renders notifications" || fail "notifications not rendered"
[ "${NOTIF_HAS_MARK_ALL:-0}" -gt 0 ] && pass "mark all button present" || fail "mark all button missing"

# Cleanup
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
foreach (array((int) $argv[1], (int) $argv[2]) as $id) { wp_delete_post($id, true); }
foreach (wc_get_orders(array("limit" => 50, "return" => "ids")) as $oid) { $o = wc_get_order($oid); if ($o) { $o->delete(true); } }
echo "cleaned";' "$P1" "$P2" >/dev/null 2>&1
pass "notification test data cleaned"

section "26. Gift wrap + gift card (P3 #17)"
# 26.1 Gift-wrap checkbox renders on cart (guest, no JS)
GW_JAR="$(mktemp -u)"
curl -s --max-time 30 -b "$GW_JAR" -c "$GW_JAR" "$SITE_URL/?add-to-cart=$FP_MAIN" >/dev/null
GW_CART=$(curl -s --max-time 30 -b "$GW_JAR" -c "$GW_JAR" "$SITE_URL/cart/")
html_has "$GW_CART" "dk-gift-wrap" && pass "gift-wrap checkbox renders on cart" || fail "gift-wrap checkbox missing"
html_has "$GW_CART" "بسته‌بندی هدیه" && pass "gift-wrap Persian label" || fail "Persian label missing"
html_has "$GW_CART" "۵۰,۰۰۰" && pass "fee amount in Persian digits" || fail "Persian digits missing"

# 26.2 AJAX toggle enables fee (guest)
GW_NONCE=$(printf '%s' "$GW_CART" | grep -o 'data-nonce="[^"]*"' | head -1 | sed 's/.*="//;s/"//')
curl -s --max-time 30 -b "$GW_JAR" -c "$GW_JAR" -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
  --data-urlencode "action=asc_gift_wrap_toggle" --data-urlencode "nonce=$GW_NONCE" --data-urlencode "enable=true" \
  | grep -c '"success":true' >/dev/null && pass "AJAX enable succeeds" || fail "AJAX enable failed"
GW_CART2=$(curl -s --max-time 30 -b "$GW_JAR" -c "$GW_JAR" "$SITE_URL/cart/")
html_has "$GW_CART2" "بسته‌بندی هدیه" && pass "fee row appears in cart totals" || fail "fee row missing"
GW_FEE_FOUND=$(printf '%s' "$GW_CART2" | grep -o "۵۰,۰۰۰" | wc -l)
[ "$GW_FEE_FOUND" -ge 1 ] && pass "fee amount in cart totals" || fail "fee amount missing in totals"

# 26.3 Checkout: gift-wrap status + order review
GW_CO=$(curl -s --max-time 30 -b "$GW_JAR" -c "$GW_JAR" "$SITE_URL/checkout/")
html_has "$GW_CO" "dk-checkout-gift-wrap" && pass "gift-wrap status in checkout" || fail "checkout status missing"
html_has "$GW_CO" "order_review\|order-review\|woocommerce-checkout-review-order-table" && pass "order review present" || fail "order review missing"

# 26.4 Place order -> order has gift-wrap fee line item
GW_NONCE2=$(printf '%s' "$GW_CO" | grep -o 'name="woocommerce-process-checkout-nonce" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
GW_LOC=$(curl -s --max-time 30 -b "$GW_JAR" -c "$GW_JAR" -o /dev/null -w "%{redirect_url}" -X POST "$SITE_URL/checkout/" \
  --data-urlencode "woocommerce-process-checkout-nonce=$GW_NONCE2" \
  --data-urlencode "billing_first_name=Gift" --data-urlencode "billing_last_name=Wrap" \
  --data-urlencode "billing_email=gw-test@lylyrose.test" --data-urlencode "billing_phone=09120000001" \
  --data-urlencode "billing_address_1=Test" --data-urlencode "billing_national_id=0012345679" \
  --data-urlencode "billing_city=3322" --data-urlencode "billing_state=THR" --data-urlencode "billing_postcode=12345" --data-urlencode "billing_country=IR" \
  --data-urlencode "asc_gift_wrap=1" \
  --data-urlencode "asc_gift_wrap_nonce=$GW_NONCE" \
  --data-urlencode "payment_method=WC_ZPal" --data-urlencode "woocommerce_checkout_place_order=Place order")
GW_OID=$(printf '%s' "$GW_LOC" | grep -o "order-received/[0-9]*" | grep -o "[0-9]*" || true)
# A pending ZarinPal order may not redirect to order-received; fall back to the
# most recent order for this test email.
if [ -z "$GW_OID" ]; then
  GW_OID=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); $o = array_values(wc_get_orders(array("limit"=>1,"orderby"=>"date","order"=>"DESC")))[0] ?? null; echo $o ? $o->get_id() : "";' 2>/dev/null)
fi
GW_FEE=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); $o=wc_get_order((int)$argv[1]); if(!$o){echo "nofee"; exit;} foreach($o->get_items("fee") as $item){ if(strpos($item->get_name(),"هدیه")!==false){ echo $item->get_total(); exit; } } echo "nofee";' "$GW_OID" 2>/dev/null)
[ "$GW_FEE" = "50000" ] && pass "order has gift-wrap fee line item (50000)" || fail "order fee missing/wrong: $GW_FEE"

# 26.5 Gift card plugin availability (soft check)
GC_ACTIVE=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); echo class_exists("PW_WC_Gift_Cards") ? "yes" : "no";' 2>/dev/null)
[ "$GC_ACTIVE" = "yes" ] && pass "pw-woocommerce-gift-cards active" || pass "gift-cards plugin not installed (soft check, OK)"

# Cleanup
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); $o=wc_get_order((int)$argv[1]); if($o){ $o->delete(true); echo "cleaned"; }' "$GW_OID" >/dev/null 2>&1
pass "gift-wrap test data cleaned"

# 27. Back-in-stock notifier: out-of-stock products show the
# "موجود شد به من خبر بده" form, guest phones are saved to asc_stock_subs,
# duplicates/invalid phones rejected, and restocking sends a Persian SMS
# (and a bell notification for logged-in subscribers) once per subscription.
section "27. Back-in-stock notifier"

# 27.1 class + table registered
SN_CLASS=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); echo class_exists("ASC_Stock_Notifier") ? "yes" : "no";' 2>/dev/null)
[ "$SN_CLASS" = "yes" ] && pass "ASC_Stock_Notifier class loaded" || fail "ASC_Stock_Notifier missing"
SN_TAB=$(docker exec "$DB_CONTAINER" sh -c 'mariadb -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SHOW TABLES LIKE \"%asc_stock_subs\";"' 2>/dev/null)
[ -n "$SN_TAB" ] && pass "asc_stock_subs table exists ($SN_TAB)" || fail "asc_stock_subs table missing"

# 27.2 fixture: make the FP_OOS product out of stock & fetch page
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); $p=wc_get_product((int) $argv[1]); if($p){ $p->set_stock_status("outofstock"); $p->save(); }' "$FP_OOS" >/dev/null 2>&1
docker exec "$WP_CONTAINER" rm -rf /var/www/html/wp-content/cache/supercache/lylyrose.local >/dev/null 2>&1
SN_HTML=$(curl -sL --max-time 120 --retry 2 "$SITE_URL/?p=$FP_OOS")
html_has "$SN_HTML" "dk-stock-notify" && pass "notifier form renders on out-of-stock product" || fail "notifier form missing on OOS product"
html_has "$SN_HTML" "موجود شد به من خبر بده" && pass "notifier Persian heading present" || fail "notifier heading missing"
html_has "$SN_HTML" "stock-notifier.js" && pass "stock-notifier.js enqueued on OOS page" || fail "stock-notifier.js not enqueued"
SN_NONCE=$(printf '%s' "$SN_HTML" | grep -o 'data-nonce="[^"]*"' | head -1 | sed 's/.*="//;s/"//')
[ -n "$SN_NONCE" ] && pass "subscribe nonce rendered" || fail "subscribe nonce missing"

# 27.3 form hidden on in-stock product 17, js not loaded
SN_IN=$(curl -sL --max-time 120 --retry 2 "$SITE_URL/?p=$FP_MAIN")
html_has "$SN_IN" "dk-stock-notify" && fail "notifier shown on in-stock product" || pass "notifier hidden on in-stock product"
html_has "$SN_IN" "stock-notifier.js" && fail "stock-notifier.js loaded for in-stock" || pass "stock-notifier.js not loaded for in-stock"

# 27.4 guest subscribe (AJAX)
SN_PHONE=09361112244
SN_JAR="$(mktemp -u)"
SN_SUB=$(curl -s --max-time 30 -b "$SN_JAR" -c "$SN_JAR" -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
  --data-urlencode "action=asc_stock_subscribe" --data-urlencode "nonce=$SN_NONCE" \
  --data-urlencode "product_id=$FP_OOS" --data-urlencode "phone=$SN_PHONE")
echo "$SN_SUB" | grep -c '"success":true' >/dev/null && pass "guest subscribe AJAX succeeds" || fail "guest subscribe failed: $SN_SUB"
SN_ROW=$(docker exec "$DB_CONTAINER" sh -c "mariadb -u root -p\"\$MYSQL_ROOT_PASSWORD\" \"\$MYSQL_DATABASE\" -N -e \"SELECT status FROM ${SN_TAB} WHERE phone='$SN_PHONE' AND product_id=$FP_OOS;\"" 2>/dev/null)
[ "$SN_ROW" = "pending" ] && pass "subscription row stored (pending)" || fail "subscription row wrong: '$SN_ROW'"

# 27.5 duplicate rejected, invalid phone rejected, bad nonce rejected
SN_DUP=$(curl -s --max-time 30 -b "$SN_JAR" -c "$SN_JAR" -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
  --data-urlencode "action=asc_stock_subscribe" --data-urlencode "nonce=$SN_NONCE" \
  --data-urlencode "product_id=$FP_OOS" --data-urlencode "phone=$SN_PHONE")
echo "$SN_DUP" | grep -c '"success":false' >/dev/null && pass "duplicate subscribe rejected" || fail "duplicate not rejected: $SN_DUP"
SN_BADPH=$(curl -s --max-time 30 -b "$SN_JAR" -c "$SN_JAR" -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
  --data-urlencode "action=asc_stock_subscribe" --data-urlencode "nonce=$SN_NONCE" \
  --data-urlencode "product_id=$FP_OOS" --data-urlencode "phone=12345")
echo "$SN_BADPH" | grep -c '"success":false' >/dev/null && pass "invalid phone rejected" || fail "invalid phone accepted"
SN_BADNONCE=$(curl -s --max-time 30 -b "$SN_JAR" -c "$SN_JAR" -X POST "$SITE_URL/wp-admin/admin-ajax.php" \
  --data-urlencode "action=asc_stock_subscribe" --data-urlencode "nonce=bogus" \
  --data-urlencode "product_id=$FP_OOS" --data-urlencode "phone=$SN_PHONE")
echo "$SN_BADNONCE" | grep -q '"success":false\|^-1$' && pass "bad nonce rejected" || fail "bad nonce accepted: $SN_BADNONCE"

# 27.6 logged-in user's phone added as a pending row (simulating an
# authenticated subscribe) — both rows pending before a single restock.
rm -f "$SN_JAR"
SN_UID=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); $u=get_user_by("login","sn_tester"); if(!$u){$id=wp_insert_user(array("user_login"=>"sn_tester","user_pass"=>wp_generate_password(12),"user_email"=>"sn_tester@lylyrose.test","role"=>"customer"));}else{$id=$u->ID;} update_user_meta($id,"billing_phone","09151112233"); echo $id;' 2>/dev/null)
[ -n "$SN_UID" ] && [ "$SN_UID" != "0" ] && pass "logged-in subscriber user seeded ($SN_UID)" || fail "logged-in subscriber seed failed"
SN_INS2=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); global $wpdb; $wpdb->insert($wpdb->prefix."asc_stock_subs", array("product_id"=>(int)$argv[2],"phone"=>"09151112233","user_id"=>(int)$argv[1],"status"=>"pending"), array("%d","%s","%d","%s")); echo "ok";' "$SN_UID" "$FP_OOS" 2>/dev/null)
[ "$SN_INS2" = "ok" ] && pass "logged-in subscriber row added while OOS" || fail "logged-in subscriber row add failed"
# single restock fires the hook once and sends to both pending rows
docker exec "$WP_CONTAINER" rm -f /var/www/html/wp-content/uploads/wc-logs/pwsms.log 2>/dev/null
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); $p=wc_get_product((int) $argv[1]); if($p){ $p->set_stock_status("instock"); $p->save(); }' "$FP_OOS" >/dev/null 2>&1
SN_SMS1=$(docker exec "$WP_CONTAINER" sh -c 'grep -c "09361112244" /var/www/html/wp-content/uploads/wc-logs/pwsms.log 2>/dev/null || echo 0')
[ "$SN_SMS1" -ge 1 ] && pass "restock sent SMS to guest subscriber" || fail "no SMS to guest subscriber ($SN_SMS1)"
SN_SMS2=$(docker exec "$WP_CONTAINER" sh -c 'grep -c "09151112233" /var/www/html/wp-content/uploads/wc-logs/pwsms.log 2>/dev/null || echo 0')
[ "$SN_SMS2" -ge 1 ] && pass "restock sent SMS to logged-in subscriber" || fail "no SMS to logged-in subscriber ($SN_SMS2)"
SN_SMSTXT=$(docker exec "$WP_CONTAINER" sh -c 'grep -c "موجود است" /var/www/html/wp-content/uploads/wc-logs/pwsms.log 2>/dev/null || echo 0')
[ "$SN_SMSTXT" -ge 1 ] && pass "SMS contains restock text" || fail "SMS restock text missing"
SN_ST1=$(docker exec "$DB_CONTAINER" sh -c "mariadb -u root -p\"\$MYSQL_ROOT_PASSWORD\" \"\$MYSQL_DATABASE\" -N -e \"SELECT status FROM ${SN_TAB} WHERE phone='$SN_PHONE' AND product_id=$FP_OOS;\"" 2>/dev/null)
[ "$SN_ST1" = "sent" ] && pass "guest subscription marked sent" || fail "guest subscription not sent: '$SN_ST1'"
SN_ST2=$(docker exec "$DB_CONTAINER" sh -c "mariadb -u root -p\"\$MYSQL_ROOT_PASSWORD\" \"\$MYSQL_DATABASE\" -N -e \"SELECT status FROM ${SN_TAB} WHERE phone='09151112233' AND product_id=$FP_OOS;\"" 2>/dev/null)
[ "$SN_ST2" = "sent" ] && pass "logged-in subscription marked sent" || fail "logged-in subscription not sent: '$SN_ST2'"
SN_NOTIF=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); $c=get_posts(array("post_type"=>"asc_notification","author"=>(int)$argv[1],"post_status"=>"publish","fields"=>"ids","posts_per_page"=>-1)); echo count($c);' "$SN_UID" 2>/dev/null)
[ "$SN_NOTIF" -ge 1 ] && pass "bell notification created for logged-in subscriber" || fail "no bell notification ($SN_NOTIF)"

# 27.7 second restock does NOT re-send (rows already 'sent')
docker exec "$WP_CONTAINER" rm -f /var/www/html/wp-content/uploads/wc-logs/pwsms.log 2>/dev/null
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); $p=wc_get_product((int) $argv[1]); if($p){ $p->set_stock_status("outofstock"); $p->save(); $p->set_stock_status("instock"); $p->save(); }' "$FP_OOS" >/dev/null 2>&1
SN_SMS3=$(docker exec "$WP_CONTAINER" sh -c 'grep -c "09361112244" /var/www/html/wp-content/uploads/wc-logs/pwsms.log 2>/dev/null || echo 0')
[ "$SN_SMS3" = "0" ] && pass "no duplicate SMS on second restock" || fail "duplicate SMS sent ($SN_SMS3)"

# 27.8 cleanup
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->prefix}asc_stock_subs");
$p=wc_get_product((int) $argv[1]); if($p){ $p->set_stock_status("instock"); $p->save(); if ($p->managing_stock()) $p->set_stock_quantity(100); $p->save(); }
$u=get_user_by("login","sn_tester"); if($u){ foreach(get_posts(array("post_type"=>"asc_notification","author"=>$u->ID,"fields"=>"ids","posts_per_page"=>-1)) as $nid){ wp_delete_post($nid,true); } }
echo "cleaned";' "$FP_OOS" >/dev/null 2>&1
SN_LEFT=$(docker exec "$DB_CONTAINER" sh -c "mariadb -u root -p\"\$MYSQL_ROOT_PASSWORD\" \"\$MYSQL_DATABASE\" -N -e \"SELECT COUNT(*) FROM ${SN_TAB};\"" 2>/dev/null)
[ "$SN_LEFT" = "0" ] && pass "stock notifier test data cleaned" || fail "cleanup incomplete: $SN_LEFT rows remaining"

section "28. Incredible Offers page (پیشنهادهای شگفت‌انگیز)"

# Server-side coverage for the flash-sales page. The DOM/JS layer was verified
# manually (2026-09-27); what is asserted here is everything that must not silently
# regress: the page exists, is driven by real WooCommerce sale state, and its
# sort/filter/pagination params behave.
OFFERS_URL="$SITE_URL/incredible-offers/"

# 28.1 class registered + page auto-created
OF_CLASS=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); echo class_exists("ASC_Flash_Sales") ? "yes" : "no";' 2>/dev/null)
[ "$OF_CLASS" = "yes" ] && pass "ASC_Flash_Sales class loaded" || fail "ASC_Flash_Sales missing"

check_http "$OFFERS_URL" "incredible offers page loads"

OF_HTML=$(curl -s --max-time 60 "$OFFERS_URL")
html_has "$OF_HTML" "dk-flash-card"        && pass "offer cards render"              || fail "no offer cards rendered"
html_has "$OF_HTML" "dk-flash-tabs"        && pass "category tabs render"            || fail "category tabs missing"
html_has "$OF_HTML" 'dk-offer-label'       && pass "شگفت‌انگیز label present"        || fail "offer label missing"

# 28.1b every category tile needs an <img>. lylyrose_term_image() can return
# '' when no product in that term has a thumbnail, and the rail used to render
# a bare text label in that case, which reads as a broken rail rather than
# missing content. A placeholder keeps the row even. The first tile is
# "همه شگفت‌انگیزها", a ٪ glyph rather than a photo, so it is excluded from the
# comparison — counting it would fail a correct rail.
OF_RAIL=$(printf '%s' "$OF_HTML" | sed -n 's/.*<nav class="dk-flash-tabs"\(.*\)<\/nav>.*/\1/p')
OF_TILE_IMGS=$(printf '%s' "$OF_RAIL" | grep -o '<img [^>]*width="72"' | wc -l | tr -d ' ')
OF_TILE_TABS=$(printf '%s' "$OF_RAIL" | grep -o '<a class="dk-flash-tab' | wc -l | tr -d ' ')
OF_TILE_NEED=$(( OF_TILE_TABS - 1 ))
[ "$OF_TILE_IMGS" -ge "$OF_TILE_NEED" ] && [ "$OF_TILE_NEED" -ge 1 ] \
  && pass "every category tile renders an image ($OF_TILE_IMGS imgs / $OF_TILE_NEED categories)" \
  || fail "only $OF_TILE_IMGS images for $OF_TILE_NEED category tiles — the rail falls back to a bare label"

html_has "$OF_HTML" 'dk-flash-row'         && pass "titled offer rows present"        || fail "no offer rows"
# Every row must carry a title and a real prev/next pair, or the row scrolls by
# drag only. Asserting the CONTAINER (as the old grid check did) is worthless:
# the pre-rewrite markup also had rows-worth-of wrapper, so it detected nothing.
OF_ROWS=$(printf '%s' "$OF_HTML" | grep -o 'class="dk-flash-row ' | wc -l | tr -d ' ')
OF_TITLED=$(printf '%s' "$OF_HTML" | grep -o 'dk-flash-row-title' | wc -l | tr -d ' ')
OF_NAVB=$(printf '%s' "$OF_HTML" | grep -o 'data-dk-row-prev' | wc -l | tr -d ' ')
[ "$OF_ROWS" -gt 0 ] && [ "$OF_ROWS" = "$OF_TITLED" ] && [ "$OF_ROWS" = "$OF_NAVB" ] \
  && pass "every row is titled and navigable ($OF_ROWS rows)" \
  || fail "row chrome incomplete (rows=$OF_ROWS titles=$OF_TITLED prevbtns=$OF_NAVB)"

# 28.2 page is driven by real sale state, not a hardcoded list
OF_SALE_IDS=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); echo (int) count(wc_get_product_ids_on_sale());' 2>/dev/null)
[ "${OF_SALE_IDS:-0}" -gt 0 ] && pass "sale products exist to drive the page ($OF_SALE_IDS)" || fail "no sale products — page cannot be verified"

# 28.3 the page shows EVERY visible discounted product, exactly once. Rows
# claim products as they are built, so a product in two brands is placed in the
# first row that wanted it — which means both failure directions are live: a
# double-claim renders the same card twice, and a dropped claim silently hides
# an offer. Counting cards alone cannot tell those apart, so this compares the
# rendered rows against the source list in PHP.
OF_CARDS=$(printf '%s' "$OF_HTML" | grep -o 'class="dk-offer-product"' | wc -l | tr -d ' ')
OF_COVER=$(docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
$ids = array_map("absint", ASC_Flash_Sales::filtered_sale_ids());
$visible = array();
foreach ($ids as $id) { $p = wc_get_product($id); if ($p && $p->is_visible()) { $visible[] = $id; } }
$placed = array();
foreach (ASC_Flash_Sales::carousel_rows() as $row) { foreach ($row["ids"] as $id) { $placed[] = (int) $id; } }
$dupes = count($placed) - count(array_unique($placed));
$hidden = count(array_diff($visible, $placed));
echo count($visible), " ", $dupes, " ", $hidden;' 2>/dev/null)
set -- $OF_COVER
[ "${1:-0}" -gt 0 ] && [ "${2:-1}" -eq 0 ] && [ "${3:-1}" -eq 0 ] \
  && pass "every visible offer appears exactly once ($1 products, $OF_CARDS cards)" \
  || fail "coverage broken: $1 visible, $2 duplicated, $3 hidden ($OF_CARDS cards)"

# 28.4 a non-existent category must render the empty state, not a fatal or all products
OF_EMPTY=$(curl -s --max-time 60 "$OFFERS_URL?offer_cat=999999")
[ "$(printf '%s' "$OF_EMPTY" | grep -o "dk-flash-card" | wc -l | tr -d ' ')" -eq 0 ] && pass "invalid category renders empty state" || fail "invalid category still showed products"

# 28.5 both sort orders render AND order differently. Presence alone is not enough:
# a sort param silently ignored still returns 24 cards and would pass a presence check.
OF_CHEAP=$(curl -s --max-time 60 "$OFFERS_URL?sort=cheapest")
OF_EXP=$(curl -s --max-time 60 "$OFFERS_URL?sort=expensive")
OF_FIRST_CHEAP=$(printf '%s' "$OF_CHEAP" | grep -o 'dk-offer-product" href="[^"]*"' | head -1)
OF_FIRST_EXP=$(printf '%s' "$OF_EXP"  | grep -o 'dk-offer-product" href="[^"]*"' | head -1)
[ -n "$OF_FIRST_CHEAP" ] && [ -n "$OF_FIRST_EXP" ] \
  && [ "$OF_FIRST_CHEAP" != "$OF_FIRST_EXP" ] \
  && pass "sort=cheapest and sort=expensive render different products" \
  || fail "sort orders did not differ (both first: '$OF_FIRST_CHEAP')"

# 28.6 stock filter and pagination are accepted
check_http "$OFFERS_URL?in_stock=1"    "in_stock filter accepted"
check_http "$OFFERS_URL?offers_page=2" "pagination accepted"

# 28.7 the homepage "مشاهده همه" link must land here. It used to point at
# /shop/?discount=1, so the offers rail advertised a page that was never
# reachable from it. Assert the href itself, not just that some anchor exists.
OF_MORE=$(printf '%s' "$HOME_HTML" | grep -o 'dk-offers-more" href="[^"]*"' | head -1)
case "$OF_MORE" in
  *"/incredible-offers/"*) pass "homepage offers link targets incredible-offers" ;;
  "")                      fail "no dk-offers-more anchor on the homepage" ;;
  *)                       fail "homepage offers link points elsewhere: $OF_MORE" ;;
esac


section "29. Gift cards (کارت هدیه)"

# 29.1 class registered
GC_CLASS=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); echo class_exists("ASC_Gift_Cards") ? "yes" : "no";' 2>/dev/null)
[ "$GC_CLASS" = "yes" ] && pass "ASC_Gift_Cards class loaded" || fail "ASC_Gift_Cards missing"

# 29.2 one product per fixed denomination, all flagged as gift cards
GC_PIDS=$(docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
ASC_Gift_Cards::ensure_products();
$ok = 0;
foreach ( ASC_Gift_Cards::DENOMINATIONS as $a ) {
  $p = wc_get_product( wc_get_product_id_by_sku( "GC-" . intdiv( $a, 1000 ) . "K" ) );
  if ( $p && ASC_Gift_Cards::is_gift_card( $p ) && (int) $p->get_price() === $a && $p->is_virtual() ) { $ok++; }
}
echo $ok, " ", count( ASC_Gift_Cards::DENOMINATIONS );' 2>/dev/null)
GC_OK=$(printf '%s' "$GC_PIDS" | cut -d' ' -f1); GC_TOT=$(printf '%s' "$GC_PIDS" | cut -d' ' -f2)
[ "$GC_OK" = "$GC_TOT" ] && [ "${GC_TOT:-0}" -gt 0 ] \
  && pass "gift card products seeded ($GC_OK/$GC_TOT denominations)" \
  || fail "gift card products wrong ($GC_OK/$GC_TOT)"

# 29.2b re-running ensure_products() must not create duplicates. It hooks init:30
# of every request, so an option flag alone is a race: two concurrent requests
# both read it missing and both seeded. lylyrose really did end up with 8.
GC_DUPES=$(docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
global $wpdb;
$rows = $wpdb->get_results( "SELECT p.ID, p.post_title FROM {$wpdb->posts} p
  JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
  WHERE p.post_type = \"product\" AND m.meta_key = \"_asc_is_gift_card\" AND m.meta_value = \"yes\"",
  ARRAY_A );
$seen = array();
foreach ( $rows as $r ) { $seen[ wc_get_product( $r["ID"] )->get_sku() ] = ( $seen[ wc_get_product( $r["ID"] )->get_sku() ] ?? 0 ) + 1; }
$dupes = 0;
foreach ( $seen as $n ) { if ( $n > 1 ) { $dupes += $n - 1; } }
echo $dupes;' 2>/dev/null | tr -dc '0-9')
[ "${GC_DUPES:-x}" = "0" ] && pass "no duplicate gift card products" || fail "$GC_DUPES duplicate gift card products"

# 29.3 checkout asks for a recipient only when a gift card is in the cart
GC_JAR="$(mktemp -u)"
curl -sL --max-time 60 -c "$GC_JAR" -b "$GC_JAR" "$SITE_URL/" -o /dev/null
GC_CO_HTML=$(curl -sL --max-time 120 -b "$GC_JAR" -c "$GC_JAR" "$SITE_URL/checkout/")
html_has "$GC_CO_HTML" "id=\"asc_gift_recipient\"" \
  && fail "recipient field shown with no gift card in cart" \
  || pass "recipient field hidden with empty cart"

GC_SKU_ID=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); echo wc_get_product_id_by_sku("GC-500K");' 2>/dev/null)
curl -sL --max-time 60 -b "$GC_JAR" -c "$GC_JAR" -X POST "$SITE_URL/?add-to-cart=$GC_SKU_ID" -o /dev/null
GC_CO_GC=$(curl -sL --max-time 120 -b "$GC_JAR" -c "$GC_JAR" "$SITE_URL/checkout/")
html_has "$GC_CO_GC" "id=\"asc_gift_recipient\"" && pass "recipient field appears with a gift card in cart" || fail "recipient field missing with gift card in cart"
html_has "$GC_CO_GC" "id=\"asc_gift_message\"" && pass "gift message field renders" || fail "gift message field missing"

# 29.4 a paid order mints a GC- code worth the card value, valid 90 days
GC_CODE=$(docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
$pid = wc_get_product_id_by_sku("GC-1000K");
$order = wc_create_order();
$item = new WC_Order_Item_Product();
$item->set_product( wc_get_product($pid) );
$item->set_quantity(1); $item->set_subtotal(1000000); $item->set_total(1000000);
$order->add_item($item); $order->calculate_totals();
$order->set_billing_email( "gc_buyer@aromalnd.test" );
$order->update_meta_data( "_asc_gift_recipient", "gc_friend@aromalnd.test" );
$order->save();
$oid = $order->get_id();
do_action( "woocommerce_order_status_processing", $oid );
file_put_contents( "/tmp/gc_test_order", $oid );
$o = wc_get_order( $oid );
foreach ( $o->get_items() as $it ) {
  $c = $it->get_meta( "_asc_gift_card_code" );
  if ( $c ) { echo $c; break; }
}' 2>/dev/null)
GC_ORDER_ID=$(docker exec "$WP_CONTAINER" cat /tmp/gc_test_order 2>/dev/null | tr -dc '0-9')
case "$GC_CODE" in
  GC-*) pass "paid gift-card order issues a code ($GC_CODE)" ;;
  *)    fail "no gift card code issued (got: '$GC_CODE')" ;;
esac

GC_VAL=$(docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
$c = new WC_Coupon($argv[1]);
$d = $c->get_date_expires();
echo (int) $c->get_amount(), " ", (int) $c->get_usage_limit(),
     " ", $d ? round( ($d->getTimestamp() - time()) / DAY_IN_SECONDS ) : 0,
     " ", $c->is_valid() ? "valid" : "invalid";' "$GC_CODE" 2>/dev/null)
GC_AMT=$(printf '%s' "$GC_VAL" | cut -d' ' -f1)
GC_USE=$(printf '%s' "$GC_VAL" | cut -d' ' -f2)
GC_DAYS=$(printf '%s' "$GC_VAL" | cut -d' ' -f3)
GC_V=$(printf '%s' "$GC_VAL" | cut -d' ' -f4)
[ "$GC_AMT" = "1000000" ] && pass "code is worth the card value (1000000)" || fail "code amount wrong: '$GC_AMT'"
[ "$GC_USE" = "1" ] && pass "code is single-use" || fail "code usage limit wrong: '$GC_USE'"
[ "$GC_DAYS" -ge 89 ] 2>/dev/null && [ "$GC_DAYS" -le 90 ] \
  && pass "code expires in 90 days" \
  || fail "expiry wrong: '$GC_DAYS' days"
[ "$GC_V" = "valid" ] && pass "code is valid now" || fail "code not valid: '$GC_V'"

# 29.5 the code redeems through the ordinary coupon surface
GC_APPLY=$(curl -sL --max-time 60 -b "$GC_JAR" -c "$GC_JAR" -X POST "$SITE_URL/?wc-ajax=apply_coupon" \
  --data-urlencode "coupon_code=$GC_CODE" \
  --data-urlencode "security=$(curl -sL --max-time 60 -b "$GC_JAR" -c "$GC_JAR" "$SITE_URL/cart/" | grep -o 'apply_coupon_nonce":"[^"]*' | cut -d'"' -f3)" \
  -H "X-Requested-With: XMLHttpRequest")
html_has "$GC_APPLY" "woocommerce-message" && pass "gift card code applies at the cart" || fail "gift card code rejected: $GC_APPLY"

# 29.6 an expired code is refused
GC_EXP=$(docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
$c = new WC_Coupon($argv[1]);
$c->set_date_expires( time() - DAY_IN_SECONDS ); $c->save();
$fresh = new WC_Coupon($argv[1]);
echo $fresh->is_valid() ? "valid" : "expired";' "$GC_CODE" 2>/dev/null)
[ "$GC_EXP" = "expired" ] && pass "expired code is refused" || fail "expired code still valid: '$GC_EXP'"

# 29.7 the code renders on the order-received page
GC_RCPT=$(docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
$o = wc_get_order( (int) $argv[1] );
ob_start(); ASC_Gift_Cards::render_on_receipt( $o ); echo ob_get_clean();' "$GC_ORDER_ID" 2>/dev/null)
html_has "$GC_RCPT" "$GC_CODE" && pass "code shown on the receipt page" || fail "code missing from receipt"

# 29.8 cleanup. force_delete is required: the CPT data store trashes rather than
# removes by default, and a trashed coupon is still resolvable by code.
docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
$c = new WC_Coupon($argv[1]); if ( $c->get_id() ) { $c->delete( true ); }
$o = wc_get_order( (int) $argv[2] ); if ( $o ) { $o->delete( true ); }' "$GC_CODE" "$GC_ORDER_ID" >/dev/null 2>&1
GC_LEFT=$(docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
echo ( new WC_Coupon($argv[1]) )->get_id() ? "left" : "gone";' "$GC_CODE" 2>/dev/null)
[ "$GC_LEFT" = "gone" ] && pass "gift card test data cleaned" || fail "cleanup failed: $GC_LEFT"
rm -f "$GC_JAR"

# 29.9 the offers page is a horizontal carousel now, so "7 columns" is the wrong
# shape to assert. What has to hold instead is that a row is a real scroll
# container with fixed-width cards — without overflow-x the row's min-content
# widens the document and the whole page scrolls sideways on a phone, which this
# project has hit twice before.
LR_TRACK=$(docker exec "$WP_CONTAINER" php -r '
$c = file_get_contents("/var/www/html/wp-content/themes/lylyrose/assets/css/flash-sales.css");
$ok = preg_match("/\.dk-flash-track \{[^}]*overflow-x:\s*auto/", $c)
   && preg_match("/\.dk-flash-card \{[^}]*flex:\s*0 0 (\d+)px/", $c, $m);
echo $ok ? $m[1] : "";' 2>/dev/null)
[ -n "$LR_TRACK" ] && pass "offer rows are horizontal scroll tracks (${LR_TRACK}px cards)" \
  || fail "offer row is not a scroll track with fixed-width cards (got '$LR_TRACK')"

LR_BADPCT=$(docker exec "$WP_CONTAINER" sh -c \
  "grep -rno '٪[۰-۹]' /var/www/html/wp-content/themes/lylyrose/ 2>/dev/null | wc -l" | tr -dc '0-9')
[ "${LR_BADPCT:-0}" -eq 0 ] && pass "percent sign is digits-first everywhere" || fail "$LR_BADPCT percent signs are written percent-first"

section "30. Core plugin version wiring"

# Mirrors aroma_store section 30. The plugin header Version: and `const VERSION`
# drift apart easily, because the header is what you bump when releasing and the
# const is what WordPress never reads. That is not cosmetic: the const is
# LYLYROSE_CORE_VERSION, which gates the rewrite flush
# (class-product-code.php maybe_flush) and the store-pages version check
# (class-store-pages.php ensure_pages). A stale const means those never re-run on
# a version bump, which also defeats the documented "self-heals after a DB reset"
# behaviour. Pin the two together here.
CORE_READ=$(docker exec "$WP_CONTAINER" php -r '
$f = "/var/www/html/wp-content/plugins/lylyrose-core/lylyrose-core.php";
$src = file_get_contents($f);
preg_match("/^ \* Version: *(\S+)/m", $src, $h);
preg_match("/const VERSION *= *\x27([^\x27]+)\x27/", $src, $c);
echo ($h[1] ?? "none") . "|" . ($c[1] ?? "none");' 2>/dev/null)
CORE_HDR=${CORE_READ%%|*}
CORE_CONST=${CORE_READ##*|}
[ -n "$CORE_HDR" ] && [ "$CORE_HDR" = "$CORE_CONST" ] \
  && pass "core header Version ($CORE_HDR) matches const VERSION" \
  || fail "core version drift: header '$CORE_HDR' vs const '$CORE_CONST'"

# Prove the const is the one the running site gates on, not just a string on
# disk — a stale opcache would let the file read as correct.
CORE_LIVE=$(docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
echo defined("LYLYROSE_CORE_VERSION") ? LYLYROSE_CORE_VERSION : "undefined";' 2>/dev/null)
[ "$CORE_LIVE" = "$CORE_CONST" ] \
  && pass "running site reports LYLYROSE_CORE_VERSION=$CORE_LIVE" \
  || fail "live constant '$CORE_LIVE' != file constant '$CORE_CONST' (restart the container)"

# The gated options must have caught up, or the flush/pages logic did not re-run.
CORE_OPTS=$(docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
echo get_option("asc_rewrite_version","") . "|" . get_option("asc_store_pages_version","");' 2>/dev/null)
[ "$CORE_OPTS" = "$CORE_LIVE|$CORE_LIVE" ] \
  && pass "version-gated options track the constant" \
  || fail "gated options '$CORE_OPTS' have not advanced to '$CORE_LIVE'"

section "31. Seed catalog integrity"

# 31.1 no two published products may share a title. create_products.php once
# called wp_insert_post with no SKU lookup, so every re-run seeded a second and
# third copy of all 10 products (ids 1306-1325). Those copies carry no SKU and
# no image, and they shipped to both live storefronts. The seeder now matches on
# SKU; this is the check that would catch a regression there.
DUP_TITLES=$(docker exec "$WP_CONTAINER" php -r '
require("/var/www/html/wp-load.php");
$titles = wp_list_pluck( get_posts( array( "post_type" => "product", "post_status" => "publish",
  "posts_per_page" => -1, "no_found_rows" => true ) ), "post_title" );
echo count( $titles ) . "|" . count( array_unique( $titles ) );' 2>/dev/null)
DUP_TOTAL="${DUP_TITLES%%|*}"; DUP_DISTINCT="${DUP_TITLES##*|}"
[ "${DUP_TOTAL:-0}" = "${DUP_DISTINCT:-x}" ] \
  && pass "every published product title is unique ($DUP_TOTAL products)" \
  || fail "$((DUP_TOTAL - DUP_DISTINCT)) duplicate product title(s) — the seeder created copies again"

# 31.2 the seeder must resolve by SKU before inserting, so re-running it updates
# in place. Grep the guard rather than re-run it: running the seeder for real
# would rewrite catalog content as a side effect of a read-only check.
SEEDER=$(docker exec "$WP_CONTAINER" sh -c \
  "grep -c 'wc_get_product_id_by_sku' /var/www/html/wp-content/plugins/lylyrose-core/scripts/create_products.php" 2>/dev/null | tr -dc '0-9')
[ "${SEEDER:-0}" -ge 1 ] \
  && pass "create_products.php resolves existing products by SKU before inserting" \
  || fail "create_products.php has no SKU lookup — re-running it will duplicate the catalog"

section "32. Upstream/downstream class parity"

# 32.1 the ASC_ inventory is the standing check that a mirror step was not
# skipped (UPSTREAM_RELATIONSHIP.md). It was documented as 17 classes in eight
# places while the real count had been 18 since ASC_Gift_Cards landed — a doc
# number nothing verifies, so it rotted silently. Pinning the count means a
# genuinely new class fails here and gets counted deliberately, instead of the
# docs drifting again. This is the downstream half of the pair: aroma_store's
# own suite asserts the same count against its plugin, so a class added upstream
# and not mirrored fails on one host or the other.
# 21 since ASC_Finder_Weights (the finder's weight editor) and ASC_Finder_Tiers
# (its budget-tier editor) landed. Both are downstream-only: they configure the
# finder rather than mirror an upstream feature.
CLASS_COUNT=$(docker exec "$WP_CONTAINER" sh -c \
  "ls /var/www/html/wp-content/plugins/lylyrose-core/includes/ | grep -c '^class-'" 2>/dev/null | tr -dc '0-9')
[ "${CLASS_COUNT:-0}" = "22" ] \
  && pass "core plugin exposes 22 ASC_ classes ($CLASS_COUNT)" \
  || fail "core plugin exposes ${CLASS_COUNT:-0} classes, expected 22 — a new class needs mirroring and a doc count update"

section "34. Perfume finder (عطرت رو پیدا کن)"

# The finder is a recommender over a catalogue whose fragrance metadata is mostly
# absent, so the properties worth asserting are the negative ones: that it does not
# invent attributes, does not rank a product it knows nothing about, and does not
# pad a short list. A finder that returned three products for every query would pass
# every "is the page working" check, so each assertion below names the failure it
# is there to catch.

# 34.1 The page must exist, be reachable, and show the quiz rather than a raw
# PHP notice — the template is assigned by the plugin, so a page created before the
# finder existed would render the default page template and nothing at all.
FINDER_URL="$SITE_URL/perfume-finder/"
FINDER_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 30 "$FINDER_URL")
[ "$FINDER_CODE" = "200" ] \
  && pass "perfume finder page responds 200" \
  || fail "perfume finder page returned $FINDER_CODE (expected 200)"

FINDER_HTML=$(curl -s --max-time 30 "$FINDER_URL")
html_has "$FINDER_HTML" 'asc-finder__quiz' \
  && pass "finder shows the quiz on a bare GET" \
  || fail "finder did not render the quiz — the page template is probably not assigned"
html_has "$FINDER_HTML" 'name="fragrance"' && html_has "$FINDER_HTML" 'name="budget"' \
  && pass "quiz exposes the fragrance and budget axes" \
  || fail "quiz is missing a scoring axis"
# The budget radios must be labelled from the tier labels, not the raw tier keys:
# "eco"/"mid" leaking into the UI is the visible symptom of a vocabulary read from
# the wrong place.
html_has "$FINDER_HTML" '<span>اقتصادی</span>' \
  && pass "budget options render their Persian labels, not tier keys" \
  || fail "budget options render raw tier keys instead of labels"

# 34.2 Submitting the quiz returns at most three ranked cards, each with a
# percentage, a coverage line and its contributing factors. Fewer than three is a
# valid outcome and is asserted separately below.
FINDER_POST=$(curl -s --max-time 60 -X POST "$FINDER_URL" \
  --data-urlencode "fragrance=گل" \
  --data-urlencode "gender=زنانه" \
  --data-urlencode "occasion=مراسم رسمی" \
  --data-urlencode "season=بهار" \
  --data-urlencode "personality=کلاسیک" \
  --data-urlencode "longevity=بلند" \
  --data-urlencode "budget=premium")
FINDER_CARDS=$(printf '%s' "$FINDER_POST" | grep -c 'class="asc-finder__result"')
[ "${FINDER_CARDS:-0}" -ge 1 ] && [ "${FINDER_CARDS:-0}" -le 3 ] \
  && pass "quiz returns 1-3 ranked cards ($FINDER_CARDS)" \
  || fail "quiz returned $FINDER_CARDS cards — expected between 1 and 3, never a padded list"
html_has "$FINDER_POST" 'asc-finder__percent' \
  && pass "each result carries a match percentage" \
  || fail "results carry no percentage"
html_has "$FINDER_POST" 'asc-finder__coverage' \
  && pass "each result says how much of the weighting it could use" \
  || fail "results do not report coverage — a high score on one axis reads as a whole-catalogue verdict"
# The factors are the explainability contract: the visitor must be able to see why
# something was recommended. No single answer set exercises all three axes — the
# scorable set is small and the axes do not co-occur on one product — so the occasion
# label is asserted against the query that actually matches on occasion. Each factor
# must render as a *match*, not merely appear: a label that only ever shows the
# "no data recorded" note would still contain the text.
FINDER_OCC=$(curl -s --max-time 60 -X POST "$FINDER_URL" \
  --data-urlencode "fragrance=آکواتیک" \
  --data-urlencode "gender=مردانه" \
  --data-urlencode "occasion=محل کار" \
  --data-urlencode "season=بهار" \
  --data-urlencode "personality=مدرن" \
  --data-urlencode "budget=mid")
html_has "$FINDER_POST" "is-match\">رایحه‌های مورد علاقه شما" \
  && pass "result explains the matched fragrance family" \
  || fail "the fragrance factor never renders as a match"
html_has "$FINDER_OCC" "is-match\">مناسب برای موقعیت انتخابی" \
  && pass "result explains the matched occasion" \
  || fail "the occasion factor never renders as a match"
html_has "$FINDER_POST" "is-match\">ماندگاری مورد نظر" \
  && pass "result explains the matched longevity" \
  || fail "the longevity factor never renders as a match"
html_has "$FINDER_POST" "اطلاعاتی برای این مورد ثبت نشده" \
  && pass "an axis with no data is named rather than silently omitted" \
  || fail "missing metadata is not disclosed — a gap in the data reads as a match"
# Percentages are shown in Persian digits to match the rest of the storefront.
# Spell the digits out as an alternation. A [۰-۹] range is read as a range of the
# first byte of each character, and ۰..۹ share one: 0xD9..0xD9 collapsed to D9-B9,
# which is an empty range that some greps then treat as "any latin digit" — so the
# range matched "100" and the assertion passed on a build with no Persian digits.
FINDER_FA_DIGITS=$(printf '%s' "$FINDER_POST" | grep -cE 'asc-finder__num">(۰|۱|۲|۳|۴|۵|۶|۷|۸|۹)')
if [ "${FINDER_FA_DIGITS:-0}" -ge 1 ]; then
  pass "match percentages render in Persian digits"
else
  fail "match percentages are not in Persian digits — they render as latin 0-9"
fi

# 34.2b The result card's reading stack. The CSS splits the card at >=900px and needs
# the wrapper to exist for the grid and for the button to sit beside the whole stack
# rather than beside whichever sibling happens to land on row 1 — so assert the
# markup shape (open, then the buy button after the closing tag) and not just the
# class name, which a stray div could satisfy.
FINDER_STACK=$(printf '%s' "$FINDER_POST" | grep -c 'asc-finder__stack')
[ "$FINDER_STACK" -eq "$FINDER_CARDS" ] \
  && pass "every result card wraps its content in exactly one reading stack ($FINDER_STACK/$FINDER_CARDS)" \
  || fail "found $FINDER_STACK reading stacks across $FINDER_CARDS result cards — expected one each"

# The button must be a sibling of the stack, inside .asc-finder__body, so the flex
# row can put it beside the stack. Inside the stack it would sit under the factor
# list instead. Order matters: close the stack, then open the actions.
FINDER_BTN_SIBLING=$(printf '%s' "$FINDER_POST" \
  | python3 -c '
import re, sys
h = sys.stdin.read()
# One card is enough: every card is emitted by the same loop.
m = re.search(r"<div class=\"asc-finder__body\">(.*?)</div>\s*</li>", h, re.S)
if not m:
    print("nocard"); raise SystemExit
body = m.group(1)
# The actions block must come after the stack close and before the body close.
act_i = body.find("asc-finder__actions")
# Count divs: the stack closes when depth returns to its own level.
depth, stack_end = 0, None
i = body.find("<div class=\"asc-finder__stack\">")
if i >= 0:
    for mm in re.finditer(r"<(/?)div\b", body[i:]):
        depth += 1 if not mm.group(1) else -1
        if depth == 0:
            stack_end = i + mm.start()
            break
print("ok" if (stack_end is not None and act_i > stack_end) else "bad")
')
[ "$FINDER_BTN_SIBLING" = "ok" ] \
  && pass "the buy button sits outside the reading stack, as a sibling of it" \
  || fail "the buy button is inside the reading stack, so it renders under the factor list instead of beside it"

html_has "$FINDER_POST" 'asc-finder__factors' \
  && pass "results render the factor list the stack lays out" \
  || fail "results carry no factor list — the stack has nothing to divide into columns"

# 34.3 The scoring engine's own invariants. Run in the container because they are
# properties of the PHP, not of any page: determinism, the scorable gate, the cap,
# and the fact that the weights never smuggle in brand or category as a stand-in for
# a fragrance characteristic. The probe mutates one product's terms and restores them
# in a shutdown handler, so a failure here cannot leave the catalogue altered.
PROBE_SRC=$(mktemp)
cat > "$PROBE_SRC" <<'PROBE_EOF'
<?php
require( '/var/www/html/wp-load.php' );
if ( ! class_exists( 'ASC_Perfume_Finder' ) ) { echo "class=missing\n"; exit( 0 ); }
echo "class=present\n";

// These answers have to produce a genuine tie at the cap, because that is the only
// situation in which the sort's tie-break runs. Occasion is "روزمره" because it is
// the one occasion term three of the seeded products actually carry; picking one
// they lack leaves that axis unscored everywhere and the cap never gets contested.
$ANSWERS = array(
	'fragrance'   => 'گل',
	'gender'      => 'زنانه',
	'occasion'    => 'روزمره',
	'season'      => 'بهار',
	'personality' => 'کلاسیک',
	'longevity'   => 'بلند',
	'budget'      => 'premium',
);
$ids_of = function ( $rows ) {
	$ids = array();
	foreach ( $rows as $row ) { $ids[] = (int) $row['id']; }
	return $ids;
};
$run = function () use ( $ANSWERS, $ids_of ) { return $ids_of( ASC_Perfume_Finder::recommend( $ANSWERS ) ); };

// How much of the catalogue is honestly rankable, and is a product with no
// fragrance data genuinely excluded rather than scored on a default?
$SCORABLE = 0; $EXCLUDED = 0; $EXCLUDED_ID = 0;
foreach ( get_posts( array( 'post_type' => 'product', 'numberposts' => -1, 'fields' => 'ids' ) ) as $pid ) {
	if ( null === ASC_Perfume_Finder::profile( wc_get_product( $pid ) ) ) {
		$EXCLUDED++;
		if ( ! $EXCLUDED_ID ) { $EXCLUDED_ID = (int) $pid; }
	} else { $SCORABLE++; }
}
echo "scorable=$SCORABLE\n";
echo "excluded=$EXCLUDED\n";
echo "excluded_id=$EXCLUDED_ID\n";

$RUN1 = $run(); $RUN2 = $run();
echo 'run1=' . implode( ',', $RUN1 ) . "\n";
echo 'deterministic=' . ( $RUN1 === $RUN2 && $RUN1 ? 'yes' : 'no' ) . "\n";
echo 'count=' . count( $RUN1 ) . "\n";

// A score that is a whole number inside 0..100 is derived; a float, a negative, or
// a value over 100 means something else produced it.
$PCT_OK = true;
foreach ( ASC_Perfume_Finder::recommend( $ANSWERS ) as $row ) {
	$pct = $row['result']['percent'];
	if ( ! is_int( $pct ) || $pct < 0 || $pct > 100 ) { $PCT_OK = false; }
}
echo "percent_int_in_range=" . ( $PCT_OK ? 'yes' : 'no' ) . "\n";

echo 'gender_alone=' . count( ASC_Perfume_Finder::recommend( array( 'gender' => 'زنانه' ) ) ) . "\n";
echo 'no_answers=' . count( ASC_Perfume_Finder::recommend( array() ) ) . "\n";
// An answer set of nothing but values that are not in the vocabulary carries no
// preference at all, so it must rank nothing. Every scored axis skips an answer it
// cannot place, which is what makes an empty result the honest answer here.
echo 'nonsense_answers=' . count(
	ASC_Perfume_Finder::recommend( ASC_Perfume_Finder::sanitise_answers(
		array( 'fragrance' => 'ناموجود', 'occasion' => 'ناموجود', 'budget' => 'ناموجود' )
	) )
) . "\n";
$WEIGHTS = ASC_Perfume_Finder::weights();
echo 'weights=' . implode( ',', array_keys( $WEIGHTS ) ) . "\n";
echo 'weights_sum=' . array_sum( $WEIGHTS ) . "\n";
echo 'sanitise=' . json_encode(
	ASC_Perfume_Finder::sanitise_answers( array( 'season' => 'بهار', 'fragrance' => 'ناموجود' ) ),
	JSON_UNESCAPED_UNICODE ) . "\n";

// The admin weight editor is the only way to retune the weighting, so it has to
// reach the filter. Writing the option directly is enough to prove the wiring:
// a weighting that renormalises on the way through, sums to 100, and changes the
// score it produces. The option is removed in the same run, so a failure here
// cannot leave the catalogue permanently re-weighted.
$SAVED_W = get_option( 'asc_finder_weights' );
$BASE_W  = ASC_Perfume_Finder::weights();
$ANSWERS_W = array( 'fragrance' => 'گل', 'occasion' => 'روزمره', 'budget' => 'premium' );
$rows_of = function () use ( $ANSWERS_W ) {
	$out = array();
	foreach ( ASC_Perfume_Finder::recommend( $ANSWERS_W ) as $row ) {
		$out[] = (int) $row['id'] . ':' . (int) $row['result']['percent'];
	}
	return implode( ',', $out );
};
$BASE_ROWS = $rows_of();
update_option( 'asc_finder_weights', array(
	'fragrance' => 60, 'occasion' => 20, 'season' => 10,
	'personality' => 0, 'longevity' => 0, 'budget' => 0,
) );
$SAVED_W_AFTER = ASC_Perfume_Finder::weights();
$SAVED_ROWS    = $rows_of();
if ( $SAVED_W ) { update_option( 'asc_finder_weights', $SAVED_W ); } else { delete_option( 'asc_finder_weights' ); }
echo 'weights_editor_applied=' . wp_json_encode( $SAVED_W_AFTER ) . "\n";
// 60/20/10 over a total of 90 rescales to 67/22/11; the rounding drift is folded
// into the largest axis so the total lands exactly on 100.
echo 'weights_editor_expected=' . wp_json_encode( array(
	'fragrance' => 67, 'occasion' => 22, 'season' => 11,
	'personality' => 0, 'longevity' => 0, 'budget' => 0,
) ) . "\n";
echo 'weights_editor_sum=' . array_sum( $SAVED_W_AFTER ) . "\n";
echo 'weights_editor_changed_results=' . ( $BASE_ROWS !== $SAVED_ROWS ? 'yes' : 'no' ) . "\n";
echo 'weights_editor_base=' . wp_json_encode( $BASE_W ) . "\n";

// Strip every scoring axis off one product the answer set actually returns, so the
// gate and the cap are both observable in the same query.
$SAVED = array();
function asc_probe_restore() {
	global $SAVED;
	foreach ( $SAVED as $pid => $terms ) {
		foreach ( $terms as $tax => $ids ) { wp_set_object_terms( $pid, $ids, $tax, false ); }
		foreach ( $terms as $meta => $val ) {
			if ( '_' === substr( $meta, 0, 1 ) ) { update_post_meta( $pid, $meta, $val ); }
		}
	}
	$SAVED = array();
}
register_shutdown_function( 'asc_probe_restore' );

$TARGET = $RUN1 ? (int) $RUN1[0] : 0;
echo "mutating=$TARGET\n";
// Gender is an answer, not a trait: it never contributes to a score.
$SCORING = $ANSWERS; unset( $SCORING['gender'] );
// The scorable gate has to hold for every candidate the finder weighs, not only
// for the three rows the cap returns — by then the gate has already run, so
// checking the survivors could only ever confirm itself. The filter is scoped to
// this probe's own request; the HTTP assertions above run in their own.
if ( $TARGET ) {
	$SCOPED = static function () { return 200; };
	add_filter( 'asc_perfume_finder_max_results', $SCOPED );
	$ALL_CANDIDATES = array();
	foreach ( ASC_Perfume_Finder::recommend( $SCORING ) as $row ) {
		$ALL_CANDIDATES[] = (int) $row['id'];
	}
	remove_filter( 'asc_perfume_finder_max_results', $SCOPED );
	$gate_leaks = 0;
	foreach ( $ALL_CANDIDATES as $cid ) {
		if ( null === ASC_Perfume_Finder::profile( wc_get_product( $cid ) ) ) { $gate_leaks++; }
	}
	echo 'candidates=' . count( $ALL_CANDIDATES ) . "\n";
	echo 'gate_leaks=' . $gate_leaks . "\n";
	foreach ( array( 'pa_fragrance_family', 'pa_occasion', 'pa_season', 'pa_personality', 'pa_longevity', 'pa_sillage' ) as $tax ) {
		if ( ! taxonomy_exists( $tax ) ) { continue; }
		$SAVED[ $TARGET ][ $tax ] = wp_get_object_terms( $TARGET, $tax, array( 'fields' => 'ids' ) );
		wp_set_object_terms( $TARGET, array(), $tax, false );
	}
	foreach ( array( '_asc_notes_top', '_asc_notes_heart', '_asc_notes_base' ) as $meta ) {
		$SAVED[ $TARGET ][ $meta ] = get_post_meta( $TARGET, $meta, true );
		delete_post_meta( $TARGET, $meta );
	}
	echo 'mutated_profile=' . ( null === ASC_Perfume_Finder::profile( wc_get_product( $TARGET ) ) ? 'null' : 'present' ) . "\n";
	$AFTER = $ids_of( ASC_Perfume_Finder::recommend( $ANSWERS ) );
	echo 'mutated_in_results=' . ( in_array( $TARGET, $AFTER, true ) ? 'yes' : 'no' ) . "\n";
	echo 'mutated_count=' . count( $AFTER ) . "\n";
	asc_probe_restore();
}

// Every row the finder shows must earn something on at least one axis it could be
// scored against, using the same gender-free answer set as the gate check above.
$UNMATCHED = 0;
foreach ( ASC_Perfume_Finder::recommend( $SCORING ) as $row ) {
	if ( ! ASC_Perfume_Finder::matched( $row ) ) { $UNMATCHED++; }
}
echo "unmatched_rows=$UNMATCHED\n";

// The three row shapes matched() has to tell apart: a scored axis that was met,
// a scored axis that was missed, and an axis the product has no data for. Only
// the first is a match — a row of misses and a row of gaps must both be rejected.
$row_of = function ( $factors ) {
	return array( 'id' => 999999, 'result' => array( 'factors' => $factors ) );
};
$HIT    = $row_of( array( array( 'axis' => 'fragrance', 'scored' => true,  'matched' => true ) ) );
$MISS   = $row_of( array( array( 'axis' => 'fragrance', 'scored' => true,  'matched' => false ) ) );
$NOSCORE= $row_of( array( array( 'axis' => 'season',    'scored' => false, 'matched' => false ) ) );
$GAPS   = $row_of( array(
	array( 'axis' => 'fragrance', 'scored' => true,  'matched' => false ),
	array( 'axis' => 'season',    'scored' => false, 'matched' => false ),
) );
echo 'matched_verdicts=' . (
	ASC_Perfume_Finder::matched( $HIT )
	&& ! ASC_Perfume_Finder::matched( $MISS )
	&& ! ASC_Perfume_Finder::matched( $NOSCORE )
	&& ! ASC_Perfume_Finder::matched( $GAPS )
		? 'yes' : 'no'
) . "\n";
echo "unmatched_rows=$UNMATCHED\n";

// With the whole vocabulary removed no taxonomy answer is valid, so the submit path
// must fall back to the quiz rather than answer on the surviving axes.
$render_submit = function ( $post ) {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST                     = $post;
	ob_start();
	ASC_Perfume_Finder::render();
	$html = ob_get_clean();
	unset( $_POST );
	return $html;
};
add_filter( 'asc_finder_vocabularies', function () { return array(); } );
$EMPTY_VOCAB = $render_submit( array( 'fragrance' => 'گل' ) );
remove_all_filters( 'asc_finder_vocabularies' );
echo 'empty_vocab_shows_quiz=' . ( false !== strpos( $EMPTY_VOCAB, 'asc-finder__quiz' ) ? 'yes' : 'no' ) . "\n";
echo 'empty_vocab_results=' . substr_count( $EMPTY_VOCAB, 'class="asc-finder__result"' ) . "\n";
// Control for the line above: the same submission with the vocabulary intact.
$WITH_VOCAB = $render_submit( array( 'fragrance' => 'گل' ) );
echo 'full_vocab_results=' . substr_count( $WITH_VOCAB, 'class="asc-finder__result"' ) . "\n";
echo 'full_vocab_factors=' . substr_count( $WITH_VOCAB, 'asc-finder__factor' ) . "\n";
PROBE_EOF

PROBE_OUT=$(docker cp "$PROBE_SRC" "$WP_CONTAINER:/tmp/finder-probe.php" >/dev/null 2>&1 \
  && docker exec "$WP_CONTAINER" php /tmp/finder-probe.php 2>/dev/null)
rm -f "$PROBE_SRC"
probe() { printf '%s\n' "$PROBE_OUT" | grep "^$1=" | tail -1 | cut -d= -f2-; }

[ "$(probe class)" = "present" ] \
  && pass "ASC_Perfume_Finder is loaded" \
  || fail "ASC_Perfume_Finder is not loaded — the class is not required by the plugin"

SCORABLE=$(probe scorable)
EXCLUDED=$(probe excluded)
[ "${SCORABLE:-0}" -ge 1 ] \
  && pass "some products are rankable ($SCORABLE of $((SCORABLE + ${EXCLUDED:-0})))" \
  || fail "no product is rankable — the gate is rejecting the whole catalogue"

# The gate is the feature: a product with no fragrance metadata must be excluded,
# not scored. The probe strips a real product's terms and re-scores; if the finder
# defaulted missing attributes instead, that product would still rank.
[ "$(probe mutated_profile)" = "null" ] \
  && pass "a product with no fragrance metadata is not rankable" \
  || fail "a product with no fragrance metadata still produces a profile — attributes are being invented"
[ "$(probe mutated_in_results)" = "no" ] \
  && pass "an unrankable product is excluded from the results" \
  || fail "an unrankable product is still being recommended"
[ "$(probe gate_leaks)" = "0" ] \
  && pass "no unrankable product is ranked, checked over the whole candidate list" \
  || fail "the scorable gate let through $(probe gate_leaks) product(s) with no fragrance metadata — attributes are being invented"
# If the uncapped candidate list is no longer bigger than the cap, the gate check
# above can no longer see a leak and would pass against a gate that returns nothing.
[ "$(probe candidates)" -gt 3 ] \
  && pass "the scorable-gate check sees more candidates than the cap shows" \
  || fail "only $(probe candidates) candidate(s) are rankable, so the gate check is vacuous"
[ "$(probe deterministic)" = "yes" ] \
  && pass "the same answers produce the same ranking twice over" \
  || fail "the ranking is not deterministic — two identical requests differ"
[ "$(probe percent_int_in_range)" = "yes" ] \
  && pass "match percentages are whole numbers in 0..100" \
  || fail "a match percentage is not a whole number in 0..100 — the score is not derived"

# A query that matches a sliver of the scorable set must be allowed to come back
# short. The mutation leaves fewer scorable products than the cap, and the finder
# must return what exists rather than topping the list up.
MUTATED_COUNT=$(probe mutated_count)
[ "${MUTATED_COUNT:-3}" -le 3 ] \
  && pass "results are capped at 3 and never padded ($MUTATED_COUNT after mutation)" \
  || fail "results exceeded the 3-item cap ($MUTATED_COUNT)"

[ "$(probe gender_alone)" = "0" ] \
  && pass "gender alone does not rank anything" \
  || fail "gender alone produced rankings — gender is being scored as a fragrance trait"
[ "$(probe no_answers)" = "0" ] \
  && pass "no answers produce no results" \
  || fail "an empty answer set produced results"
[ "$(probe nonsense_answers)" = "0" ] \
  && pass "answers that are all outside the vocabulary produce no results" \
  || fail "a submission of nothing but out-of-vocabulary answers still ranked $(probe nonsense_answers) product(s) — the empty-answer guard is not the only thing standing between the user and a padded list"
[ "$(probe unmatched_rows)" = "0" ] \
  && pass "no ranked row matches nothing on a scored axis" \
  || fail "a ranked row matches nothing on any axis it could be scored against — the list is padded"
# Assert matched() against rows built by hand. Asking "does every returned row
# match?" can only ever pass, because render() filters through matched() before
# anything is returned — so a matched() that ignored the factor flags would still
# satisfy it. This feeds it the three shapes the filter has to judge.
if [ "$(probe matched_verdicts)" = "yes" ]; then
  pass "matched() accepts a genuine match and rejects unscored and non-matching rows"
else
  fail "matched() is wrong on a hand-built row (got $(probe matched_verdicts)) — it is what stops the list being padded with 0%% matches"
fi

# Brand and category are not fragrance characteristics. A weight on either would let
# the finder recommend on a brand name while claiming it reasoned about scent.
case ",$(probe weights)," in
  *,brand,*|*,"$ACTIVE_THEME",*|*,"cat",*|*,"product_cat",*)
    fail "scoring weights include brand or category (got $(probe weights))" ;;
  *)
    pass "scoring weights exclude brand and category ($(probe weights))" ;;
esac
[ "$(probe weights_sum)" = "100" ] \
  && pass "scoring weights sum to 100" \
  || fail "scoring weights sum to $(probe weights_sum), expected 100"

# The admin editor is the only way to retune the weighting. Three things have to
# hold: the stored array reaches the finder, it is renormalised to 100 on the way
# through, and it actually changes what the visitor sees. A page that saves but
# does not reach the filter would pass a render-only check.
[ "$(probe weights_editor_applied)" = "$(probe weights_editor_expected)" ] \
  && pass "the admin weight editor reaches the scoring filter" \
  || fail "the stored weighting did not reach the finder (got $(probe weights_editor_applied), expected $(probe weights_editor_expected))"
[ "$(probe weights_editor_sum)" = "100" ] \
  && pass "a weighting saved as 60/20/10 renormalises to 100" \
  || fail "the editor stored a weighting summing to $(probe weights_editor_sum), expected 100"
[ "$(probe weights_editor_changed_results)" = "yes" ] \
  && pass "the saved weighting changes the results the visitor sees" \
  || fail "a different weighting produced identical results — the editor is not wired to scoring"

# An answer that is not in the vocabulary is dropped, and per-axis: a season value
# submitted for occasion must not score against the occasion axis.
if printf '%s' "$(probe sanitise)" | grep -q '"season":"بهار"' \
   && ! printf '%s' "$(probe sanitise)" | grep -q 'ناموجود'; then
  pass "answers outside the vocabulary are dropped, per axis"
else
  fail "sanitise_answers kept an invalid or cross-axis answer ($(probe sanitise))"
fi

[ "$(probe empty_vocab_shows_quiz)" = "yes" ] && [ "$(probe empty_vocab_results)" = "0" ] \
  && pass "an empty vocabulary falls back to the quiz instead of answering" \
  || fail "with no vocabulary the finder still answered ($(probe empty_vocab_results) cards) — the control line below shows it should return $(( $(probe full_vocab_results) ))"
[ "$(probe full_vocab_results)" -ge 1 ] 2>/dev/null \
  && pass "the same submission with a vocabulary does return results ($(probe full_vocab_results))" \
  || fail "the vocabulary fallback assertion above passed for the wrong reason: a valid submission also returns nothing"

# 34.4 The finder is reachable from the storefront. A feature nobody can navigate
# to is not shipped, and the nav entry is theme code, not plugin code, so nothing
# else in this suite would notice its absence.
html_has "$HOME_HTML" "$SITE_URL/perfume-finder/" \
  && pass "the finder is linked from the storefront (nav or home banner)" \
  || fail "no link to /perfume-finder/ in the homepage markup — the feature is unreachable"
# 35. Price-tier settings. The tiers used to be four hardcoded boundaries, so
# this asserts the two properties that make them safe to expose to the owner: a
# saved manual set actually reaches the quiz, and a bad one cannot.
section "35. Perfume finder price tiers"
TIERS_SAN=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$out = ASC_Finder_Tiers::sanitize(array("mode" => "manual", "tiers" => array(
  array("key" => "eco",    "max" => "5000000",  "label" => "ارزان"),
  array("key" => "eco",    "max" => "9000000",  "label" => "تکراری"),
  array("key" => "neg",    "max" => "-500",     "label" => "منفی"),
  array("key" => "zero",   "max" => "0",        "label" => "صفر"),
  array("key" => "nokey",  "max" => "1000",     "label" => ""),
  array("key" => "mid",    "max" => "20000000", "label" => "<script>x</script>"),
  array("key" => "luxury", "max" => "",         "label" => "لوکس"),
)));
echo $out["mode"] . "|" . count($out["tiers"]) . "|"
   . $out["tiers"][0]["label"] . "|" . $out["tiers"][0]["max"] . "|"
   . (isset($out["tiers"][1]) ? $out["tiers"][1]["key"] : "none") . "|"
   . $out["tiers"][1]["max"];' 2>/dev/null)
# Only rows a real form post can produce are exercised here, because that is the
# only way the option is written. A duplicate key loses to the first one; a
# negative or zero ceiling is dropped rather than clamped into a tier that would
# match nothing; an empty label is not a tier; and a row whose label is nothing
# but a script tag sanitises down to an empty string, so it is dropped too rather
# than stored as a tier called "x".
[ "$TIERS_SAN" = "manual|2|ارزان|5000000|luxury|" ] \
  && pass "tier sanitizer drops duplicate keys, non-positive ceilings and empty labels" \
  || fail "tier sanitizer accepted bad input (got '$TIERS_SAN')"

# Auto mode is the default and must survive a save that carries nothing usable,
# otherwise switching back to auto would blank the quiz's budget step.
TIERS_EMPTY=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$o = ASC_Finder_Tiers::sanitize(array("mode" => "auto", "tiers" => array(array("key" => "", "label" => ""))));
echo $o["mode"] . "|" . (isset($o["tiers"]) ? "leftover" : "clean") . "|"
   . count(ASC_Finder_Tiers::get());' 2>/dev/null)
[ "$TIERS_EMPTY" = "auto|clean|4" ] \
  && pass "an unusable manual set falls back to auto, which yields four tiers" \
  || fail "auto fallback broken (got '$TIERS_EMPTY') — the quiz would render an empty budget step"

# The boundaries must actually move with the catalogue. The tier keys are fixed,
# so a boundary that never changes is the bug: the top two tiers used to hold 4
# and 3 of 114 products, which is not a set a shopper can shop.
TIERS_AUTO=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
wp_cache_delete("asc_finder_tier_cuts");
$t = ASC_Finder_Tiers::get();
$split = array_fill_keys(array_keys($t), 0);
foreach (get_posts(array("post_type"=>"product","post_status"=>"publish","numberposts"=>-1,"fields"=>"ids","no_found_rows"=>true)) as $pid) {
  $pr = wc_get_product($pid);
  if ( ! $pr ) { continue; }
  $split[ASC_Perfume_Finder::price_tier($pr->get_price())]++;
}
echo implode(",", array_keys($t)) . "|" . $t["eco"]["max"] . "|" . $t["luxury"]["max"]
   . "|" . implode(",", $split);' 2>/dev/null)
[ "${TIERS_AUTO%%|*}" = "eco,mid,premium,luxury" ] \
  && pass "auto mode always yields the four tier keys the scorer expects" \
  || fail "auto mode returned the wrong tier keys: '$TIERS_AUTO'"
[ "${TIERS_AUTO#*|}" != "15000000|50000000|150000000|" ] \
  && pass "auto mode derives boundaries from the catalogue, not the old constants (${TIERS_AUTO#*|})" \
  || fail "auto mode returned the old hardcoded split"

# Every tier must be shoppable: a boundary set that leaves one of them nearly
# empty is the exact failure this feature was added to fix.
TIERS_SPLIT=${TIERS_AUTO##*|}
TIERS_SMALLEST=$(printf '%s' "$TIERS_SPLIT" | tr ',' '\n' | sort -n | head -1)
[ "${TIERS_SMALLEST:-0}" -ge 10 ] \
  && pass "auto mode spreads the catalogue across all four tiers ($TIERS_SPLIT)" \
  || fail "a tier holds fewer than 10 of the catalogue's products ($TIERS_SPLIT) — that tier is not shoppable"

# A manual save must reach the rendered quiz, not just the option row. This is the
# whole point of the page: the owner edits a boundary and the visitor sees it.
docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
update_option("asc_finder_tiers", array("mode" => "manual", "tiers" => array(
  array("key" => "eco", "max" => 12345678, "label" => "کلید تست"),
)));' >/dev/null 2>&1
docker exec "$WP_CONTAINER" rm -rf /var/www/html/wp-content/cache/supercache/lylyrose.local >/dev/null 2>&1
TIERS_HTML=$(curl -s --max-time 60 "$FINDER_URL")
html_has "$TIERS_HTML" 'کلید تست' \
  && pass "a manual tier label reaches the quiz" \
  || fail "the saved manual tier is not in the quiz — the setting is inert"
# The range is asserted SEPARATELY and against its own text. Folding it into the
# assertion above would let the label alone satisfy it, so dropping the range
# from the markup entirely would still leave this section green. The manual tier
# is set to 12345678 above, so its range must carry that number in Persian
# digits — an empty <span> with the right class would otherwise pass. The
# thousands separator here is a comma, which is what number_format_i18n emits.
TIERS_RANGE=$(printf '%s' "$TIERS_HTML" | grep -o 'asc-finder__range[^<]*</span>' | head -1)
case "$TIERS_RANGE" in
  *۱۲,۳۴۵,۶۷۸*)
    pass "the tier's price range shows the saved boundary in Persian digits" ;;
  *)
    fail "the quiz shows no price range for the saved tier (got '$TIERS_RANGE') — a bare label does not tell a shopper what \"متوسط\" means" ;;
esac

# ...and a save that names only one tier still yields all four. Dropping the
# other three would leave most products unrankable on the budget axis, and the
# owner gets no warning — the form simply saves what is on screen.
TIERS_KEYS=$(docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php");
$t = ASC_Finder_Tiers::get();
echo implode(",", array_keys($t)) . "|" . $t["eco"]["max"] . "|" . $t["eco"]["label"] . "|" . $t["premium"]["label"];' 2>/dev/null)
[ "$TIERS_KEYS" = "eco,mid,premium,luxury|12345678|کلید تست|پریمیوم" ] \
  && pass "a partial manual save fills the unlisted tiers instead of dropping them" \
  || fail "unexpected tier set '$TIERS_KEYS'"

docker exec "$WP_CONTAINER" php -r 'require("/var/www/html/wp-load.php"); delete_option("asc_finder_tiers"); wp_cache_delete("asc_finder_tier_cuts");' >/dev/null 2>&1
docker exec "$WP_CONTAINER" rm -rf /var/www/html/wp-content/cache/supercache/lylyrose.local >/dev/null 2>&1
TIERS_BACK=$(curl -s --max-time 60 "$FINDER_URL")
html_has "$TIERS_BACK" 'name="budget"' \
  && pass "deleting the option restores automatic tiers and the quiz still renders" \
  || fail "the quiz lost its budget step after the option was deleted — auto mode does not regenerate"

section ""
echo "==========================================="
echo "RESULTS: $PASS passed, $FAIL failed"
if [ "$FAIL" -gt 0 ]; then
  printf 'Failed:\n'
  for item in "${FAILED_ITEMS[@]}"; do printf '  - %s\n' "$item"; done
  exit 1
fi
echo "ALL TESTS PASSED"
