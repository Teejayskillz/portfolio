<?php
/**
 * Read / delete a single contact message.
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

require_admin();

if (is_post()) {
    require_csrf();
    $action = post_string('action');
    $id = to_int($_POST['id'] ?? 0);

    if ($action === 'delete') {
        if (message_delete($id)) {
            flash('success', 'Message deleted.');
        } else {
            flash('error', 'That message could not be found.');
        }
    }

    redirect(admin_url('messages.php'));
}

$id = to_int(query_string('id'));
$message = $id > 0 ? message_by_id($id) : null;

if ($message === null) {
    abort_page(404, 'Message not found', 'That message does not exist, or it was deleted.', 'Return to the inbox and try again.');
}

$replySubject = 'Re: ' . (string) $message['subject'];
$replyBody = "\n\n---\nOriginal message from " . $message['name'] . ' (' . format_datetime((string) $message['created_at']) . "):\n"
    . $message['message'];

$whatsappValue = trim((string) ($message['whatsapp'] ?? ''));
$whatsappLink = $whatsappValue !== ''
    ? whatsapp_contact_url(
        $whatsappValue,
        'Hi ' . (string) $message['name'] . ', thanks for your message through the website. '
        . 'I am replying about: ' . (string) $message['subject']
    )
    : '';

$page = [
    'title'    => 'Message',
    'subtitle' => 'From ' . (string) $message['name'],
    'active'   => 'messages',
    'actions'  => '<a class="btn btn--ghost btn--sm" href="' . e(admin_url('messages.php')) . '">Back to inbox</a>',
];

admin_header($page);
?>

<div class="page-head">
    <div>
        <h2 class="page-head__title"><?= e((string) $message['subject']) ?></h2>
        <p class="page-head__sub">
            <?= e((string) $message['name']) ?> ·
            <?= e((string) $message['email']) ?> ·
            <?= e(format_datetime((string) $message['created_at'])) ?>
        </p>
    </div>
    <div class="page-head__actions">
        <?php if ($whatsappLink !== ''): ?>
            <a class="btn btn--primary btn--sm" href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener noreferrer">
                <?= icon('whatsapp', 'icon', 14) ?> Chat on WhatsApp
            </a>
        <?php endif; ?>
        <a class="btn btn--<?= $whatsappLink !== '' ? 'ghost' : 'primary' ?> btn--sm" href="<?= e(email_url($replySubject, $replyBody)) ?>">
            <?= icon('mail', 'icon', 14) ?> Reply by email
        </a>
        <?= admin_message_badge((string) $message['mail_status']) ?>
    </div>
</div>

<?php if ($message['mail_status'] === 'failed'): ?>
    <div class="alert alert--warning" role="status">
        <span class="alert__icon" aria-hidden="true"></span>
        <p>
            The server could not email this message. It is stored here so you can still reply — use the
            button above or the email address below.
        </p>
    </div>
<?php elseif ($message['mail_status'] === 'spam'): ?>
    <div class="alert alert--info" role="status">
        <span class="alert__icon" aria-hidden="true"></span>
        <p>This submission was blocked by the spam checks and was not emailed.</p>
    </div>
<?php endif; ?>

<div class="form-layout">
    <div>
        <section class="panel">
            <header class="panel__head">
                <h2 class="panel__title">Message</h2>
                <p class="panel__hint"><?= e((string) mb_strlen((string) $message['message'])) ?> characters</p>
            </header>
            <div class="panel__body">
                <p class="message-body"><?= e((string) $message['message']) ?></p>
            </div>
        </section>
    </div>

    <div>
        <section class="form-section">
            <div class="form-section__head">
                <h2 class="form-section__title">Details</h2>
            </div>
            <dl class="message-meta">
                <div>
                    <dt>Name</dt>
                    <dd><?= e((string) $message['name']) ?></dd>
                </div>
                <div>
                    <dt>Email</dt>
                    <dd><a href="<?= e(email_url($replySubject, $replyBody)) ?>"><?= e((string) $message['email']) ?></a></dd>
                </div>
                <div>
                    <dt>WhatsApp</dt>
                    <dd>
                        <?php if ($whatsappValue !== ''): ?>
                            <a href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener noreferrer">
                                <?= e($whatsappValue) ?>
                            </a>
                        <?php else: ?>
                            Not provided
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt>Received</dt>
                    <dd><?= e(format_datetime((string) $message['created_at'])) ?></dd>
                </div>
                <div>
                    <dt>IP address</dt>
                    <dd><?= e((string) $message['ip_address']) ?></dd>
                </div>
                <div>
                    <dt>Browser</dt>
                    <dd><?= e(truncate_text((string) $message['user_agent'], 80)) ?></dd>
                </div>
                <div>
                    <dt>Delivery</dt>
                    <dd><?= e((string) $message['mail_status']) ?></dd>
                </div>
            </dl>

            <form method="post" action="<?= e(admin_url('message.php')) ?>" style="margin-top: 1rem"
                  data-confirm="Delete this message permanently?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= e((string) $message['id']) ?>">
                <button class="btn btn--danger btn--sm" type="submit">
                    <?= icon('trash', 'icon', 14) ?> Delete message
                </button>
            </form>
        </section>

        <section class="form-section">
            <div class="form-section__head">
                <h2 class="form-section__title">Reply tips</h2>
            </div>
            <p class="field__hint">
                Reply from your own mailbox so the thread lives in one place. Confirming scope, timeline
                and budget in writing avoids most misunderstandings.
            </p>
        </section>
    </div>
</div>

<?php admin_footer(); ?>
