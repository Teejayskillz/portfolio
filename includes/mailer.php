<?php
/**
 * Outbound mail.
 *
 * Every message goes through send_plain_mail(), which reports delivery honestly.
 * Addresses and the visitor confirmation are managed in Admin → Email settings
 * (stored in SQLite) and fall back to includes/config.php on a fresh install.
 *
 * Transports:
 *   native  — PHP mail() via the host MTA
 *   capture — writes .eml files to storage/logs/captured (automated tests only)
 *   log     — writes to storage/logs/mail.log and reports failure (local preview)
 */

declare(strict_types=1);

function mail_transport(): string
{
    $transport = strtolower(trim((string) config('mail.transport', 'native')));

    return in_array($transport, ['native', 'log', 'capture'], true) ? $transport : 'native';
}

/** Strip CR/LF so user input can never inject extra mail headers. */
function sanitize_mail_string(string $value, int $maxLength = 200): string
{
    $value = str_replace(["\r", "\n", "\0"], ' ', $value);
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

    return mb_substr($value, 0, $maxLength);
}

function normalize_mail_body(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);

    return str_replace("\n", "\r\n", trim($body));
}

/**
 * Placeholder substitution for mail templates.
 *
 * Supported: {name} {email} {whatsapp} {subject} {message} {owner_name} {site_name} {site_url}
 *
 * @param array<string, string> $variables
 */
function render_mail_template(string $template, array $variables): string
{
    $replacements = [];
    foreach ($variables as $key => $value) {
        $replacements['{' . $key . '}'] = $value;
    }

    return strtr($template, $replacements);
}

/** @return array<string, string> */
function mail_template_variables(array $data): array
{
    return [
        'name'       => (string) ($data['name'] ?? ''),
        'email'      => (string) ($data['email'] ?? ''),
        'whatsapp'   => (string) ($data['whatsapp'] ?? ''),
        'subject'    => (string) ($data['subject'] ?? ''),
        'message'    => (string) ($data['message'] ?? ''),
        'owner_name' => (string) config('owner.name', ''),
        'site_name'  => (string) config('brand', config('owner.name', '')),
        'site_url'   => rtrim(site_url(''), '/'),
    ];
}

/**
 * Send one plain-text email.
 *
 * @param array{
 *   from?:string, from_name?:string, reply_to?:string, reply_name?:string,
 *   extra_headers?:list<string>, context?:string
 * } $options
 * @return array{ok:bool, error:string}
 */
function send_plain_mail(string $to, string $subject, string $body, array $options = []): array
{
    $settings = mail_settings();
    $from = sanitize_mail_string((string) ($options['from'] ?? $settings['from']), 190);
    $fromName = sanitize_mail_string((string) ($options['from_name'] ?? $settings['from_name']), 100);
    $replyTo = trim((string) ($options['reply_to'] ?? ''));
    $replyName = sanitize_mail_string((string) ($options['reply_name'] ?? ''), 100);
    $context = sanitize_mail_string((string) ($options['context'] ?? 'Email'), 80);

    $to = sanitize_mail_string($to, 190);
    $subject = sanitize_mail_string($subject, 200);

    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        app_log('mail', 'Invalid recipient address', ['to' => $to, 'context' => $context]);

        return ['ok' => false, 'error' => 'The receiving email address is not configured correctly.'];
    }

    if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
        app_log('mail', 'Invalid sender address', ['from' => $from, 'context' => $context]);

        return ['ok' => false, 'error' => 'The sender email address is not configured correctly. Update it in Admin → Email settings.'];
    }

    $headers = [
        'From: ' . mb_encode_mimeheader($fromName !== '' ? $fromName : 'Website', 'UTF-8', 'B', "\r\n") . ' <' . $from . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: TijaniDev-Portfolio',
    ];

    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false) {
        $headers[] = 'Reply-To: '
            . mb_encode_mimeheader($replyName !== '' ? $replyName : $replyTo, 'UTF-8', 'B', "\r\n")
            . ' <' . $replyTo . '>';
    }

    foreach ((array) ($options['extra_headers'] ?? []) as $extra) {
        $extra = trim((string) $extra);
        if ($extra !== '' && !str_contains($extra, "\n") && !str_contains($extra, "\r")) {
            $headers[] = $extra;
        }
    }

    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
    $body = normalize_mail_body($body);
    $headerText = implode("\r\n", $headers);

    if (mail_transport() === 'capture') {
        // Automated tests only. Never enabled on production.
        $dir = APP_STORAGE . '/logs/captured';
        ensure_dir($dir);
        $file = $dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';
        $written = @file_put_contents(
            $file,
            'To: ' . $to . "\r\n"
            . 'Subject: ' . $subject . "\r\n"
            . $headerText . "\r\n\r\n" . $body,
            LOCK_EX
        );
        app_log('mail', 'CAPTURE transport used (no email was sent)', ['file' => $file, 'context' => $context]);

        return $written === false
            ? ['ok' => false, 'error' => 'The capture directory is not writable.']
            : ['ok' => true, 'error' => ''];
    }

    if (mail_transport() === 'log') {
        $logFile = APP_STORAGE . '/logs/' . (string) config('mail.log_file', 'mail.log');
        $entry = '[' . gmdate('c') . '] LOG TRANSPORT (not delivered)' . "\n"
            . 'To: ' . $to . "\n"
            . 'Subject: ' . $subject . "\n"
            . 'Headers: ' . $headerText . "\n\n" . $body . "\n"
            . str_repeat('=', 70) . "\n\n";
        ensure_dir(dirname($logFile));
        $written = @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

        if ($written === false) {
            return ['ok' => false, 'error' => 'The local mail log could not be written.'];
        }

        app_log('mail', 'LOG transport used (not delivered)', ['to' => $to, 'context' => $context]);

        return ['ok' => false, 'error' => 'Local log transport is active, so no email was delivered.'];
    }

    $envelope = '-f' . $from;
    $sent = false;
    $lastError = '';

    try {
        $sent = @mail($to, $encodedSubject, $body, $headerText, $envelope);
    } catch (Throwable $exception) {
        $lastError = $exception->getMessage();
    }

    if (!$sent) {
        // Some hosts reject -f; retry through PHP's default sendmail path.
        try {
            $sent = @mail($to, $encodedSubject, $body, $headerText);
        } catch (Throwable $exception) {
            $lastError = $exception->getMessage();
        }
    }

    app_log('mail', $sent ? 'Mail accepted by the mail server' : 'Mail delivery failed', [
        'to'      => $to,
        'context' => $context,
        'error'   => $lastError,
    ]);

    return $sent
        ? ['ok' => true, 'error' => '']
        : ['ok' => false, 'error' => 'The server could not deliver the email right now.'];
}

