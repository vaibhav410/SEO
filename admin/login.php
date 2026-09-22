<?php
define('ADMIN_PUBLIC', true);
require __DIR__ . '/_init.php';

header('X-Robots-Tag: noindex, nofollow');
if (current_user()) {
    redirect('/admin/dashboard.php');
}

$error = '';
$email = '';
if (is_post()) {
    csrf_verify();
    $email = input('email');
    $password = (string) ($_POST['password'] ?? '');

    if (too_many_login_attempts($email)) {
        http_response_code(429);
        $error = 'Too many failed attempts. Please wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } elseif (attempt_login($email, $password)) {
        $to = $_SESSION['intended'] ?? '';
        unset($_SESSION['intended']);
        // Only follow a local admin path, never an external URL.
        $adminPrefix = base_path() . '/admin/';
        if (is_string($to) && str_starts_with($to, $adminPrefix) && !str_contains($to, '//')) {
            header('Location: ' . $to, true, 302);
            exit;
        }
        redirect('/admin/dashboard.php');
    } else {
        $error = 'Email or password is incorrect.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in · GrowthHub Admin</title>
    <link rel="icon" href="<?= e(url('/assets/images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="auth-page">
<main class="auth-card">
    <div class="auth-brand">
        <img src="<?= e(url('/assets/images/logo.svg')) ?>" alt="" width="40" height="40">
        <div><strong>SYSCOM GrowthHub</strong><span>SEO &amp; growth admin</span></div>
    </div>
    <h1>Sign in</h1>
    <?= render_flash() ?>
    <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="username" autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    </form>
    <p class="auth-foot"><a href="<?= e(url('/')) ?>">&larr; Back to website</a></p>
</main>
</body>
</html>
