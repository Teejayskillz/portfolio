<?php
/**
 * Change the signed-in admin password. Forced on first sign-in.
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

require_admin();

$user = admin_user();
if (!is_array($user)) {
    redirect(admin_url('login.php'));
}

$errors = [];
$mustChange = (int) $user['must_change_password'] === 1;

if (is_post()) {
    require_csrf();

    $validated = validate_password_change($_POST, (string) $user['username']);
    $errors = $validated['errors'];

    if ($errors === []) {
        if (!password_verify((string) $validated['data']['current_password'], (string) db_value(
            'SELECT password_hash FROM admin_users WHERE id = :id',
            [':id' => (int) $user['id']]
        ))) {
            $errors['current_password'] = 'Your current password is not correct.';
        }
    }

    if ($errors === []) {
        change_admin_password($user, (string) $validated['data']['new_password']);
        flash('success', 'Password updated. Use the new password the next time you sign in.');
        redirect(admin_url('index.php'));
    }
}

$page = [
    'title'    => 'Account security',
    'subtitle' => 'Change your dashboard password',
    'active'   => 'account',
];

admin_header($page);
?>

<?php if ($mustChange): ?>
    <div class="alert alert--warning" role="status">
        <span class="alert__icon" aria-hidden="true"></span>
        <p>
            You are signed in with a temporary password. Set a new one now — the rest of the dashboard
            stays locked until you do.
        </p>
    </div>
<?php endif; ?>

<div class="form-layout">
    <div>
        <section class="form-section">
            <div class="form-section__head">
                <h2 class="form-section__title">Change password</h2>
                <p class="form-section__hint">
                    Use at least <?= e((string) config('security.password_min', 10)) ?> characters with a
                    mix of letters and numbers. Longer is stronger.
                </p>
            </div>

            <?php if ($errors !== []): ?>
                <div class="alert alert--error" role="alert">
                    <span class="alert__icon" aria-hidden="true"></span>
                    <p>Please fix the highlighted fields.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(admin_url('change-password.php')) ?>" class="admin-form" data-guard>
                <?= csrf_field() ?>

                <div class="field<?= isset($errors['current_password']) ? ' field--error' : '' ?>">
                    <label for="current-password">Current password</label>
                    <input type="password" id="current-password" name="current_password" required maxlength="200"
                           autocomplete="current-password" autofocus>
                    <?php if (isset($errors['current_password'])): ?>
                        <p class="field__error"><?= e($errors['current_password']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['new_password']) ? ' field--error' : '' ?>" style="margin-top: .8rem">
                    <label for="new-password">New password</label>
                    <input type="password" id="new-password" name="new_password" required minlength="<?= e((string) config('security.password_min', 10)) ?>"
                           maxlength="200" autocomplete="new-password">
                    <?php if (isset($errors['new_password'])): ?>
                        <p class="field__error"><?= e($errors['new_password']) ?></p>
                    <?php else: ?>
                        <p class="field__hint">Avoid names, dates and reused passwords.</p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['confirm_password']) ? ' field--error' : '' ?>" style="margin-top: .8rem">
                    <label for="confirm-password">Repeat new password</label>
                    <input type="password" id="confirm-password" name="confirm_password" required maxlength="200"
                           autocomplete="new-password">
                    <?php if (isset($errors['confirm_password'])): ?>
                        <p class="field__error"><?= e($errors['confirm_password']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <a class="btn btn--ghost btn--sm" href="<?= e(admin_url('index.php')) ?>">Cancel</a>
                    <button class="btn btn--primary btn--sm" type="submit">
                        Update password <?= icon('lock', 'icon', 14) ?>
                    </button>
                </div>
            </form>
        </section>
    </div>

    <div>
        <section class="form-section">
            <div class="form-section__head">
                <h2 class="form-section__title">Account</h2>
            </div>
            <dl class="system-list">
                <div class="system-row">
                    <dt>Username</dt>
                    <dd><?= e((string) $user['username']) ?></dd>
                </div>
                <div class="system-row">
                    <dt>Display name</dt>
                    <dd><?= e((string) $user['display_name']) ?></dd>
                </div>
                <div class="system-row">
                    <dt>Last sign-in</dt>
                    <dd><?= e(format_datetime((string) $user['last_login_at']) ?: 'this session') ?></dd>
                </div>
                <div class="system-row">
                    <dt>Password status</dt>
                    <dd class="<?= $mustChange ? 'is-fail' : 'is-ok' ?>">
                        <?= $mustChange ? 'Temporary — change now' : 'Custom password set' ?>
                    </dd>
                </div>
            </dl>
        </section>

        <section class="form-section">
            <div class="form-section__head">
                <h2 class="form-section__title">Security notes</h2>
            </div>
            <ul class="system-list">
                <li class="system-row"><dt>Session timeout</dt><dd><?= e((string) round((int) config('security.session_idle', 1800) / 60)) ?> min idle</dd></li>
                <li class="system-row"><dt>Login throttling</dt><dd><?= e((string) config('security.login_max_attempts', 5)) ?> attempts / <?= e((string) round((int) config('security.login_window', 900) / 60)) ?> min</dd></li>
                <li class="system-row"><dt>CSRF protection</dt><dd class="is-ok">every POST</dd></li>
                <li class="system-row"><dt>Password storage</dt><dd>bcrypt (password_hash)</dd></li>
            </ul>
        </section>
    </div>
</div>

<?php admin_footer(); ?>
