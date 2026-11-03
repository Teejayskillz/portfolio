<?php
/**
 * Contact form endpoint.
 *
 * GET requests are redirected to the contact section. POST requests are
 * validated, spam-checked and mailed. A message is never reported as sent when
 * the host refused to deliver it.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

ensure_installed();

if (!is_post()) {
    redirect(section_url('contact'));
}

require_csrf();

$result = contact_submit($_POST, client_ip(), user_agent());

// Honeypot / impossible-speed submissions look successful to the sender.
if ($result['spam']) {
    redirect(public_url('thank-you.php'));
}

if (!$result['ok']) {
    remember_input($_POST);
    remember_errors($result['errors']);

    if (isset($result['errors']['form'])) {
        flash('error', (string) $result['errors']['form']);
    } else {
        flash('error', 'Your message was not sent. Please check the highlighted fields and try again.');
    }

    redirect(section_url('contact'));
}

flash('success', 'Thanks — your message was delivered. I will reply by email.');

foreach ($result['warnings'] ?? [] as $warning) {
    flash('warning', (string) $warning);
}

redirect(public_url('thank-you.php'));
