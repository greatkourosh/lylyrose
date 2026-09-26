<?php
/**
 * Update a single plugin by its plugin file, then report the result.
 *
 * Usage: php update-plugin.php <plugin-file> e.g. woocommerce/woocommerce.php
 *
 * Updates one plugin at a time on purpose: a batch that breaks something makes
 * it ambiguous which plugin did it.
 */
define('WP_USE_THEMES', false);
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader-skin.php';
require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

$file = $argv[1] ?? null;
if (!$file) {
    fwrite(STDERR, "usage: update-plugin.php <plugin-file> [--inactive]\n");
    exit(2);
}
// Some plugins ship in the deploy artifact but are inactive locally (Wordfence,
// Rank Math, WP Super Cache, Gateland, Persian shipping). Their code still ships
// to production, so they get updated but must stay inactive here.
$allow_inactive = in_array('--inactive', array_slice($argv, 2), true);

$installed = get_plugin_data(WP_PLUGIN_DIR . '/' . $file, false, false);
$was       = $installed['Version'] ?? '?';
$name      = $installed['Name'] ?? dirname($file);
$is_active = is_plugin_active($file);
echo "plugin : $name ($file)\n";
echo "active : " . ($is_active ? 'yes' : 'no') . "\n";
echo "before : $was\n";

if (!$is_active && !$allow_inactive) {
    echo "RESULT: skipped (not active locally)\n";
    exit(0);
}

wp_update_plugins();
$response = (array) get_site_transient('update_plugins')->response;
if (!isset($response[$file])) {
    echo "RESULT: no update offered\n";
    exit(0);
}
$new = (array) $response[$file];
echo "after  : " . ($new['new_version'] ?? '?') . "\n";

// Keep the active-plugin entry: the upgrader deactivates on some failures and a
// half-updated plugin is worse than an untouched one.
$active_before = get_option('active_plugins');

$upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
ob_start();
$result = $upgrader->run(array(
    // 'package' must be the downloadable URL from the update transient, not the
    // plugin file: passing the file makes the upgrader try to HTTP-GET that
    // literal path, which surfaces as an empty "download_failed".
    'package'                     => $new['package'],
    'destination'                 => WP_PLUGIN_DIR,
    'clear_destination'           => false,
    'clear_working'               => true,
    'abort_if_destination_exists' => false,
    'is_multi'                    => false,
    'allow_root'                  => true,
    'maintenance_mode'            => false,
    'hook_extra'                  => array('plugin' => $file, 'type' => 'plugin', 'action' => 'update'),
));
$out = ob_get_clean();
$out = trim(preg_replace('/\s+/', ' ', strip_tags($out)));
if ($out) {
    echo "upgrader: $out\n";
}

if (is_wp_error($result)) {
    echo "RESULT: ERROR -> " . $result->get_error_message() . "\n";
    // If it got deactivated, put it back so the next run tests the real state.
    if (!is_plugin_active($file)) {
        echo "reactivating after failure\n";
        activate_plugin($file);
    }
    exit(1);
}

$now = get_plugin_data(WP_PLUGIN_DIR . '/' . $file, false, false);
echo "now    : " . ($now['Version'] ?? '?') . "\n";
echo "active : " . (is_plugin_active($file) ? 'yes (unchanged)' : 'NO - DEACTIVATED BY UPDATE') . "\n";

$active_after = get_option('active_plugins');
$sorted_a = $active_before; sort($sorted_a);
$sorted_b = $active_after;  sort($sorted_b);
if ($sorted_a !== $sorted_b) {
    echo "active list changed:\n";
    echo "  - " . implode("\n  - ", array_diff($sorted_a, $sorted_b)) . "\n";
    echo "  + " . implode("\n  + ", array_diff($sorted_b, $sorted_a)) . "\n";
}

echo "RESULT: ok\n";
