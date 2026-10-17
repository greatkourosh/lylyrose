<?php
/**
 * Update all St. Ives product names to include brand name
 * Usage: docker exec -u root lylyrose-wp php /var/www/html/wp-content/update-stives-names.php
 */

require_once '/var/www/html/wp-load.php';

if (!function_exists('wc_get_product')) {
    die("WooCommerce not active\n");
}

$json_path = '/var/www/html/wp-content/stives-products.json';
if (!file_exists($json_path)) {
    die("JSON file not found\n");
}

$data = json_decode(file_get_contents($json_path), true);
if (!$data || !isset($data['products'])) {
    die("Invalid JSON\n");
}

echo "Updating St. Ives product names to include brand...\n\n";

$updated = 0;
$skipped = 0;

foreach ($data['products'] as $product) {
    $sku = $product['sku'];
    $name_fa = $product['name_fa'] ?? $product['name_en'];
    $name_en = $product['name_en'] ?? '';

    echo "Processing: $sku\n";

    $product_id = wc_get_product_id_by_sku($sku);
    if (!$product_id) {
        echo "  ⊘ Product not found\n\n";
        $skipped++;
        continue;
    }

    try {
        $wc_product = wc_get_product($product_id);
        $current_name = $wc_product->get_name();

        // Check if name already starts with brand
        if (preg_match('/^(St\.?\s*Ives|سنت\s*ایوز)/ui', $current_name)) {
            echo "  ⊘ Already has brand prefix\n\n";
            $skipped++;
            continue;
        }

        // Build new name with brand
        $new_name_fa = 'سنت ایوز ' . $name_fa;

        // Update product name
        wp_update_post([
            'ID' => $product_id,
            'post_title' => $new_name_fa
        ]);

        echo "  Old: $current_name\n";
        echo "  New: $new_name_fa\n";
        echo "  ✓ Updated\n\n";
        $updated++;

    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n\n";
    }
}

echo str_repeat('=', 60) . "\n";
echo "Updated: $updated products\n";
echo "Skipped: $skipped products\n";
