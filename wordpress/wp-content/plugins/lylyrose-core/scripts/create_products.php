<?php
/**
 * Lyly Rose - Create sample perfume products
 * Run via: docker exec lylyrose-wp bash -c "su -s /bin/bash www-data -c 'php /tmp/create_products.php'"
 */

define( 'WP_USE_THEMES', false );
require_once '/var/www/html/wp-load.php';

if ( ! class_exists( 'WooCommerce' ) ) {
    echo "WooCommerce not active. Aborting.\n";
    exit( 1 );
}

// ---- Helper: create/get term ----
function lylyrose_ensure_term( $taxonomy, $name, $slug = '' ) {
    if ( ! $slug ) {
        $slug = sanitize_title( $name );
    }
    $term = term_exists( $slug, $taxonomy );
    if ( ! $term ) {
        $term = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
        if ( is_wp_error( $term ) ) {
            return null;
        }
    }
    return $term;
}

// ---- 1. Create product categories ----
$categories = array(
    'perfume'               => 'عطر و ادکلن',
    'men'                   => 'مردانه',
    'women'                 => 'زنانه',
    'unisex'                => 'یونیسکس',
    'eau-de-parfum'         => 'ادو پرفیوم',
    'eau-de-toilette'       => 'ادو تویلت',
    'perfume-oil'           => 'عطر روغنی',
    'body-spray'            => 'بادی اسپلش',
    'gift-sets'             => 'ست هدیه',
    'samples'               => 'سمپل',
    'accessories'           => 'اکسسوری عطر',
);

$cat_ids = array();
foreach ( $categories as $slug => $name ) {
    $term = lylyrose_ensure_term( 'product_cat', $name, $slug );
    if ( $term ) {
        $cat_ids[ $slug ] = (int) $term['term_id'];
    }
}

// ---- 2. Create brands ----
$brands = array(
    'chanel'   => 'شنل',
    'dior'     => 'دیور',
    'tester'   => 'تستر',
    'gucci'    => 'گوچی',
    'versace'  => 'ورساچه',
    'zara'     => 'زارا',
    'boss'     => 'باس',
    'hermes'   => 'هرمس',
    'cacharel' => 'کاشارل',
    'davidoff' => 'دیویداف',
    'armani'   => 'آرمانی',
    'lancome'  => 'لانکوم',
    'monte'    => 'مونت بلانک',
    'lacoste'  => 'لاکست',
    'calvin'   => 'کلوین کلاین',
);

$brand_ids = array();
foreach ( $brands as $slug => $name ) {
    $term = lylyrose_ensure_term( 'pa_brand', $name, $slug );
    if ( $term ) {
        $brand_ids[ $slug ] = (int) $term['term_id'];
    }
}

// ---- 3. Create gender terms ----
$genders = array(
    'men'    => 'مردانه',
    'women'  => 'زنانه',
    'unisex' => 'یونیسکس',
);
$gender_ids = array();
foreach ( $genders as $slug => $name ) {
    $term = lylyrose_ensure_term( 'pa_gender', $name, $slug );
    if ( $term ) {
        $gender_ids[ $slug ] = (int) $term['term_id'];
    }
}

// ---- 4. Concentration terms ----
$concs = array(
    'edp'          => 'ادو پرفیوم',
    'edt'          => 'ادو تویلت',
    'perfume-oil'  => 'عطر روغنی',
    'parfum'       => 'پرفیوم',
);
$conc_ids = array();
foreach ( $concs as $slug => $name ) {
    $term = lylyrose_ensure_term( 'pa_concentration', $name, $slug );
    if ( $term ) {
        $conc_ids[ $slug ] = (int) $term['term_id'];
    }
}

// ---- 5. Volume terms ----
$vols = array(
    '30'  => '۳۰ میل',
    '50'  => '۵۰ میل',
    '75'  => '۷۵ میل',
    '100' => '۱۰۰ میل',
    '10'  => '۱۰ میل',
);
$vol_ids = array();
foreach ( $vols as $slug => $name ) {
    $term = lylyrose_ensure_term( 'pa_volume', $name, $slug );
    if ( $term ) {
        $vol_ids[ $slug ] = (int) $term['term_id'];
    }
}

echo "Categories: " . count( $cat_ids ) . ", Brands: " . count( $brand_ids ) . ", Genders: " . count( $gender_ids ) . "\n";

