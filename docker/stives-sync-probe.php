<?php
/* one-shot live sync: St. Ives titles, slugs, brand, digikala galleries.
   Input: stives-sync.json in same dir (deleted after).
   Emits per-product progress lines. */
require_once __DIR__ . '/wp-load.php';

$d = json_decode(file_get_contents(__DIR__ . '/stives-sync.json'), true);
if (!$d || empty($d['products'])) { die('NO SYNC DATA'); }

$report = ["brand:" . $d['brand']['slug'] . '|' . $d['brand']['name']];

// brand
$t = term_exists($d['brand']['slug'], 'product_brand');
if (!$t) {
    $t = wp_insert_term($d['brand']['name'], 'product_brand', ['slug' => $d['brand']['slug']]);
    $report[] = is_wp_error($t) ? 'brand:ERR ' . $t->get_error_message() : 'brand:created ' . $t['term_id'];
} else {
    $report[] = 'brand:exists ' . (is_array($t) ? $t['term_id'] : $t);
}
$brand_id = is_array($t) ? $t['term_id'] : (int)$t;

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$n_img = 0; $n_err = 0;

foreach ($d['products'] as $r) {
    $id = wc_get_product_id_by_sku($r['sku']);
    if (!$id) { $report[] = $r['sku'] . ':MISSING'; continue; }

    $title = sanitize_text_field($r['title']);
    $slug  = sanitize_title(rawurldecode($r['slug']));
    wp_update_post(['ID' => $id, 'post_title' => $title, 'post_name' => $slug]);

    wp_set_object_terms($id, $brand_id, 'product_brand', false);

    // digikala meta + gallery (skip downloads when featured image already set)
    $dg = $r['digikala'];
    if ($dg && !empty($dg['id'])) {
        update_post_meta($id, '_digikala_id', $dg['id']);
        update_post_meta($id, '_digikala_url', $dg['url']);
        update_post_meta($id, '_digikala_title', $dg['title_fa'] ?? '');
    }

    $gallery = ($dg && !empty($dg['gallery'])) ? $dg['gallery'] : [];
    $have_img = (bool)get_post_thumbnail_id($id);

    if (!$have_img && $gallery) {
        $ids = [];
        foreach ($gallery as $i => $u) {
            $tmp = download_url($u, 60);
            if (is_wp_error($tmp)) { $n_err++; continue; }
            $file = ['name' => basename(parse_url($u, PHP_URL_PATH)), 'tmp_name' => $tmp];
            $att = media_handle_sideload($file, $id, $title . ' - ' . ($i + 1));
            if (!is_wp_error($att)) { $ids[] = $att; $n_img++; }
            else { @unlink($tmp); $n_err++; }
        }
        if ($ids) {
            set_post_thumbnail($id, $ids[0]);
            if (count($ids) > 1) {
                update_post_meta($id, '_product_image_gallery', implode(',', array_slice($ids, 1)));
            }
        }
    }

    $report[] = $r['sku'] . ':ok img=' . ($have_img ? 'kept' : count($ids ?? []));
}

echo implode("\n", $report) . "\nimg_downloaded=$n_img err=$n_err";
