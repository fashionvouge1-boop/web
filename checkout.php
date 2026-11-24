<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/payment.php';

$cart     = cart_items();
$items    = $cart['items'];
$subtotal = $cart['subtotal'];

if (!$items) {
    set_flash('info', 'Your cart is empty — add a product before checking out.');
    redirect('shop.php');
}

$shipping = shipping_fee($subtotal);

/* Applied coupon (kept in the session between requests). */
$coupon   = null;
$discount = 0.0;
if (!empty($_SESSION['coupon_code'])) {
    $cv = coupon_validate((string)$_SESSION['coupon_code'], $subtotal);
    if ($cv['ok']) {
        $coupon   = $cv['coupon'];
        $discount = $cv['discount'];
    } else {
        unset($_SESSION['coupon_code']);
    }
}
$total = max(0, $subtotal + $shipping - $discount);

/* Repopulate the form after a validation error. */
$old = $_SESSION['checkout_old'] ?? [];
unset($_SESSION['checkout_old']);

$user = current_user();
$val = function (string $field, string $default = '') use ($old, $user) {
    if (isset($old[$field])) return $old[$field];
    if ($user) {
        if ($field === 'name')  return $user['name'];
        if ($field === 'email') return $user['email'];
        if ($field === 'phone') return (string)$user['phone'];
    }
    return $default;
};

