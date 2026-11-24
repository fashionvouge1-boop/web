<?php
/** Admin — single customer profile and order history. */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$customer = $stmt->fetch();

$pageTitle = 'Customer Details';
$adminNav  = 'customers';
require __DIR__ . '/includes/header.php';

if (!$customer) {
    echo '<div class="empty"><h2>Customer not found</h2><p>That customer does not exist.</p>'
       . '<a class="btn btn--accent" href="' . e(url('admin/customers.php')) . '">Back to customers</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$oStmt = $pdo->prepare('SELECT o.*, (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_count
                        FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC');
$oStmt->execute([$customer['id']]);
$orders = $oStmt->fetchAll();

$spent = 0.0;
foreach ($orders as $o) {
    if ($o['status'] !== 'cancelled') {
        $spent += (float)$o['total'];
    }
}
?>
<div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1.2rem">
    <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/customers.php')) ?>">&larr; All customers</a>
    <h2 style="margin:0"><?= e($customer['name']) ?></h2>
    <?= $customer['role'] === 'admin' ? '<span class="badge badge-muted">Admin</span>' : '<span class="badge badge-info">Customer</span>' ?>
</div>

<div class="stat-grid" style="margin-bottom:1.2rem">
    <div class="stat"><div class="stat__num"><?= count($orders) ?></div><div class="stat__label">Orders</div></div>
    <div class="stat stat--accent"><div class="stat__num"><?= money($spent) ?></div><div class="stat__label">Lifetime Spend</div></div>
    <div class="stat"><div class="stat__num"><?= e(date('d M Y', strtotime($customer['created_at']))) ?></div><div class="stat__label">Joined</div></div>
</div>

<div class="panel">
    <h3>Contact</h3>
    <div class="summary-row"><span>Email</span><strong><?= e($customer['email']) ?></strong></div>
    <div class="summary-row"><span>Phone</span><span><?= e($customer['phone'] ?: '—') ?></span></div>
</div>

<div class="panel">
    <div class="panel__head"><h3>Order History</h3></div>
    <?php if (!$orders): ?>
        <p class="muted">This customer has not placed any orders yet.</p>
    <?php else: ?>
        <div class="table-wrap" style="border:0">
            <table class="data-table" style="min-width:560px">
                <thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Payment</th><th>Total</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><strong><?= e($o['order_number']) ?></strong></td>
                        <td><?= e(date('d M Y', strtotime($o['created_at']))) ?></td>
                        <td><?= (int)$o['item_count'] ?></td>
                        <td><?= e(strtoupper($o['payment_method'])) ?> &middot; <?= e(ucfirst($o['payment_status'])) ?></td>
                        <td><strong><?= money($o['total']) ?></strong></td>
                        <td><?= order_status_badge($o['status']) ?></td>
                        <td><a class="btn btn--outline btn--sm" href="<?= e(url('admin/order-detail.php?id=' . (int)$o['id'])) ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
