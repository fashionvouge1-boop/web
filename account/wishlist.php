<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$items = wishlist_items();

$pageTitle = 'My Wishlist';
require __DIR__ . '/../includes/header.php';
?>
<div class="container">
    <div class="breadcrumb"><a href="<?= e(url('account/dashboard.php')) ?>">My Account</a> / <span>Wishlist</span></div>

    <h1 style="margin-top:1rem">My Wishlist</h1>
    <?php require __DIR__ . '/../includes/account-nav.php'; ?>

    <?php if (!$items): ?>
        <div class="empty">
            <h2>Your wishlist is empty</h2>
            <p>Tap the heart on any product to save it here for later.</p>
            <a class="btn btn--accent" href="<?= e(url('shop.php')) ?>">Browse products</a>
        </div>
    <?php else: ?>
        <p class="muted"><?= count($items) ?> saved item<?= count($items) === 1 ? '' : 's' ?>.</p>
        <div class="grid">
            <?php foreach ($items as $p): echo render_product_card($p); endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
