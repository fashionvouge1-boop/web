<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$user = current_user();
$id = (int)($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);
$order = $stmt->fetch();

$pageTitle = 'Order Details';
require __DIR__ . '/../includes/header.php';
?>
<div class="container">
    <div class="breadcrumb">
        <a href="<?= e(url('account/dashboard.php')) ?>">My Account</a> /
        <a href="<?= e(url('account/orders.php')) ?>">Orders</a> /
        <span>Details</span>
    </div>

    <?php if (!$order): ?>
        <div class="empty">
            <h2>Order not found</h2>
            <p>This order does not exist or does not belong to your account.</p>
            <a class="btn btn--accent" href="<?= e(url('account/orders.php')) ?>">Back to orders</a>
        </div>
    <?php else:
        $itemStmt = db()->prepare('SELECT i.*, p.slug, p.image FROM order_items i
                                   LEFT JOIN products p ON p.id = i.product_id
                                   WHERE i.order_id = ?');
        $itemStmt->execute([$order['id']]);
        $orderItems = $itemStmt->fetchAll();
        ?>
        <h1 style="margin-top:1rem">Order <span class="muted"><?= e($order['order_number']) ?></span></h1>
        <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-bottom:1.4rem">
            <?= order_status_badge($order['status']) ?>
            <span class="muted" style="font-size:.88rem">Placed on <?= e(date('d M Y, g:i a', strtotime($order['created_at']))) ?></span>
        </div>

        <div class="checkout-layout">
            <div class="panel">
                <h3>Items</h3>
                <?php foreach ($orderItems as $it): ?>
                    <div class="cart-item" style="padding:.8rem 0;border-bottom:1px solid var(--line)">
                        <?php if ($it['image']): ?>
                            <img src="<?= e(image_url($it['image'])) ?>" alt="" style="width:60px;height:74px;object-fit:cover;border-radius:8px">
                        <?php endif; ?>
                        <div style="flex:1">
                            <?php if ($it['slug']): ?>
                                <a class="cart-item__name" href="<?= e(url('product.php?slug=' . urlencode($it['slug']))) ?>"><?= e($it['product_name']) ?></a>
                            <?php else: ?>
                                <span class="cart-item__name"><?= e($it['product_name']) ?></span>
                            <?php endif; ?>
                            <?php
                            $variant = [];
                            if (!empty($it['size']))  { $variant[] = 'Size: ' . $it['size']; }
                            if (!empty($it['color'])) { $variant[] = 'Colour: ' . $it['color']; }
                            ?>
                            <div class="muted" style="font-size:.82rem"><?= money($it['price']) ?> &times; <?= (int)$it['qty'] ?></div>
                            <?php if ($variant): ?>
                                <div class="muted" style="font-size:.8rem"><?= e(implode(' · ', $variant)) ?></div>
                            <?php endif; ?>
                        </div>
                        <strong><?= money($it['subtotal']) ?></strong>
                    </div>
                <?php endforeach; ?>

                <div style="padding-top:1rem">
                    <div class="summary-row"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
                    <div class="summary-row"><span>Shipping</span><span><?= $order['shipping'] > 0 ? money($order['shipping']) : 'Free' ?></span></div>
                    <div class="summary-row total"><span>Total</span><span><?= money($order['total']) ?></span></div>
                </div>
            </div>

            <aside>
                <div class="panel">
                    <h3>Shipping address</h3>
                    <p style="margin:0">
                        <strong><?= e($order['shipping_name']) ?></strong><br>
                        <?= e($order['shipping_address']) ?><br>
                        <?= e($order['shipping_city']) ?><?= $order['shipping_state'] ? ', ' . e($order['shipping_state']) : '' ?>
                        <?= $order['shipping_zip'] ? ' - ' . e($order['shipping_zip']) : '' ?><br>
                        <?= e($order['shipping_country']) ?><br>
                        <?= e($order['shipping_phone']) ?><br>
                        <?= e($order['shipping_email']) ?>
                    </p>
                </div>

                <div class="panel" style="margin-top:1.4rem">
                    <h3>Payment</h3>
                    <div class="summary-row"><span>Method</span><span><?= e(strtoupper($order['payment_method'])) ?></span></div>
                    <div class="summary-row"><span>Status</span>
                        <?= $order['payment_status'] === 'paid'
                            ? '<span class="badge badge-success">Paid</span>'
                            : '<span class="badge badge-warning">' . e(ucfirst($order['payment_status'])) . '</span>' ?>
                    </div>
                    <?php if ($order['payment_ref']): ?>
                        <div class="summary-row"><span>Reference</span><span style="font-size:.82rem"><?= e($order['payment_ref']) ?></span></div>
                    <?php endif; ?>
                    <?php if ($order['notes']): ?>
                        <div class="summary-row"><span>Notes</span><span><?= e($order['notes']) ?></span></div>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
