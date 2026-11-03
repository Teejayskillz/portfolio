<?php
/**
 * Thank-you page, shown after a contact message is accepted by the mail server.
 *
 * @var array<string, mixed> $meta
 */

if (!defined('APP_BOOTSTRAP')) {
    http_response_code(404);
    exit;
}

$owner = config('owner');
site_header($meta);
?>

<?= alerts_html() ?>

<main id="main" class="status-page">
    <div class="container container--narrow">
        <div class="status-page__card">
            <span class="status-page__icon"><?= icon('check', 'icon', 26) ?></span>
            <p class="status-page__code">Message sent</p>
            <h1 class="status-page__title">Thank you — your message is on its way.</h1>
            <p class="status-page__text">
                I've received your message and will reply to the email address you provided. If the matter
                is urgent, WhatsApp is the quickest way to reach me.
            </p>
            <div class="status-page__actions">
                <a class="btn btn--primary" href="<?= e(projects_url()) ?>">
                    View my projects <?= icon('arrow-right', 'icon', 16) ?>
                </a>
                <a class="btn btn--ghost" href="<?= e(whatsapp_url('Hi Tijani, I just sent a message through your website.')) ?>"
                   target="_blank" rel="noopener noreferrer">
                    <?= icon('whatsapp', 'icon', 16) ?> Chat on WhatsApp
                </a>
                <a class="btn btn--ghost" href="<?= e(home_url()) ?>">
                    Back to home
                </a>
            </div>
            <p class="form-note" style="margin-top: 1.6rem;">
                Delivered to <?= e($owner['email']) ?>. If it doesn't arrive, send it again or use
                <a href="<?= e(email_url('Project enquiry')) ?>">email</a> directly.
            </p>
        </div>
    </div>
</main>

<?php site_footer($meta); ?>
