<?php
/**
 * Shared helper functions: URLs, escaping, money, auth, cart, CSRF, flash.
 */
require_once __DIR__ . '/../config/database.php';

/* ------------------------------------------------------------------ *
 *  URLs & output
 * ------------------------------------------------------------------ */

/** Build an absolute-path URL relative to the app base. */
function url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

/** Escape HTML output. */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Absolute URL (scheme + host) for canonical / OpenGraph tags. */
function absolute_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . url($path);
}

/** The URL of the current request (used as the default canonical). */
function current_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri    = $_SERVER['REQUEST_URI'] ?? '/';
    return $scheme . '://' . $host . $uri;
}

/** Format a price with the site currency. */
function money($amount): string
{
    return CURRENCY . number_format((float)$amount, 2);
}

/** Redirect and stop execution. */
function redirect(string $path): void
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

/* ------------------------------------------------------------------ *
 *  Flash messages
 * ------------------------------------------------------------------ */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ------------------------------------------------------------------ *
 *  CSRF protection
 * ------------------------------------------------------------------ */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/* ------------------------------------------------------------------ *
 *  Authentication
 * ------------------------------------------------------------------ */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT id, name, email, phone, role, created_at FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && ($u['role'] ?? 'customer') === 'admin';
}

/** Block access to admin pages for non-admins. */
function require_admin(): void
{
    if (!is_admin()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? url('admin/dashboard.php');
        set_flash('error', 'Administrator access required. Please sign in.');
        redirect('admin/login.php');
    }
}

function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? url('account/dashboard.php');
        set_flash('info', 'Please sign in to continue.');
        redirect('account/login.php');
    }
}

/* ------------------------------------------------------------------ *
 *  Products & categories
 * ------------------------------------------------------------------ */
function all_categories(): array
{
    return db()->query('SELECT * FROM categories ORDER BY gender, name')->fetchAll();
}

function categories_by_gender(string $gender): array
{
    $stmt = db()->prepare('SELECT * FROM categories WHERE gender = ? ORDER BY name');
    $stmt->execute([$gender]);
    return $stmt->fetchAll();
}

function get_product(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_product_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug
                           FROM products p JOIN categories c ON c.id = p.category_id
                           WHERE p.slug = ? AND p.is_active = 1');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

/** Effective selling price (sale price when present). */
function product_price(array $p): float
{
    return (float)($p['sale_price'] !== null && $p['sale_price'] > 0 ? $p['sale_price'] : $p['price']);
}

/** Percentage saved, or 0 when there is no discount. */
function discount_percent(array $p): int
{
    if ($p['sale_price'] !== null && (float)$p['price'] > 0 && (float)$p['sale_price'] < (float)$p['price']) {
        return (int)round((1 - (float)$p['sale_price'] / (float)$p['price']) * 100);
    }
    return 0;
}

/* ------------------------------------------------------------------ *
 *  Product variants (sizes & colours)
 * ------------------------------------------------------------------ */

/** Split a comma separated column into a clean list. */
function csv_list(?string $value): array
{
    if ($value === null || trim($value) === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $value)), fn($v) => $v !== ''));
}

/** Available sizes for a product (empty when the product has none). */
function product_sizes(array $p): array
{
    return csv_list($p['sizes'] ?? null);
}

/** Available colours for a product (empty when the product has none). */
function product_colors(array $p): array
{
    return csv_list($p['colors'] ?? null);
}

function product_has_variants(array $p): bool
{
    return product_sizes($p) || product_colors($p);
}

/** A hex swatch for a colour name (falls back to a neutral grey). */
function color_hex(string $name): string
{
    $map = [
        'black' => '#111827', 'white' => '#ffffff', 'navy' => '#1e3a5f', 'grey' => '#9ca3af',
        'gray' => '#9ca3af', 'beige' => '#e7d8c9', 'cream' => '#f5f0e6', 'brown' => '#8b5e3c',
        'tan' => '#d2b48c', 'red' => '#dc2626', 'maroon' => '#7f1d1d', 'blue' => '#2563eb',
        'green' => '#16a34a', 'emerald' => '#10b981', 'olive' => '#6b7a3a', 'pink' => '#ec4899',
        'yellow' => '#facc15', 'purple' => '#7c3aed', 'orange' => '#f97316',
    ];
    return $map[strtolower(trim($name))] ?? '#d1d5db';
}

