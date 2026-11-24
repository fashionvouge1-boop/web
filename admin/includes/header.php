<?php
/**
 * Admin layout — header.
 * Expects: $pageTitle, and optionally $adminNav (active nav key).
 */
require_once __DIR__ . '/../../includes/functions.php';

$__admin = current_user();
$__nav   = $adminNav ?? '';
$__title = $pageTitle ?? 'Admin';

$__links = [
    'dashboard'  => ['Dashboard',    'admin/dashboard.php',  '&#9632;'],
    'orders'     => ['Orders',       'admin/orders.php',     '&#128230;'],
    'customers'  => ['Customers',    'admin/customers.php',  '&#128100;'],
    'products'   => ['Products',     'admin/products.php',   '&#127890;'],
    'newproduct' => ['Add Product',  'admin/product-form.php', '&#10133;'],
    'categories' => ['Categories',   'admin/categories.php', '&#128193;'],
    'reviews'    => ['Reviews',      'admin/reviews.php',    '&#11088;'],
    'coupons'    => ['Coupons',      'admin/coupons.php',    '&#127991;'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($__title) ?> &middot; Admin &middot; <?= e(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
</head>
<body class="admin">
<div class="overlay" id="adminOverlay"></div>
<div class="admin-shell">
    <aside class="admin-side" id="adminSide">
        <a class="admin-brand" href="<?= e(url('admin/dashboard.php')) ?>">
            <span class="admin-brand__mark">S</span>
            <span><?= e(SITE_NAME) ?><small>Admin Panel</small></span>
        </a>
        <nav class="admin-nav">
            <span class="admin-nav__label">Overview</span>
            <?php foreach (['dashboard', 'orders', 'customers'] as $k): ?>
                <a class="<?= $__nav === $k ? 'is-active' : '' ?>" href="<?= e(url($__links[$k][1])) ?>">
                    <span class="ic"><?= $__links[$k][2] ?></span> <?= e($__links[$k][0]) ?>
                </a>
            <?php endforeach; ?>
            <span class="admin-nav__label">Catalogue</span>
            <?php foreach (['products', 'newproduct', 'categories', 'reviews', 'coupons'] as $k): ?>
                <a class="<?= $__nav === $k ? 'is-active' : '' ?>" href="<?= e(url($__links[$k][1])) ?>">
                    <span class="ic"><?= $__links[$k][2] ?></span> <?= e($__links[$k][0]) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-side__foot">
            <a href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">View store &#8599;</a>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-top">
            <button class="admin-burger" id="adminBurger" aria-label="Menu">&#9776;</button>
            <h1 class="admin-top__title"><?= e($__title) ?></h1>
            <div class="admin-top__user">
                <span>&#128100; <strong><?= e($__admin['name'] ?? 'Admin') ?></strong></span>
                <a href="<?= e(url('admin/logout.php')) ?>">Sign out</a>
            </div>
        </header>
        <main class="admin-content">
            <?php foreach (get_flashes() as $f): ?>
                <div class="alert alert--<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
