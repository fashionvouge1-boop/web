<?php
/**
 * Admin — import product data from a remote product link.
 * Fetches the page and extracts name, description, price, discount,
 * colours, sizes and the list of product images as JSON.
 */
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

function json_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if (!is_admin()) {
    json_out(['ok' => false, 'error' => 'Administrator access required.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf'] ?? null)) {
    json_out(['ok' => false, 'error' => 'Your session expired. Please reload the page.'], 400);
}

$url = trim((string)($_POST['url'] ?? ''));
if (!preg_match('#^https?://#i', $url)) {
    json_out(['ok' => false, 'error' => 'Enter a full product URL starting with http:// or https://']);
}

$data = scrape_product_url($url);
if ($data === null) {
    json_out(['ok' => false, 'error' => 'Could not read that page. Check the link and try again.'], 422);
}

$data['ok'] = true;
json_out($data);