/** Resolve a stored image path to a usable URL (remote URLs pass through). */
function image_url(?string $path): string
{
    $path = (string)$path;
    if ($path === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#', $path) || strpos($path, 'data:') === 0) {
        return $path;
    }
    return url($path);
}

/** Per-colour images for a product as [colour => [image, image, ...]]. */
function product_colour_images(int $productId): array
{
    $stmt = db()->prepare('SELECT color, image FROM product_images
                           WHERE product_id = ? ORDER BY sort_order, id');
    $stmt->execute([$productId]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        if ($row['color'] !== null && $row['color'] !== '') {
            $map[$row['color']][] = $row['image'];
        }
    }
    return $map;
}

/** All image rows for a product (ordered). */
function product_image_rows(int $productId): array
{
    $stmt = db()->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

/** General (not colour-specific) gallery images for a product. */
function product_gallery_images(int $productId): array
{
    $stmt = db()->prepare("SELECT image FROM product_images
                           WHERE product_id = ? AND (color IS NULL OR color = '')
                           ORDER BY sort_order, id");
    $stmt->execute([$productId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[] = $row['image'];
    }
    return $out;
}

/** Build a URL-safe slug from any text. */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('#[^a-z0-9]+#', '-', $text);
    return trim((string)$text, '-');
}

/* ------------------------------------------------------------------ *
 *  Image uploads (admin)
 * ------------------------------------------------------------------ */

/** Save an uploaded image and return its path relative to the project root. */
function save_upload(array $file, string $subdir = 'uploads/products'): ?string
{
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return null;
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime    = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        return null;
    }

    $dir = ROOT_PATH . '/' . trim($subdir, '/');
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }

    $name = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return null;
    }
    return trim($subdir, '/') . '/' . $name;
}

/** Extract a single file entry from a multi-file input array. */
function upload_entry(array $files, int $index): array
{
    return [
        'name'     => $files['name'][$index] ?? '',
        'type'     => $files['type'][$index] ?? '',
        'tmp_name' => $files['tmp_name'][$index] ?? '',
        'error'    => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
        'size'     => $files['size'][$index] ?? 0,
    ];
}

/** Turn submitted colour names into a clean comma-separated string. */
function clean_csv_field(?string $raw, int $max = 12): string
{
    $parts = array_filter(array_map('trim', explode(',', (string)$raw)), fn($v) => $v !== '');
    $parts = array_values(array_unique($parts));
    return implode(',', array_slice($parts, 0, $max));
}

/* ------------------------------------------------------------------ *
 *  Product import — read product data from a remote product page
 * ------------------------------------------------------------------ */

/** Fetch a remote page's HTML (null on failure). */
function fetch_remote_html(string $url, int $timeout = 15): ?string
{
    if (!function_exists('curl_init') || !preg_match('#^https?://#i', $url)) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; StyleHubImporter/1.0)',
        CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml'],
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $code >= 400 || $body === '') {
        return null;
    }
    return (string)$body;
}

/** Recursively collect schema.org Product nodes from decoded JSON-LD. */
function jsonld_product_nodes($node): array
{
    $found = [];
    if (!is_array($node)) {
        return $found;
    }
    if (isset($node['@type'])) {
        foreach ((array)$node['@type'] as $type) {
            if (strtolower((string)$type) === 'product') {
                $found[] = $node;
                break;
            }
        }
    }
    foreach ($node as $value) {
        if (is_array($value)) {
            $found = array_merge($found, jsonld_product_nodes($value));
        }
    }
    return $found;
}

