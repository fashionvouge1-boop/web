<?php
/** Admin — create, edit and manage discount coupons. */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();

/* ---------- Handle create / update / delete / toggle ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('admin/coupons.php');
    }
    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM coupons WHERE id = ?')->execute([$id]);
        set_flash('info', 'Coupon deleted.');
        redirect('admin/coupons.php');
    }

    if ($action === 'toggle' && $id) {
        $pdo->prepare('UPDATE coupons SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        set_flash('success', 'Coupon status updated.');
        redirect('admin/coupons.php');
    }

    if ($action === 'save') {
        $code      = strtoupper(trim((string)($_POST['code'] ?? '')));
        $type      = ($_POST['type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
        $value     = round((float)($_POST['value'] ?? 0), 2);
        $minOrder  = round((float)($_POST['min_order'] ?? 0), 2);
        $maxUses   = max(0, (int)($_POST['max_uses'] ?? 0));
        $startsAt  = trim((string)($_POST['starts_at'] ?? ''));
        $expiresAt = trim((string)($_POST['expires_at'] ?? ''));
        $isActive  = !empty($_POST['is_active']) ? 1 : 0;

        $errors = [];
        if ($code === '' || !preg_match('/^[A-Z0-9_-]{3,40}$/', $code)) {
            $errors[] = 'Enter a code of 3–40 letters/digits (A–Z, 0–9, - or _).';
        }
        if ($value <= 0) {
            $errors[] = 'The discount value must be greater than zero.';
        }
        if ($type === 'percent' && $value > 100) {
            $errors[] = 'A percentage discount cannot exceed 100%.';
        }
        if ($startsAt !== '' && $expiresAt !== '' && strtotime($expiresAt) < strtotime($startsAt)) {
            $errors[] = 'The expiry date cannot be before the start date.';
        }

        /* Codes must be unique. */
        if (!$errors) {
            $dupe = $pdo->prepare('SELECT id FROM coupons WHERE code = ? AND id <> ?');
            $dupe->execute([$code, $id]);
            if ($dupe->fetchColumn()) {
                $errors[] = 'That coupon code is already in use.';
            }
        }

        if ($errors) {
            set_flash('error', implode(' ', $errors));
            $_SESSION['coupon_old'] = [
                'id' => $id, 'code' => $code, 'type' => $type, 'value' => $value,
                'min_order' => $minOrder, 'max_uses' => $maxUses,
                'starts_at' => $startsAt, 'expires_at' => $expiresAt, 'is_active' => $isActive,
            ];
            redirect('admin/coupons.php' . ($id ? '?edit=' . $id : '?new=1'));
        }

        $params = [
            $code, $type, $value, $minOrder, $maxUses,
            $startsAt !== '' ? $startsAt . ' 00:00:00' : null,
            $expiresAt !== '' ? $expiresAt . ' 23:59:59' : null,
            $isActive,
        ];
        if ($id) {
            $params[] = $id;
            $pdo->prepare('UPDATE coupons SET code=?, type=?, value=?, min_order=?, max_uses=?,
                           starts_at=?, expires_at=?, is_active=? WHERE id=?')->execute($params);
            set_flash('success', 'Coupon ' . $code . ' updated.');
        } else {
            $pdo->prepare('INSERT INTO coupons (code, type, value, min_order, max_uses, starts_at, expires_at, is_active)
                           VALUES (?,?,?,?,?,?,?,?)')->execute($params);
            set_flash('success', 'Coupon ' . $code . ' created.');
        }
        redirect('admin/coupons.php');
    }
}

/* ---------- Load the form (create or edit) ---------- */
$editId   = (int)($_GET['edit'] ?? 0);
$creating = isset($_GET['new']);
$form = [
    'id' => 0, 'code' => '', 'type' => 'percent', 'value' => '',
    'min_order' => '0', 'max_uses' => '0', 'starts_at' => '', 'expires_at' => '', 'is_active' => 1,
];
if ($editId) {
    $st = $pdo->prepare('SELECT * FROM coupons WHERE id = ?');
    $st->execute([$editId]);
    $row = $st->fetch();
    if ($row) {
        $form = [
            'id' => (int)$row['id'], 'code' => $row['code'], 'type' => $row['type'], 'value' => $row['value'],
            'min_order' => $row['min_order'], 'max_uses' => $row['max_uses'],
            'starts_at' => $row['starts_at'] ? substr($row['starts_at'], 0, 10) : '',
            'expires_at' => $row['expires_at'] ? substr($row['expires_at'], 0, 10) : '',
            'is_active' => (int)$row['is_active'],
        ];
    }
}
/* Restore an invalid submission. */
if (!empty($_SESSION['coupon_old'])) {
    $form = $_SESSION['coupon_old'] + $form;
    unset($_SESSION['coupon_old']);
    $creating = empty($form['id']);
}
$showForm = $creating || $editId > 0;

/* ---------- Listing ---------- */
$coupons = $pdo->query('SELECT * FROM coupons ORDER BY is_active DESC, created_at DESC')->fetchAll();

