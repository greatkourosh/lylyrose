<?php
/**
 * Apply a WordPress core patch update in place.
 *
 * WP_ADMIN is deliberately NOT defined: doing so makes wp-load.php take the
 * admin auth path, which die()s silently in CLI. The upgrader classes load
 * fine without it.
 */
define('WP_USE_THEMES', false);
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/class-language-pack-upgrader.php';

global $wp_version, $wpdb;

$before = $wp_version;
$prefix_before = $wpdb->prefix;
echo "core before: $before\n";
echo "prefix: $prefix_before\n";

$backup = '/tmp/wp-core-backup-' . $before;
@mkdir($backup, 0755, true);
if (file_exists('/var/www/html/wp-config.php')) {
    copy('/var/www/html/wp-config.php', "$backup/wp-config.php");
    echo "backed up wp-config.php -> $backup\n";
}
if (file_exists('/var/www/html/wp-content/languages')) {
    exec('cp -a /var/www/html/wp-content/languages ' . escapeshellarg($backup) . '/languages');
    echo "backed up languages dir\n";
}

wp_version_check();
$core_update = get_site_transient('update_core');
$offered = (array) $core_update;
$version = $offered['response'] === 'upgrade' ? ($offered['packages']['core'] ?? null) : null;

if (!$version) {
    echo "RESULT: no core upgrade offered by update_core transient\n";
    // Fall back to what the API says, in case the transient is stale.
    $api = wp_remote_get('https://api.wordpress.org/core/version-check/1.7/');
    $d = json_decode(wp_remote_retrieve_body($api), true);
    $ver = $d['offers'][0]['version'] ?? null;
    if ($ver && version_compare($ver, $before, '>')) {
        $version = $d['offers'][0]['packages']['full'] ?? null;
        echo "api offers: $ver\n";
    } else {
        echo "RESULT: core already current\n";
        exit(0);
    }
}

$locale_pkg = $offered['translations'][determine_locale()][0] ?? null;
echo "offered: $version\n";
echo "fa_IR pack: " . ($locale_pkg ?? 'none (core strings stay en_US)') . "\n";

$upgrader = new WP_Upgrader();
$result = $upgrader->run(array(
    'package'           => $version,
    'destination'       => '/var/www/html',
    'clear_destination' => false,
    'clear_working'     => true,
    'is_multi'          => false,
    'allow_root'        => true,
    'maintenance_mode'  => false,
    // WP_Upgrader defaults this to true (right for a fresh plugin install, wrong
    // for a core update into an existing docroot). Left on, it aborts with an
    // empty 'folder_exists' error because /var/www/html is never empty.
    'abort_if_destination_exists' => false,
    'hook_extra'        => array('type' => 'core', 'action' => 'update'),
));

if (is_wp_error($result)) {
    echo "RESULT: ERROR -> " . $result->get_error_message() . "\n";
    exit(1);
}

// The fa_IR language pack is applied separately from core; without it the
// admin and front end fall back to English.
if ($locale_pkg) {
    $lp = new Language_Pack_Upgrader();
    $lp->upgrade($locale_pkg);
    echo "fa_IR pack installed\n";
}

clearstatcache();
$after = trim(shell_exec("grep -oP \"(?<=^\\\$wp_version = ')[^']+\" /var/www/html/wp-includes/version.php"));
echo "core after: $after\n";
echo "config intact: " . (file_exists('/var/www/html/wp-config.php') ? 'YES' : 'NO') . "\n";
global $wpdb;
echo "prefix intact: " . ($wpdb->prefix === $prefix_before ? "YES ($prefix_before)" : "CHANGED -> {$wpdb->prefix}") . "\n";
echo "RESULT: updated\n";
