<?php
/**
 * Import St. Ives products from JSON into WooCommerce
 * Usage: docker exec -u root wordpress php /var/www/html/wp-content/../docker/import-stives-products.php
 */

require_once '/var/www/html/wp-load.php';

if (!function_exists('wc_get_product')) {
    die("WooCommerce not active\n");
}

$json_path = '/var/www/html/wp-content/stives-products.json';
if (!file_exists($json_path)) {
    die("JSON file not found: $json_path\n");
}

$data = json_decode(file_get_contents($json_path), true);
if (!$data || !isset($data['products'])) {
    die("Invalid JSON structure\n");
}

echo "Starting St. Ives product import...\n";
echo "Total products in JSON: " . count($data['products']) . "\n\n";

$imported = 0;
$skipped = 0;
$errors = [];

foreach ($data['products'] as $product) {
    $sku = $product['sku'];
    $name_fa = $product['name_fa'] ?? $product['name_en'];
    $name_en = $product['name_en'];

    echo "Processing: $sku - $name_fa\n";

    // Check if product already exists by SKU
    $existing_id = wc_get_product_id_by_sku($sku);
    if ($existing_id) {
        echo "  ⊘ Skipped (already exists: ID $existing_id)\n";
        $skipped++;
        continue;
    }

    try {
        // Create product
        $wc_product = new WC_Product_Simple();
        $wc_product->set_name($name_fa);
        $wc_product->set_sku($sku);
        $wc_product->set_status('draft'); // Keep as draft until reviewed
        $wc_product->set_catalog_visibility('visible');

        // Description
        $desc_fa = $product['desc_fa'] ?? '';
        $desc_en = $product['desc_en'] ?? '';

        // Use Digikala description if available and local is empty
        if (empty($desc_fa) && isset($product['digikala']['description_fa'])) {
            $desc_fa = $product['digikala']['description_fa'];
        }

        $wc_product->set_description($desc_fa);
        $wc_product->set_short_description($desc_en);

        // Brand: St. Ives
        $brand_term = term_exists('St. Ives', 'product_brand');
        if (!$brand_term) {
            $brand_term = wp_insert_term('St. Ives', 'product_brand');
        }
        if (!is_wp_error($brand_term)) {
            wp_set_object_terms($wc_product->get_id(), (int)$brand_term['term_id'], 'product_brand');
        }

        // Meta data
        $meta_data = [
            '_stives_variant_en' => $product['variant_en'] ?? '',
            '_stives_variant_fa' => $product['variant_fa'] ?? '',
            '_stives_page' => $product['page'] ?? '',
            '_stives_packshot' => $product['packshot'] ?? '',
        ];

        if (isset($product['size'])) {
            $meta_data['_stives_size'] = $product['size'];
        }
        if (isset($product['tag_en'])) {
            $meta_data['_stives_tags'] = $product['tag_en'];
        }
        if (isset($product['digikala']['id'])) {
            $meta_data['_digikala_id'] = $product['digikala']['id'];
            $meta_data['_digikala_url'] = $product['digikala']['url'];
        }

        foreach ($meta_data as $key => $value) {
            if (!empty($value)) {
                $wc_product->update_meta_data($key, $value);
            }
        }

        // Save product first to get ID
        $product_id = $wc_product->save();

        if (!$product_id) {
            throw new Exception("Failed to create product");
        }

        // Import gallery images from Digikala
        if (isset($product['digikala']['gallery']) && is_array($product['digikala']['gallery'])) {
            $image_ids = [];
            foreach ($product['digikala']['gallery'] as $idx => $image_url) {
                $image_id = import_external_image($image_url, $product_id, $name_fa . " - " . ($idx + 1));
                if ($image_id) {
                    $image_ids[] = $image_id;
                }
            }

            if (!empty($image_ids)) {
                // First image as featured
                set_post_thumbnail($product_id, $image_ids[0]);
                // Rest as gallery
                if (count($image_ids) > 1) {
                    update_post_meta($product_id, '_product_image_gallery', implode(',', array_slice($image_ids, 1)));
                }
                echo "  ✓ Imported " . count($image_ids) . " images\n";
            }
        }

        echo "  ✓ Created product ID: $product_id\n";
        $imported++;

    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
        $errors[] = "$sku: " . $e->getMessage();
    }

    echo "\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "Import complete!\n";
echo "Imported: $imported\n";
echo "Skipped: $skipped\n";
echo "Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nError details:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

/**
 * Import external image into WordPress media library
 */
function import_external_image($url, $post_id, $title) {
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Download to temp file
    $tmp = download_url($url);
    if (is_wp_error($tmp)) {
        echo "    ✗ Image download failed: " . $tmp->get_error_message() . "\n";
        return false;
    }

    $file_array = [
        'name' => basename($url),
        'tmp_name' => $tmp
    ];

    // Import into media library
    $id = media_handle_sideload($file_array, $post_id, $title);

    if (is_wp_error($id)) {
        @unlink($tmp);
        echo "    ✗ Image import failed: " . $id->get_error_message() . "\n";
        return false;
    }

    return $id;
}