$pageTitle = 'Coupons';
$adminNav  = 'coupons';
require __DIR__ . '/includes/header.php';
?>

<div class="panel">
    <div class="panel__head">
        <h3>Discount coupons</h3>
        <?php if (!$showForm): ?>
            <a class="btn btn--accent btn--sm" href="<?= e(url('admin/coupons.php?new=1')) ?>">+ New coupon</a>
        <?php else: ?>
            <a class="btn btn--outline btn--sm" href="<?= e(url('admin/coupons.php')) ?>">Back to list</a>
        <?php endif; ?>
    </div>

    <?php if ($showForm): ?>
    <form method="post" action="<?= e(url('admin/coupons.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
        <div class="form-grid">
            <div class="field">
                <label for="code">Coupon code *</label>
                <input id="code" name="code" required value="<?= e($form['code']) ?>" placeholder="WELCOME10">
                <div class="hint">Letters and digits only — shown to customers at checkout.</div>
            </div>
            <div class="field">
                <label for="type">Discount type *</label>
                <select id="type" name="type">
                    <option value="percent" <?= $form['type'] === 'percent' ? 'selected' : '' ?>>Percentage (%)</option>
                    <option value="fixed" <?= $form['type'] === 'fixed' ? 'selected' : '' ?>>Fixed amount</option>
                </select>
            </div>
            <div class="field">
                <label for="value">Discount value *</label>
                <input id="value" type="number" step="0.01" min="0" name="value" required value="<?= e((string)$form['value']) ?>">
                <div class="hint">E.g. 10 for 10% or &#8377;10 off.</div>
            </div>
            <div class="field">
                <label for="min_order">Minimum order value</label>
                <input id="min_order" type="number" step="0.01" min="0" name="min_order" value="<?= e((string)$form['min_order']) ?>">
            </div>
            <div class="field">
                <label for="max_uses">Maximum uses</label>
                <input id="max_uses" type="number" min="0" name="max_uses" value="<?= e((string)$form['max_uses']) ?>">
                <div class="hint">0 = unlimited.</div>
            </div>
            <div class="field">
                <label for="is_active">Status</label>
                <select id="is_active" name="is_active">
                    <option value="1" <?= !empty($form['is_active']) ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= empty($form['is_active']) ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="field">
                <label for="starts_at">Starts on</label>
                <input id="starts_at" type="date" name="starts_at" value="<?= e($form['starts_at']) ?>">
                <div class="hint">Leave blank to start immediately.</div>
            </div>
            <div class="field">
                <label for="expires_at">Expires on</label>
                <input id="expires_at" type="date" name="expires_at" value="<?= e($form['expires_at']) ?>">
                <div class="hint">Leave blank for no expiry.</div>
            </div>
        </div>
        <button class="btn btn--accent" type="submit"><?= $form['id'] ? 'Save changes' : 'Create coupon' ?></button>
    </form>
    <?php elseif (!$coupons): ?>
        <div class="empty"><h2>No coupons yet</h2><p>Create your first discount code.</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Code</th><th>Discount</th><th>Min order</th><th>Uses</th><th>Valid</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($coupons as $c): ?>
                <?php $usedUp = (int)$c['max_uses'] > 0 && (int)$c['used_count'] >= (int)$c['max_uses']; ?>
                <tr>
                    <td><strong><?= e($c['code']) ?></strong></td>
                    <td><?= $c['type'] === 'percent' ? e(rtrim(rtrim(number_format((float)$c['value'], 2), '0'), '.')) . '%' : money($c['value']) ?></td>
                    <td><?= (float)$c['min_order'] > 0 ? money($c['min_order']) : '<span class="muted">—</span>' ?></td>
                    <td><?= (int)$c['used_count'] ?><?= (int)$c['max_uses'] > 0 ? ' / ' . (int)$c['max_uses'] : ' <span class="muted">/ ∞</span>' ?></td>
                    <td class="muted" style="font-size:.8rem">
                        <?php
                        $from = $c['starts_at'] ? date('d M Y', strtotime($c['starts_at'])) : 'Now';
                        $to   = $c['expires_at'] ? date('d M Y', strtotime($c['expires_at'])) : 'No expiry';
                        echo e($from . ' → ' . $to);
                        ?>
                    </td>
                    <td>
                        <?php if ($c['is_active'] && !$usedUp): ?>
                            <span class="badge badge-success">Active</span>
                        <?php elseif ($usedUp): ?>
                            <span class="badge badge-muted">Used up</span>
                        <?php else: ?>
                            <span class="badge badge-muted">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:.35rem;flex-wrap:wrap">
                            <a class="btn btn--outline btn--sm" href="<?= e(url('admin/coupons.php?edit=' . (int)$c['id'])) ?>">Edit</a>
                            <form method="post" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <input type="hidden" name="action" value="toggle">
                                <button class="btn btn--ghost btn--sm" type="submit"><?= $c['is_active'] ? 'Disable' : 'Enable' ?></button>
                            </form>
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this coupon permanently?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="btn btn--ghost btn--sm" type="submit">Delete</button>
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

<?php require __DIR__ . '/includes/footer.php'; ?>
