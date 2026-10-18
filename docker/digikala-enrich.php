<?php
/*
 * Enrich St. Ives products that had no Digikala data.
 * For each mapped SKU: set _digikala_* meta, append Digikala gallery images,
 * and set the description when the product has none.
 *
 * Appending (not replacing) the gallery: these products already carry their
 * catalogue packshot, which is the primary image.
 *
 * Usage: docker exec -u root lylyrose-wp php /var/www/html/wp-content/digikala-enrich.php
 */

require_once '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// Curated best-match per SKU. Sizes vary between our SKUs and Digikala's listings,
// so size variants of one formula may share a Digikala page; that is the same product.
$map = [
    'STIVES-FC-TEATREE-ACNE'                 => 15649048, // TEA TREE 200ml
    'STIVES-FC-COLLAGEN-RENEWING'            => 14870859, // احیا کننده (Renewing) 300
    'STIVES-FC-ARGANCUC-HYDRATING'           => 21651763, // HYDRATING 200
    'STIVES-FC-ALOEHYAL-LIGHTENING'          => 14870882, // روشن کننده (Lightening) 300
    'STIVES-FFW-CHAMOMILE-CALMSOOTHE'        => 22080749, // Soothing 200
    'STIVES-FFW-TEATREE-ACNE'                => 14870946, // ضدجوش (anti-acne) 300
    'STIVES-FFW-WATERMELON-HYDRATEGLOW'      => 14870920, // آبرسان (hydrating) 300
    'STIVES-FFW-APRICOT-GLOWMOIST'           => 6811731,  // زردآلو apricot درخشان 200
    'STIVES-ET-BHA-2SALICYLIC'               => 2610794,  // Black Head Clearing 170
    'STIVES-ET-AHA-5GLYCOLIC-APRICOT'        => 14871533, // زردآلو apricot 300
    'STIVES-BL-OATMEALSHEA-SOOTHING'         => 21145802, // جو دوسر و شی باتر 621
    'STIVES-MC-COLLAGENELASTIN-RENEWING-90'  => 15434219, // کلاژن 283
    'STIVES-MC-COLLAGENELASTIN-RENEWING-170' => 15434219, // کلاژن 283
    'STIVES-MC-ARGANGRAPE-NOURISHING-621'    => 14871078, // آرگان و هسته انگور 300
    'STIVES-MC-ARGANGRAPE-NOURISHING-170'    => 14871078, // آرگان و هسته انگور 300
    'STIVES-MC-ALOEVERA-LIGHTENING-621'      => 11378696, // LIGHTENING 90
    'STIVES-MC-ALOEVERA-LIGHTENING-170'      => 11378696, // LIGHTENING 90
    'STIVES-HM-BOTOXSHAMPOO-NOURISHING'      => 11379796, // BOTOX shampoo 500
    'STIVES-HL-BOTOXLEAVEIN-NOURISHING'      => 11380405, // BOTOX 500 (closest listing)
    'STIVES-HM-COLLAGENKERATINSHAMPOO-REPAIRING' => 11379571, // KERATIN COLLAGEN shampoo 500
    'STIVES-HL-COLLAGENKERATINLEAVEIN-REPAIRING' => 11380600, // REPAIRING KERATIN COLLAGEN 500
    'STIVES-HM-ARGANMACADAMIASHAMPOO-BOOSTER'    => 17573321, // آرگان و ماکادمیا shampoo 500
    'STIVES-HL-ARGANMACADAMIALEAVEIN-BOOSTER'    => 11380511, // BOOSTER ARGAN MACADAMIA 500
];

function dk_json($url) {
    $ctx = stream_context_create(['http' => [
        'timeout' => 40,
        'header'  => "User-Agent: Mozilla/5.0\r\nAccept: application/json\r\n",
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    return $raw ? json_decode($raw, true) : null;
}

$ok = 0; $bad = 0;

foreach ($map as $sku => $dkpid) {
    $id = wc_get_product_id_by_sku($sku);
    if (!$id) { echo "MISSING $sku\n"; $bad++; continue; }

    $j = dk_json("https://api.digikala.com/v2/product/$dkpid/");
    $p = $j['data']['product'] ?? null;
    if (!$p) { echo "NOAPI $sku ($dkpid)\n"; $bad++; continue; }

    $url = "https://www.digikala.com/product/dkp-$dkpid/";

    update_post_meta($id, '_digikala_id', $dkpid);
    update_post_meta($id, '_digikala_url', $url);
    update_post_meta($id, '_digikala_title', $p['title_fa'] ?? '');

    // append images not already in the gallery
    $seen = (array) get_post_meta($id, '_digikala_image_urls', true);
    $gallery_ids = wc_get_product($id)->get_gallery_image_ids();
    $added = 0;
    foreach ($p['images']['list'] ?? [] as $im) {
        $img = is_array($im['url'] ?? null) ? ($im['url'][0] ?? '') : ($im['url'] ?? '');
        if (!$img || in_array($img, $seen, true)) continue;
        $tmp = download_url($img, 60);
        if (is_wp_error($tmp)) continue;
        $att = media_handle_sideload(
            ['name' => basename(parse_url($img, PHP_URL_PATH)) ?: "dk-$dkpid.jpg", 'tmp_name' => $tmp],
            $id,
            ($p['title_fa'] ?? $sku)
        );
        if (is_wp_error($att)) { @unlink($tmp); continue; }
        $gallery_ids[] = $att;
        $seen[] = $img;
        $added++;
    }
    if ($added) {
        update_post_meta($id, '_product_image_gallery', implode(',', array_unique($gallery_ids)));
        update_post_meta($id, '_digikala_image_urls', $seen);
    }

    // description only when the product has none
    $prod = wc_get_product($id);
    $desc = trim((string) ($p['description'] ?? ''));
    if (mb_strlen(trim($prod->get_description())) < 10 && $desc !== '') {
        $prod->set_description($desc);
        $prod->save();
    }

    echo "OK $sku -> dkp-$dkpid imgs+$added\n";
    $ok++;
}

echo "\ndone ok=$ok bad=$bad\n";