<?php
/** Admin — manage product categories. */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo    = db();
$errors = [];

/* ---------- Delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        set_flash('error', 'Your session expired.');
    } else {
        $cid  = (int)($_POST['id'] ?? 0);
        $used = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE category_id = ' . $cid)->fetchColumn();
        if ($used > 0) {
            set_flash('error', "That category still has {$used} product(s). Re-assign them first.");
        } else {
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$cid]);
            set_flash('success', 'Category deleted.');
        }
    }
    redirect('admin/categories.php');
}

/* ---------- Create / update ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }
    $cid    = (int)($_POST['id'] ?? 0);
    $name   = trim((string)($_POST['name'] ?? ''));
    $gender = (string)($_POST['gender'] ?? 'unisex');

    if ($name === '') {
        $errors[] = 'Category name is required.';
    }
    if (!in_array($gender, ['men', 'women', 'unisex'], true)) {
        $gender = 'unisex';
    }

    if (!$errors) {
        $slug = slugify($name);
        if ($slug === '') {
            $slug = 'category';
        }
        $base = $slug;
        $n    = 1;
        do {
            $chk = $pdo->prepare('SELECT id FROM categories WHERE slug = ? AND id <> ?');
            $chk->execute([$slug, $cid]);
            $taken = (bool)$chk->fetch();
            if ($taken) {
                $slug = $base . '-' . (++$n);
            }
        } while ($taken);

        if ($cid > 0) {
            $pdo->prepare('UPDATE categories SET name = ?, slug = ?, gender = ? WHERE id = ?')
                ->execute([$name, $slug, $gender, $cid]);
            set_flash('success', 'Category updated.');
        } else {
            $pdo->prepare('INSERT INTO categories (name, slug, gender) VALUES (?,?,?)')
                ->execute([$name, $slug, $gender]);
            set_flash('success', 'Category added.');
        }
    } else {
        foreach ($errors as $err) {
            set_flash('error', $err);
        }
    }
    redirect('admin/categories.php');
}

$editId = (int)($_GET['edit'] ?? 0);
$edit   = null;
if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM categories WHERE id = ?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

$categories = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
                           FROM categories c ORDER BY c.gender, c.name')->fetchAll();

$pageTitle = 'Categories';
$adminNav  = 'categories';
require __DIR__ . '/includes/header.php';
?>
<div class="detail-grid">
    <div class="panel">
        <h3>All Categories</h3>
        <?php if (!$categories): ?>
            <p class="muted">No categories yet.</p>
        <?php else: ?>
            <div class="table-wrap" style="border:0">
                <table class="data-table" style="min-width:480px">
                    <thead><tr><th>Name</th><th>Slug</th><th>Shop</th><th>Products</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td><strong><?= e($c['name']) ?></strong></td>
                            <td class="muted"><?= e($c['slug']) ?></td>
                            <td><?= e(ucfirst($c['gender'])) ?></td>
                            <td><?= (int)$c['product_count'] ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn btn--outline btn--sm" href="<?= e(url('admin/categories.php?edit=' . (int)$c['id'])) ?>">Edit</a>
                                    <form method="post" style="display:inline" data-confirm="Delete category &quot;<?= e($c['name']) ?>&quot;?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                        <button class="btn btn--danger btn--sm" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <aside class="panel">
        <h3><?= $edit ? 'Edit Category' : 'Add Category' ?></h3>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
            <div class="field">
                <label for="name">Name *</label>
                <input id="name" type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="e.g. Jackets">
            </div>
            <div class="field">
                <label for="gender">Shop for</label>
                <select id="gender" name="gender">
                    <?php foreach (['women' => 'Women', 'men' => 'Men', 'unisex' => 'Unisex'] as $g => $lbl): ?>
                        <option value="<?= $g ?>" <?= ($edit['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn--accent btn--block" type="submit"><?= $edit ? 'Save changes' : 'Add category' ?></button>
            <?php if ($edit): ?>
                <a class="btn btn--ghost btn--block" style="margin-top:.6rem" href="<?= e(url('admin/categories.php')) ?>">Cancel</a>
            <?php endif; ?>
        </form>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
