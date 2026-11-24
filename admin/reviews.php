<?php
/** Admin — moderate customer product reviews. */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

/* ---------- Handle moderation actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $return = (string)($_POST['return'] ?? 'admin/reviews.php');
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect($return);
    }
    $id     = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    if ($id) {
        if ($action === 'approve' || $action === 'reject') {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $pdo->prepare('UPDATE reviews SET status = ? WHERE id = ?')->execute([$status, $id]);
            set_flash('success', 'Review ' . ($status === 'approved' ? 'approved and published.' : 'rejected.'));
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
            set_flash('info', 'Review deleted.');
        }
    }
    redirect($return);
}

/* ---------- Filters ---------- */
$status  = trim((string)($_GET['status'] ?? ''));
$allowed = ['pending', 'approved', 'rejected'];
$where   = [];
$params  = [];
if (in_array($status, $allowed, true)) {
    $where[]  = 'r.status = ?';
    $params[] = $status;
}
$sql = 'FROM reviews r LEFT JOIN products p ON p.id = r.product_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

$perPage   = 20;
$page      = max(1, (int)($_GET['page'] ?? 1));
$totalRows = (int)(function () use ($pdo, $sql, $params) {
    $st = $pdo->prepare('SELECT COUNT(*) ' . $sql);
    $st->execute($params);
    return $st->fetchColumn();
})();
$pages  = max(1, (int)ceil($totalRows / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$st = $pdo->prepare('SELECT r.*, p.name AS product_name, p.slug AS product_slug '
                  . $sql . ' ORDER BY (r.status = "pending") DESC, r.created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset);
$st->execute($params);
$reviews = $st->fetchAll();

/* Counts per status for the tabs. */
$counts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($pdo->query('SELECT status, COUNT(*) AS c FROM reviews GROUP BY status')->fetchAll() as $row) {
    $counts[$row['status']] = (int)$row['c'];
    $counts['all'] += (int)$row['c'];
}

$tabs = ['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

$pageTitle = 'Reviews';
$adminNav  = 'reviews';
require __DIR__ . '/includes/header.php';
?>

<div class="tabs" style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1.2rem">
    <?php foreach ($tabs as $key => $label): ?>
        <?php $isActive = ($key === 'all' && $status === '') || $status === $key; ?>
        <a class="btn <?= $isActive ? 'btn--accent' : 'btn--outline' ?> btn--sm"
           href="<?= e(url('admin/reviews.php' . ($key === 'all' ? '' : '?status=' . $key))) ?>">
            <?= e($label) ?> (<?= (int)($counts[$key] ?? 0) ?>)
        </a>
    <?php endforeach; ?>
    <span class="muted" style="margin-left:auto;align-self:center"><?= number_format($totalRows) ?> shown</span>
</div>

<?php if (!$reviews): ?>
    <div class="empty"><h2>No reviews found</h2><p>Nothing to moderate here yet.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Product</th><th>Reviewer</th><th>Rating</th><th>Review</th><th>Date</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($reviews as $r):
                $return = 'admin/reviews.php' . ($status !== '' ? '?status=' . urlencode($status) : '') . ($page > 1 ? ($status !== '' ? '&' : '?') . 'page=' . $page : '');
                $badge  = ['pending' => 'badge-warning', 'approved' => 'badge-success', 'rejected' => 'badge-danger'][$r['status']] ?? 'badge-muted';
            ?>
                <tr>
                    <td>
                        <?php if ($r['product_slug']): ?>
                            <a href="<?= e(url('product.php?slug=' . urlencode($r['product_slug']))) ?>" target="_blank" rel="noopener"><?= e($r['product_name']) ?></a>
                        <?php else: ?>
                            <span class="muted">(deleted)</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($r['author_name']) ?></td>
                    <td><?= star_html((float)$r['rating']) ?></td>
                    <td style="max-width:340px">
                        <?php if ($r['title']): ?><strong><?= e($r['title']) ?></strong><br><?php endif; ?>
                        <span class="muted" style="font-size:.85rem"><?= e(mb_strimwidth((string)$r['body'], 0, 160, '…')) ?></span>
                    </td>
                    <td class="muted" style="font-size:.8rem"><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
                    <td><span class="badge <?= $badge ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                    <td>
                        <div style="display:flex;gap:.35rem;flex-wrap:wrap">
                            <?php if ($r['status'] !== 'approved'): ?>
                                <form method="post" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="return" value="<?= e($return) ?>">
                                    <button class="btn btn--accent btn--sm" type="submit">Approve</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($r['status'] !== 'rejected'): ?>
                                <form method="post" style="display:inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="return" value="<?= e($return) ?>">
                                    <button class="btn btn--ghost btn--sm" type="submit">Reject</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this review permanently?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="return" value="<?= e($return) ?>">
                                <button class="btn btn--ghost btn--sm" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:1.2rem">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <?php $qs = 'status=' . urlencode($status) . '&page=' . $i; ?>
                <a class="btn <?= $i === $page ? 'btn--accent' : 'btn--outline' ?> btn--sm"
                   href="<?= e(url('admin/reviews.php?' . $qs)) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
