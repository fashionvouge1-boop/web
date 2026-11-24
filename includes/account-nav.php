<?php
/** Shared sub-navigation for the account area. */
$__current = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<div class="account-nav">
    <a href="<?= e(url('account/dashboard.php')) ?>" class="<?= $__current === 'dashboard.php' ? 'is-active' : '' ?>">Dashboard</a>
    <a href="<?= e(url('account/orders.php')) ?>" class="<?= in_array($__current, ['orders.php', 'order-detail.php'], true) ? 'is-active' : '' ?>">My Orders</a>
    <a href="<?= e(url('account/wishlist.php')) ?>" class="<?= $__current === 'wishlist.php' ? 'is-active' : '' ?>">Wishlist</a>
    <a href="<?= e(url('shop.php')) ?>">Continue shopping</a>
    <a href="<?= e(url('account/logout.php')) ?>">Sign out</a>
</div>
