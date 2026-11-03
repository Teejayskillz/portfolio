<?php
/**
 * Admin sign-in.
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

if (admin_session_active()) {
    redirect(admin_url('index.php'));
}

$errors = [];
$username = '';
$notice = '';

if (is_post()) {
    require_csrf();

    $validated = validate_login($_POST);
    $username = (string) $validated['data']['username'];
    $errors = $validated['errors'];

    if ($errors === []) {
        $result = attempt_login($username, (string) $validated['data']['password'], client_ip());

        if ($result['ok'] && is_array($result['user'])) {
            $mustChange = (int) $result['user']['must_change_password'] === 1;
            establish_admin_session($result['user']);
            flash('success', 'Welcome back, ' . admin_display_name() . '.');
            redirect($mustChange ? admin_url('change-password.php') : admin_url('index.php'));
        }

        $errors['general'] = $result['message'];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="description" content="Sign in to the private portfolio dashboard to manage projects, images and messages.">
    <link rel="canonical" href="<?= e(absolute_url(admin_url('login.php'))) ?>">
    <script>document.documentElement.classList.add('has-js');</script>
    <title>Sign in — <?= e(config('owner.name')) ?> dashboard</title>
    <meta name="theme-color" content="#050807">
    <meta name="color-scheme" content="dark">
    <link rel="icon" type="image/png" href="<?= e(asset_url('img/brand-favicon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <link rel="stylesheet" href="<?= e(asset_url('css/premium.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/admin.css')) ?>">
</head>
<body class="site admin-body">
<main class="auth-page">
    <div class="auth-shell">
        <div class="auth-brand">
            <a class="brand" href="<?= e(home_url()) ?>" aria-label="<?= e(config('owner.name')) ?> — home">
                <span class="brand__mark" aria-hidden="true">TA</span>
                <span class="brand__text">
                    <strong>Tijani<span class="brand__accent">.</span>dev</strong>
                    <small>Portfolio dashboard</small>
                </span>
            </a>
        </div>

        <div class="auth-card">
            <p class="auth-card__eyebrow">Restricted area</p>
            <h1 class="auth-card__title">Sign in to manage your work</h1>
            <p class="auth-card__sub">Use the admin account created during installation.</p>

            <?php if ($errors !== []): ?>
                <div class="alert alert--error" role="alert">
                    <span class="alert__icon" aria-hidden="true"></span>
                    <p><?= e($errors['general'] ?? 'Please check the fields below.') ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(admin_url('login.php')) ?>" data-guard>
                <?= csrf_field() ?>

                <div class="field<?= isset($errors['username']) ? ' field--error' : '' ?>">
                    <label for="login-username">Username</label>
                    <input type="text" id="login-username" name="username" value="<?= e($username) ?>"
                           required maxlength="60" autocomplete="username" autocapitalize="none" spellcheck="false" autofocus>
                    <?php if (isset($errors['username'])): ?>
                        <p class="field__error"><?= e($errors['username']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['password']) ? ' field--error' : '' ?>">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" required maxlength="200"
                           autocomplete="current-password">
                    <?php if (isset($errors['password'])): ?>
                        <p class="field__error"><?= e($errors['password']) ?></p>
                    <?php endif; ?>
                </div>

                <button class="btn btn--primary" type="submit">
                    Sign in <?= icon('arrow-right', 'icon', 16) ?>
                </button>
            </form>
        </div>

        <p class="auth-foot">
            <a href="<?= e(home_url()) ?>">← Back to the website</a>
        </p>
    </div>
</main>
</body>
</html>
