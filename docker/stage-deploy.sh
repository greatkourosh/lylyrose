#!/usr/bin/env bash
# stage-deploy.sh — Build a production deploy tree for lylyrose.ir.
#
# Reproduces the artifacts the 2026-09-24 deploy used, with the two config
# omissions that silently broke that deploy already baked in:
#   1. $table_prefix in wp-config.php   (without it WP ignores imported tables
#                                        and 302s every request to install.php)
#   2. the # BEGIN WordPress block in .htaccess  (without it every pretty
#                                        permalink 404s despite valid rewrite_rules)
#
# Produces:
#   $STAGE_DIR/         full WordPress install — upload its CONTENTS into docroot
#   $STAGE_DIR/uploads/ uploads tree, uploaded separately
#   $SQL_DUMP           database dump
#
# Usage: docker/stage-deploy.sh
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/.." && pwd)"

STAGE_DIR="${STAGE_DIR:-/tmp/lylyrose_deploy}"
SQL_DUMP="${SQL_DUMP:-/tmp/lylyrose.sql}"
WP_VERSION_URL="${WP_VERSION_URL:-https://fa.wordpress.org/latest-fa_IR.zip}"

# Credentials are never baked in — wp-config ships placeholders you fill on the
# host (cPanel may prefix the DB name/user, so the values are only known then).
DB_NAME_PLACEHOLDER="REPLACE_WITH_DB_NAME"
DB_USER_PLACEHOLDER="REPLACE_WITH_DB_USER"
DB_PASS_PLACEHOLDER="REPLACE_WITH_DB_PASSWORD"
SITE_URL_PLACEHOLDER="https://lylyrose.ir"

WP_CONTAINER="${WP_CONTAINER:-lylyrose-wp}"
DB_CONTAINER="${DB_CONTAINER:-lylyrose-db}"
DB_NAME="${DB_NAME:-lylyrose}"
# The app user (MYSQL_USER) has no LOCK TABLES / dump privileges; the dump and
# the residue scrub both need root.
DB_USER="${DB_USER:-root}"

# wp-content paths regenerated at runtime on the host, never shipped.
WP_CONTENT_EXCLUDES=(
  --exclude=advanced-cache.php --exclude=object-cache.php
  --exclude=wp-cache-config.php --exclude=autoptimize_404_handler.php
  --exclude=uploads/ --exclude=cache/ --exclude=updraft/
  --exclude=upgrade/ --exclude=upgrade-temp-backup/
)

say() { printf '\n==> %s\n' "$1"; }

# ---------------------------------------------------------------- preflight --
say "Preflight"
for cmd in curl unzip rsync docker; do
  command -v "$cmd" >/dev/null 2>&1 || { echo "ERR: $cmd not found" >&2; exit 1; }
done
docker ps --format '{{.Names}}' | grep -qx "$WP_CONTAINER" \
  || { echo "ERR: container '$WP_CONTAINER' is not running (docker compose up -d)" >&2; exit 1; }
docker ps --format '{{.Names}}' | grep -qx "$DB_CONTAINER" \
  || { echo "ERR: container '$DB_CONTAINER' is not running" >&2; exit 1; }

# The DB name comes from .env so the dump works regardless of local values.
if [ -f "$REPO/.env" ]; then
  set -a; . "$REPO/.env"; set +a
  DB_NAME="${MYSQL_DATABASE:-$DB_NAME}"
fi
DB_PASSWORD="${MYSQL_ROOT_PASSWORD:-}"
[ -n "$DB_PASSWORD" ] || { echo "ERR: no DB password (set MYSQL_ROOT_PASSWORD in .env)" >&2; exit 1; }

say "Scrubbing test residue from the database"
# The suite creates and deletes its own fixtures, but an interrupted run can
# leave test users, SMS archives and subscription rows behind, and the dump
# would ship them. $table_prefix is a wp-config constant, so it is read from
# WordPress rather than queried as an option row.
TABLE_PREFIX="$(docker exec "$WP_CONTAINER" php -r \
  'require("/var/www/html/wp-load.php"); global $wpdb; echo $wpdb->prefix;' 2>/dev/null | tr -d '\r\n')"
