#!/bin/sh
# Two jobs, in order:
#
# 1. Rebind bind-mounted wp-content to www-data (33). The host bind-mount resets
#    these to the host uid (1000) on every start, which silently breaks SMS
#    logging, Autoptimize caching, and any other runtime write.
# 2. Hand off to the official WordPress entrypoint, which is what copies core
#    files out of /usr/src/wordpress and renders wp-config.php.
#
# Step 2 is why this script exists rather than aroma_store's. That one execs the
# command directly, so it never reached the official entrypoint and a fresh
# volume was left holding nothing but wp-content -- no core, no wp-config, no
# site. Keeping the chown and re-execing the official entrypoint fixes both.
set -e

echo "[entrypoint] fixing wp-content ownership to www-data:33"
chown -R www-data:www-data /var/www/html/wp-content || \
  echo "[entrypoint] WARNING: could not chown wp-content (read-only mount?)"

# The official entrypoint dereferences $1 unguarded, so it cannot be invoked
# with no arguments. Default to the image's own command when none is supplied.
if [ "$#" -eq 0 ]; then
  set -- apache2-foreground
fi

echo "[entrypoint] handing off to official WordPress entrypoint: $*"
exec /usr/local/bin/docker-entrypoint.sh "$@"
