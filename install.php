<?php
/**
 * One-time installer.
 *
 * Creates the SQLite database, the schema, the first admin account and the
 * starter content. Refuses to run again once an admin account exists.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

send_noindex_header();

$installed = Database::isInstalled();

if ($installed) {
    http_response_code(409);
}

$uploadsPath = (string) config('uploads.path');

$checks = [
    [
        'label' => 'PHP version',
        'value' => PHP_VERSION,
        'ok'    => version_compare(PHP_VERSION, '8.0.0', '>='),
        'note'  => 'PHP 8.0 or newer is required.',
    ],
    [
        'label' => 'PDO SQLite extension',
        'value' => extension_loaded('pdo_sqlite') ? 'available' : 'missing',
        'ok'    => extension_loaded('pdo_sqlite'),
        'note'  => 'Enable pdo_sqlite in cPanel → Select PHP Version → Extensions.',
    ],
    [
        'label' => 'mbstring extension',
        'value' => extension_loaded('mbstring') ? 'available' : 'missing',
        'ok'    => extension_loaded('mbstring'),
        'note'  => 'Used for safe string handling and UTF-8.',
    ],
    [
        'label' => 'Fileinfo extension',
        'value' => extension_loaded('fileinfo') ? 'available' : 'missing',
        'ok'    => extension_loaded('fileinfo'),
        'note'  => 'Used to verify uploaded image types.',
    ],
    [
        'label' => 'Storage directory',
        'value' => APP_STORAGE,
        'ok'    => (is_dir(APP_STORAGE) && is_writable(APP_STORAGE)) || ensure_dir(APP_STORAGE),
        'note'  => 'Must be writable by PHP. Ideally outside public_html.',
    ],
    [
        'label' => 'Uploads directory',
        'value' => $uploadsPath,
        'ok'    => (is_dir($uploadsPath) && is_writable($uploadsPath)) || ensure_dir($uploadsPath),
        'note'  => 'Where project images are stored.',
    ],
    [
        'label' => 'Mail function',
        'value' => function_exists('mail') ? 'available' : 'missing',
        'ok'    => function_exists('mail'),
        'note'  => 'Required for contact-form delivery.',
    ],
];

$criticalOk = true;
foreach ($checks as $check) {
    if (!$check['ok']) {
        $criticalOk = false;
    }
}

$errors = [];
$success = false;
$form = [
    'username' => 'admin',
    'seed_projects' => true,
];

if (!$installed && is_post() && $criticalOk) {
    require_csrf();

    $username = clean_single_line(post_string('username'));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    $seedProjects = isset($_POST['seed_projects']);
    $form = ['username' => $username, 'seed_projects' => $seedProjects];

    if (preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username) !== 1) {
        $errors['username'] = 'Use 3–50 characters: letters, numbers, dots, dashes or underscores.';
    }

    if ($password === 'admin') {
        // The documented temporary credential is allowed once; the account is
        // forced to choose a new password on the first sign-in.
    } else {
        foreach (password_policy_errors($password, $username) as $message) {
            $errors['password'] = $errors['password'] ?? $message;
        }
    }

    if ($password !== $confirm) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }

    if ($errors === []) {
        try {
            ensure_dir(APP_STORAGE);
            ensure_dir(APP_STORAGE . '/logs');
            ensure_upload_directory();

            Database::migrate();
            seed_reference_data(db());
            create_admin(db(), $username, $password, true, (string) config('owner.name'));

            $seeded = 0;
            if ($seedProjects) {
                $seeded = seed_owner_projects(db());
            }

            @file_put_contents(
                APP_STORAGE . '/install.lock',
                json_encode([
                    'installed_at' => now_utc(),
                    'php'          => PHP_VERSION,
                    'seeded'       => $seeded,
                ], JSON_PRETTY_PRINT)
            );

            log_activity(null, 'system.installed', 'system', null, 'Portfolio installed');

            $success = true;
            $installed = true;
        } catch (Throwable $exception) {
            app_log('install', 'Installation failed', ['error' => $exception->getMessage()]);
            $errors['general'] = APP_DEBUG
                ? 'Installation failed: ' . $exception->getMessage()
                : 'Installation failed. Check that the storage and uploads directories are writable, then try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Install — <?= e(config('owner.name')) ?> portfolio</title>
    <meta name="theme-color" content="#050807">
    <link rel="icon" type="image/png" href="<?= e(asset_url('img/brand-favicon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <link rel="stylesheet" href="<?= e(asset_url('css/premium.css')) ?>">
</head>
<body class="site install-page">
<main class="setup">
    <div class="container container--narrow">
        <a class="brand" href="<?= e(home_url()) ?>" aria-label="<?= e(config('owner.name')) ?> — home">
            <span class="brand__mark" aria-hidden="true">TA</span>
            <span class="brand__text">
                <strong>Tijani<span class="brand__accent">.</span>dev</strong>
                <small>Portfolio installer</small>
            </span>
        </a>

        <header class="setup__head">
            <p class="eyebrow">Installation</p>
            <h1 class="setup__title">Set up your portfolio</h1>
            <p class="setup__lead">
                This creates the SQLite database, your admin account and the starter content.
                Run it once, then delete <code>install.php</code> from the server.
            </p>
        </header>

        <?php if ($success): ?>
            <div class="setup__card">
                <span class="status-page__icon"><?= icon('check', 'icon', 26) ?></span>
                <h2 class="setup__card-title">Installation complete</h2>
                <p class="setup__lead">
                    The database is ready. Sign in with the credentials you just created — you will be
                    asked to set a new password immediately.
                </p>
                <div class="setup__actions">
                    <a class="btn btn--primary" href="<?= e(admin_url('login.php')) ?>">
                        Open the dashboard <?= icon('arrow-up-right', 'icon', 16) ?>
                    </a>
                    <a class="btn btn--ghost" href="<?= e(home_url()) ?>">View the site</a>
                </div>
                <div class="alert alert--warning" role="status">
                    <span class="alert__icon" aria-hidden="true"></span>
                    <p>Delete <code>install.php</code> from your hosting account now. The installer
                        refuses to run again while an admin account exists, but removing it is safer.</p>
                </div>
            </div>
        <?php elseif ($installed): ?>
            <div class="setup__card">
                <span class="status-page__icon"><?= icon('shield', 'icon', 26) ?></span>
                <h2 class="setup__card-title">Already installed</h2>
                <p class="setup__lead">
                    An admin account already exists in the database, so the installer is disabled.
                    Sign in to continue, or restore a backup if you need to start over.
                </p>
                <div class="setup__actions">
                    <a class="btn btn--primary" href="<?= e(admin_url('login.php')) ?>">Go to sign in</a>
                    <a class="btn btn--ghost" href="<?= e(home_url()) ?>">View the site</a>
                </div>
            </div>
        <?php else: ?>
            <?php if ($errors !== []): ?>
                <div class="alert alert--error" role="alert">
                    <span class="alert__icon" aria-hidden="true"></span>
                    <p>
                        <?= e($errors['general'] ?? 'Please fix the highlighted fields and try again.') ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="setup__card">
                <h2 class="setup__card-title">Server check</h2>
                <ul class="setup-list">
                    <?php foreach ($checks as $check): ?>
                        <li class="setup-list__item<?= $check['ok'] ? ' is-ok' : ' is-fail' ?>">
                            <span class="setup-list__icon"><?= icon($check['ok'] ? 'check' : 'close', 'icon', 14) ?></span>
                            <span class="setup-list__body">
                                <strong><?= e($check['label']) ?></strong>
                                <span><?= e($check['value']) ?></span>
                                <?php if (!$check['ok']): ?>
                                    <em><?= e($check['note']) ?></em>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="setup__card">
                <h2 class="setup__card-title">Admin account</h2>

                <form method="post" action="<?= e(public_url('install.php')) ?>" class="form-grid" data-enhance>
                    <?= csrf_field() ?>

                    <div class="field field--full<?= isset($errors['username']) ? ' field--error' : '' ?>">
                        <label for="install-username">Username</label>
                        <input type="text" id="install-username" name="username"
                               value="<?= e($form['username']) ?>" required minlength="3" maxlength="50"
                               autocomplete="username" autocapitalize="none" spellcheck="false">
                        <?php if (isset($errors['username'])): ?>
                            <p class="field__error"><?= e($errors['username']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field<?= isset($errors['password']) ? ' field--error' : '' ?>">
                        <label for="install-password">Temporary password</label>
                        <input type="password" id="install-password" name="password" value="admin"
                               required minlength="4" maxlength="200" autocomplete="new-password">
                        <?php if (isset($errors['password'])): ?>
                            <p class="field__error"><?= e($errors['password']) ?></p>
                        <?php else: ?>
                            <p class="field__hint">The default is <code>admin</code>. You must change it on first sign-in.</p>
                        <?php endif; ?>
                    </div>

                    <div class="field<?= isset($errors['password_confirm']) ? ' field--error' : '' ?>">
                        <label for="install-password-confirm">Confirm password</label>
                        <input type="password" id="install-password-confirm" name="password_confirm" value="admin"
                               required minlength="4" maxlength="200" autocomplete="new-password">
                        <?php if (isset($errors['password_confirm'])): ?>
                            <p class="field__error"><?= e($errors['password_confirm']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field field--full setup__checkbox">
                        <label class="setup-check">
                            <input type="checkbox" name="seed_projects" value="1" checked>
                            <span>
                                Add starter projects from my public GitHub repositories
                                <small>Three real projects with short, factual copy. Review and edit them in the dashboard.</small>
                            </span>
                        </label>
                    </div>

                    <div class="field field--full">
                        <button class="btn btn--primary" type="submit"<?= $criticalOk ? '' : ' disabled' ?>>
                            Run installation <?= icon('arrow-right', 'icon', 16) ?>
                        </button>
                        <?php if (!$criticalOk): ?>
                            <p class="field__error">Fix the failed server checks above before installing.</p>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <p class="setup__foot">
            <?= e(config('owner.name')) ?> · <?= e(config('owner.location')) ?> ·
            <a href="<?= e(home_url()) ?>">Back to site</a>
        </p>
    </div>
</main>
<script src="<?= e(asset_url('js/site.js')) ?>" defer></script>
</body>
</html>