if [ -z "$TABLE_PREFIX" ]; then
  echo "ERR: could not read \$table_prefix from WordPress" >&2
  exit 1
fi
echo "  table prefix: $TABLE_PREFIX"

dbq() { docker exec "$DB_CONTAINER" sh -c "mariadb -u'$DB_USER' -p'$DB_PASSWORD' -N -B '$DB_NAME' -e \"$1\"" 2>/dev/null; }

dbq "DELETE FROM ${TABLE_PREFIX}users
     WHERE user_login IN ('wallet_tester','sn_tester','user_362223344');" || true
# Only these tables if they exist; an absent one is not an error.
for t in woocommerce_ir_sms_archive wc_subscriptions; do
  dbq "DELETE FROM ${TABLE_PREFIX}${t};" || true
done
echo "  scrubbed test users, SMS archive, subscriptions"

# ------------------------------------------------------------------- dump ---
say "Dumping database -> $SQL_DUMP"
# MariaDB container has no mysqldump binary; mariadb-dump is the client.
docker exec "$DB_CONTAINER" sh -c \
  "mariadb-dump -u'$DB_USER' -p'$DB_PASSWORD' --single-transaction --routines --triggers '$DB_NAME'" \
  > "$SQL_DUMP"
echo "  $(du -h "$SQL_DUMP" | cut -f1)"

# ---------------------------------------------------------------- uploads ---
say "Copying uploads -> $STAGE_DIR/uploads"
rm -rf "$STAGE_DIR"
mkdir -p "$STAGE_DIR"
docker cp "$WP_CONTAINER:/var/www/html/wp-content/uploads" "$STAGE_DIR/uploads"
# Logs and generated CSS caches must not ship.
rm -rf "$STAGE_DIR/uploads/wc-logs" "$STAGE_DIR/uploads/ao_ccss" \
       "$STAGE_DIR/uploads/wflogs" 2>/dev/null || true

# ------------------------------------------------------------------- core --
say "Downloading WordPress core (fa_IR)"
CORE_TMP="$(mktemp -d)"
trap 'rm -rf "$CORE_TMP"' EXIT
curl -fsSL --retry 5 --connect-timeout 20 --max-time 300 \
  "$WP_VERSION_URL" -o "$CORE_TMP/wp.zip"
unzip -q "$CORE_TMP/wp.zip" -d "$CORE_TMP"
CORE_DIR="$CORE_TMP/wordpress"
[ -f "$CORE_DIR/wp-includes/version.php" ] || { echo "ERR: core zip malformed" >&2; exit 1; }
# WooCommerce 11.1.0 requires WP 7.0+; the Docker image's 6.5.5 fatals.
WP_VER="$(grep -oP "wp_version\s*=\s*'\K[^']+" "$CORE_DIR/wp-includes/version.php")"
echo "  core version: $WP_VER"

# The repo tracks only wp-content; core comes from the official fa_IR zip.
rsync -a "$CORE_DIR/" "$STAGE_DIR/" --exclude=wp-content/

say "Copying repo wp-content"
mkdir -p "$STAGE_DIR/wp-content"
rsync -a "$REPO/wordpress/wp-content/" "$STAGE_DIR/wp-content/" \
  "${WP_CONTENT_EXCLUDES[@]}"
# The active theme only. Inactive dev themes stay local (see DEPLOY_PREP).
find "$STAGE_DIR/wp-content/themes" -maxdepth 1 -mindepth 1 -type d \
  ! -name lylyrose ! -name twentytwentyone ! -name twentytwentytwo \
  ! -name twentytwentythree ! -name twentytwentyfour ! -name twentytwentyfive \
  -exec rm -rf {} + 2>/dev/null || true

