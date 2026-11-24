<?php
/**
 * UPI payment page.
 *
 * Shown after the customer places an order with the "UPI Apps" method.
 * Displays the full order summary and lets the shopper open Google Pay,
 * PhonePe, Paytm (or any UPI app) with the amount pre-filled, then confirm.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/payment.php';

$orderNumber = (string)($_GET['order'] ?? '');
$stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ?');
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

$user = current_user();
$allowed = $order && (
    (int)$order['user_id'] === (int)($user['id'] ?? 0)
    || !empty($_SESSION['my_orders'][$orderNumber])
);

$pageTitle = 'Complete your payment';
$noindex   = true;
require __DIR__ . '/includes/header.php';

/* UPI configuration (VPA that receives the money). */
global $GATEWAYS;
$upiCfg  = $GATEWAYS['upi'] ?? [];
$vpa     = (string)($upiCfg['vpa'] ?? '');
$payee   = (string)($upiCfg['payee'] ?? SITE_NAME);
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

        $amount = (float)$order['total'];
        /* NPCI requires the transaction ref to be alphanumeric. */
        $tr = preg_replace('/[^A-Za-z0-9]/', '', (string)$order['order_number']);

        $upiParams = http_build_query([
            'pa' => $vpa,
            'pn' => $payee,
            'tr' => $tr,
            'tn' => 'Payment for order ' . $order['order_number'],
            'am' => number_format($amount, 2, '.', ''),
            'cu' => 'INR',
        ]);
        $upiUri    = 'upi://pay?' . $upiParams;
        $qrSrc     = 'https://api.qrserver.com/v1/create-qr-code/?size=230x230&data=' . urlencode($upiUri);
        $isPaid    = $order['payment_status'] === 'paid';
        ?>

        <ul class="step-list" style="margin-top:1.4rem">
            <li><a href="<?= e(url('cart.php')) ?>"><span>1</span> Cart</a></li>
            <li class="is-active"><span>2</span> Payment</li>
            <li><span>3</span> Confirmation</li>
        </ul>

        <div class="upi-wrap">
            <!-- ============ PAY WITH UPI APP ============ -->
            <div class="panel upi-card">
                <div class="upi-head">
                    <h2>Complete your payment</h2>
                    <p class="muted">Order <strong><?= e($order['order_number']) ?></strong> is waiting for payment. Choose your UPI app to continue — the amount is already filled in.</p>
                </div>

                <div class="upi-amount">
                    <span class="muted">Amount to pay</span>
                    <strong><?= money($amount) ?></strong>
                </div>

                <div class="upi-apps">
                    <a class="upi-app upi-app--gpay" href="tez://upi/pay?<?= e($upiParams) ?>" data-app="Google Pay">
                        <span class="upi-app__icon" aria-hidden="true">&#128241;</span>
                        <span class="upi-app__label">Pay with Google Pay</span>
                    </a>
                    <a class="upi-app upi-app--phonepe" href="phonepe://pay?<?= e($upiParams) ?>" data-app="PhonePe">
                        <span class="upi-app__icon" aria-hidden="true">&#128179;</span>
                        <span class="upi-app__label">Pay with PhonePe</span>
                    </a>
                    <a class="upi-app upi-app--paytm" href="paytmmp://pay?<?= e($upiParams) ?>" data-app="Paytm">
                        <span class="upi-app__icon" aria-hidden="true">&#128176;</span>
                        <span class="upi-app__label">Pay with Paytm</span>
                    </a>
                    <a class="upi-app upi-app--any" href="<?= e($upiUri) ?>" data-app="your UPI app">
                        <span class="upi-app__icon" aria-hidden="true">&#128273;</span>
                        <span class="upi-app__label">Pay with any UPI app</span>
                    </a>
                </div>

                <p id="upiStatus" class="upi-status" role="status"></p>

                <div class="upi-qr">
                    <p class="muted" style="font-size:.8rem;margin:.4rem 0">On a computer? Scan this QR with any UPI app.</p>
                    <img src="<?= e($qrSrc) ?>" width="230" height="230" alt="UPI payment QR code"
                         onerror="this.parentNode.style.display='none'">
                    <p class="muted" style="font-size:.78rem;margin:.4rem 0">Or pay to UPI ID: <strong><?= e($vpa) ?></strong></p>
                </div>
            </div>

            <!-- ============ ORDER SUMMARY ============ -->
            <aside class="panel">
                <h3>Order details</h3>
                <?php foreach ($orderItems as $it): ?>
                    <div class="upi-row">
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
                <?php if ((float)$order['discount'] > 0): ?>
                    <div class="summary-row"><span>Discount<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></span><span>&minus;<?= money($order['discount']) ?></span></div>
                <?php endif; ?>
                <div class="summary-row"><span>Shipping</span><span><?= $order['shipping'] > 0 ? money($order['shipping']) : 'Free' ?></span></div>
                <div class="summary-row total"><span>Total</span><span><?= money($order['total']) ?></span></div>

                <hr style="border:0;border-top:1px solid var(--line);margin:1rem 0">

                <h3 style="font-size:1rem">Shipping to</h3>
                <p class="muted" style="margin:0;font-size:.88rem">
                    <?= e($order['shipping_name']) ?><br>
                    <?= e($order['shipping_address']) ?><br>
                    <?= e($order['shipping_city']) ?><?= $order['shipping_state'] ? ', ' . e($order['shipping_state']) : '' ?>
                    <?= $order['shipping_zip'] ? ' - ' . e($order['shipping_zip']) : '' ?><br>
                    <?= e($order['shipping_phone']) ?>
                </p>

                <div style="display:flex;flex-direction:column;gap:.6rem;margin-top:1.4rem">
                    <?php if ($isPaid): ?>
                        <a class="btn btn--accent btn--block btn--lg" href="<?= e(url('payment-success.php?order=' . urlencode($order['order_number']))) ?>">View order confirmation</a>
                    <?php else: ?>
                        <a class="btn btn--accent btn--block btn--lg" href="<?= e(url('payment-success.php?order=' . urlencode($order['order_number']) . '&upi=done')) ?>">I've completed the payment</a>
                        <a class="btn btn--outline btn--block" href="<?= e(url('checkout.php')) ?>">Cancel &amp; go back</a>
                    <?php endif; ?>
                </div>
                <p class="muted text-center" style="font-size:.78rem;margin-top:.8rem">&#128274; Payments are secured by your UPI app.</p>
            </aside>
        </div>
    <?php endif; ?>
</div>

<script>
/* Give quick feedback when a shopper taps an app button. */
document.querySelectorAll('.upi-app').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var status = document.getElementById('upiStatus');
        var app = btn.getAttribute('data-app') || 'your UPI app';
        if (status) status.textContent = 'Opening ' + app + '… confirm the payment there, then tap "I\'ve completed the payment".';
        if (navigator.vibrate) navigator.vibrate(40);
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
