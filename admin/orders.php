<?php
/** Admin — all orders with filters and pagination. */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

$status  = trim((string)($_GET['status'] ?? ''));
$payment = trim((string)($_GET['payment'] ?? ''));
$q       = trim((string)($_GET['q'] ?? ''));

$where  = [];
$params = [];

if ($status !== '' && in_array($status, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'], true)) {
    $where[]  = 'o.status = ?';
    $params[] = $status;
}
if ($payment !== '' && in_array($payment, ['pending', 'paid', 'failed', 'refunded'], true)) {
    $where[]  = 'o.payment_status = ?';
    $params[] = $payment;
}
if ($q !== '') {
    $where[]  = '(o.order_number LIKE ? OR o.shipping_name LIKE ? OR o.shipping_email LIKE ?)';
    $like     = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
$sql = 'FROM orders o LEFT JOIN users u ON u.id = o.user_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

$perPage   = 15;
$page      = max(1, (int)($_GET['page'] ?? 1));
$totalRows = (int)(function () use ($pdo, $sql, $params) {
    $st = $pdo->prepare('SELECT COUNT(*) ' . $sql);
    $st->execute($params);
    return $st->fetchColumn();
})();
$pages  = max(1, (int)ceil($totalRows / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$st = $pdo->prepare('SELECT o.*, u.name AS customer_name,
                            (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) AS item_count '
                    . $sql . ' ORDER BY o.created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
$st->execute($params);
$orders = $st->fetchAll();

$qs = function (array $extra = []) use ($status, $payment, $q) {
    return http_build_query(array_filter(array_merge(['status' => $status, 'payment' => $payment, 'q' => $q], $extra), fn($v) => $v !== ''));
};

$pageTitle = 'Orders';
$adminNav  = 'orders';
require __DIR__ . '/includes/header.php';
?>

<form class="filters-bar" method="get">
    <div class="field">
        <label for="q">Search</label>
        <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Order #, name or email">
    </div>
    <div class="field">
        <label for="status">Order status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="payment">Payment</label>
        <select id="payment" name="payment">
            <option value="">All</option>
            <?php foreach (['pending', 'paid', 'failed', 'refunded'] as $s): ?>
                <option value="<?= $s ?>" <?= $payment === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn--outline" type="submit">Filter</button>
    <?php if ($status || $payment || $q): ?>
        <a class="btn btn--ghost" href="<?= e(url('admin/orders.php')) ?>">Reset</a>
    <?php endif; ?>
    <span class="muted" style="margin-left:auto;align-self:center"><?= number_format($totalRows) ?> order<?= $totalRows === 1 ? '' : 's' ?></span>
</form>

<?php if (!$orders): ?>
    <div class="empty"><h2>No orders found</h2><p>Try changing the filters above.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Order</th><th>Customer</th><th>Items</th><th>Payment</th><th>Total</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td>
                        <strong><?= e($o['order_number']) ?></strong><br>
                        <span class="muted" style="font-size:.78rem"><?= e(date('d M Y, g:i a', strtotime($o['created_at']))) ?></span>
                    </td>
                    <td>
                        <?= e($o['customer_name'] ?? ($o['shipping_name'] ?: 'Guest')) ?><br>
                        <span class="muted" style="font-size:.78rem"><?= e($o['shipping_email']) ?></span>
                    </td>
                    <td><?= (int)$o['item_count'] ?></td>
                    <td>
                        <?= e(strtoupper($o['payment_method'])) ?><br>
                        <?= $o['payment_status'] === 'paid'
                            ? '<span class="badge badge-success">Paid</span>'
                            : '<span class="badge badge-warning">' . e(ucfirst($o['payment_status'])) . '</span>' ?>
                    </td>
                    <td><strong><?= money($o['total']) ?></strong></td>
                    <td><?= order_status_badge($o['status']) ?></td>
                    <td><a class="btn btn--outline btn--sm" href="<?= e(url('admin/order-detail.php?id=' . (int)$o['id'])) ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:1.2rem">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a class="btn <?= $i === $page ? 'btn--accent' : 'btn--outline' ?> btn--sm" href="<?= e(url('admin/orders.php?' . $qs(['page' => $i]))) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
