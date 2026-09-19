<?php
require_once dirname( __DIR__, 4 ) . '/wp-load.php';

echo "Adding helper functions to functions.php...\n";

// Add the owns_shop helper
$helper_code = "
/**
 * Check if user can manage the shop
 */
if ( ! function_exists( 'owns_shop' ) ) {
    function owns_shop() {
        if ( is_user_logged_in() && current_user_can( 'manage_woocommerce' ) ) {
            return true;
        }
        return false;
    }
}

";

$functions_php = file_get_contents( dirname( __DIR__ ) . '/functions.php' );

if ( strpos( $functions_php, 'function owns_shop' ) === false && strpos( $functions_php, 'Entry footer' ) !== false ) {
    $replace_pos = strpos( $functions_php, '// Entry footer' );
    if ( $replace_pos !== false ) {
        $functions_php = substr( $functions_php, 0, $replace_pos ) . $helper_code . '// Entry footer' . substr( $functions_php, $replace_pos );
        file_put_contents( dirname( __DIR__ ) . '/functions.php', $functions_php );
        echo "owns_shop helper added.\n";
    }
} else {
    echo "owns_shop helper already exists or structure different.\n";
}

// Format quotes and fix missing tags in templates
$single_product = file_get_contents( dirname( __DIR__ ) . '/woocommerce/single-product.php' );
$checkout = file_get_contents( dirname( __DIR__ ) . '/woocommerce/checkout.php' );

// Fix comment
$single_product = str_replace( '<?php echo apply_filters( \'woocommerce_order_review_html\', ; ?>', '<?php echo apply_filters( \'woocommerce_order_review_html\', \'\', ; ?>', $single_product );
$single_product = str_replace( '<!--', '', $single_product );
$single_product = str_replace( '-->', '', $single_product );

// Remove undefined variables
$single_product = str_replace( '$product, $cart_item', '$product, $cart_item, $cart_item_key', $single_product );

file_put_contents( dirname( __DIR__ ) . '/woocommerce/single-product.php', $single_product );
file_put_contents( dirname( __DIR__ ) . '/woocommerce/checkout.php', $checkout );

echo "Templates formatted and helper functions added.\n";
echo "WordPress URL: http://localhost:8080" . PHP_EOL;
echo "Admin login: http://localhost:8080/wp-admin (admin/admin123)" . PHP_EOL;