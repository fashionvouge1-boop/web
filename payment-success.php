<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/payment-verify.php';

$orderNumber = (string)($_GET['order'] ?? '');
$stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ?');
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

$user = current_user();
$allowed = $order && (
    (int)$order['user_id'] === (int)($user['id'] ?? 0)
    || !empty($_SESSION['my_orders'][$orderNumber])
);

/* Confirm the payment with the gateway on return (Razorpay / Stripe).
   The Demo gateway already settles at placement, so this is a no-op there. */
if ($order && $order['payment_status'] !== 'paid') {
    $verify = verify_payment((string)$order['payment_method'], $order, array_merge($_GET, $_POST));
    if ($verify['status'] === 'paid') {
        db()->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing',
                       payment_ref = COALESCE(?, payment_ref) WHERE id = ?")
            ->execute([$verify['ref'] ?? null, $order['id']]);
        $order['payment_status'] = 'paid';
        $order['status']         = 'processing';
        if ($verify['ref']) { $order['payment_ref'] = $verify['ref']; }
    } elseif ($verify['status'] === 'failed') {
        db()->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?")->execute([$order['id']]);
        $order['payment_status'] = 'failed';
    }
}

$pageTitle = 'Order Confirmation';
$noindex   = true;
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <?php if (!$allowed): ?>
        <div class="empty">
            <h2>Order not found</h2>
            <p>We couldn't find that order, or you don't have permission to view it.</p>
            <a class="btn btn--accent" href="<?= e(url('shop.php')) ?>">Continue shopping</a>
        </div>
    <?php else:
        $itemStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $itemStmt->execute([$order['id']]);
        $orderItems = $itemStmt->fetchAll();
        $paid = $order['payment_status'] === 'paid';
        ?>
        <div class="confirm">
            <div class="confirm__icon">&#10003;</div>
            <h1>Thank you for your order!</h1>
            <p class="muted">
                Your order <strong><?= e($order['order_number']) ?></strong> has been received.
                A confirmation has been sent to <?= e($order['shipping_email']) ?>.
            </p>

            <div class="confirm__box">
                <div class="summary-row"><span>Order number</span><strong><?= e($order['order_number']) ?></strong></div>
                <div class="summary-row"><span>Date</span><span><?= e(date('d M Y, g:i a', strtotime($order['created_at']))) ?></span></div>
                <div class="summary-row"><span>Payment method</span><span><?= e(strtoupper($order['payment_method'])) ?></span></div>
                <div class="summary-row">
                    <span>Payment status</span>
                    <?= $paid
                        ? '<span class="badge badge-success">Paid</span>'
                        : '<span class="badge badge-warning">' . e(ucfirst($order['payment_status'])) . '</span>' ?>
                </div>
                <div class="summary-row"><span>Order status</span><span><?= order_status_badge($order['status']) ?></span></div>

                <hr style="border:0;border-top:1px solid var(--line);margin:1rem 0">

                <h3 style="font-size:1rem">Items</h3>
                <?php foreach ($orderItems as $it): ?>
                    <div class="summary-row">
                        <span>
                            <?= e($it['product_name']) ?> &times; <?= (int)$it['qty'] ?>
                            <?php
                            $variant = [];
                            if (!empty($it['size']))  { $variant[] = 'Size: ' . $it['size']; }
                            if (!empty($it['color'])) { $variant[] = 'Colour: ' . $it['color']; }
                            if ($variant):
                            ?>
                                <br><small class="muted"><?= e(implode(' · ', $variant)) ?></small>
                            <?php endif; ?>
                        </span>
                        <span><?= money($it['subtotal']) ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="summary-row"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
                <div class="summary-row"><span>Shipping</span><span><?= $order['shipping'] > 0 ? money($order['shipping']) : 'Free' ?></span></div>
                <div class="summary-row total"><span>Total</span><span><?= money($order['total']) ?></span></div>

                <hr style="border:0;border-top:1px solid var(--line);margin:1rem 0">

                <h3 style="font-size:1rem">Shipping to</h3>
                <p class="muted" style="margin:0">
                    <?= e($order['shipping_name']) ?><br>
                    <?= e($order['shipping_address']) ?><br>
                    <?= e($order['shipping_city']) ?><?= $order['shipping_state'] ? ', ' . e($order['shipping_state']) : '' ?>
                    <?= $order['shipping_zip'] ? ' - ' . e($order['shipping_zip']) : '' ?><br>
                    <?= e($order['shipping_country']) ?><br>
                    <?= e($order['shipping_phone']) ?>
                </p>
            </div>

            <div style="display:flex;gap:.8rem;justify-content:center;flex-wrap:wrap;margin-top:1.6rem">
                <a class="btn btn--accent btn--lg" href="<?= e(url('account/orders.php')) ?>">View my orders</a>
                <a class="btn btn--outline btn--lg" href="<?= e(url('shop.php')) ?>">Continue shopping</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
