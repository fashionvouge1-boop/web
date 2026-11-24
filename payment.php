<?php
/**
 * Order placement + payment processing.
 * Receives the checkout form (POST), stores the order, then hands off
 * to the selected payment gateway.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/payment.php';

/* ---------------- Guard ---------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('checkout.php');
}
if (!csrf_verify($_POST['csrf'] ?? null)) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('checkout.php');
}

$cart     = cart_items();
$items    = $cart['items'];
$subtotal = $cart['subtotal'];

if (!$items) {
    set_flash('info', 'Your cart is empty.');
    redirect('shop.php');
}

/* ---------------- Validate billing fields ---------------- */
$fields = ['name', 'email', 'phone', 'address', 'city'];
$errors = [];
$data = [];
foreach (['name', 'email', 'phone', 'address', 'city', 'state', 'zip', 'country', 'notes'] as $f) {
    $data[$f] = trim((string)($_POST[$f] ?? ''));
}
foreach ($fields as $f) {
    if ($data[$f] === '') {
        $errors[] = ucfirst($f) . ' is required.';
    }
}
if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}
if ($data['country'] === '') {
    $data['country'] = 'India';
}

$gatewayCode = (string)($_POST['payment_method'] ?? '');
$gateway = get_gateway($gatewayCode);
if (!$gateway || !$gateway->isEnabled()) {
    $errors[] = 'Please choose a valid payment method.';
}

/* Cash on Delivery is offered but not serviceable — force an online payment. */
if (gateway_blocked($gatewayCode)) {
    $_SESSION['checkout_old'] = $data;
    set_flash('error', COD_BLOCK_MESSAGE);
    redirect('checkout.php');
}

if ($errors) {
    $_SESSION['checkout_old'] = $data;
    set_flash('error', implode(' ', $errors));
    redirect('checkout.php');
}

/* ---------------- Store the order ---------------- */
$shipping = shipping_fee($subtotal);

/* Re-validate any coupon that was applied at checkout. */
$coupon   = null;
$discount = 0.0;
if (!empty($_SESSION['coupon_code'])) {
    $cv = coupon_validate((string)$_SESSION['coupon_code'], $subtotal);
    if ($cv['ok']) {
        $coupon   = $cv['coupon'];
        $discount = $cv['discount'];
    }
}

$total = max(0, $subtotal + $shipping - $discount);
$user  = current_user();

$pdo = db();
$pdo->beginTransaction();

$orderNumber = generate_order_number();
$stmt = $pdo->prepare('INSERT INTO orders
    (user_id, order_number, subtotal, shipping, discount, coupon_code, total, status, payment_method, payment_status,
     shipping_name, shipping_email, shipping_phone, shipping_address, shipping_city,
     shipping_state, shipping_zip, shipping_country, notes)
    VALUES (?,?,?,?,?,?,?,?,?,?, ?,?,?,?,?, ?,?,?,?)');
$stmt->execute([
    $user['id'] ?? null, $orderNumber, $subtotal, $shipping, $discount,
    $coupon['code'] ?? null, $total,
    'pending', $gateway->code(), 'pending',
    $data['name'], $data['email'], $data['phone'], $data['address'], $data['city'],
    $data['state'], $data['zip'], $data['country'], $data['notes'],
]);
$orderId = (int)$pdo->lastInsertId();

$itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, price, qty, subtotal, size, color)
                           VALUES (?,?,?,?,?,?,?,?)');
foreach ($items as $row) {
    $p = $row['product'];
    $itemStmt->execute([
        $orderId, $p['id'], $p['name'], product_price($p), $row['qty'], $row['line_total'],
        $row['size'] !== '' ? $row['size'] : null,
        $row['color'] !== '' ? $row['color'] : null,
    ]);
}
$pdo->commit();

/* ---------------- Run the gateway ---------------- */
$order = [
    'id'            => $orderId,
    'order_number'  => $orderNumber,
    'total'         => $total,
    'shipping_name' => $data['name'],
    'shipping_email'=> $data['email'],
    'shipping_phone'=> $data['phone'],
];

try {
    $result = $gateway->pay($order);
} catch (Throwable $ex) {
    // Payment could not be initialised — roll the order back so the customer can retry.
    db()->prepare('DELETE FROM orders WHERE id = ?')->execute([$orderId]);
    set_flash('error', 'We could not start the payment: ' . $ex->getMessage());
    redirect('checkout.php');
}

/* ---------------- Record the payment result ---------------- */
$status = match ($result['status']) {
    'paid'   => ['paid', 'processing'],
    'pending'=> ['pending', 'pending'],
    default  => ['pending', 'pending'],
};

db()->prepare('UPDATE orders SET payment_status = ?, status = ?, payment_ref = ? WHERE id = ?')
    ->execute([$status[0], $status[1], $result['ref'] ?? null, $orderId]);

/* Record coupon usage and release it from the session. */
if ($coupon) {
    db()->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?')->execute([(int)$coupon['id']]);
    unset($_SESSION['coupon_code']);
}

/* Reserve stock and email the customer their confirmation. */
order_decrement_stock($orderId);
send_order_confirmation($orderId);

// Let the (possibly guest) customer view this confirmation.
$_SESSION['my_orders'][$orderNumber] = true;

// Only empty the cart once the order is safely placed.
cart_clear();

/* ---------------- Hand off to the gateway / confirmation ---------------- */
if (!empty($result['html'])) {
    $pageTitle = 'Complete your payment';
    require __DIR__ . '/includes/header.php';
    echo '<div class="container" style="padding:3rem 0;text-align:center">'
       . '<h1>Completing your payment…</h1>'
       . '<p class="muted">Order <strong>' . e($orderNumber) . '</strong> — do not close this window.</p>'
       . '</div>';
    echo $result['html'];
    if (!empty($result['redirect'])) {
        echo '<script>window.location.href=' . json_encode(url($result['redirect'])) . ';</script>';
    }
    require __DIR__ . '/includes/footer.php';
    exit;
}

if (!empty($result['redirect'])) {
    redirect($result['redirect']);
}

redirect('payment-success.php?order=' . urlencode($orderNumber));
