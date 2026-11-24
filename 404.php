<?php
/** Friendly 404 page. Served by Apache via "ErrorDocument 404 /404.php". */
require_once __DIR__ . '/includes/functions.php';

http_response_code(404);
$pageTitle = 'Page not found';
$noindex   = true;
require __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="empty" style="padding:4rem 0">
        <div style="font-family:var(--font-head);font-size:4.5rem;font-weight:700;color:var(--line);line-height:1">404</div>
        <h1>Page not found</h1>
        <p class="muted">The page you're looking for doesn't exist or may have been moved.</p>
        <p style="margin-top:1.2rem;display:flex;gap:.8rem;justify-content:center;flex-wrap:wrap">
            <a class="btn btn--accent btn--lg" href="<?= e(url('index.php')) ?>">Back to home</a>
            <a class="btn btn--outline btn--lg" href="<?= e(url('shop.php')) ?>">Continue shopping</a>
        </p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