# ------------------------------------------------------------- wp-config ----
say "Generating wp-config.php"
# $table_prefix is the line the 2026-09-24 deploy was missing. WordPress writes
# it during install; a hand-built config must carry it or every table lookup
# silently misses and WP redirects to install.php.
cat > "$STAGE_DIR/wp-config.php" <<PHPEOF
<?php
define( 'DB_NAME', '$DB_NAME_PLACEHOLDER' );
define( 'DB_USER', '$DB_USER_PLACEHOLDER' );
define( 'DB_PASSWORD', '$DB_PASS_PLACEHOLDER' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY',         'REPLACE_WITH_FRESH_SALT' );
define( 'SECURE_AUTH_KEY',  'REPLACE_WITH_FRESH_SALT' );
define( 'LOGGED_IN_KEY',    'REPLACE_WITH_FRESH_SALT' );
define( 'NONCE_KEY',        'REPLACE_WITH_FRESH_SALT' );
define( 'AUTH_SALT',        'REPLACE_WITH_FRESH_SALT' );
define( 'SECURE_AUTH_SALT', 'REPLACE_WITH_FRESH_SALT' );
define( 'LOGGED_IN_SALT',   'REPLACE_WITH_FRESH_SALT' );
define( 'NONCE_SALT',       'REPLACE_WITH_FRESH_SALT' );

\$table_prefix = 'wp_';

define( 'WP_HOME', '$SITE_URL_PLACEHOLDER' );
define( 'WP_SITEURL', '$SITE_URL_PLACEHOLDER' );

define( 'DISALLOW_FILE_EDIT', true );
define( 'FS_METHOD', 'direct' );
define( 'WP_MEMORY_LIMIT', '256M' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
PHPEOF

say "Fetching fresh salts"
CONFIG_FILE="$STAGE_DIR/wp-config.php"
# The endpoint returns 8 complete define() lines, so they replace the whole
# placeholder line each -- substituting the text into an existing define()
# would nest one call inside another and break the parse.
if curl -fsSL --retry 3 --max-time 60 https://api.wordpress.org/secret-key/1.1/salt/ \
     > "$CONFIG_FILE.salts" && [ -s "$CONFIG_FILE.salts" ] \
   && grep -q "AUTH_KEY" "$CONFIG_FILE.salts"; then
  python3 - "$CONFIG_FILE" "$CONFIG_FILE.salts" <<'PYEOF'
import sys
cfg, salt_file = sys.argv[1], sys.argv[2]
with open(cfg) as fh:
    body = fh.read()
with open(salt_file) as fh:
    salts = fh.read().strip().split('\n')
it = iter(salts)
out = []
for line in body.split('\n'):
    if 'REPLACE_WITH_FRESH_SALT' in line:
        out.append(next(it, line))
    else:
        out.append(line)
with open(cfg, 'w') as fh:
    fh.write('\n'.join(out))
PYEOF
  rm -f "$CONFIG_FILE.salts"
  echo "  salts injected"
else
  rm -f "$CONFIG_FILE.salts"
  echo "  WARN: could not fetch salts; fill the 8 keys on the host before deploying"
fi
# Guard the two bugs that broke the last deploy.
grep -q "table_prefix = 'wp_'" "$CONFIG_FILE" \
  || { echo "ERR: \$table_prefix missing from generated wp-config" >&2; exit 1; }
# A wp-config that does not parse takes the whole site down, and the usual
# symptom (redirect to install.php) looks like a database problem.
if ! docker exec -i "$WP_CONTAINER" php -l < "$CONFIG_FILE" >/dev/null 2>&1; then
  echo "ERR: generated wp-config.php does not parse:" >&2
  docker exec -i "$WP_CONTAINER" php -l < "$CONFIG_FILE" >&2 || true
  exit 1
fi

# ------------------------------------------------------------- .htaccess ----
say "Generating .htaccess"
# cPanel creates an addon-domain .htaccess containing only its PHP-ini
# directives, so the rewrite block must be present or every pretty permalink
# 404s while the rewrite_rules sit unused in the database.
cat > "$STAGE_DIR/.htaccess" <<'HTEOF'
# BEGIN WordPress
# The directives (RewriteEngine and the mod_rewrite rule) are listed at the
# top level so they run before any PHP handler.
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]

# add a trailing slash to non-existent directories
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} (.+)/$
RewriteRule ^ %1 [L,R=301]

# send actual file requests (not directories) to WordPress
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTEOF

