<?php
/** Admin — product catalogue: list, search, filter, toggle and delete. */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

/* ---------- Delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        set_flash('error', 'Your session expired. Please try again.');
    } else {
        $pid = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$pid]);
        set_flash('success', 'Product deleted.');
    }
    redirect('admin/products.php');
}

/* ---------- Toggle active ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    if (csrf_verify($_POST['csrf'] ?? null)) {
        $pid = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ?')->execute([$pid]);
        set_flash('success', 'Product visibility updated.');
    }
    redirect('admin/products.php');
}

$q    = trim((string)($_GET['q'] ?? ''));
$cat  = (int)($_GET['category'] ?? 0);

$where  = ['1=1'];
$params = [];
if ($q !== '') {
    $where[]  = '(p.name LIKE ? OR p.slug LIKE ?)';
    $like     = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($cat > 0) {
    $where[]  = 'p.category_id = ?';
    $params[] = $cat;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$perPage   = 20;
$page      = max(1, (int)($_GET['page'] ?? 1));
$totalRows = (int)(function () use ($pdo, $whereSql, $params) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM products p $whereSql");
    $st->execute($params);
    return $st->fetchColumn();
})();
$pages  = max(1, (int)ceil($totalRows / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$st = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p
                     LEFT JOIN categories c ON c.id = p.category_id
                     $whereSql ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset");
$st->execute($params);
$products = $st->fetchAll();

$categories = all_categories();

$pageTitle = 'Products';
$adminNav  = 'products';
require __DIR__ . '/includes/header.php';
?>

<form class="filters-bar" method="get">
    <div class="field">
        <label for="q">Search</label>
        <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Product name or slug">
    </div>
    <div class="field">
        <label for="category">Category</label>
        <select id="category" name="category">
            <option value="0">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn btn--outline" type="submit">Filter</button>
    <?php if ($q || $cat): ?><a class="btn btn--ghost" href="<?= e(url('admin/products.php')) ?>">Reset</a><?php endif; ?>
    <a class="btn btn--accent" style="margin-left:auto" href="<?= e(url('admin/product-form.php')) ?>">+ Add Product</a>
</form>

<?php if (!$products): ?>
    <div class="empty">
        <h2>No products found</h2>
        <p>Add your first product to the catalogue.</p>
        <a class="btn btn--accent" href="<?= e(url('admin/product-form.php')) ?>">+ Add Product</a>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Variants</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td>
                        <div style="display:flex;gap:.7rem;align-items:center">
                            <img class="thumb" src="<?= e(image_url($p['image'])) ?>" alt="">
                            <div>
                                <strong><?= e($p['name']) ?></strong>
                                <?php if ($p['featured']): ?><span class="badge badge-info">Featured</span><?php endif; ?>
                                <div class="muted" style="font-size:.76rem"><?= e($p['slug']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= e($p['category_name'] ?? '—') ?><br><span class="muted" style="font-size:.76rem"><?= e(ucfirst($p['gender'])) ?></span></td>
                    <td>
                        <?php if ($p['sale_price'] !== null && (float)$p['sale_price'] > 0 && (float)$p['sale_price'] < (float)$p['price']): ?>
                            <strong><?= money($p['sale_price']) ?></strong><br>
                            <s class="muted" style="font-size:.78rem"><?= money($p['price']) ?></s>
                        <?php else: ?>
                            <strong><?= money($p['price']) ?></strong>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int)$p['stock'] <= 0): ?>
                            <span class="badge badge-danger">Out</span>
                        <?php elseif ((int)$p['stock'] <= 5): ?>
                            <span class="badge badge-warning"><?= (int)$p['stock'] ?> left</span>
                        <?php else: ?>
                            <?= (int)$p['stock'] ?>
                        <?php endif; ?>
                    </td>
                    <td class="muted" style="font-size:.8rem">
                        <?php if ($p['sizes']): ?><div>Sizes: <?= e($p['sizes']) ?></div><?php endif; ?>
                        <?php if ($p['colors']): ?><div>Colours: <?= e($p['colors']) ?></div><?php endif; ?>
                        <?php if (!$p['sizes'] && !$p['colors']): ?>Single<?php endif; ?>
                    </td>
                    <td><?= $p['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-muted">Hidden</span>' ?></td>
                    <td>
                        <div class="row-actions">
                            <a class="btn btn--outline btn--sm" href="<?= e(url('admin/product-form.php?id=' . (int)$p['id'])) ?>">Edit</a>
                            <a class="btn btn--ghost btn--sm" href="<?= e(url('product.php?slug=' . urlencode($p['slug']))) ?>" target="_blank" rel="noopener">View</a>
                            <form method="post" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button class="btn btn--ghost btn--sm" type="submit"><?= $p['is_active'] ? 'Hide' : 'Show' ?></button>
                            </form>
                            <form method="post" style="display:inline" data-confirm="Delete &quot;<?= e($p['name']) ?>&quot; permanently?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button class="btn btn--danger btn--sm" type="submit">Delete</button>
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
                <a class="btn <?= $i === $page ? 'btn--accent' : 'btn--outline' ?> btn--sm"
                   href="<?= e(url('admin/products.php?' . http_build_query(array_filter(['q' => $q, 'category' => $cat ?: '', 'page' => $i])))) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