// ---- 6. Define sample products ----
$products = array(
    array(
        'name' => 'ادو پرفیوم شنل شماره ۵',
        'sku'  => 'CHANEL-5-50',
        'brand' => 'chanel',
        'gender' => 'women',
        'concentration' => 'parfum',
        'volume' => '50',
        'categories' => array( 'women', 'eau-de-parfum', 'perfume' ),
        'price' => 8500000,
        'sale'  => 7950000,
        'stock' => 25,
        'status' => 'publish',
        'desc'  => 'عطر کلاسیک شنل شماره ۵، یکی از معروف‌ترین عطرهای جهان با رایحه گل‌های سفید، که توسط Ernest Beaux در ۱۹۲۱ خلق شد. ادو پرفیوم با ماندگاری طولانی برای استفاده روزانه و مجالس.',
        'short' => 'عطر زنانه معروف و کلاسیک با رایحه گل‌های سفید',
        'notes' => array(
            'top'   => 'آلدهید، برگاموت، لیمو',
            'heart' => 'گل یاس، گل رز، زنبق',
            'base'  => 'سدر، چوب صندل، عنبر',
        ),
    ),
    array(
        'name' => 'ادو تویلت هرمس ترویل وه (سایه بازی)',
        'sku'  => 'HERMES-TD-50',
        'brand' => 'hermes',
        'gender' => 'men',
        'concentration' => 'edt',
        'volume' => '50',
        'categories' => array( 'men', 'eau-de-toilette', 'perfume' ),
        'price' => 6800000,
        'sale'  => 0,
        'stock' => 12,
        'status' => 'publish',
        'desc'  => 'رایحه چوبی و پیچیده هرمس. از ترکیب سدر، شمعدانی و برگاموت، عطری مردانه و خاص برای شب‌های رسمی.',
        'short' => 'عطر چوبی و مردانه هرمس',
        'notes' => array(
            'top'   => 'برگاموت، سماق',
            'heart' => 'شمعدانی، نعناع',
            'base'  => 'سدر، عنبر، خزه بلوط',
        ),
    ),
    array(
        'name' => 'ادو تویلت دیور ساواج',
        'sku'  => 'DIOR-SAUVAGE-100',
        'brand' => 'dior',
        'gender' => 'men',
        'concentration' => 'edt',
        'volume' => '100',
        'categories' => array( 'men', 'eau-de-toilette', 'perfume' ),
        'price' => 9200000,
        'sale'  => 8600000,
        'stock' => 40,
        'status' => 'publish',
        'desc'  => 'ساواج دیور، عطری مردانه و مدرن با رایحه تند و خنک وانیل، گریپ‌فروت و فلفل سیاه. ماندگاری عالی.',
        'short' => 'ادو تویلت مردانه محبوب با رایحه تند',
        'notes' => array(
            'top'   => 'کالابریان برگاموت، فلفل',
            'heart' => 'اسطوخودوس، گریپ‌فروت',
            'base'  => 'چوب صندل، وانیل، فلفل سیاه',
        ),
    ),
    array(
        'name' => 'عطر روغنی ورساچه اروس من',
        'sku'  => 'VERSACE-EROS-30',
        'brand' => 'versace',
        'gender' => 'men',
        'concentration' => 'perfume-oil',
        'volume' => '30',
        'categories' => array( 'men', 'perfume-oil', 'perfume' ),
        'price' => 4500000,
        'sale'  => 0,
        'stock' => 18,
        'status' => 'publish',
        'desc'  => 'عطر روغنی ورساچه اروس با رایحه‌های چوبی و معطر، مناسب مردان جوان و پرانرژی. ماندگاری بسیار بالا.',
        'short' => 'عطر روغنی مردانه ورساچه با ماندگاری بالا',
        'notes' => array(
            'top'   => 'نعناع، سیب سبز، لیمو',
            'heart' => 'چوب سدر، شمعدانی',
            'base'  => 'وانیل، عنبر، خزه بلوط',
        ),
    ),
    array(
        'name' => 'ادو پرفیوم زنانه گوچی بلوم',
        'sku'  => 'GUCCI-BLOOM-75',
        'brand' => 'gucci',
        'gender' => 'women',
        'concentration' => 'edp',
        'volume' => '75',
        'categories' => array( 'women', 'eau-de-parfum', 'perfume' ),
        'price' => 7800000,
        'sale'  => 0,
        'stock' => 8,
        'status' => 'publish',
        'desc'  => 'عطر گل‌های سفید گوچی بلوم با رایحه یاس، زنبق و ترنج. عطری شاداب و شاد برای روزهای بهاری.',
        'short' => 'رایحه گل‌های سفید و یاس',
        'notes' => array(
            'top'   => 'ترنج، برگاموت',
            'heart' => 'یاس، زنبق',
            'base'  => 'چوب صندل، مشک',
        ),
    ),
    array(
        'name' => 'ست هدیه عطر آرمانی اکوا دی جیو',
        'sku'  => 'ARMANI-ADG-GIFT',
        'brand' => 'armani',
        'gender' => 'men',
        'concentration' => 'edt',
        'volume' => '100',
        'categories' => array( 'gift-sets', 'men', 'perfume' ),
        'price' => 12500000,
        'sale'  => 0,
        'stock' => 5,
        'status' => 'publish',
        'desc'  => 'ست هدیه اکوا دی جیو آرمانی شامل ادو تویلت ۱۰۰ میل، ژل دوش و یک دفترچه یادداشت. انتخابی عالی برای هدیه.',
        'short' => 'ست لوکس هدیه با عطر مردانه آرمانی',
        'notes' => array(
            'top'   => 'لیمو، ترنج',
            'heart' => 'گل مریم، رزماری',
            'base'  => 'چوب سدر، اسطوخودوس',
        ),
    ),
    array(
        'name' => 'سمپل عطر لانکوم لاوی است',
        'sku'  => 'LANCOME-LVE-10',
        'brand' => 'lancome',
        'gender' => 'women',
        'concentration' => 'edp',
        'volume' => '10',
        'categories' => array( 'samples', 'women', 'perfume' ),
        'price' => 950000,
        'sale'  => 850000,
        'stock' => 100,
        'status' => 'publish',
        'desc'  => 'سمپل ۱۰ میل از عطر معروف لانکوم لاویست. برای تست عطر قبل از خرید. رایحه گل‌های سفید و بهار نارنج.',
        'short' => 'سمپل تست عطر لانکوم لاویست',
        'notes' => array(
            'top'   => 'بهار نارنج، گلابی',
            'heart' => 'گل رز، گل یاس',
            'base'  => 'وانیل، چوب پچولی',
        ),
    ),
    array(
        'name' => 'بادی اسپلش زارا وود',
        'sku'  => 'ZARA-WOOD-100',
        'brand' => 'zara',
        'gender' => 'unisex',
        'concentration' => 'edt',
        'volume' => '100',
        'categories' => array( 'body-spray', 'unisex', 'perfume' ),
        'price' => 1200000,
        'sale'  => 990000,
        'stock' => 60,
        'status' => 'publish',
        'desc'  => 'بادی اسپلش زارا با رایحه چوبی و گرم، مناسب استفاده روزانه و مرطوب کردن پوست. رایحه سبک و دلپذیر.',
        'short' => 'اسپری بدن با رایحه چوبی و ملایم',
        'notes' => array(
            'top'   => 'چوب سدر',
            'heart' => 'چوب صندل',
            'base'  => 'عنبر، وانیل',
        ),
    ),
    array(
        'name' => 'ادو پرفیوم کلین کلاین اترنیتی',
        'sku'  => 'CK-ETERNITY-50',
        'brand' => 'calvin',
        'gender' => 'unisex',
        'concentration' => 'edp',
        'volume' => '50',
        'categories' => array( 'unisex', 'eau-de-parfum', 'perfume' ),
        'price' => 5600000,
        'sale'  => 0,
        'stock' => 14,
        'status' => 'publish',
        'desc'  => 'عطر کلین کلاین اترنیتی با رایحه گل‌ها و چوب‌های گرم، مناسب برای افراد واقع‌بین و معتقد به عشق ابدی. .',
        'short' => 'عطر خنک و گل‌دار کلین کلاین',
        'notes' => array(
            'top'   => 'لیمو، برگاموت',
            'heart' => 'گل رز، یاس',
            'base'  => 'چوب سدر، ماس',
        ),
    ),
    array(
        'name' => 'ادو تویلت دیویداف کول واتر',
        'sku'  => 'DAVIDOFF-CW-100',
        'brand' => 'davidoff',
        'gender' => 'men',
        'concentration' => 'edt',
        'volume' => '100',
        'categories' => array( 'men', 'eau-de-toilette', 'perfume' ),
        'price' => 3900000,
        'sale'  => 0,
        'stock' => 30,
        'status' => 'publish',
        'desc'  => 'کول واتر دیویداف با رایحه خنک و تازه آب، نعناع و موز. عطری آرامش‌بخش برای استفاده روزمره و محل کار.',
        'short' => 'عطر خنک و تازه مردانه',
        'notes' => array(
            'top'   => 'بادرنجبویه، نعناع، خیار',
            'heart' => 'زنجبیل، گشنیز',
            'base'  => 'چوب سدر، خزه بلوط',
        ),
    ),
);

