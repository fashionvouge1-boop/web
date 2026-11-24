<?php
/**
 * Apply / remove a coupon code. Stores the chosen code in the session;
 * the discount itself is always recalculated from the live cart.
 */
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('checkout.php');
}
if (!csrf_verify($_POST['csrf'] ?? null)) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect('checkout.php');
}

$action = (string)($_POST['action'] ?? 'apply');

if ($action === 'remove') {
    unset($_SESSION['coupon_code']);
    set_flash('info', 'Coupon removed.');
    redirect('checkout.php');
}

$cart     = cart_items();
$subtotal = (float)$cart['subtotal'];
$code     = (string)($_POST['code'] ?? '');

$res = coupon_validate($code, $subtotal);
if ($res['ok']) {
    $_SESSION['coupon_code'] = $res['coupon']['code'];
    set_flash('success', 'Coupon ' . $res['coupon']['code'] . ' applied — you saved ' . money($res['discount']) . '.');
} else {
    set_flash('error', $res['error']);
}

redirect('checkout.php');