/**
 * Notify the site owner about a contact submission.
 *
 * @param array{name:string,email:string,subject:string,message:string} $data
 * @return array{ok:bool, error:string}
 */
function send_contact_notification(
    array $data,
    string $ip,
    string $userAgent,
    ?array $settings = null
): array {
    $settings ??= mail_settings();

    $whatsapp = trim((string) ($data['whatsapp'] ?? ''));
    $whatsappLine = '';
    if ($whatsapp !== '') {
        $whatsappUrl = whatsapp_contact_url($whatsapp);
        $whatsappLine = 'WhatsApp: ' . $whatsapp . ($whatsappUrl !== '' ? ' (' . $whatsappUrl . ')' : '') . "\n";
    }

    $subject = 'Portfolio enquiry: ' . sanitize_mail_string($data['subject'], 120);
    $body = "New message from the website contact form.\n\n"
        . "Name:    {$data['name']}\n"
        . "Email:   {$data['email']}\n"
        . $whatsappLine
        . "Subject: {$data['subject']}\n"
        . "Date:    " . gmdate('Y-m-d H:i:s') . " UTC\n"
        . 'IP:      ' . $ip . "\n"
        . 'Agent:   ' . ($userAgent !== '' ? $userAgent : 'unknown') . "\n"
        . "\n" . str_repeat('-', 60) . "\n\n"
        . $data['message'] . "\n";

    return send_plain_mail((string) $settings['to'], $subject, $body, [
        'from'          => (string) $settings['from'],
        'from_name'     => (string) $settings['from_name'],
        'reply_to'      => (string) $data['email'],
        'reply_name'    => (string) $data['name'],
        'extra_headers' => ['X-Contact-IP: ' . $ip],
        'context'       => 'Owner notification',
    ]);
}

/**
 * Send the visitor a confirmation copy of their message.
 *
 * @param array{name:string,email:string,subject:string,message:string} $data
 * @return array{ok:bool, error:string}
 */
function send_visitor_confirmation(array $data, ?array $settings = null): array
{
    $settings ??= mail_settings();

    if (empty($settings['confirmation_enabled'])) {
        return ['ok' => false, 'error' => 'Visitor confirmation is disabled.'];
    }
    if (filter_var((string) $data['email'], FILTER_VALIDATE_EMAIL) === false) {
        return ['ok' => false, 'error' => 'The visitor email address is not valid.'];
    }

    $variables = mail_template_variables($data);
    $subject = sanitize_mail_string(render_mail_template((string) $settings['confirmation_subject'], $variables), 150);
    $body = render_mail_template((string) $settings['confirmation_body'], $variables);

    return send_plain_mail((string) $data['email'], $subject, $body, [
        'from'       => (string) $settings['from'],
        'from_name'  => (string) $settings['from_name'],
        'reply_to'   => (string) $settings['to'],
        'reply_name' => (string) config('owner.name', 'Owner'),
        'context'    => 'Visitor confirmation',
    ]);
}

/** Test message triggered from Admin → Email settings. */
function send_test_email(?array $settings = null): array
{
    $settings ??= mail_settings();
    $siteName = (string) config('brand', 'Website');
    $subject = 'Test email from ' . $siteName;
    $body = "This is a test email from your portfolio dashboard.\n\n"
        . "If you received it, contact-form notifications can be delivered to {$settings['to']}.\n\n"
        . 'Sent: ' . gmdate('Y-m-d H:i:s') . " UTC\n"
        . 'Site: ' . rtrim(site_url(''), '/') . "\n\n"
        . "You can change these email settings at any time in Admin → Email settings.\n";

    return send_plain_mail((string) $settings['to'], $subject, $body, [
        'from'       => (string) $settings['from'],
        'from_name'  => (string) $settings['from_name'],
        'reply_to'   => (string) $settings['to'],
        'reply_name' => (string) config('owner.name', $siteName),
        'context'    => 'Settings test',
    ]);
}
