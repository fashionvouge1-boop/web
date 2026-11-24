<?php
/**
 * Wishlist toggle.
 * Accepts a POST from the heart buttons; returns JSON for AJAX callers,
 * otherwise redirects back to the referring page.
 */
require_once __DIR__ . '/includes/functions.php';

$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
$return = (string)($_POST['return'] ?? ($_SERVER['HTTP_REFERER'] ?? url('shop.php')));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('account/wishlist.php');
}

if (!csrf_verify($_POST['csrf'] ?? null)) {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['error' => 'invalid_csrf']); exit; }
    set_flash('error', 'Your session expired. Please try again.');
    redirect($return);
}

if (!is_logged_in()) {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['error' => 'login_required']); exit; }
    $_SESSION['redirect_after_login'] = $return;
    set_flash('info', 'Please sign in to use your wishlist.');
    redirect('account/login.php');
}

$productId = (int)($_POST['product_id'] ?? 0);
$product   = $productId ? get_product($productId) : null;

if (!$product || (int)$product['is_active'] !== 1) {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['error' => 'not_found']); exit; }
    set_flash('error', 'That product is unavailable.');
    redirect($return);
}

$saved = wishlist_toggle($productId);

/* Count straight from the database (the in-request cache would be stale). */
$cStmt = db()->prepare('SELECT COUNT(*) FROM wishlist WHERE user_id = ?');
$cStmt->execute([(int)current_user()['id']]);
$count = (int)$cStmt->fetchColumn();

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'saved' => $saved, 'count' => $count]);
    exit;
}

set_flash('success', $saved ? 'Added to your wishlist.' : 'Removed from your wishlist.');
redirect($return);