// ---- 7. Create products ----
foreach ( $products as $p ) {
    $product_id = wp_insert_post( array(
        'post_title'   => $p['name'],
        'post_content' => $p['desc'],
        'post_excerpt' => $p['short'],
        'post_status'  => $p['status'],
        'post_type'    => 'product',
    ) );

    if ( is_wp_error( $product_id ) || ! $product_id ) {
        echo "FAILED to create: {$p['name']}\n";
        continue;
    }

    // Product type
    wp_set_object_terms( $product_id, 'simple', 'product_type' );

    // Categories
    $terms = array();
    foreach ( $p['categories'] as $c ) {
        if ( isset( $cat_ids[ $c ] ) ) {
            $terms[] = $cat_ids[ $c ];
        }
    }
    if ( $terms ) {
        wp_set_object_terms( $product_id, $terms, 'product_cat' );
    }

    // Brand
    if ( isset( $brand_ids[ $p['brand'] ] ) ) {
        wp_set_object_terms( $product_id, $brand_ids[ $p['brand'] ], 'pa_brand' );
    }
    // Gender
    if ( isset( $gender_ids[ $p['gender'] ] ) ) {
        wp_set_object_terms( $product_id, $gender_ids[ $p['gender'] ], 'pa_gender' );
    }
    // Concentration
    if ( isset( $conc_ids[ $p['concentration'] ] ) ) {
        wp_set_object_terms( $product_id, $conc_ids[ $p['concentration'] ], 'pa_concentration' );
    }
    // Volume
    if ( isset( $vol_ids[ $p['volume'] ] ) ) {
        wp_set_object_terms( $product_id, $vol_ids[ $p['volume'] ], 'pa_volume' );
    }

    // Product meta
    update_post_meta( $product_id, '_regular_price', $p['price'] );
    update_post_meta( $product_id, '_price', $p['sale'] ? $p['sale'] : $p['price'] );
    if ( $p['sale'] ) {
        update_post_meta( $product_id, '_sale_price', $p['sale'] );
    }
    update_post_meta( $product_id, '_sku', $p['sku'] );
    update_post_meta( $product_id, '_stock', $p['stock'] );
    update_post_meta( $product_id, '_stock_status', $p['stock'] > 0 ? 'instock' : 'outofstock' );
    update_post_meta( $product_id, '_manage_stock', 'yes' );
    update_post_meta( $product_id, '_visibility', 'visible' );
    update_post_meta( $product_id, '_downloadable', 'no' );
    update_post_meta( $product_id, '_virtual', 'no' );
    update_post_meta( $product_id, '_sold_individually', 'yes' );

    // Notes
    update_post_meta( $product_id, '_top_notes', $p['notes']['top'] );
    update_post_meta( $product_id, '_heart_notes', $p['notes']['heart'] );
    update_post_meta( $product_id, '_base_notes', $p['notes']['base'] );

    // Longevity / sillage
    update_post_meta( $product_id, '_longevity', 'بلند' );
    update_post_meta( $product_id, '_sillage', 'متوسط' );

    // Product type flag for WooCommerce
    wp_set_object_terms( $product_id, 'simple', 'product_type' );

    // Mark on-sale badges (timestamps)
    if ( $p['sale'] ) {
        update_post_meta( $product_id, '_sale_price_dates_from', '' );
        update_post_meta( $product_id, '_sale_price_dates_to', '' );
    }

    echo "Created: {$p['name']} (ID: {$product_id})\n";
}

echo "\nDone! " . count( $products ) . " products created.\n";