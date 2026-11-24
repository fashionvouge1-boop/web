<?php
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect('account/dashboard.php');
}

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }
    $old['name']  = trim((string)($_POST['name'] ?? ''));
    $old['email'] = trim((string)($_POST['email'] ?? ''));
    $old['phone'] = trim((string)($_POST['phone'] ?? ''));
    $password     = (string)($_POST['password'] ?? '');
    $confirm      = (string)($_POST['confirm_password'] ?? '');

    if ($old['name'] === '')  $errors[] = 'Please enter your full name.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $check = db()->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$old['email']]);
        if ($check->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, phone) VALUES (?,?,?,?)');
            $stmt->execute([$old['name'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $old['phone']]);
            $_SESSION['user_id'] = (int)db()->lastInsertId();
            set_flash('success', 'Welcome to ' . SITE_NAME . ', ' . $old['name'] . '!');
            redirect($_SESSION['redirect_after_login'] ?? 'account/dashboard.php');
        }
    }
}

$pageTitle = 'Create Account';
require __DIR__ . '/../includes/header.php';
?>
<div class="container">
    <div class="auth-wrap">
        <div class="auth-card">
            <h1>Create your account</h1>
            <p class="muted">Join <?= e(SITE_NAME) ?> to shop faster and track your orders.</p>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert--error" style="margin:.8rem 0"><?= e($err) ?></div>
            <?php endforeach; ?>

            <form method="post" style="margin-top:1rem">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="name">Full name</label>
                    <input id="name" name="name" required value="<?= e($old['name']) ?>">
                </div>
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" type="email" name="email" required value="<?= e($old['email']) ?>">
                </div>
                <div class="field">
                    <label for="phone">Phone (optional)</label>
                    <input id="phone" name="phone" value="<?= e($old['phone']) ?>">
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label for="password">Password</label>
                        <input id="password" type="password" name="password" required minlength="6">
                    </div>
                    <div class="field">
                        <label for="confirm_password">Confirm password</label>
                        <input id="confirm_password" type="password" name="confirm_password" required minlength="6">
                    </div>
                </div>
                <button class="btn btn--accent btn--block btn--lg" type="submit" style="margin-top:1rem">Create account</button>
            </form>

            <p class="auth-alt">Already have an account? <a href="<?= e(url('account/login.php')) ?>">Sign in</a></p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
