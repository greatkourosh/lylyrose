<?php
/**
 * The hero slide photo control must store an attachment ID, not a URL.
 *
 * The bug this pins: WP_Customize_Image_Control writes the attachment URL into
 * the setting, and the setting's absint() sanitizer reduces that URL to 0, so
 * every Customizer pick saved as "no photo" and no slide ever got .has-photo.
 * Nothing else in the suite touched this, which is why it shipped green.
 *
 * Prints MARKER: value lines for docker/run-tests.sh to grep.
 */
$_SERVER['HTTP_HOST'] = 'lylyrose.local';
require '/var/www/html/wp-load.php';
// The Customize classes are admin-only and not loaded by wp-load.
require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';

$wp_customize = new WP_Customize_Manager();
do_action( 'customize_register', $wp_customize );

$id      = 'lylyrose_hero_photo_sale';
$control = $wp_customize->get_control( $id );
$setting = $wp_customize->get_setting( $id );

printf( "CONTROL_CLASS: %s\n", $control ? get_class( $control ) : 'none' );
printf( "CONTROL_TYPE: %s\n", $control ? $control->type : 'none' );
printf(
	"STORES_ID: %s\n",
	$control instanceof WP_Customize_Media_Control && ! ( $control instanceof WP_Customize_Upload_Control ) ? 'yes' : 'no'
);
printf( "SANITIZE_ID: %s\n", call_user_func( $setting->sanitize_callback, 9269 ) );
printf(
	"SANITIZE_URL: %s\n",
	call_user_func( $setting->sanitize_callback, 'https://x/wp-content/uploads/2026/10/hero-banner-clean.webp' )
);

// A photo must already be wired, and its path must resolve on disk.
$picked = (int) get_theme_mod( $id );
printf( "PICKED_ID: %d\n", $picked );
$path = lylyrose_hero_photo( 'sale' );
printf( "HERO_PATH: %s\n", $path );
printf( "PATH_ON_DISK: %s\n", ( $path && file_exists( '/var/www/html' . $path ) ) ? 'yes' : 'no' );