/** Merge one schema.org Product node into the result array. */
function merge_ld_product(array &$out, array $p): void
{
    if ($out['name'] === '' && !empty($p['name'])) {
        $out['name'] = (string)$p['name'];
    }
    if ($out['description'] === '' && !empty($p['description'])) {
        $out['description'] = trim(strip_tags((string)$p['description']));
    }
    if ($out['brand'] === '' && !empty($p['brand'])) {
        $b = $p['brand'];
        $out['brand'] = is_array($b) ? (string)($b['name'] ?? '') : (string)$b;
    }

    foreach (['color', 'colour'] as $key) {
        if (!empty($p[$key])) {
            foreach ((array)$p[$key] as $c) {
                if (is_string($c) && trim($c) !== '') {
                    $out['colors'][] = trim($c);
                }
            }
        }
    }
    if (!empty($p['size'])) {
        foreach ((array)$p['size'] as $s) {
            if (is_string($s) && trim($s) !== '') {
                $out['sizes'][] = trim($s);
            }
        }
    }
    if (!empty($p['image'])) {
        foreach ((array)$p['image'] as $img) {
            if (is_array($img)) {
                $img = $img['url'] ?? ($img['contentUrl'] ?? '');
            }
            $img = trim((string)$img);
            if ($img !== '' && preg_match('#^https?://#i', $img)) {
                $out['images'][] = $img;
            }
        }
    }

    /* Offers give the price (range → list price + discounted price). */
    if (!empty($p['offers'])) {
        $offers = $p['offers'];
        $list   = isset($offers['@type']) || isset($offers['price']) ? [$offers] : (array)$offers;
        $prices = [];
        foreach ($list as $o) {
            if (!is_array($o)) {
                continue;
            }
            foreach (['price', 'lowPrice', 'highPrice'] as $pk) {
                if (isset($o[$pk]) && is_numeric(str_replace(',', '', (string)$o[$pk]))) {
                    $prices[] = (float)str_replace(',', '', (string)$o[$pk]);
                }
            }
            if ($out['currency'] === '' && !empty($o['priceCurrency'])) {
                $out['currency'] = (string)$o['priceCurrency'];
            }
        }
        if ($prices) {
            $max = max($prices);
            $min = min($prices);
            if ($out['price'] === '') {
                $out['price'] = (string)$max;
            }
            if ($max > $min && $out['sale_price'] === '') {
                $out['sale_price'] = (string)$min;
            }
        }
    }

    if (!empty($p['hasVariant'])) {
        foreach ((array)$p['hasVariant'] as $variant) {
            if (is_array($variant)) {
                merge_ld_product($out, $variant);
            }
        }
    }
}

/** Read colour / size options from the page's <select> menus. */
function detect_select_variants(string $html, array &$out): void
{
    if (!class_exists('DOMDocument')) {
        return;
    }
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();

    foreach ($dom->getElementsByTagName('select') as $select) {
        $name = strtolower(implode(' ', [
            $select->getAttribute('name'), $select->getAttribute('id'),
            $select->getAttribute('aria-label'), $select->getAttribute('data-name'),
        ]));
        $options = [];
        foreach ($select->getElementsByTagName('option') as $option) {
            $text = trim(preg_replace('/\s+/', ' ', $option->textContent));
            if ($text !== '' && !preg_match('/^(select|choose|please|--)/i', $text)) {
                $options[] = $text;
            }
        }
        if (!$options) {
            continue;
        }
        if (preg_match('/colou?r/', $name)) {
            $out['colors'] = array_merge($out['colors'], $options);
        } elseif (preg_match('/size/', $name)) {
            $out['sizes'] = array_merge($out['sizes'], $options);
        }
    }
}

/**
 * Fetch a product page and extract product data for the admin form:
 * name, description, price, discount, colours, sizes and image list.
 * Returns null when the page cannot be read.
 */