# ------------------------------------------------------------------ verify --
say "Verifying staged tree"
fail=0
check() {  # check <label> <condition-result>
  if [ "$2" = "0" ]; then echo "  OK    $1"; else echo "  FAIL  $1"; fail=1; fi
}

grep -q "table_prefix = 'wp_'" "$STAGE_DIR/wp-config.php"; check "wp-config has \$table_prefix" $?
grep -q "REPLACE_WITH_DB_PASSWORD" "$STAGE_DIR/wp-config.php"; check "wp-config DB password is a placeholder" $?
# Salts must be gone: no REPLACE_WITH_FRESH_SALT left anywhere in the file.
! grep -q "REPLACE_WITH_FRESH_SALT" "$STAGE_DIR/wp-config.php"; check "wp-config salts filled" $?
grep -q "BEGIN WordPress" "$STAGE_DIR/.htaccess"; check ".htaccess has WordPress rewrite block" $?
[ -f "$STAGE_DIR/wp-content/themes/lylyrose/style.css" ]; check "lylyrose theme present" $?
[ -d "$STAGE_DIR/wp-content/plugins/lylyrose-core" ]; check "lylyrose-core plugin present" $?
[ -f "$STAGE_DIR/wp-content/plugins/woocommerce/woocommerce.php" ]; check "WooCommerce present" $?
[ -f "$STAGE_DIR/wp-content/plugins/woocommerce/includes/wc-template-functions.php" ]; check "WooCommerce core file intact" $?
[ -f "$STAGE_DIR/wp-includes/class-wp-rewrite.php" ]; check "WordPress core present" $?
[ ! -e "$STAGE_DIR/wp-content/advanced-cache.php" ]; check "runtime drop-in caches excluded" $?
[ ! -e "$STAGE_DIR/wp-content/object-cache.php" ]; check "object-cache.php excluded" $?
[ ! -e "$STAGE_DIR/wp-content/uploads" ]; check "uploads excluded from main tree (shipped separately)" $?
[ -d "$STAGE_DIR/uploads" ]; check "uploads tree present" $?
[ ! -e "$STAGE_DIR/uploads/wc-logs" ]; check "wc-logs stripped from uploads" $?
[ ! -e "$STAGE_DIR/wp-content/themes/digikala" ]; check "dev theme digikala not shipped" $?
[ -s "$SQL_DUMP" ]; check "database dump non-empty" $?
# Every localhost:8080 URL must already have been rewritten; see DEPLOY_PREP.
if grep -q "localhost:8080" "$SQL_DUMP" 2>/dev/null; then
  echo "  WARN  dump still contains localhost:8080 — run search-replace before import"
fi

echo
echo "  tree:     $STAGE_DIR  ($(du -sh "$STAGE_DIR" 2>/dev/null | cut -f1))"
echo "  files:    $(find "$STAGE_DIR" -type f | wc -l)"
echo "  dump:     $SQL_DUMP  ($(du -h "$SQL_DUMP" | cut -f1))"
echo
echo "Next steps (see docs/DEPLOY_PREP.md):"
echo "  1. Upload the CONTENTS of $STAGE_DIR into the docroot."
echo "  2. Upload $STAGE_DIR/uploads into <docroot>/wp-content/uploads (644 files, 755 dirs)."
echo "  3. Fill DB_NAME/DB_USER/DB_PASSWORD in wp-config.php (cPanel may prefix them)."
echo "  4. Import $SQL_DUMP, then delete the dump from any shared location."
echo "  5. URL rewrite (dump still carries localhost:8080):"
echo "       wp search-replace 'http://localhost:8080' '$SITE_URL_PLACEHOLDER' --all-tables --precise"
echo "     All occurrences are in PLAIN columns, so no serialized length recompute"
echo "     is needed; confirm wp_options.siteurl and wp_options.home afterwards."
echo "  6. GATE: home 200 AND /shop/ /cart/ /checkout/ 200 — not home alone."

if [ "$fail" -ne 0 ]; then
  echo
  echo "STAGING FAILED — fix the failures above before uploading." >&2
  exit 1
fi
echo
echo "Staging OK."
