<?php
/** Admin — single order: details, items and status management. */
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pdo = db();
$id  = (int)($_GET['id'] ?? 0);

/* ---------- Handle status updates ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('admin/order-detail.php?id=' . $id);
    }
    $action = $_POST['form'] ?? '';

    if ($action === 'order') {
        $status = (string)($_POST['status'] ?? '');
        if (in_array($status, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'], true)) {
            $prev = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
            $prev->execute([$id]);
            $oldStatus = (string)$prev->fetchColumn();

            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $id]);
            set_flash('success', 'Order status updated to ' . ucfirst($status) . '.');

            /* Email the customer about the new status. */
            if ($oldStatus !== $status) {
                $row = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
                $row->execute([$id]);
                if ($row = $row->fetch()) {
                    send_mail(
                        (string)$row['shipping_email'],
                        'Order ' . $row['order_number'] . ' update — ' . SITE_NAME,
                        order_status_email($row)
                    );
                }
            }
        }
    } elseif ($action === 'payment') {
        $ps = (string)($_POST['payment_status'] ?? '');
        if (in_array($ps, ['pending', 'paid', 'failed', 'refunded'], true)) {
            $pdo->prepare('UPDATE orders SET payment_status = ? WHERE id = ?')->execute([$ps, $id]);
            set_flash('success', 'Payment status updated to ' . ucfirst($ps) . '.');
        }
    }
    redirect('admin/order-detail.php?id=' . $id);
}

$stmt = $pdo->prepare('SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.id AS customer_id
                       FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();

$pageTitle = 'Order Details';
$adminNav  = 'orders';
require __DIR__ . '/includes/header.php';

if (!$order) {
    echo '<div class="empty"><h2>Order not found</h2><p>That order does not exist.</p>'
       . '<a class="btn btn--accent" href="' . e(url('admin/orders.php')) . '">Back to orders</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$itemStmt = $pdo->prepare('SELECT i.*, p.slug, p.image FROM order_items i
                           LEFT JOIN products p ON p.id = i.product_id
                           WHERE i.order_id = ?');
$itemStmt->execute([$order['id']]);
$items = $itemStmt->fetchAll();
?>
<div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1.2rem">
    <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/orders.php')) ?>">&larr; All orders</a>
    <h2 style="margin:0"><?= e($order['order_number']) ?></h2>
    <?= order_status_badge($order['status']) ?>
    <?= $order['payment_status'] === 'paid'
        ? '<span class="badge badge-success">Paid</span>'
        : '<span class="badge badge-warning">' . e(ucfirst($order['payment_status'])) . '</span>' ?>
</div>

<div class="detail-grid">
    <div>
        <div class="panel">
            <h3>Items (<?= count($items) ?>)</h3>
            <?php foreach ($items as $it): ?>
                <div class="item-row">
                    <?php if ($it['image']): ?>
                        <img src="<?= e(image_url($it['image'])) ?>" alt="">
                    <?php endif; ?>
                    <div style="flex:1">
                        <?php if ($it['slug']): ?>
                            <a href="<?= e(url('product.php?slug=' . urlencode($it['slug']))) ?>" target="_blank" rel="noopener" style="font-weight:600"><?= e($it['product_name']) ?></a>
                        <?php else: ?>
                            <span style="font-weight:600"><?= e($it['product_name']) ?></span>
                        <?php endif; ?>
                        <div class="muted" style="font-size:.82rem"><?= money($it['price']) ?> &times; <?= (int)$it['qty'] ?></div>
                        <?php if (!empty($it['size']) || !empty($it['color'])): ?>
                            <div style="margin-top:.25rem">
                                <?php if (!empty($it['size'])): ?><span class="variant-tag">Size: <?= e($it['size']) ?></span><?php endif; ?>
                                <?php if (!empty($it['color'])): ?><span class="variant-tag">Colour: <?= e($it['color']) ?></span><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <strong><?= money($it['subtotal']) ?></strong>
                </div>
            <?php endforeach; ?>

            <div style="padding-top:1rem;max-width:320px;margin-left:auto">
                <div class="summary-row"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
                <div class="summary-row"><span>Shipping</span><span><?= $order['shipping'] > 0 ? money($order['shipping']) : 'Free' ?></span></div>
                <div class="summary-row total"><span>Total</span><span><?= money($order['total']) ?></span></div>
            </div>
        </div>

        <div class="panel">
            <h3>Customer &amp; Shipping</h3>
            <div class="form-grid">
                <div>
                    <p><strong><?= e($order['shipping_name']) ?></strong><br>
                    <?= e($order['shipping_address']) ?><br>
                    <?= e($order['shipping_city']) ?><?= $order['shipping_state'] ? ', ' . e($order['shipping_state']) : '' ?>
                    <?= $order['shipping_zip'] ? ' - ' . e($order['shipping_zip']) : '' ?><br>
                    <?= e($order['shipping_country']) ?></p>
                </div>
                <div>
                    <p class="muted" style="margin:0 0 .3rem">Contact</p>
                    <p><?= e($order['shipping_email']) ?><br><?= e($order['shipping_phone']) ?></p>
                    <?php if ($order['customer_id']): ?>
                        <a class="btn btn--outline btn--sm" href="<?= e(url('admin/customer-detail.php?id=' . (int)$order['customer_id'])) ?>">View customer</a>
                    <?php else: ?>
                        <span class="badge badge-muted">Guest checkout</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($order['notes']): ?>
                <p class="muted" style="margin-top:1rem"><strong>Customer note:</strong> <?= e($order['notes']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <aside>
        <div class="panel">
            <h3>Order Status</h3>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="order">
                <div class="field">
                    <label for="status">Change to</label>
                    <select id="status" name="status">
                        <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn--accent btn--block" type="submit">Update status</button>
            </form>
        </div>

        <div class="panel">
            <h3>Payment</h3>
            <div class="summary-row"><span>Method</span><strong><?= e(strtoupper($order['payment_method'])) ?></strong></div>
            <?php if ($order['payment_ref']): ?>
                <div class="summary-row"><span>Reference</span><span style="font-size:.8rem"><?= e($order['payment_ref']) ?></span></div>
            <?php endif; ?>
            <form method="post" style="margin-top:.8rem">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="payment">
                <div class="field">
                    <label for="payment_status">Payment status</label>
                    <select id="payment_status" name="payment_status">
                        <?php foreach (['pending', 'paid', 'failed', 'refunded'] as $s): ?>
                            <option value="<?= $s ?>" <?= $order['payment_status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn--outline btn--block" type="submit">Update payment</button>
            </form>
        </div>

        <div class="panel">
            <h3>Timeline</h3>
            <div class="summary-row"><span>Placed</span><span><?= e(date('d M Y, g:i a', strtotime($order['created_at']))) ?></span></div>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
