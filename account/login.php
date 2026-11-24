<?php
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect('account/dashboard.php');
}

$errors = [];
$email = '';

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
            $_SESSION['user_id'] = (int)$user['id'];
            set_flash('success', 'Welcome back, ' . $user['name'] . '!');
            $to = $_SESSION['redirect_after_login'] ?? 'account/dashboard.php';
            unset($_SESSION['redirect_after_login']);
            redirect($to);
        }
        $errors[] = 'Invalid email or password.';
    }
}

$pageTitle = 'Sign In';
require __DIR__ . '/../includes/header.php';
?>
<div class="container">
    <div class="auth-wrap">
        <div class="auth-card">
            <h1>Welcome back</h1>
            <p class="muted">Sign in to your <?= e(SITE_NAME) ?> account.</p>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert--error" style="margin:.8rem 0"><?= e($err) ?></div>
            <?php endforeach; ?>

            <form method="post" style="margin-top:1rem">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" type="email" name="email" required value="<?= e($email) ?>">
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required>
                </div>
                <button class="btn btn--accent btn--block btn--lg" type="submit" style="margin-top:1rem">Sign in</button>
            </form>

            <p class="auth-alt">New to <?= e(SITE_NAME) ?>? <a href="<?= e(url('account/register.php')) ?>">Create an account</a></p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