$gateways = available_gateways();
$pageTitle = 'Checkout';
$noindex   = true;
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="breadcrumb"><a href="<?= e(url('cart.php')) ?>">Cart</a> / <span>Checkout</span></div>

    <ul class="step-list" style="margin-top:1.4rem">
        <li><a href="<?= e(url('cart.php')) ?>"><span>1</span> Shopping Cart</a></li>
        <li class="is-active"><span>2</span> Billing &amp; Payment</li>
        <li><span>3</span> Confirmation</li>
    </ul>

    <?php if (!$user): ?>
        <div class="alert alert--info">
            Have an account? <a href="<?= e(url('account/login.php')) ?>" style="font-weight:600">Sign in</a>
            to save this order to your history, or continue as a guest below.
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('payment.php')) ?>" id="checkoutForm">
        <?= csrf_field() ?>
        <div class="checkout-layout">
            <!-- ============ BILLING DETAILS ============ -->
            <div>
                <div class="panel">
                    <h3>Billing &amp; Shipping Details</h3>
                    <div class="form-grid">
                        <div class="field">
                            <label for="name">Full name *</label>
                            <input id="name" name="name" required value="<?= e($val('name')) ?>">
                        </div>
                        <div class="field">
                            <label for="email">Email address *</label>
                            <input id="email" type="email" name="email" required value="<?= e($val('email')) ?>">
                        </div>
                        <div class="field">
                            <label for="phone">Phone number *</label>
                            <input id="phone" name="phone" required value="<?= e($val('phone')) ?>">
                        </div>
                        <div class="field">
                            <label for="country">Country *</label>
                            <input id="country" name="country" required value="<?= e($val('country', 'India')) ?>">
                        </div>
                        <div class="field field--full">
                            <label for="address">Street address *</label>
                            <input id="address" name="address" required value="<?= e($val('address')) ?>" placeholder="House no, street, area">
                        </div>
                        <div class="field">
                            <label for="city">City *</label>
                            <input id="city" name="city" required value="<?= e($val('city')) ?>">
                        </div>
                        <div class="field">
                            <label for="state">State / Province</label>
                            <input id="state" name="state" value="<?= e($val('state')) ?>">
                        </div>
                        <div class="field">
                            <label for="zip">ZIP / Postal code</label>
                            <input id="zip" name="zip" value="<?= e($val('zip')) ?>">
                        </div>
                        <div class="field">
                            <label for="notes">Order notes (optional)</label>
                            <input id="notes" name="notes" value="<?= e($val('notes')) ?>" placeholder="Delivery instructions">
                        </div>
                    </div>
                </div>

                <div class="panel" style="margin-top:1.4rem">
                    <h3>Payment Method</h3>
                    <?php
                    /* Default-check the first online gateway (never COD). */
                    $__onlineCode = null;
                    foreach ($gateways as $__c => $__g) {
                        if ($__c !== 'cod') { $__onlineCode = $__c; break; }
                    }
                    ?>
                    <div class="pay-options">
                        <?php foreach ($gateways as $code => $gw): $__isCod = ($code === 'cod'); ?>
                            <label class="pay-option <?= $code === $__onlineCode ? 'is-selected' : '' ?>"
                                   <?= $__isCod ? 'data-cod="1" data-block-message="' . e(COD_BLOCK_MESSAGE) . '"' : '' ?>>
                                <input type="radio" name="payment_method" value="<?= e($code) ?>"
                                       data-blocked="0"<?= $__isCod ? ' data-cod="1"' : '' ?>
                                       <?= $code === $__onlineCode ? 'checked' : '' ?>>
                                <span>
                                    <strong><?= e($gw->label()) ?></strong>
                                    <small>
                                        <?php
                                        echo [
                                            'cod'      => 'Pay with cash when your order is delivered.',
                                            'demo'     => 'Sandbox gateway — simulates a successful card / UPI payment.',
                                            'razorpay' => 'Pay securely with cards, UPI, netbanking &amp; wallets.',
                                            'stripe'   => 'Pay securely with international cards.',
                                        ][$code] ?? 'Secure online payment.';
                                        ?>
                                    </small>
                                    <?php if ($__isCod): ?>
                                        <small class="pay-note" style="display:none;color:#b91c1c;font-weight:600;margin-top:.35rem"><?= e(COD_BLOCK_MESSAGE) ?></small>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="alert alert--error" id="payError" style="display:none;margin-top:1rem" role="alert"></div>
                </div>
            </div>

            <!-- ============ ORDER SUMMARY ============ -->
            <aside class="panel">
                <h3>Your Order</h3>
                <?php foreach ($items as $row): $p = $row['product']; ?>
                    <div style="display:flex;gap:.75rem;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--line)">
                        <img src="<?= e(image_url($p['image'])) ?>" alt="" style="width:52px;height:64px;object-fit:cover;border-radius:8px">
                        <div style="flex:1">
                            <div style="font-size:.88rem;font-weight:600"><?= e($p['name']) ?></div>
                            <?php if ($row['size'] !== '' || $row['color'] !== ''): ?>
                                <div class="muted" style="font-size:.78rem">
                                    <?php if ($row['size'] !== ''): ?>Size: <?= e($row['size']) ?><?php endif; ?>
                                    <?php if ($row['color'] !== ''): ?><?= $row['size'] !== '' ? ' · ' : '' ?>Colour: <?= e($row['color']) ?><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="muted" style="font-size:.8rem">Qty: <?= (int)$row['qty'] ?></div>
                        </div>
                        <strong style="font-size:.9rem"><?= money($row['line_total']) ?></strong>
                    </div>
                <?php endforeach; ?>

                <div class="coupon-box">
                    <?php if ($coupon): ?>
                        <div class="coupon-applied">
                            <span>&#127991; <strong><?= e($coupon['code']) ?></strong> applied</span>
                            <button form="couponForm" type="submit" name="action" value="remove" class="coupon-remove">Remove</button>
                        </div>
                    <?php else: ?>
                        <div class="coupon-input">
                            <input form="couponForm" id="coupon_code" name="code" placeholder="Have a coupon code?" autocomplete="off">
                            <button form="couponForm" type="submit" name="action" value="apply" class="btn btn--outline btn--sm">Apply</button>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="padding-top:1rem">
                    <div class="summary-row"><span>Subtotal</span><span><?= money($subtotal) ?></span></div>
                    <?php if ($discount > 0): ?>
                        <div class="summary-row summary-row--discount"><span>Discount (<?= e($coupon['code']) ?>)</span><span>&minus;<?= money($discount) ?></span></div>
                    <?php endif; ?>
                    <div class="summary-row"><span>Shipping</span><span><?= $shipping > 0 ? money($shipping) : 'Free' ?></span></div>
                    <div class="summary-row total"><span>Total</span><span><?= money($total) ?></span></div>
                </div>

                <button class="btn btn--accent btn--block btn--lg" type="submit" style="margin-top:1rem">Place Order</button>
                <p class="muted text-center" style="font-size:.8rem;margin-top:.8rem">&#128274; Your details are transmitted securely.</p>
            </aside>
        </div>
    </form>

    <form id="couponForm" method="post" action="<?= e(url('coupon.php')) ?>" hidden><?= csrf_field() ?></form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
