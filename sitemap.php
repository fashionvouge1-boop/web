<?php
/**
 * Dynamic XML sitemap — lists the store's public, indexable URLs.
 * Reference it from robots.txt as:  Sitemap: https://your-domain/sitemap.php
 */
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=UTF-8');

$urls = [];

$urls[] = ['loc' => absolute_url('index.php'), 'priority' => '1.0', 'changefreq' => 'daily'];
$urls[] = ['loc' => absolute_url('shop.php'),  'priority' => '0.9', 'changefreq' => 'daily'];
$urls[] = ['loc' => absolute_url('shop.php?gender=women'), 'priority' => '0.8', 'changefreq' => 'weekly'];
$urls[] = ['loc' => absolute_url('shop.php?gender=men'),   'priority' => '0.8', 'changefreq' => 'weekly'];

foreach (db()->query('SELECT slug FROM categories ORDER BY name')->fetchAll() as $c) {
    $urls[] = ['loc' => absolute_url('shop.php?category=' . urlencode($c['slug'])), 'priority' => '0.7', 'changefreq' => 'weekly'];
}

foreach (db()->query('SELECT slug, created_at FROM products WHERE is_active = 1 ORDER BY created_at DESC')->fetchAll() as $p) {
    $urls[] = [
        'loc'        => absolute_url('product.php?slug=' . urlencode($p['slug'])),
        'lastmod'    => date('Y-m-d', strtotime($p['created_at'])),
        'priority'   => '0.8',
        'changefreq' => 'weekly',
    ];
}

foreach (['about', 'contact', 'shipping-returns', 'privacy', 'terms'] as $slug) {
    $urls[] = ['loc' => absolute_url('page.php?slug=' . $slug), 'priority' => '0.3', 'changefreq' => 'yearly'];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo '    <loc>' . e($u['loc']) . "</loc>\n";
    if (!empty($u['lastmod'])) {
        echo '    <lastmod>' . e($u['lastmod']) . "</lastmod>\n";
    }
    if (!empty($u['changefreq'])) {
        echo '    <changefreq>' . e($u['changefreq']) . "</changefreq>\n";
    }
    if (!empty($u['priority'])) {
        echo '    <priority>' . e($u['priority']) . "</priority>\n";
    }
    echo "  </url>\n";
}
echo '</urlset>' . "\n";
