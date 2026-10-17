<?php
/**
 * Fix 4 botched titles from the brand-move run.
 * Usage: docker exec -u root lylyrose-wp php /var/www/html/wp-content/fix-four-stives-titles.php
 */

require_once '/var/www/html/wp-load.php';

$fix = [
    'STIVES-HS-BOTOXCONDITIONING-NOURISHING'   => 'اسپری نرم کننده مو سنت ایوز بوتاکس',
    'STIVES-HS-COLLAGENKERATINCOND-REPAIRING'  => 'اسپری نرم کننده مو سنت ایوز کلاژن کراتین (ترمیم کننده)',
    'STIVES-HL-BOTOXLEAVEIN-NOURISHING'        => 'کرم مو بعد از حمام سنت ایوز بوتاکس (بدون آبکشی)',
    'STIVES-HL-COLLAGENKERATINLEAVEIN-REPAIRING' => 'کرم مو بعد از حمام سنت ایوز کلاژن کراتین (بدون آبکشی)',
];

foreach ($fix as $sku => $new_title) {
    $id = wc_get_product_id_by_sku($sku);
    if (!$id) {
        echo "  ✗ $sku not found\n";
        continue;
    }

    $p = get_post($id);
    $old = $p->post_title;

    $prefix = '';
    if (preg_match('/^(sku-\d+-)/u', $p->post_name, $m)) {
        $prefix = $m[1];
    }
    $slug = wp_unique_post_slug($prefix . sanitize_title($new_title), $id, 'publish', 'product', 0);

    wp_update_post(['ID' => $id, 'post_title' => $new_title, 'post_name' => $slug]);

    echo "  ✓ $sku\n";
    echo "    $old\n";
    echo "    → $new_title\n";
    echo "    URL: " . get_permalink($id) . "\n\n";
}

echo "Done.\n";
