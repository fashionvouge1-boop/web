<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$user = current_user();

/* Aggregated stats */
$stmt = db()->prepare('SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS spent FROM orders WHERE user_id = ?');
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

$recentStmt = db()->prepare('SELECT o.*, (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_count
                             FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC LIMIT 5');
$recentStmt->execute([$user['id']]);
$recent = $recentStmt->fetchAll();

$pendingStmt = db()->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status IN ('pending','processing','shipped')");
$pendingStmt->execute([$user['id']]);
$pending = (int)$pendingStmt->fetchColumn();

$pageTitle = 'My Account';
require __DIR__ . '/../includes/header.php';
?>
<div class="container">
    <div class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> / <span>My Account</span></div>

    <h1 style="margin-top:1rem">Hello, <?= e($user['name']) ?> &#128075;</h1>
    <p class="muted">Manage your profile and keep track of your orders.</p>

    <?php require __DIR__ . '/../includes/account-nav.php'; ?>

    <div class="stat-grid">
        <div class="stat"><div class="stat__num"><?= (int)$stats['orders'] ?></div><div class="stat__label">Total orders</div></div>
        <div class="stat"><div class="stat__num"><?= $pending ?></div><div class="stat__label">Active orders</div></div>
        <div class="stat"><div class="stat__num"><?= money($stats['spent']) ?></div><div class="stat__label">Total spent</div></div>
        <div class="stat"><div class="stat__num"><?= cart_count() ?></div><div class="stat__label">Items in cart</div></div>
    </div>

    <div class="panel">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
            <h3 style="margin:0">Recent orders</h3>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('account/orders.php')) ?>">View all</a>
        </div>

        <?php if (!$recent): ?>
            <div class="empty" style="padding:2.5rem 1rem">
                <p>You haven't placed any orders yet.</p>
                <a class="btn btn--accent" href="<?= e(url('shop.php')) ?>">Start shopping</a>
            </div>
        <?php else: ?>
            <div class="table-wrap" style="border:0;margin-top:1rem">
                <table class="data-table">
                    <thead>
                        <tr><th>Order</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent as $o): ?>
                        <tr>
                            <td><strong><?= e($o['order_number']) ?></strong></td>
                            <td><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
                            <td><?= (int)$o['item_count'] ?></td>
                            <td><?= money($o['total']) ?></td>
                            <td><?= order_status_badge($o['status']) ?></td>
                            <td><a class="btn btn--ghost btn--sm" href="<?= e(url('account/order-detail.php?id=' . (int)$o['id'])) ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel" style="margin-top:1.4rem">
        <h3>Account details</h3>
        <div class="summary-row"><span>Name</span><span><?= e($user['name']) ?></span></div>
        <div class="summary-row"><span>Email</span><span><?= e($user['email']) ?></span></div>
        <div class="summary-row"><span>Phone</span><span><?= e($user['phone'] ?: '—') ?></span></div>
        <div class="summary-row"><span>Member since</span><span><?= e(date('d M Y', strtotime($user['created_at']))) ?></span></div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
