# WordPress core is overlaid onto the shared aroma_store image rather than the
# bare 6.5 base: the shared image carries the PHP redis extension and the
# aroma-entrypoint.sh that rebinds bind-mounted wp-content to www-data (33).
# Building from the bare base would regress that ownership fix.
FROM aroma_store-wordpress:latest

# WooCommerce 11.1.0 requires WordPress >= 7.0 and calls
# WP_Block_Templates_Registry, which does not exist in the 6.5.5 core this
# image ships. A fresh volume seeds /var/www/html from /usr/src/wordpress, so
# without this overlay the very first `docker compose up` fatals on any WC
# page. The existing local stack only worked because its volume had been
# hand-upgraded to 7.1 by copying core files out of the upstream container.
#
# The trailing assertions fail the build -- rather than a later deploy -- if
# the core cannot support the installed WooCommerce.
ARG WP_MIN_VERSION=7.0

# The shared image's aroma-entrypoint.sh execs the command directly, so the
# official WordPress entrypoint never runs and a fresh volume is left without
# core files. This one keeps the wp-content ownership fix and then hands off to
# the official entrypoint, which does the core copy and wp-config generation.
COPY entrypoint.sh /usr/local/bin/lylyrose-entrypoint.sh
RUN chmod +x /usr/local/bin/lylyrose-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/lylyrose-entrypoint.sh"]
CMD ["apache2-foreground"]

RUN set -eux; \
    curl -fL --retry 5 --connect-timeout 15 --max-time 300 \
      https://wordpress.org/latest.zip -o /tmp/wp.zip; \
    # wp-config-docker.php is added by the image, not shipped in the WordPress
    # zip, so it has to be carried across or a fresh volume never gets a
    # generated wp-config.php.
    cp /usr/src/wordpress/wp-config-docker.php /tmp/wp-config-docker.php; \
    rm -rf /usr/src/wordpress/*; \
    php -r '$z=new ZipArchive; $z->open("/tmp/wp.zip"); $z->extractTo("/tmp/wpx"); $z->close();'; \
    cp -a /tmp/wpx/wordpress/. /usr/src/wordpress/; \
    cp /tmp/wp-config-docker.php /usr/src/wordpress/wp-config-docker.php; \
    rm -rf /tmp/wp.zip /tmp/wpx; \
    grep -q "class WP_Block_Templates_Registry" \
      /usr/src/wordpress/wp-includes/class-wp-block-templates-registry.php; \
    v=$(grep -oP "(?<=^\\\$wp_version = ')[^']+" /usr/src/wordpress/wp-includes/version.php); \
    echo "WordPress core: $v (min $WP_MIN_VERSION)"; \
    php -r 'exit(version_compare($argv[1], $argv[2], ">=") ? 0 : 1);' "$v" "$WP_MIN_VERSION"
