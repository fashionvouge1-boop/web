<?php
/** Admin dashboard — key numbers and the latest orders. */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

$stats = [
    'orders'    => (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'revenue'   => (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status <> 'cancelled'")->fetchColumn(),
    'pending'   => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','processing')")->fetchColumn(),
    'customers' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn(),
    'products'  => (int)$pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn(),
    'lowstock'  => (int)$pdo->query('SELECT COUNT(*) FROM products WHERE stock <= 5 AND is_active = 1')->fetchColumn(),
];

$recent = $pdo->query('SELECT o.*, u.name AS customer_name,
                              (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_count
                       FROM orders o
                       LEFT JOIN users u ON u.id = o.user_id
                       ORDER BY o.created_at DESC LIMIT 8')->fetchAll();

$topProducts = $pdo->query('SELECT i.product_name, SUM(i.qty) AS units, SUM(i.subtotal) AS revenue
                            FROM order_items i
                            GROUP BY i.product_name
                            ORDER BY units DESC LIMIT 5')->fetchAll();

$pageTitle = 'Dashboard';
$adminNav  = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="stat-grid">
    <div class="stat"><div class="stat__num"><?= number_format($stats['orders']) ?></div><div class="stat__label">Total Orders</div></div>
    <div class="stat stat--accent"><div class="stat__num"><?= money($stats['revenue']) ?></div><div class="stat__label">Total Revenue</div></div>
    <div class="stat"><div class="stat__num"><?= number_format($stats['pending']) ?></div><div class="stat__label">Pending Orders</div></div>
    <div class="stat"><div class="stat__num"><?= number_format($stats['customers']) ?></div><div class="stat__label">Customers</div></div>
    <div class="stat"><div class="stat__num"><?= number_format($stats['products']) ?></div><div class="stat__label">Active Products</div></div>
    <div class="stat"><div class="stat__num"><?= number_format($stats['lowstock']) ?></div><div class="stat__label">Low Stock (&le;5)</div></div>
</div>

<div class="detail-grid">
    <div class="panel">
        <div class="panel__head">
            <h3>Recent Orders</h3>
            <a class="btn btn--outline btn--sm" href="<?= e(url('admin/orders.php')) ?>">View all</a>
        </div>
        <?php if (!$recent): ?>
            <p class="muted">No orders yet. They will appear here as customers check out.</p>
        <?php else: ?>
            <div class="table-wrap" style="border:0">
                <table class="data-table" style="min-width:520px">
                    <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $o): ?>
                        <tr>
                            <td><strong><?= e($o['order_number']) ?></strong><br><span class="muted" style="font-size:.78rem"><?= e(date('d M Y', strtotime($o['created_at']))) ?></span></td>
                            <td><?= e($o['customer_name'] ?? ($o['shipping_name'] ?: 'Guest')) ?></td>
                            <td><?= (int)$o['item_count'] ?></td>
                            <td><?= money($o['total']) ?></td>
                            <td><?= order_status_badge($o['status']) ?></td>
                            <td><a class="btn btn--outline btn--sm" href="<?= e(url('admin/order-detail.php?id=' . (int)$o['id'])) ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="panel">
            <h3>Top Selling Products</h3>
            <?php if (!$topProducts): ?>
                <p class="muted">No sales data yet.</p>
            <?php else: ?>
                <?php foreach ($topProducts as $t): ?>
                    <div class="summary-row">
                        <span><?= e($t['product_name']) ?> <span class="muted">&times;<?= (int)$t['units'] ?></span></span>
                        <strong><?= money($t['revenue']) ?></strong>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h3>Quick Actions</h3>
            <div style="display:grid;gap:.6rem">
                <a class="btn btn--accent btn--block" href="<?= e(url('admin/product-form.php')) ?>">+ Add new product</a>
                <a class="btn btn--outline btn--block" href="<?= e(url('admin/orders.php')) ?>">Manage orders</a>
                <a class="btn btn--outline btn--block" href="<?= e(url('admin/customers.php')) ?>">View customers</a>
                <a class="btn btn--ghost btn--block" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">Open storefront</a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
