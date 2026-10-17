<?php
/**
 * Move "سنت ایوز" from start of title into position after the product-type phrase,
 * restore correct Persian titles for a few SKUs, and refresh slugs (keeping sku-NNNN- prefix).
 * Usage: docker exec -u root lylyrose-wp php /var/www/html/wp-content/move-stives-brand-name.php
 */

require_once '/var/www/html/wp-load.php';

fix_mu_redirect_host();

function fix_mu_redirect_host() {
    // ensure canonical host used in slug redirects n/a
}

$restore = [
    'STIVES-FFW-TEATREE-ACNE'            => 'فوم شستشو صورت حاوی درخت چای (مناسب پوست چرب)',
    'STIVES-SCRUB-BHA2-APRICOT'          => 'اسکراب لایه بردار پوست (حاوی 2 درصد سالیسیلیک اسید و عصاره زردآلو)',
    'STIVES-SCRUB-FRESHSKIN-APRICOT'     => 'اسکراب پوست (حاوی عصاره زردآلو)',
    'STIVES-HM-ARGANMACADAMIA-BOOSTER'   => 'ماسک موی داخل حمایت آرگان و ماکادمیا',
    'STIVES-HL-ARGANMACADAMIALEAVEIN-BOOSTER' => 'کرم مو بعد از حمام حاوی روغن آرگان و ماکادمیا (بدون آبکشی)',
    'STIVES-HS-COLLAGENKERATINCOND-REPAIRING' => 'اسپری نرم کننده مو کلاژن کراتین (ترمیم کننده)',
    'STIVES-HL-COLLAGENKERATINLEAVEIN-REPAIRING' => 'کرم مو بعد از حمام کلاژن کراتین (بدون آبکشی)',
];

$types = [
    'اسپری نرم کننده مو', 'اسپری نرم کننده',
    'پرایمر محافظت کننده',
    'فوم شستشو صورت', 'فوم شستشو',
    'محلول میسلارواتر',
    'تونر لایه بردار پوست', 'تونر لایه بردار',
    'شامپو اسکراب بدن', 'شامپو بدن', 'شامپو موی', 'شامپو مو',
    'لوسیون بدن',
    'اسکراب لایه بردار پوست', 'اسکراب پوست',
    'کرم مرطوب کننده پوست', 'کرم ضد آفتاب', 'کرم مو بعد از حمام', 'کرم مو',
    'ماسک موی داخل', 'ماسک موی',
    'روغن آرگان',
    'ژل فیس واش',
    'شامپو',
];

$args = [
    'post_type'      => 'product',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'meta_query'     => [
        ['key' => '_sku', 'value' => 'STIVES-', 'compare' => 'LIKE'],
    ],
];

$products = get_posts($args);
$updated = 0;

foreach ($products as $p) {
    $sku = get_post_meta($p->ID, '_sku', true);
    $old = $p->post_title;

    $base = isset($restore[$sku]) ? $restore[$sku] : preg_replace('/^سنت\s+ایوز\s+/u', '', $old);

    $inserted = false;
    foreach ($types as $t) {
        if (mb_strpos($base, $t) === 0) {
            $rest = mb_substr($base, mb_strlen($t));
            if ($rest === '' || preg_match('/^\s+(حاوی|مناسب|مخصوص|بدون|لایه|داخل|سری|مدل|\(|BOTOX|KERATIN|NOURISHING|REPAIRING|BOOSTER|SPF|TEA|FRESH|GLOW|ARAN|ARGAN|\d)/u', $rest)) {
                $new = $t . ' سنت ایوز' . $rest;
                $inserted = true;
                break;
            }
        }
    }

    if (!$inserted) {
        $words = preg_split('/\s+/u', $base, -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) >= 2) {
            array_splice($words, 2, 0, ['سنت', 'ایوز']);
        }
        $new = implode(' ', $words);
    }

    if ($new === $old) {
        echo "  ⊘ unchanged: $old\n";
        continue;
    }

    $prefix = '';
    if (preg_match('/^(sku-\d+-)/u', $p->post_name, $m)) {
        $prefix = $m[1];
    }
    $slug = $prefix . sanitize_title($new);
    $slug = wp_unique_post_slug($slug, $p->ID, 'publish', 'product', 0);

    wp_update_post([
        'ID'         => $p->ID,
        'post_title' => $new,
        'post_name'  => $slug,
    ]);

    echo "  ✓ $old\n";
    echo "    → $new\n";
    echo "    URL: " . get_permalink($p->ID) . "\n";
    $updated++;
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "Updated $updated products\n";
