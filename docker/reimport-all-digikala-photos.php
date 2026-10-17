<?php
/**
 * Re-import ALL Digikala photos for St. Ives products and add admin field
 * Usage: docker exec -u root lylyrose-wp php /var/www/html/wp-content/reimport-all-digikala-photos.php
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

echo "Re-importing ALL Digikala photos for St. Ives products...\n\n";

$updated = 0;
$skipped = 0;

foreach ($data['products'] as $product) {
    $sku = $product['sku'];
    $name_fa = $product['name_fa'] ?? $product['name_en'];

    // Only process products with Digikala data
    if (!isset($product['digikala']) || empty($product['digikala']['gallery'])) {
        continue;
    }

    echo "Processing: $sku - $name_fa\n";

    $product_id = wc_get_product_id_by_sku($sku);
    if (!$product_id) {
        echo "  ⊘ Product not found\n\n";
        $skipped++;
        continue;
    }

    try {
        $wc_product = wc_get_product($product_id);
        $digikala = $product['digikala'];

        // Delete existing gallery images first
        $existing_thumbnail = $wc_product->get_image_id();
        $existing_gallery = $wc_product->get_gallery_image_ids();

        $all_existing = array_merge(
            $existing_thumbnail ? [$existing_thumbnail] : [],
            $existing_gallery
        );

        if (!empty($all_existing)) {
            echo "  → Removing " . count($all_existing) . " old images...\n";
            foreach ($all_existing as $img_id) {
                wp_delete_attachment($img_id, true);
            }
        }

        // Import ALL gallery images from Digikala
        $image_ids = [];
        $total = count($digikala['gallery']);

        foreach ($digikala['gallery'] as $idx => $image_url) {
            echo "  → Downloading image " . ($idx + 1) . "/$total...";
            $image_id = import_external_image($image_url, $product_id, $name_fa . " - " . ($idx + 1));
            if ($image_id) {
                $image_ids[] = $image_id;
                echo " ✓\n";
            } else {
                echo " ✗\n";
            }
        }

        if (!empty($image_ids)) {
            // First image as featured/thumbnail
            set_post_thumbnail($product_id, $image_ids[0]);

            // ALL remaining images as gallery
            if (count($image_ids) > 1) {
                $gallery_ids = array_slice($image_ids, 1);
                update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
            }

            echo "  ✓ Set 1 featured + " . (count($image_ids) - 1) . " gallery images\n";
        }

        // Update Digikala metadata for admin display
        if (isset($digikala['id'])) {
            update_post_meta($product_id, '_digikala_id', $digikala['id']);
            update_post_meta($product_id, '_digikala_url', $digikala['url']);
            update_post_meta($product_id, '_digikala_title', $digikala['title_fa'] ?? '');
        }

        $updated++;

    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo str_repeat('=', 60) . "\n";
echo "Updated: $updated products\n";
echo "Skipped: $skipped products\n";

/**
 * Import external image into WordPress media library
 */
function import_external_image($url, $post_id, $title) {
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $tmp = download_url($url);
    if (is_wp_error($tmp)) {
        return false;
    }

    $file_array = [
        'name' => basename(parse_url($url, PHP_URL_PATH)),
        'tmp_name' => $tmp
    ];

    $id = media_handle_sideload($file_array, $post_id, $title);

    if (is_wp_error($id)) {
        @unlink($tmp);
        return false;
    }

    return $id;
}
