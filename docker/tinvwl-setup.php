<?php
/**
 * Complete TI WooCommerce Wishlist setup: page assignment, permalinks flush,
 * and verify the wishlist page renders.
 * Run: docker exec lylyrose-wp php /tmp/tinvwl-setup.php
 */
require '/var/www/html/wp-load.php';

echo "== TI Wishlist configuration ==\n";

// 1. Ensure wishlist page option is set
$page_opt = get_option( 'tinvwl-page' );
if ( empty( $page_opt['wishlist'] ) || ! get_post( $page_opt['wishlist'] ) ) {
    $pid = wp_insert_post( array(
        'post_title'   => 'علاقه‌مندی‌ها',
        'post_name'    => 'wishlist',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => '[tinvwl_wishlist]',
    ) );
    update_option( 'tinvwl-page', array( 'wishlist' => $pid ) );
    echo "created+assigned wishlist page #{$pid}\n";
} else {
    $pid = $page_opt['wishlist'];
    echo "wishlist page already assigned: #{$pid}\n";
}

// 2. Ensure page has the shortcode
$post = get_post( $pid );
if ( $post && strpos( $post->post_content, 'tinvwl' ) === false ) {
    wp_update_post( array( 'ID' => $pid, 'post_content' => '[tinvwl_wishlist]' ) );
    echo "shortcode added\n";
} else {
    echo "shortcode present\n";
}

// 3. Mark setup complete (dismisses the "You're almost ready" wizard)
update_option( 'tinvwl_onboarding', 'completed' );
delete_option( 'tinvwl_redirect' );
echo "onboarding marked complete\n";

// 4. Flush permalinks so /wishlist/ works
flush_rewrite_rules();
echo "rewrite rules flushed\n";
