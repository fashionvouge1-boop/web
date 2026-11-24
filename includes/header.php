<?php
require_once __DIR__ . '/functions.php';
$user        = current_user();
$__cartCount = cart_count();
$__wishCount = wishlist_count();
$__navCats   = all_categories();
$__pageTitle = $pageTitle ?? SITE_NAME;
$__desc      = $pageDescription ?? (SITE_TAGLINE . '. Shop curated fashion, footwear, beauty and accessories for him and her — fast delivery and easy returns.');
$__canonical = $canonical ?? current_url();
$__ogType    = $ogType ?? 'website';
$__noindex   = $noindex ?? false;
$__fullTitle = ($__pageTitle === SITE_NAME) ? SITE_NAME : ($__pageTitle . ' · ' . SITE_NAME);
$__jsonLd        = $pageJsonLd ?? null;
$__jsonLdDefault = [
    '@context' => 'https://schema.org',
    '@type'    => 'WebSite',
    'name'     => SITE_NAME,
    'url'      => absolute_url('index.php'),
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => absolute_url('shop.php') . '?q={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($__fullTitle) ?></title>
    <meta name="description" content="<?= e($__desc) ?>">
    <link rel="canonical" href="<?= e($__canonical) ?>">
    <?php if ($__noindex): ?><meta name="robots" content="noindex, follow"><?php endif; ?>

    <meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
    <meta property="og:type" content="<?= e($__ogType) ?>">
    <meta property="og:title" content="<?= e($__pageTitle) ?>">
    <meta property="og:description" content="<?= e($__desc) ?>">
    <meta property="og:url" content="<?= e($__canonical) ?>">
    <?php if (!empty($pageImage)): ?>
        <meta property="og:image" content="<?= e($pageImage) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($__pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($__desc) ?>">
    <?php if (!empty($pageImage)): ?><meta name="twitter:image" content="<?= e($pageImage) ?>"><?php endif; ?>

    <script type="application/ld+json"><?= json_encode($__jsonLdDefault, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php if ($__jsonLd): ?>
        <script type="application/ld+json"><?= is_array($__jsonLd) ? json_encode($__jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $__jsonLd ?></script>
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="<?= e($bodyClass ?? '') ?>">

<!-- Announcement bar -->
<div class="announce">
    <div class="container announce__inner">
        <span>Free shipping on orders over <?= money(FREE_SHIPPING_OVER) ?></span>
        <span class="announce__sep">|</span>
        <span>Easy 30-day returns</span>
    </div>
</div>

<!-- Header -->
<header class="site-header" id="siteHeader">
    <div class="container header__inner">
        <button class="icon-btn header__burger" id="navToggle" aria-label="Menu">&#9776;</button>

        <a class="logo" href="<?= e(url('index.php')) ?>">
            <span class="logo__mark">S</span>
            <span class="logo__text"><?= e(SITE_NAME) ?></span>
        </a>

        <nav class="main-nav" id="mainNav">
            <a href="<?= e(url('index.php')) ?>">Home</a>
            <a href="<?= e(url('shop.php')) ?>">Shop All</a>
            <a href="<?= e(url('shop.php?gender=women')) ?>">Women</a>
            <a href="<?= e(url('shop.php?gender=men')) ?>">Men</a>
            <div class="dropdown">
                <a href="<?= e(url('shop.php')) ?>" class="dropdown__toggle">Categories &#9662;</a>
                <div class="dropdown__menu">
                    <?php foreach ($__navCats as $c): ?>
                        <a href="<?= e(url('shop.php?category=' . $c['slug'])) ?>"><?= e($c['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </nav>

        <form class="header__search" action="<?= e(url('shop.php')) ?>" method="get">
            <input type="search" name="q" placeholder="Search products..." value="<?= e($_GET['q'] ?? '') ?>">
            <button type="submit" aria-label="Search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            </button>
        </form>

        <div class="header__actions">
            <div class="dropdown account-menu">
                <button class="icon-btn" aria-label="Account">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
                </button>
                <div class="dropdown__menu dropdown__menu--right">
                    <?php if ($user): ?>
                        <span class="dropdown__label">Hi, <?= e($user['name']) ?></span>
                        <?php if (is_admin()): ?>
                            <a href="<?= e(url('admin/dashboard.php')) ?>"><strong>Admin Panel</strong></a>
                        <?php endif; ?>
                        <a href="<?= e(url('account/dashboard.php')) ?>">My Account</a>
                        <a href="<?= e(url('account/orders.php')) ?>">My Orders</a>
                        <a href="<?= e(url('account/wishlist.php')) ?>">My Wishlist</a>
                        <a href="<?= e(url('account/logout.php')) ?>">Sign out</a>
                    <?php else: ?>
                        <a href="<?= e(url('account/login.php')) ?>">Sign in</a>
                        <a href="<?= e(url('account/register.php')) ?>">Create account</a>
                    <?php endif; ?>
                </div>
            </div>

            <a class="icon-btn wishlist-btn" href="<?= e(url('account/wishlist.php')) ?>" aria-label="Wishlist">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
                <span class="cart-count wish-count"<?= $__wishCount > 0 ? '' : ' style="display:none"' ?>><?= (int)$__wishCount ?></span>
            </a>

            <a class="icon-btn cart-btn" href="<?= e(url('cart.php')) ?>" aria-label="Cart">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M6 6 5 3H3"/></svg>
                <?php if ($__cartCount > 0): ?><span class="cart-count"><?= (int)$__cartCount ?></span><?php endif; ?>
            </a>
        </div>
    </div>
</header>

<main class="site-main">
<?php foreach (get_flashes() as $f): ?>
    <div class="container">
        <div class="alert alert--<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    </div>
<?php endforeach; ?>
