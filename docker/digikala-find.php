<?php
/*
 * Find Digikala counterparts for St. Ives SKUs that have none yet.
 * Prints candidates only; writes nothing.
 * Usage: docker exec lylyrose-wp php /var/www/html/wp-content/digikala-find.php
 */

require_once '/var/www/html/wp-load.php';

function dk_get($url) {
    $ctx = stream_context_create(['http' => [
        'timeout' => 30,
        'header'  => "User-Agent: Mozilla/5.0\r\nAccept: application/json\r\n",
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    return $raw ? json_decode($raw, true) : null;
}

// Persian search term per SKU (product type + hero ingredient).
$targets = [
    'STIVES-FC-TEATREE-ACNE'                 => 'ژل فیس واش اس تی ایوز درخت چای',
    'STIVES-FC-COLLAGEN-RENEWING'            => 'ژل فیس واش اس تی ایوز کلاژن',
    'STIVES-FC-ARGANCUC-HYDRATING'           => 'ژل فیس واش اس تی ایوز آرگان خیار',
    'STIVES-FC-ALOEHYAL-LIGHTENING'          => 'ژل فیس واش اس تی ایوز آلوئه ورا هیالورونیک',
    'STIVES-FFW-CHAMOMILE-CALMSOOTHE'        => 'فوم شستشو صورت اس تی ایوز بابونه',
    'STIVES-FFW-TEATREE-ACNE'                => 'فوم شستشو صورت اس تی ایوز درخت چای',
    'STIVES-FFW-WATERMELON-HYDRATEGLOW'      => 'فوم شستشو صورت اس تی ایوز هندوانه',
    'STIVES-FFW-APRICOT-GLOWMOIST'           => 'فوم شستشو صورت اس تی ایوز زردآلو',
    'STIVES-ET-BHA-2SALICYLIC'               => 'تونر لایه بردار اس تی ایوز سالیسیلیک',
    'STIVES-ET-AHA-5LACTIC-ROSE'             => 'تونر لایه بردار اس تی ایوز لاکتیک گل رز',
    'STIVES-ET-AHA-5GLYCOLIC-APRICOT'        => 'تونر لایه بردار اس تی ایوز گلیکولیک زردآلو',
    'STIVES-BL-OATMEALSHEA-SOOTHING'         => 'لوسیون بدن اس تی ایوز جو دو سر شی باتر',
    'STIVES-MC-COLLAGENELASTIN-RENEWING-90'  => 'کرم مرطوب کننده اس تی ایوز کلاژن الاستین',
    'STIVES-MC-ARGANGRAPE-NOURISHING-621'    => 'کرم مرطوب کننده اس تی ایوز آرگان هسته انگور',
    'STIVES-MC-ALOEVERA-LIGHTENING-621'      => 'کرم مرطوب کننده اس تی ایوز آلوئه ورا ویتامین سی',
    'STIVES-MC-COLLAGENELASTIN-RENEWING-170' => 'کرم مرطوب کننده اس تی ایوز کلاژن الاستین',
    'STIVES-MC-ARGANGRAPE-NOURISHING-170'    => 'کرم مرطوب کننده اس تی ایوز آرگان هسته انگور',
    'STIVES-MC-ALOEVERA-LIGHTENING-170'      => 'کرم مرطوب کننده اس تی ایوز آلوئه ورا ویتامین سی',
    'STIVES-SPF50-COLLAGENELASTIN'           => 'ضد آفتاب اس تی ایوز SPF 50',
    'STIVES-HM-CURLREVIVE-SOFTSHINY'         => 'شامپو موهای فر اس تی ایوز',
    'STIVES-HP-CURLCOMPLEX-BOUNCY'           => 'پرایمر موهای فر اس تی ایوز',
    'STIVES-HL-CURLREVITALIZE-ELASTIC'       => 'کرم مو بعد از حمام اس تی ایوز فر',
    'STIVES-HM-BOTOXSHAMPOO-NOURISHING'      => 'شامپو بوتاکس اس تی ایوز',
    'STIVES-HL-BOTOXLEAVEIN-NOURISHING'      => 'کرم مو بوتاکس اس تی ایوز',
    'STIVES-HM-COLLAGENKERATINSHAMPOO-REPAIRING' => 'شامپو کلاژن کراتین اس تی ایوز',
    'STIVES-HL-COLLAGENKERATINLEAVEIN-REPAIRING' => 'کرم مو کلاژن کراتین اس تی ایوز',
    'STIVES-HM-ARGANMACADAMIASHAMPOO-BOOSTER'    => 'شامپو آرگان ماکادمیا اس تی ایوز',
    'STIVES-HL-ARGANMACADAMIALEAVEIN-BOOSTER'    => 'کرم مو آرگان ماکادمیا اس تی ایوز',
    'STIVES-HO-ARGANOIL-100'                     => 'روغن آرگان اس تی ایوز',
];

foreach ($targets as $sku => $q) {
    $j = dk_get('https://api.digikala.com/v1/search/?q=' . rawurlencode($q));
    $items = $j['data']['products'] ?? [];
    echo "\n=== $sku\n    q: $q\n";
    $n = 0;
    foreach ($items as $it) {
        $t = $it['title_fa'] ?? '';
        // only show plausible St. Ives hits
        if (stripos($t, 'ایوز') === false && stripos($t, 'Ives') === false) continue;
        echo '  ' . ($it['id'] ?? '?') . ' | ' . $t . "\n";
        if (++$n >= 6) break;
    }
    if ($n === 0) echo "  (no St. Ives hit)\n";
}