<?php
/**
 * Export real Digikala perfume products for WooCommerce import.
 *
 * CLI examples:
 *   php generate-products.php
 *   php generate-products.php --limit=10 --dry-run
 *   php generate-products.php --query=عطر --output=/tmp/products.csv
 *
 * This script only writes a CSV; it does not create or publish WordPress products.
 */

const DIGIKALA_API_URL = 'https://api.digikala.com/v1/search/';
const DEFAULT_QUERY = 'عطر';
const DEFAULT_LIMIT = 100;
const MAX_PAGES = 20;

$options = getopt('', ['limit::', 'query::', 'output::', 'dry-run']);
$limit = isset($options['limit']) ? max(1, min(100, (int) $options['limit'])) : DEFAULT_LIMIT;
$query = isset($options['query']) && trim($options['query']) !== '' ? trim($options['query']) : DEFAULT_QUERY;
$output = isset($options['output']) && trim($options['output']) !== ''
    ? $options['output']
    : __DIR__ . '/sample-products.csv';
$dry_run = array_key_exists('dry-run', $options);

function digikala_request(string $query, int $page): array
{
    $url = DIGIKALA_API_URL . '?' . http_build_query([
        'q' => $query,
        'page' => $page,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: AromaStoreProductExporter/1.0 (+https://aroma-store.local)',
        ],
    ]);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $error !== '') {
        throw new RuntimeException('Digikala request failed: ' . ($error ?: 'unknown cURL error'));
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("Digikala returned HTTP {$status}.");
    }

    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data['data']['products']) || !is_array($data['data']['products'])) {
        throw new RuntimeException('Unexpected response from Digikala search API.');
    }

    return $data['data'];
}

function product_image(array $product): string
{
    $images = $product['images'] ?? [];
    foreach (['main', 'webp', 'list'] as $key) {
        $image = $images[$key] ?? null;
        if (is_array($image)) {
            foreach (['webp_url', 'url'] as $url_key) {
                $url = $image[$url_key] ?? null;
                if (is_array($url) && isset($url[0]) && is_string($url[0])) {
                    return $url[0];
                }
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }
        }
        if (is_string($image) && $image !== '') {
            return $image;
        }
    }
    if (isset($images['url']) && is_string($images['url'])) {
        return $images['url'];
    }
    return '';
}

function product_price(array $product, string $key): int
{
    $variant = $product['default_variant'] ?? [];
    $price = $variant['price'][$key] ?? 0;
    return is_numeric($price) ? (int) $price : 0;
}

function product_url(array $product): string
{
    $url = $product['url'] ?? '';
    if (is_array($url)) {
        $url = $url['uri'] ?? '';
    }
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    return str_starts_with($url, 'http')
        ? $url
        : 'https://www.digikala.com' . (str_starts_with($url, '/') ? '' : '/') . $url;
}

function csv_row(array $row): string
{
    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, $row, ',', '"', '\\');
    rewind($handle);
    $line = stream_get_contents($handle);
    fclose($handle);
    return $line;
}

$products = [];
$seen_ids = [];
$page = 1;

try {
    while (count($products) < $limit && $page <= MAX_PAGES) {
        $data = digikala_request($query, $page);
        $page_products = $data['products'];
        if (!$page_products) {
            break;
        }

        foreach ($page_products as $product) {
            $id = (int) ($product['id'] ?? 0);
            $title = trim((string) ($product['title_fa'] ?? $product['title_en'] ?? ''));
            $url = product_url($product);
            $regular_price = product_price($product, 'rrp_price');
            $sale_price = product_price($product, 'selling_price');

            if (!$id || $title === '' || isset($seen_ids[$id]) || $url === '' || $sale_price <= 0) {
                continue;
            }

            if ($regular_price <= 0 || $regular_price < $sale_price) {
                $regular_price = $sale_price;
            }

            $seen_ids[$id] = true;
            $products[] = [
                'id' => $id,
                'sku' => 'DIGIKALA-' . $id,
                'name' => $title,
                'slug' => sanitize_slug($title . '-' . $id),
                'description' => $title . '؛ محصول موجود در دیجی‌کالا. پیش از انتشار، اطلاعات محصول و مجوز استفاده از محتوا را بررسی کنید.',
                'short_description' => 'خرید ' . $title,
                'category_tag' => 'عطر و ادکلن',
                'tags' => 'دیجی‌کالا,عطر,ادکلن',
                'regular_price' => $regular_price,
                'sale_price' => $sale_price < $regular_price ? $sale_price : '',
                'status' => 'draft',
                'published' => 0,
                'stock_status' => 'instock',
                'stock_quantity' => 10,
                'backorders' => 'no',
                'sold_individually' => 'no',
                'weight' => '',
                'length' => '',
                'width' => '',
                'height' => '',
                'ship_class' => '',
                'images' => product_image($product),
                'download_limit' => '',
                'download_expiry' => '',
                'product_url' => $url,
                'buy_now_url' => '',
            ];

            if (count($products) >= $limit) {
                break 2;
            }
        }
        $page++;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "ERROR: {$exception->getMessage()}\n");
    exit(1);
}

if (count($products) < $limit) {
    fwrite(STDERR, "WARNING: Digikala returned only " . count($products) . " usable products.\n");
}

$header = array_keys($products[0] ?? [
    'id', 'sku', 'name', 'slug', 'description', 'short_description', 'category_tag', 'tags',
    'regular_price', 'sale_price', 'status', 'published', 'stock_status', 'stock_quantity',
    'backorders', 'sold_individually', 'weight', 'length', 'width', 'height', 'ship_class',
    'images', 'download_limit', 'download_expiry', 'product_url', 'buy_now_url',
]);
$csv = "\xEF\xBB\xBF" . csv_row($header);
foreach ($products as $product) {
    $csv .= csv_row(array_map(static fn ($column) => $product[$column] ?? '', $header));
}

if (!$dry_run) {
    $directory = dirname($output);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        fwrite(STDERR, "ERROR: Could not create output directory: {$directory}\n");
        exit(1);
    }
    if (file_put_contents($output, $csv) === false) {
        fwrite(STDERR, "ERROR: Could not write output file: {$output}\n");
        exit(1);
    }
}

echo ($dry_run ? 'Dry run successful. ' : 'Generated CSV successfully. ')
    . 'Products: ' . count($products) . "\n";
if (!$dry_run) {
    echo "File: {$output}\n";
}

echo "First product: " . ($products[0]['name'] ?? 'none') . "\n";

function sanitize_slug(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
    return trim($value, '-') ?: 'product';
}
