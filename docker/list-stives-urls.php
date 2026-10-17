<?php
/**
 * List St. Ives product URLs
 */

require_once '/var/www/html/wp-load.php';

$args = [
    'post_type' => 'product',
    'post_status' => 'publish',
    'posts_per_page' => 10,
    'meta_query' => [
        [
            'key' => '_sku',
            'value' => 'STIVES',
            'compare' => 'LIKE'
        ]
    ]
];

$query = new WP_Query($args);

if ($query->have_posts()) {
    echo "Sample St. Ives product URLs:\n\n";
    while ($query->have_posts()) {
        $query->the_post();
        $product = wc_get_product(get_the_ID());
        echo $product->get_name() . ":\n";
        echo get_permalink() . "\n\n";
    }
    wp_reset_postdata();
}

echo "\nBrand page: " . home_url('/برند/st-ives/') . "\n";
echo "Shop page: " . home_url('/shop/') . "\n";
