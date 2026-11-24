<?php
/**
 * Product review submission.
 * Receives the review form (POST) from product.php and stores it as pending.
 */
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('shop.php');
}

$productId = (int)($_POST['product_id'] ?? 0);
$product   = $productId ? get_product($productId) : null;
$back      = $product ? url('product.php?slug=' . urlencode($product['slug'])) . '#reviews' : url('shop.php');

if (!csrf_verify($_POST['csrf'] ?? null)) {
    set_flash('error', 'Your session expired. Please try again.');
    redirect($back);
}

require_login();

if (!$product || (int)$product['is_active'] !== 1) {
    set_flash('error', 'That product is no longer available.');
    redirect('shop.php');
}

$user   = current_user();
$rating = (int)($_POST['rating'] ?? 0);
$title  = trim((string)($_POST['title'] ?? ''));
$body   = trim((string)($_POST['body'] ?? ''));

if ($rating < 1 || $rating > 5) {
    set_flash('error', 'Please choose a star rating from 1 to 5.');
    redirect($back);
}
if (mb_strlen($body) < 5) {
    set_flash('error', 'Please write a few words about the product.');
    redirect($back);
}
if (mb_strlen($body) > 2000) {
    $body = mb_substr($body, 0, 2000);
}
if (mb_strlen($title) > 190) {
    $title = mb_substr($title, 0, 190);
}

$pdo = db();
$exists = $pdo->prepare('SELECT COUNT(*) FROM reviews WHERE product_id = ? AND user_id = ?');
$exists->execute([$productId, (int)$user['id']]);
if ((int)$exists->fetchColumn() > 0) {
    set_flash('info', 'You have already reviewed this product.');
    redirect($back);
}

$pdo->prepare('INSERT INTO reviews (product_id, user_id, author_name, rating, title, body, status)
               VALUES (?,?,?,?,?,?,?)')
    ->execute([$productId, (int)$user['id'], $user['name'], $rating, $title ?: null, $body, 'pending']);

set_flash('success', 'Thank you! Your review has been submitted and is awaiting approval.');
redirect($back);
