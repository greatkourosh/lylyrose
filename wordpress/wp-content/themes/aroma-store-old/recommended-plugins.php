<?php
/**
 * Recommended Plugins for Aroma Store
 * This file lists all recommended plugins for WordPress + WooCommerce
 */

return array(
    'plugins' => array(
        // Dokan - Multivendor Marketplace
        'dokan-lite' => array(
            'name' => 'Dokan Lite',
            'description' => 'Turn your WooCommerce store into a multivendor marketplace',
            'plugin_path' => 'dokan-lite/dokan.php',
            'url' => 'https://wordpress.org/plugins/dokan-lite/',
        ),
        
        // RTL Support for Persian
        'wp-rtl' => array(
            'name' => 'WP RTL',
            'description' => 'Automatic RTL support for WordPress',
            'plugin_path' => 'wp-rtl/wp-rtl.php',
            'url' => 'https://wordpress.org/plugins/wp-rtl/',
        ),
        
        // Persian Numbers and Dates
        'persian-woocommerce' => array(
            'name' => 'Persian WooCommerce',
            'description' => 'Persian numbers, dates, and RTL support for WooCommerce',
            'plugin_path' => 'persian-woocommerce/persian-woocommerce.php',
            'url' => 'https://wordpress.org/plugins/persian-woocommerce/',
        ),
        
        // Persian Numbers
        'persian-numeric' => array(
            'name' => 'Persian Numeric',
            'description' => 'Convert English numbers to Persian numbers throughout the site',
            'plugin_path' => 'persian-numeric/persian-numeric.php',
            'url' => 'https://wordpress.org/plugins/persian-numeric/',
        ),
        
        // WooCommerce Product Vendors (for Dokan compatibility)
        'woocommerce-product-vendors' => array(
            'name' => 'WooCommerce Product Vendors',
            'description' => 'Product vendors management (alternative to Dokan)',
            'plugin_path' => 'woocommerce-product-vendors/woocommerce-product-vendors.php',
            'url' => 'https://wordpress.org/plugins/woocommerce-product-vendors/',
        ),
        
        // Regenerate Thumbnails
        'regenerate-thumbnails' => array(
            'name' => 'Regenerate Thumbnails',
            'description' => 'Regenerate thumbnails for uploaded images',
            'plugin_path' => 'regenerate-thumbnails/regenerate-thumbnails.php',
            'url' => 'https://wordpress.org/plugins/regenerate-thumbnails/',
        ),
        
        // WooCommerce PDF Invoices & Packing Slips
        'woocommerce-pdf-invoices-packing-slips' => array(
            'name' => 'WooCommerce PDF Invoices & Packing Slips',
            'description' => 'Generate PDF invoices and packing slips',
            'plugin_path' => 'woocommerce-pdf-invoices-packing-slips/woocommerce-pdf-invoices-packing-slips.php',
            'url' => 'https://wordpress.org/plugins/woocommerce-pdf-invoices-packing-slips/',
        ),
        
        // WooCommerce Shipping & Tax
        'woocommerce-shipping' => array(
            'name' => 'WooCommerce Shipping & Tax',
            'description' => 'Official WooCommerce Shipping & Tax extension',
            'plugin_path' => 'woocommerce-shipping/woocommerce-shipping.php',
            'url' => 'https://wordpress.org/plugins/woocommerce-shipping/',
        ),
        
        // WooCommerce Payments
        'woocommerce-payments' => array(
            'name' => 'WooCommerce Payments',
            'description' => 'Accept payments via WooCommerce',
            'plugin_path' => 'woocommerce-payments/woocommerce-payments.php',
            'url' => 'https://wordpress.org/plugins/woocommerce-payments/',
        ),
        
        // WP Rollback
        'wp-rollback' => array(
            'name' => 'WP Rollback',
            'description' => 'Rollback plugins and themes to previous versions',
            'plugin_path' => 'wp-rollback/wp-rollback.php',
            'url' => 'https://wordpress.org/plugins/wp-rollback/',
        ),
    ),
    
    'plugin_order' => array(
        'persian-woocommerce',
        'persian-numeric',
        'wp-rtl',
        'dokan-lite',
        'woocommerce-product-vendors',
        'regenerate-thumbnails',
        'woocommerce-pdf-invoices-packing-slips',
        'woocommerce-shipping',
        'woocommerce-payments',
        'wp-rollback',
    ),
);