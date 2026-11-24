<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$user = current_user();

$stmt = db()->prepare('SELECT o.*, (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_count
                       FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC');
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

$pageTitle = 'My Orders';
require __DIR__ . '/../includes/header.php';
?>
<div class="container">
    <div class="breadcrumb"><a href="<?= e(url('account/dashboard.php')) ?>">My Account</a> / <span>Orders</span></div>

    <h1 style="margin-top:1rem">My Orders</h1>
    <?php require __DIR__ . '/../includes/account-nav.php'; ?>

    <?php if (!$orders): ?>
        <div class="empty">
            <h2>No orders yet</h2>
            <p>When you place an order it will appear here.</p>
            <a class="btn btn--accent" href="<?= e(url('shop.php')) ?>">Start shopping</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Order</th><th>Date</th><th>Items</th><th>Payment</th><th>Total</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><strong><?= e($o['order_number']) ?></strong></td>
                        <td><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
                        <td><?= (int)$o['item_count'] ?></td>
                        <td><?= e(strtoupper($o['payment_method'])) ?> <?= $o['payment_status'] === 'paid' ? '<span class="badge badge-success">Paid</span>' : '<span class="badge badge-warning">' . e(ucfirst($o['payment_status'])) . '</span>' ?></td>
                        <td><?= money($o['total']) ?></td>
                        <td><?= order_status_badge($o['status']) ?></td>
                        <td><a class="btn btn--outline btn--sm" href="<?= e(url('account/order-detail.php?id=' . (int)$o['id'])) ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
