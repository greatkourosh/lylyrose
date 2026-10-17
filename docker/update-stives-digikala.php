<?php
/**
 * Update existing St. Ives products with Digikala gallery images and descriptions
 * Usage: docker exec -u root lylyrose-wp php /var/www/html/wp-content/update-stives-digikala.php
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

echo "Updating St. Ives products with Digikala data...\n";
echo "Total products in JSON: " . count($data['products']) . "\n\n";

$updated = 0;
$skipped = 0;
$errors = [];

foreach ($data['products'] as $product) {
    $sku = $product['sku'];
    $name_fa = $product['name_fa'] ?? $product['name_en'];

    // Only process products with Digikala data
    if (!isset($product['digikala']) || empty($product['digikala']['gallery'])) {
        continue;
    }

    echo "Processing: $sku - $name_fa\n";

    // Find existing product by SKU
    $product_id = wc_get_product_id_by_sku($sku);
    if (!$product_id) {
        echo "  ⊘ Skipped (product not found)\n\n";
        $skipped++;
        continue;
    }

    try {
        $wc_product = wc_get_product($product_id);

        // Check if product already has gallery images
        $existing_gallery = $wc_product->get_gallery_image_ids();
        $existing_thumbnail = $wc_product->get_image_id();

        if (!empty($existing_thumbnail) && !empty($existing_gallery)) {
            echo "  ⊘ Already has images (thumbnail + " . count($existing_gallery) . " gallery)\n\n";
            $skipped++;
            continue;
        }

        $digikala = $product['digikala'];
        $changes = [];

        // Import gallery images from Digikala
        if (isset($digikala['gallery']) && is_array($digikala['gallery'])) {
            $image_ids = [];
            $imported_count = 0;

            foreach ($digikala['gallery'] as $idx => $image_url) {
                echo "  → Downloading image " . ($idx + 1) . "/" . count($digikala['gallery']) . "...";
                $image_id = import_external_image($image_url, $product_id, $name_fa . " - " . ($idx + 1));
                if ($image_id) {
                    $image_ids[] = $image_id;
                    $imported_count++;
                    echo " ✓\n";
                } else {
                    echo " ✗\n";
                }
            }

            if (!empty($image_ids)) {
                // First image as featured/thumbnail
                if (empty($existing_thumbnail)) {
                    set_post_thumbnail($product_id, $image_ids[0]);
                    $changes[] = "Set featured image";
                }

                // Rest as gallery
                if (count($image_ids) > 1) {
                    $gallery_ids = array_slice($image_ids, 1);
                    update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
                    $changes[] = count($gallery_ids) . " gallery images";
                }
            }
        }

        // Update description if empty and Digikala has one
        if (isset($digikala['description_fa']) && !empty($digikala['description_fa'])) {
            $current_desc = $wc_product->get_description();
            if (empty($current_desc) || strlen(trim($current_desc)) < 50) {
                $wc_product->set_description($digikala['description_fa']);
                $wc_product->save();
                $changes[] = "Added description";
            }
        }

        // Update Digikala metadata
        if (isset($digikala['id'])) {
            update_post_meta($product_id, '_digikala_id', $digikala['id']);
            update_post_meta($product_id, '_digikala_url', $digikala['url']);
            update_post_meta($product_id, '_digikala_title', $digikala['title_fa']);
        }

        if (!empty($changes)) {
            echo "  ✓ Updated: " . implode(', ', $changes) . "\n";
            $updated++;
        } else {
            echo "  ⊘ No changes needed\n";
            $skipped++;
        }

    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
        $errors[] = "$sku: " . $e->getMessage();
    }

    echo "\n";
}

echo str_repeat('=', 60) . "\n";
echo "Update complete!\n";
echo "Updated: $updated\n";
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
        return false;
    }

    $file_array = [
        'name' => basename(parse_url($url, PHP_URL_PATH)),
        'tmp_name' => $tmp
    ];

    // Import into media library
    $id = media_handle_sideload($file_array, $post_id, $title);

    if (is_wp_error($id)) {
        @unlink($tmp);
        return false;
    }

    return $id;
}