function scrape_product_url(string $url): ?array
{
    $html = fetch_remote_html($url);
    if ($html === null) {
        return null;
    }

    $out = [
        'name' => '', 'description' => '', 'brand' => '',
        'price' => '', 'sale_price' => '', 'currency' => '',
        'colors' => [], 'sizes' => [], 'images' => [],
    ];

    /* 1) JSON-LD structured data (most reliable). */
    if (preg_match_all('#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches)) {
        foreach ($matches[1] as $json) {
            $data = json_decode(trim($json), true);
            if (!is_array($data)) {
                continue;
            }
            foreach (jsonld_product_nodes($data) as $product) {
                merge_ld_product($out, $product);
            }
        }
    }

    /* 2) OpenGraph / meta fallbacks. */
    $meta = function (string $prop) use ($html): string {
        $p = preg_quote($prop, '#');
        if (preg_match('#<meta[^>]+(?:property|name)=["\']' . $p . '["\'][^>]*content=["\']([^"\']*)["\']#i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES);
        }
        if (preg_match('#<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']' . $p . '["\']#i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES);
        }
        return '';
    };

    if ($out['name'] === '') {
        $out['name'] = $meta('og:title');
        if ($out['name'] === '' && preg_match('#<title[^>]*>(.*?)</title>#is', $html, $t)) {
            $out['name'] = trim(html_entity_decode(strip_tags($t[1]), ENT_QUOTES));
        }
    }
    if ($out['description'] === '') {
        $out['description'] = $meta('og:description') ?: $meta('description');
    }
    if ($out['price'] === '') {
        $out['price'] = $meta('product:price:amount') ?: ($meta('og:price:amount') ?: $meta('price'));
    }
    if ($out['currency'] === '') {
        $out['currency'] = $meta('product:price:currency') ?: $meta('og:price:currency');
    }
    if (!$out['images']) {
        $ogImg = $meta('og:image');
        if ($ogImg !== '') {
            $out['images'][] = $ogImg;
        }
    }

    /* 3) Variant options rendered as <select> menus. */
    detect_select_variants($html, $out);

    /* Clean up. */
    $out['name']        = trim(preg_replace('/\s+/', ' ', $out['name']));
    $out['description'] = trim(preg_replace('/\s+/', ' ', $out['description']));
    $out['price']       = ($out['price'] !== '' && is_numeric(preg_replace('/[^0-9.]/', '', (string)$out['price'])))
        ? (string)round((float)preg_replace('/[^0-9.]/', '', (string)$out['price']), 2) : '';
    $out['sale_price']  = ($out['sale_price'] !== '') ? (string)round((float)$out['sale_price'], 2) : '';
    $out['images']      = array_values(array_unique($out['images']));
    $out['image_count'] = count($out['images']);
    $out['colors']      = array_values(array_unique(array_filter($out['colors'], fn($v) => strlen($v) <= 40)));
    $out['sizes']       = array_values(array_unique(array_filter($out['sizes'], fn($v) => strlen($v) <= 20)));

    return $out;
}

/* ------------------------------------------------------------------ *
 *  Cart
 *  Stored in the session keyed by a variant key so the same product can
 *  sit in the cart in several sizes / colours:
 *      $_SESSION['cart']['12|M|Black'] = ['id'=>12,'size'=>'M','color'=>'Black','qty'=>2]
 * ------------------------------------------------------------------ */
function cart(): array
{
    return $_SESSION['cart'] ?? [];
}

/** Build the unique key for a product variant. */
function cart_key(int $productId, ?string $size, ?string $color): string
{
    return $productId . '|' . ($size ?? '') . '|' . ($color ?? '');
}

/**
 * Add a product variant to the cart.
 * Returns false when a required size / colour is missing or invalid.
 */
function cart_add(int $productId, int $qty = 1, ?string $size = null, ?string $color = null): bool
{
    $product = get_product($productId);
    if (!$product) {
        return false;
    }

    $sizes  = product_sizes($product);
    $colors = product_colors($product);

    $size  = $sizes  ? (in_array((string)$size, $sizes, true) ? (string)$size : null) : '';
    $color = $colors ? (in_array((string)$color, $colors, true) ? (string)$color : null) : '';

    if ($sizes && $size === null)   { return false; }   // size selection required
    if ($colors && $color === null) { return false; }   // colour selection required

    $qty = max(1, $qty);
    $key = cart_key($productId, $size, $color);
    $current = (int)($_SESSION['cart'][$key]['qty'] ?? 0);
    $_SESSION['cart'][$key] = [
        'id'    => $productId,
        'size'  => $size,
        'color' => $color,
        'qty'   => min($current + $qty, max(1, (int)$product['stock'])),
    ];
    return true;
}

/** Change the quantity of an existing cart line (identified by its key). */
function cart_set(string $key, int $qty): void
{
    if (!isset($_SESSION['cart'][$key])) {
        return;
    }
    if ($qty <= 0) {
        cart_remove($key);
        return;
    }
    $product = get_product((int)$_SESSION['cart'][$key]['id']);
    if (!$product) {
        cart_remove($key);
        return;
    }
    $_SESSION['cart'][$key]['qty'] = min($qty, max(1, (int)$product['stock']));
}

function cart_remove(string $key): void
{
    unset($_SESSION['cart'][$key]);
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
}

/** Number of individual units in the cart. */
function cart_count(): int
{
    return (int)array_sum(array_column(cart(), 'qty'));
}

/** Cart contents with product data, chosen variants, line totals and a subtotal. */
function cart_items(): array
{
    $items = [];
    $subtotal = 0.0;
    foreach (cart() as $key => $entry) {
        $p = get_product((int)$entry['id']);
        if (!$p) {
            continue;
        }
        $qty  = (int)$entry['qty'];
        $line = product_price($p) * $qty;
        $subtotal += $line;
        $items[] = [
            'key'        => $key,
            'product'    => $p,
            'size'       => $entry['size'] ?? '',
            'color'      => $entry['color'] ?? '',
            'qty'        => $qty,
            'line_total' => $line,
        ];
    }
    return ['items' => $items, 'subtotal' => $subtotal];
}

/* ------------------------------------------------------------------ *
 *  Orders
 * ------------------------------------------------------------------ */
function shipping_fee(float $subtotal): float
{
    if ($subtotal <= 0 || $subtotal >= FREE_SHIPPING_OVER) {
        return 0.0;
    }
    return (float)SHIPPING_FLAT;
}

function generate_order_number(): string
{
    return 'SH-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function order_status_badge(string $status): string
{
    $map = [
        'pending'    => 'badge-warning',
        'processing' => 'badge-info',
        'shipped'    => 'badge-info',
        'delivered'  => 'badge-success',
        'cancelled'  => 'badge-danger',
    ];
    return '<span class="badge ' . ($map[$status] ?? 'badge-muted') . '">' . e(ucfirst($status)) . '</span>';
}

/* ------------------------------------------------------------------ *
 *  Reviews & ratings
 * ------------------------------------------------------------------ */

/** Approved-review totals for every product (loaded once per request). */
function product_rating_map(): array
{
    static $map = null;
    if ($map === null) {
        $map  = [];
        $rows = db()->query("SELECT product_id, COUNT(*) AS cnt, AVG(rating) AS avg
                             FROM reviews WHERE status = 'approved' GROUP BY product_id")->fetchAll();
        foreach ($rows as $r) {
            $map[(int)$r['product_id']] = ['count' => (int)$r['cnt'], 'avg' => round((float)$r['avg'], 1)];
        }
    }
    return $map;
}

/** ['count' => int, 'avg' => float] for a product. */
function product_rating(int $productId): array
{
    return product_rating_map()[$productId] ?? ['count' => 0, 'avg' => 0.0];
}

/** Review rows for a product (approved only unless $all is true). */
function product_reviews(int $productId, bool $all = false): array
{
    $sql = 'SELECT * FROM reviews WHERE product_id = ?'
         . ($all ? '' : " AND status = 'approved'")
         . ' ORDER BY created_at DESC, id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

/** Star markup for a rating out of 5 (rounded to the nearest whole star). */
function star_html(float $rating): string
{
    $whole = (int)round($rating);
    $out   = '<span class="stars" aria-label="' . number_format($rating, 1) . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<span class="star' . ($i <= $whole ? ' is-on' : '') . '">&#9733;</span>';
    }
    return $out . '</span>';
}

/** Has this user bought (and paid for) the product? */
function user_purchased_product(int $userId, int $productId): bool
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM order_items i
                           JOIN orders o ON o.id = i.order_id
                           WHERE o.user_id = ? AND i.product_id = ? AND o.payment_status = 'paid'");
    $stmt->execute([$userId, $productId]);
    return (int)$stmt->fetchColumn() > 0;
}

/* ------------------------------------------------------------------ *
 *  Wishlist (saved for later — requires an account)
 * ------------------------------------------------------------------ */
function wishlist_ids(): array
{
    static $ids = null;
    if ($ids !== null) {
        return $ids;
    }
    $ids = [];
    if (is_logged_in()) {
        $stmt = db()->prepare('SELECT product_id FROM wishlist WHERE user_id = ?');
        $stmt->execute([(int)current_user()['id']]);
        foreach ($stmt->fetchAll() as $row) {
            $ids[] = (int)$row['product_id'];
        }
    }
    return $ids;
}

function wishlist_has(int $productId): bool
{
    return in_array($productId, wishlist_ids(), true);
}

function wishlist_count(): int
{
    return count(wishlist_ids());
}

/** Toggle a product in the wishlist. Returns true when it is now saved. */
function wishlist_toggle(int $productId): bool
{
    $userId = (int)(current_user()['id'] ?? 0);
    if (!$userId) {
        return false;
    }
    if (wishlist_has($productId)) {
        db()->prepare('DELETE FROM wishlist WHERE user_id = ? AND product_id = ?')->execute([$userId, $productId]);
        return false;
    }
    db()->prepare('INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?,?)')->execute([$userId, $productId]);
    return true;
}

/** Saved products (active only). */
function wishlist_items(): array
{
    if (!is_logged_in()) {
        return [];
    }
    $stmt = db()->prepare('SELECT p.*, c.name AS category_name
                           FROM wishlist w
                           JOIN products p ON p.id = w.product_id
                           LEFT JOIN categories c ON c.id = p.category_id
                           WHERE w.user_id = ? AND p.is_active = 1
                           ORDER BY w.created_at DESC');
    $stmt->execute([(int)current_user()['id']]);
    return $stmt->fetchAll();
}

/* ------------------------------------------------------------------ *
 *  Coupons
 * ------------------------------------------------------------------ */

/** Find a usable, active coupon by code (null when missing / expired / used up). */
function find_coupon(string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM coupons WHERE code = ?');
    $stmt->execute([$code]);
    $c = $stmt->fetch();
    if (!$c) {
        return null;
    }
    $now = time();
    if (!$c['is_active']) {
        return null;
    }
    if (!empty($c['starts_at']) && strtotime($c['starts_at']) > $now) {
        return null;
    }
    if (!empty($c['expires_at']) && strtotime($c['expires_at']) < $now) {
        return null;
    }
    if ((int)$c['max_uses'] > 0 && (int)$c['used_count'] >= (int)$c['max_uses']) {
        return null;
    }
    return $c;
}

/** Money amount taken off for a coupon on the given subtotal. */
function coupon_discount(array $coupon, float $subtotal): float
{
    if ($subtotal < (float)$coupon['min_order']) {
        return 0.0;
    }
    $value = (float)$coupon['value'];
    $off   = $coupon['type'] === 'percent' ? $subtotal * $value / 100 : $value;
    return round(min($off, $subtotal), 2);
}

/**
 * Validate a coupon code against a subtotal.
 * Returns ['ok'=>bool, 'coupon'=>?array, 'discount'=>float, 'error'=>string].
 */
function coupon_validate(string $code, float $subtotal): array
{
    $fail = ['ok' => false, 'coupon' => null, 'discount' => 0.0, 'error' => ''];
    $code = strtoupper(trim($code));
    if ($code === '') {
        $fail['error'] = 'Enter a coupon code.';
        return $fail;
    }
    $c = find_coupon($code);
    if (!$c) {
        $fail['error'] = 'That coupon code is invalid or has expired.';
        return $fail;
    }
    if ($subtotal < (float)$c['min_order']) {
        $fail['coupon']  = $c;
        $fail['error']   = 'This coupon needs a minimum order of ' . money($c['min_order']) . '.';
        return $fail;
    }
    return ['ok' => true, 'coupon' => $c, 'discount' => coupon_discount($c, $subtotal), 'error' => ''];
}

/* ------------------------------------------------------------------ *
 *  Order notifications & stock
 * ------------------------------------------------------------------ */

/**
 * Send a transactional email. A copy is always written to a local log file
 * so the whole flow can be inspected even without a mail server configured.
 */
function send_mail(string $to, string $subject, string $html): bool
{
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'Reply-To: ' . MAIL_FROM,
    ]);
    $sent = function_exists('mail') ? (bool)@mail($to, $subject, $html, $headers) : false;

    @file_put_contents(
        rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'stylehub_mail.log',
        '[' . date('c') . '] to=' . $to . ' | subject=' . $subject . PHP_EOL . $html . PHP_EOL . str_repeat('-', 70) . PHP_EOL,
        FILE_APPEND
    );
    return $sent;
}

/** Wrap email content in the shared branded shell. */
function email_layout(string $title, string $bodyHtml): string
{
    return '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #e6e8ec;border-radius:12px;overflow:hidden">'
        . '<div style="background:#111827;color:#fff;padding:18px 24px;font-size:18px;font-weight:700">' . e(SITE_NAME) . '</div>'
        . '<div style="padding:24px;color:#0f172a">'
        . '<h1 style="font-size:20px;margin:0 0 12px">' . e($title) . '</h1>' . $bodyHtml
        . '</div>'
        . '<div style="padding:16px 24px;background:#f7f8fa;color:#64748b;font-size:12px">You are receiving this email because you shopped with ' . e(SITE_NAME) . '.</div>'
        . '</div>';
}

/** Itemised order table for emails. */
function order_items_table(array $items): string
{
    $rows = '';
    foreach ($items as $it) {
        $variant = [];
        if (!empty($it['size']))  { $variant[] = 'Size: ' . $it['size']; }
        if (!empty($it['color'])) { $variant[] = 'Colour: ' . $it['color']; }
        $rows .= '<tr>'
              . '<td style="padding:8px 0;border-bottom:1px solid #eee">' . e($it['product_name'])
              . ($variant ? '<br><span style="color:#64748b;font-size:12px">' . e(implode(' · ', $variant)) . '</span>' : '')
              . ' <span style="color:#64748b">&times; ' . (int)$it['qty'] . '</span></td>'
              . '<td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;white-space:nowrap">' . e(money($it['subtotal'])) . '</td>'
              . '</tr>';
    }
    return '<table style="width:100%;border-collapse:collapse">' . $rows . '</table>';
}

/** "Order confirmed" email body. */
function order_confirmation_email(array $order, array $items): string
{
    $body = '<p>Thanks for your order, ' . e($order['shipping_name']) . '!</p>'
          . '<p>Your order <strong>' . e($order['order_number']) . '</strong> has been received. Here is a summary:</p>'
          . order_items_table($items)
          . '<p style="text-align:right;margin-top:12px">'
          . 'Subtotal: ' . e(money($order['subtotal'])) . '<br>'
          . 'Shipping: ' . ((float)$order['shipping'] > 0 ? e(money($order['shipping'])) : 'Free') . '<br>';
    if ((float)($order['discount'] ?? 0) > 0) {
        $body .= 'Discount' . (!empty($order['coupon_code']) ? ' (' . e($order['coupon_code']) . ')' : '')
               . ': &minus;' . e(money($order['discount'])) . '<br>';
    }
    $body .= '<strong style="font-size:16px">Total: ' . e(money($order['total'])) . '</strong></p>'
          . '<h3 style="font-size:15px;margin:18px 0 6px">Shipping to</h3>'
          . '<p style="color:#334155;margin:0">' . e($order['shipping_name']) . '<br>'
          . e($order['shipping_address']) . '<br>'
          . e($order['shipping_city']) . ($order['shipping_state'] ? ', ' . e($order['shipping_state']) : '')
          . ($order['shipping_zip'] ? ' - ' . e($order['shipping_zip']) : '') . '<br>'
          . e($order['shipping_country']) . '<br>' . e($order['shipping_phone']) . '</p>'
          . '<p style="margin-top:18px"><a href="' . e(url('account/orders.php')) . '" style="background:#e11d48;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none">View your order</a></p>';
    return email_layout('Order ' . $order['order_number'] . ' confirmed', $body);
}

/** "Order status changed" email body. */
function order_status_email(array $order): string
{
    $messages = [
        'pending'    => 'We have received your order and it is awaiting confirmation.',
        'processing' => 'Good news — your order is now being prepared.',
        'shipped'    => 'Your order has been shipped and is on its way.',
        'delivered'  => 'Your order has been delivered. We hope you love it!',
        'cancelled'  => 'Your order has been cancelled. If this was a mistake, please contact us.',
    ];
    $line = $messages[$order['status']] ?? ('Your order status is now ' . $order['status'] . '.');
    $body = '<p>' . e($line) . '</p>'
          . '<p>Order <strong>' . e($order['order_number']) . '</strong> — status: <strong>' . e(ucfirst($order['status'])) . '</strong></p>'
          . '<p><a href="' . e(url('account/orders.php')) . '" style="background:#e11d48;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none">Track your order</a></p>';
    return email_layout('Order update: ' . ucfirst($order['status']), $body);
}

/** Reduce product stock for every line in an order (never below zero). */
function order_decrement_stock(int $orderId): void
{
    $st = db()->prepare('SELECT product_id, qty FROM order_items WHERE order_id = ? AND product_id IS NOT NULL');
    $st->execute([$orderId]);
    $upd = db()->prepare('UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?');
    foreach ($st->fetchAll() as $row) {
        $upd->execute([(int)$row['qty'], (int)$row['product_id']]);
    }
}

/** Send the order-confirmation email for an order id. */
function send_order_confirmation(int $orderId): bool
{
    $st = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $st->execute([$orderId]);
    $order = $st->fetch();
    if (!$order) {
        return false;
    }
    $it = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $it->execute([$orderId]);
    return send_mail(
        (string)$order['shipping_email'],
        'Order ' . $order['order_number'] . ' confirmed — ' . SITE_NAME,
        order_confirmation_email($order, $it->fetchAll())
    );
}

/* ------------------------------------------------------------------ *
 *  UI partial
 * ------------------------------------------------------------------ */

/** Render a single product card (used on the home, shop and related grids). */
function render_product_card(array $p): string
{
    $link          = url('product.php?slug=' . urlencode($p['slug']));
    $off           = discount_percent($p);
    $inStock       = (int)$p['stock'] > 0;
    $hasVariants   = product_has_variants($p);

    ob_start(); ?>
    <?php $__rt = product_rating((int)$p['id']); $__wish = wishlist_has((int)$p['id']); ?>
    <article class="card">
        <div class="card__media">
            <a href="<?= e($link) ?>" aria-label="<?= e($p['name']) ?>">
                <img loading="lazy" src="<?= e(image_url($p['image'])) ?>" alt="<?= e($p['name']) ?>">
            </a>
            <div class="card__tags">
                <?php if ($off > 0): ?><span class="tag tag--sale">-<?= $off ?>%</span><?php endif; ?>
                <?php if (!$inStock): ?><span class="tag tag--out">Sold out</span><?php endif; ?>
            </div>
            <form class="card__wish" method="post" action="<?= e(url('wishlist.php')) ?>" data-wishlist>
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <input type="hidden" name="return" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
                <button type="submit" class="wish-btn<?= $__wish ? ' is-on' : '' ?>"
                        aria-label="Save to wishlist" aria-pressed="<?= $__wish ? 'true' : 'false' ?>">&#9829;</button>
            </form>
            <div class="card__quick">
                <?php if ($hasVariants): ?>
                    <a class="btn btn--light btn--block btn--sm<?= $inStock ? '' : ' is-disabled' ?>"
                       href="<?= e($link) ?>" <?= $inStock ? '' : 'aria-disabled="true" tabindex="-1"' ?>>
                        <?= $inStock ? 'Select options' : 'Sold out' ?>
                    </a>
                <?php else: ?>
                    <form method="post" action="<?= e(url('cart.php')) ?>" data-ajax-cart>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn--light btn--block btn--sm" type="submit" <?= $inStock ? '' : 'disabled' ?>>
                            <?= $inStock ? 'Add to cart' : 'Sold out' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="card__body">
            <span class="card__cat"><?= e($p['category_name'] ?? '') ?></span>
            <h3 class="card__title"><a href="<?= e($link) ?>"><?= e($p['name']) ?></a></h3>
            <?php if ($__rt['count'] > 0): ?>
                <div class="card__rating"><?= star_html($__rt['avg']) ?> <span class="muted">(<?= $__rt['count'] ?>)</span></div>
            <?php endif; ?>
            <div class="card__price">
                <span class="price-now"><?= money(product_price($p)) ?></span>
                <?php if ($off > 0): ?><span class="price-was"><?= money($p['price']) ?></span><?php endif; ?>
            </div>
        </div>
    </article>
    <?php
    return ob_get_clean();
}
