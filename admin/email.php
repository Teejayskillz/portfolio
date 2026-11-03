<?php
/**
 * Email settings: owner notifications + visitor confirmation.
 *
 * Stored in SQLite through includes/settings.php, so the owner never has to
 * edit config files to change an address or the confirmation copy.
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

require_admin();

$saved = mail_settings();
$custom = mail_settings_are_custom();
$errors = [];

$form = [
    'to'                   => (string) $saved['to'],
    'from'                 => (string) $saved['from'],
    'from_name'            => (string) $saved['from_name'],
    'confirmation_enabled' => !empty($saved['confirmation_enabled']) ? 1 : 0,
    'confirmation_subject' => (string) $saved['confirmation_subject'],
    'confirmation_body'    => (string) $saved['confirmation_body'],
];

if (is_post()) {
    require_csrf();
    $action = post_string('action', 'save');

    if ($action === 'test') {
        $result = send_test_email(mail_settings());

        if ($result['ok']) {
            flash('success', 'Test email accepted by the mail server. Check the inbox for ' . mail_settings()['to'] . ' (and the spam folder).');
        } else {
            flash('error', 'Test email failed: ' . $result['error']);
        }

        redirect(admin_url('email.php'));
    }

    if ($action === 'reset') {
        if (setting_delete_prefix('mail.')) {
            log_activity(current_actor_id(), 'settings.email_reset', 'settings', null, 'Reset email settings to defaults');
            flash('success', 'Email settings reset to the values in the configuration file.');
        } else {
            flash('error', 'The email settings could not be reset.');
        }

        redirect(admin_url('email.php'));
    }

    $validated = validate_email_settings($_POST);
    $errors = $validated['errors'];
    $form = [
        'to'                   => post_string('to'),
        'from'                 => post_string('from'),
        'from_name'            => post_string('from_name'),
        'confirmation_enabled' => isset($_POST['confirmation_enabled']) ? 1 : 0,
        'confirmation_subject' => post_string('confirmation_subject'),
        'confirmation_body'    => (string) ($_POST['confirmation_body'] ?? ''),
    ];

    if ($errors === []) {
        $data = $validated['data'];
        $savedOk = true;

        foreach ([
            'mail.to'                   => (string) $data['to'],
            'mail.from'                 => (string) $data['from'],
            'mail.from_name'            => (string) $data['from_name'],
            'mail.confirmation_enabled' => (string) $data['confirmation_enabled'],
            'mail.confirmation_subject' => (string) $data['confirmation_subject'],
            'mail.confirmation_body'    => (string) $data['confirmation_body'],
        ] as $key => $value) {
            $savedOk = setting_set($key, $value) && $savedOk;
        }

        if ($savedOk) {
            log_activity(current_actor_id(), 'settings.email_saved', 'settings', null, 'Updated email settings');
            flash('success', 'Email settings saved. New contact submissions will use them immediately.');
            redirect(admin_url('email.php'));
        }

        $errors['general'] = 'The settings could not be saved. Check that the storage directory is writable.';
    }
}

$transport = mail_transport();
$activeSettings = mail_settings();

$page = [
    'title'    => 'Email settings',
    'subtitle' => 'Notifications and visitor confirmations',
    'active'   => 'email',
    'actions'  => '<a class="btn btn--ghost btn--sm" href="' . e(admin_url('messages.php')) . '">'
        . icon('message', 'icon', 14) . ' Messages</a>',
];

admin_header($page);
?>

<div class="page-head">
    <div>
        <h2 class="page-head__title">Email configuration</h2>
        <p class="page-head__sub">
            Choose where enquiries land and what visitors receive after submitting the contact form.
            <?= $custom
                ? '<span class="badge badge--live">Saved in dashboard</span>'
                : '<span class="badge">Using configuration defaults</span>' ?>
        </p>
    </div>
</div>

<?php if ($transport !== 'native'): ?>
    <div class="alert alert--warning" role="status">
        <span class="alert__icon" aria-hidden="true"></span>
        <p>
            The server mail transport is <strong><?= e($transport) ?></strong>, so no email is actually
            delivered. Use <code>'transport' => 'native'</code> in <code>includes/config.local.php</code>
            on the live server.
        </p>
    </div>
<?php endif; ?>

<?php if (isset($errors['general'])): ?>
    <div class="alert alert--error" role="alert">
        <span class="alert__icon" aria-hidden="true"></span>
        <p><?= e($errors['general']) ?></p>
    </div>
<?php elseif ($errors !== []): ?>
    <div class="alert alert--error" role="alert">
        <span class="alert__icon" aria-hidden="true"></span>
        <p>Please fix the highlighted fields below.</p>
    </div>
<?php endif; ?>

<div class="form-layout">
    <div>
        <form method="post" action="<?= e(admin_url('email.php')) ?>" class="admin-form" data-guard>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">

            <section class="form-section">
                <div class="form-section__head">
                    <h2 class="form-section__title">Owner notifications</h2>
                    <p class="form-section__hint">Where each contact submission is sent. Replies go straight back to the visitor.</p>
                </div>

                <div class="field<?= isset($errors['to']) ? ' field--error' : '' ?>">
                    <label for="email-to">Your notification email *</label>
                    <input type="email" id="email-to" name="to" value="<?= e($form['to']) ?>"
                           required maxlength="190" autocomplete="email" placeholder="you@gmail.com">
                    <?php if (isset($errors['to'])): ?>
                        <p class="field__error"><?= e($errors['to']) ?></p>
                    <?php else: ?>
                        <p class="field__hint">Every contact message is delivered here. Gmail or your domain mailbox both work.</p>
                    <?php endif; ?>
                </div>

                <div class="form-grid" style="margin-top: .9rem">
                    <div class="field<?= isset($errors['from_name']) ? ' field--error' : '' ?>">
                        <label for="email-from-name">Sender name *</label>
                        <input type="text" id="email-from-name" name="from_name" value="<?= e($form['from_name']) ?>"
                               required maxlength="100" placeholder="TijaniDev">
                        <?php if (isset($errors['from_name'])): ?>
                            <p class="field__error"><?= e($errors['from_name']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field<?= isset($errors['from']) ? ' field--error' : '' ?>">
                        <label for="email-from">Sender email *</label>
                        <input type="email" id="email-from" name="from" value="<?= e($form['from']) ?>"
                               required maxlength="190" placeholder="no-reply@tijanidev.com.ng">
                        <?php if (isset($errors['from'])): ?>
                            <p class="field__error"><?= e($errors['from']) ?></p>
                        <?php else: ?>
                            <p class="field__hint">Ideally a mailbox on your own domain (helps deliverability).</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="form-section">
                <div class="form-section__head">
                    <h2 class="form-section__title">Visitor confirmation</h2>
                    <p class="form-section__hint">An automatic receipt sent to the person who filled in the form.</p>
                </div>

                <label class="check-card">
                    <input type="checkbox" name="confirmation_enabled" value="1"<?= $form['confirmation_enabled'] === 1 ? ' checked' : '' ?>>
                    <span>
                        Send visitors a confirmation email
                        <small>Only sent after your own notification is accepted, so nobody is told "delivered" when it was not.</small>
                    </span>
                </label>

                <div class="field<?= isset($errors['confirmation_subject']) ? ' field--error' : '' ?>" style="margin-top: .9rem">
                    <label for="confirmation-subject">
                        Subject *
                        <span class="field__hint" data-count-for="confirmation-subject"></span>
                    </label>
                    <input type="text" id="confirmation-subject" name="confirmation_subject"
                           value="<?= e($form['confirmation_subject']) ?>" required maxlength="150">
                    <?php if (isset($errors['confirmation_subject'])): ?>
                        <p class="field__error"><?= e($errors['confirmation_subject']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['confirmation_body']) ? ' field--error' : '' ?>" style="margin-top: .9rem">
                    <label for="confirmation-body">
                        Message *
                        <span class="field__hint" data-count-for="confirmation-body"></span>
                    </label>
                    <textarea id="confirmation-body" name="confirmation_body" rows="12" maxlength="5000"
                              class="textarea--tall" required><?= e($form['confirmation_body']) ?></textarea>
                    <?php if (isset($errors['confirmation_body'])): ?>
                        <p class="field__error"><?= e($errors['confirmation_body']) ?></p>
                    <?php else: ?>
                        <p class="field__hint">Plain text. Blank lines separate paragraphs.</p>
                    <?php endif; ?>
                </div>

                <div style="margin-top: .8rem">
                    <p class="field__hint" style="margin-bottom: .4rem">Available placeholders</p>
                    <ul class="tech-pills">
                        <li class="tech-pills__item">{name}</li>
                        <li class="tech-pills__item">{email}</li>
                        <li class="tech-pills__item">{subject}</li>
                        <li class="tech-pills__item">{message}</li>
                        <li class="tech-pills__item">{owner_name}</li>
                        <li class="tech-pills__item">{site_name}</li>
                        <li class="tech-pills__item">{site_url}</li>
                    </ul>
                </div>

                <div class="form-actions">
                    <p class="form-note">Settings are stored in SQLite and included in database backups.</p>
                    <button class="btn btn--primary" type="submit">
                        <?= icon('check', 'icon', 16) ?> Save email settings
                    </button>
                </div>
            </section>
        </form>
    </div>

    <div>
        <section class="form-section">
            <div class="form-section__head">
                <h2 class="form-section__title">Send method</h2>
                <p class="form-section__hint">How this host delivers mail.</p>
            </div>
            <dl class="system-list">
                <div class="system-row">
                    <dt>Transport</dt>
                    <dd class="<?= $transport === 'native' ? 'is-ok' : 'is-fail' ?>"><?= e($transport === 'native' ? 'PHP mail()' : $transport) ?></dd>
                </div>
                <div class="system-row">
                    <dt>Notification goes to</dt>
                    <dd><?= e($activeSettings['to']) ?></dd>
                </div>
                <div class="system-row">
                    <dt>Sent from</dt>
                    <dd><?= e($activeSettings['from_name']) ?> &lt;<?= e($activeSettings['from']) ?>&gt;</dd>
                </div>
                <div class="system-row">
                    <dt>Visitor confirmation</dt>
                    <dd class="<?= $activeSettings['confirmation_enabled'] ? 'is-ok' : '' ?>">
                        <?= $activeSettings['confirmation_enabled'] ? 'enabled' : 'disabled' ?>
                    </dd>
                </div>
            </dl>

            <form method="post" action="<?= e(admin_url('email.php')) ?>" style="margin-top: 1rem" data-guard>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="test">
                <button class="btn btn--ghost btn--sm" type="submit">
                    <?= icon('send', 'icon', 14) ?> Send test email
                </button>
                <p class="field__hint" style="margin-top: .5rem">
                    Uses the saved settings. Save first if you just changed the address.
                </p>
            </form>
        </section>

        <section class="form-section">
            <div class="form-section__head">
                <h2 class="form-section__title">How it works</h2>
            </div>
            <ul class="system-list">
                <li class="system-row"><dt>1. Visitor submits</dt><dd>Owner notification sent first</dd></li>
                <li class="system-row"><dt>2. On success</dt><dd>Visitor confirmation sent</dd></li>
                <li class="system-row"><dt>3. On failure</dt><dd>Visitor sees an honest error + WhatsApp fallback</dd></li>
                <li class="system-row"><dt>4. Always</dt><dd>The message is stored under Messages</dd></li>
            </ul>
            <p class="field__hint" style="margin-top: .8rem">
                Replies to the owner notification go to the visitor. Replies to the confirmation go
                to <?= e($activeSettings['to']) ?>.
            </p>
        </section>

        <section class="form-section">
            <div class="form-section__head">
                <h2 class="form-section__title">Reset</h2>
                <p class="form-section__hint">Fall back to the addresses in the configuration file.</p>
            </div>
            <form method="post" action="<?= e(admin_url('email.php')) ?>"
                  data-confirm="Reset email settings to the configuration defaults?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset">
                <button class="btn btn--danger btn--sm" type="submit">Reset to defaults</button>
            </form>
        </section>
    </div>
</div>

<?php admin_footer(); ?>
