<?php
require_once __DIR__ . '/includes/functions.php';

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
$returnTo = $_POST['return'] ?? ($_SERVER['HTTP_REFERER'] ?? url('cart.php'));

/* ---------------- Handle cart actions (POST) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['error' => 'invalid_csrf']); exit; }
        set_flash('error', 'Your session expired. Please try again.');
        redirect('cart.php');
    }

    $action    = $_POST['action'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);
    $size      = isset($_POST['size'])  ? trim((string)$_POST['size'])  : null;
    $color     = isset($_POST['color']) ? trim((string)$_POST['color']) : null;

    switch ($action) {
        case 'add':
            if (!cart_add($productId, max(1, (int)($_POST['qty'] ?? 1)), $size, $color)) {
                if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['error' => 'variant_required']); exit; }
                set_flash('error', 'Please choose the required size / colour first.');
                redirect($returnTo);
            }
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'cart_count' => cart_count()]);
                exit;
            }
            set_flash('success', 'Product added to your cart.');
            redirect($returnTo);
            break;

        case 'buynow':
            if (!cart_add($productId, max(1, (int)($_POST['qty'] ?? 1)), $size, $color)) {
                set_flash('error', 'Please choose the required size / colour first.');
                redirect($_SERVER['HTTP_REFERER'] ?? url('shop.php'));
            }
            redirect('checkout.php');
            break;

        case 'update':
            foreach ((array)($_POST['qty'] ?? []) as $key => $qty) {
                cart_set((string)$key, (int)$qty);
            }
            set_flash('success', 'Cart updated.');
            redirect('cart.php');
            break;

        case 'remove':
            cart_remove((string)($_POST['cart_key'] ?? ''));
            set_flash('info', 'Item removed from your cart.');
            redirect('cart.php');
            break;

        case 'clear':
            cart_clear();
            set_flash('info', 'Your cart is now empty.');
            redirect('cart.php');
            break;
    }
}

/* ---------------- Load cart ---------------- */
$cart     = cart_items();
$items    = $cart['items'];
$subtotal = $cart['subtotal'];
$shipping = shipping_fee($subtotal);
$total    = $subtotal + $shipping;

$pageTitle = 'Shopping Cart';
$noindex   = true;
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="breadcrumb"><a href="<?= e(url('index.php')) ?>">Home</a> / <span>Cart</span></div>

    <ul class="step-list" style="margin-top:1.4rem">
        <li class="is-active"><span>1</span> Shopping Cart</li>
        <li><span>2</span> Billing &amp; Payment</li>
        <li><span>3</span> Confirmation</li>
    </ul>

    <?php if (!$items): ?>
        <div class="empty">
            <h2>Your cart is empty</h2>
            <p>Looks like you haven't added anything yet.</p>
            <a class="btn btn--accent btn--lg" href="<?= e(url('shop.php')) ?>">Start shopping</a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <div class="panel">
                <form method="post" action="<?= e(url('cart.php')) ?>" id="cartForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">

                    <div class="table-wrap" style="border:0">
                        <table class="cart-table">
                            <thead>
                                <tr><th>Product</th><th>Price</th><th>Quantity</th><th>Total</th><th></th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items as $row): $p = $row['product']; ?>
                                <tr>
                                    <td>
                                        <div class="cart-item">
                                            <img src="<?= e(image_url($p['image'])) ?>" alt="<?= e($p['name']) ?>">
                                            <div>
                                                <a class="cart-item__name" href="<?= e(url('product.php?slug=' . urlencode($p['slug']))) ?>"><?= e($p['name']) ?></a>
                                                <div class="muted" style="font-size:.8rem"><?= e($p['category_name'] ?? '') ?></div>
                                                <?php if ($row['size'] !== '' || $row['color'] !== ''): ?>
                                                    <div class="cart-item__variant">
                                                        <?php if ($row['size'] !== ''): ?><span>Size: <strong><?= e($row['size']) ?></strong></span><?php endif; ?>
                                                        <?php if ($row['color'] !== ''): ?><span>Colour: <strong><?= e($row['color']) ?></strong></span><?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= money(product_price($p)) ?></td>
                                    <td>
                                        <div class="qty">
                                            <button type="button" data-step="down" aria-label="Decrease">&minus;</button>
                                            <input type="number" name="qty[<?= e($row['key']) ?>]" value="<?= (int)$row['qty'] ?>" min="1" max="<?= max(1, (int)$p['stock']) ?>">
                                            <button type="button" data-step="up" aria-label="Increase">+</button>
                                        </div>
                                    </td>
                                    <td><strong><?= money($row['line_total']) ?></strong></td>
                                    <td>
                                        <button type="button" class="remove-link" data-cart-remove="<?= e($row['key']) ?>"
                                                style="background:none;border:0;cursor:pointer" title="Remove">&times;</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-top:1.2rem">
                        <a class="btn btn--ghost" href="<?= e(url('shop.php')) ?>">&larr; Continue shopping</a>
                        <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                            <button class="btn btn--outline" type="submit">Update cart</button>
                            <button class="btn btn--ghost" type="button" data-cart-clear>Clear cart</button>
                        </div>
                    </div>
                </form>
            </div>

            <aside class="panel">
                <h3>Order Summary</h3>
                <div class="summary-row"><span>Subtotal</span><span><?= money($subtotal) ?></span></div>
                <div class="summary-row"><span>Shipping</span><span><?= $shipping > 0 ? money($shipping) : 'Free' ?></span></div>
                <div class="summary-row total"><span>Total</span><span><?= money($total) ?></span></div>
                <?php if ($shipping > 0): ?>
                    <p class="muted" style="font-size:.82rem;margin-top:.6rem">Add <?= money(max(0, FREE_SHIPPING_OVER - $subtotal)) ?> more for free shipping.</p>
                <?php endif; ?>
                <a class="btn btn--accent btn--block btn--lg" href="<?= e(url('checkout.php')) ?>" style="margin-top:1rem">Proceed to Checkout</a>
                <p class="muted text-center" style="font-size:.8rem;margin-top:.8rem">&#128274; Secure checkout</p>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
