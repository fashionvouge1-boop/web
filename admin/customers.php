<?php
/** Admin — customer list with order counts and lifetime spend. */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();
$q   = trim((string)($_GET['q'] ?? ''));

$where  = "WHERE u.role = 'customer'";
$params = [];
if ($q !== '') {
    $where   .= ' AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $like     = '%' . $q . '%';
    $params   = [$like, $like, $like];
}

$customers = $pdo->prepare("SELECT u.*,
                                   (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count,
                                   (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.user_id = u.id AND o.status <> 'cancelled') AS spent
                            FROM users u $where ORDER BY u.created_at DESC");
$customers->execute($params);
$customers = $customers->fetchAll();

$pageTitle = 'Customers';
$adminNav  = 'customers';
require __DIR__ . '/includes/header.php';
?>

<form class="filters-bar" method="get">
    <div class="field">
        <label for="q">Search customers</label>
        <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Name, email or phone">
    </div>
    <button class="btn btn--outline" type="submit">Search</button>
    <?php if ($q): ?><a class="btn btn--ghost" href="<?= e(url('admin/customers.php')) ?>">Reset</a><?php endif; ?>
    <span class="muted" style="margin-left:auto;align-self:center"><?= count($customers) ?> customer<?= count($customers) === 1 ? '' : 's' ?></span>
</form>

<?php if (!$customers): ?>
    <div class="empty"><h2>No customers yet</h2><p>Registered customers will be listed here.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Customer</th><th>Contact</th><th>Orders</th><th>Lifetime Spend</th><th>Joined</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><strong><?= e($c['name']) ?></strong><?= ($c['role'] === 'admin') ? ' <span class="badge badge-muted">Admin</span>' : '' ?></td>
                    <td><?= e($c['email']) ?><br><span class="muted" style="font-size:.8rem"><?= e($c['phone'] ?: '—') ?></span></td>
                    <td><?= (int)$c['order_count'] ?></td>
                    <td><strong><?= money($c['spent']) ?></strong></td>
                    <td><?= e(date('d M Y', strtotime($c['created_at']))) ?></td>
                    <td><a class="btn btn--outline btn--sm" href="<?= e(url('admin/customer-detail.php?id=' . (int)$c['id'])) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
