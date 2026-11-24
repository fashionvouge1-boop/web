<?php
/**
 * Admin sign-in. Only users with the "admin" role may enter the panel.
 */
require_once __DIR__ . '/../includes/functions.php';

if (is_admin()) {
    redirect('admin/dashboard.php');
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter your email and password.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (($user['role'] ?? 'customer') !== 'admin') {
                $errors[] = 'This account does not have administrator access.';
            } else {
                $_SESSION['user_id'] = (int)$user['id'];
                set_flash('success', 'Welcome back, ' . $user['name'] . '.');
                $to = $_SESSION['redirect_after_login'] ?? 'admin/dashboard.php';
                unset($_SESSION['redirect_after_login']);
                redirect($to);
            }
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Sign In &middot; <?= e(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
</head>
<body class="admin">
<div class="admin-login">
    <div class="admin-login__card">
        <div class="admin-login__brand">
            <span class="admin-brand__mark">S</span>
            <div>
                <strong style="font-family:var(--font-head)"><?= e(SITE_NAME) ?></strong>
                <div class="muted" style="font-size:.78rem">Admin Panel</div>
            </div>
        </div>

        <h1>Admin sign in</h1>
        <p class="muted">Enter your administrator credentials to continue.</p>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert--error" style="margin:.8rem 0"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" style="margin-top:1rem">
            <?= csrf_field() ?>
            <div class="field">
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" required value="<?= e($email) ?>" autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>
            <button class="btn btn--accent btn--block btn--lg" type="submit">Sign in</button>
        </form>

        <p class="text-center muted" style="margin-top:1.2rem;font-size:.82rem">
            <a href="<?= e(url('index.php')) ?>">&larr; Back to store</a>
        </p>
    </div>
</div>
</body>
</html>
