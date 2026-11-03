<?php
/**
 * Outbound mail.
 *
 * Delivery is reported honestly: send_contact_notification() returns ok=false
 * when the host refuses the message so the UI never claims a message was sent
 * when it was not. The "log" transport exists for local preview only.
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

/**
 * Send the contact-form notification to the site owner.
 *
 * @param array{name:string,email:string,subject:string,message:string} $data
 * @return array{ok:bool, error:string}
 */
function send_contact_notification(array $data, string $ip, string $userAgent): array
{
    $to = sanitize_mail_string((string) config('mail.to', ''), 190);
    $from = sanitize_mail_string((string) config('mail.from', ''), 190);
    $fromName = sanitize_mail_string((string) config('mail.from_name', 'Website'), 100);

    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
        app_log('mail', 'Invalid mail configuration', ['to' => $to, 'from' => $from]);

        return ['ok' => false, 'error' => 'The contact address is not configured correctly.'];
    }

    $subject = 'Portfolio enquiry: ' . sanitize_mail_string($data['subject'], 120);
    $subject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");

    $message = "New message from the website contact form.\n\n"
        . "Name:    {$data['name']}\n"
        . "Email:   {$data['email']}\n"
        . "Subject: {$data['subject']}\n"
        . "Date:    " . gmdate('Y-m-d H:i:s') . " UTC\n"
        . "IP:      {$ip}\n"
        . "Agent:   " . ($userAgent !== '' ? $userAgent : 'unknown') . "\n"
        . "\n" . str_repeat('-', 60) . "\n\n"
        . $data['message'] . "\n";

    $headers = implode("\r\n", [
        'From: ' . mb_encode_mimeheader($fromName, 'UTF-8', 'B', "\r\n") . ' <' . $from . '>',
        'Reply-To: ' . mb_encode_mimeheader(sanitize_mail_string($data['name'], 100), 'UTF-8', 'B', "\r\n") . ' <' . $data['email'] . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: TijaniDev-Portfolio',
        'X-Contact-IP: ' . $ip,
    ]);

    if (mail_transport() === 'capture') {
        // Automated tests only. Never enabled on production: install.php writes
        // "native" and the admin diagnostics page flags anything else.
        $dir = APP_STORAGE . '/logs/captured';
        ensure_dir($dir);
        $file = $dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';
        $written = @file_put_contents($file, 'To: ' . $to . "\r\n" . $headers . "\r\n\r\n" . $message, LOCK_EX);
        app_log('mail', 'CAPTURE transport used (no email was sent)', ['file' => $file]);

        if ($written === false) {
            return ['ok' => false, 'error' => 'The capture directory is not writable.'];
        }

        return ['ok' => true, 'error' => ''];
    }

    if (mail_transport() === 'log') {
        $logFile = APP_STORAGE . '/logs/' . (string) config('mail.log_file', 'mail.log');
        $entry = '[' . gmdate('c') . "] LOG TRANSPORT (not delivered)\n"
            . 'To: ' . $to . "\n" . 'Subject: ' . $subject . "\n"
            . 'Headers: ' . $headers . "\n\n" . $message . "\n"
            . str_repeat('=', 70) . "\n\n";
        ensure_dir(dirname($logFile));
        $written = @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

        if ($written === false) {
            return ['ok' => false, 'error' => 'The local mail log could not be written.'];
        }

        app_log('mail', 'Contact notification written to log transport', ['to' => $to]);

        return ['ok' => false, 'error' => 'Local log transport is active, so no email was delivered.'];
    }

    $envelope = '-f' . $from;
    $sent = false;
    $lastError = '';

    try {
        $sent = @mail($to, $subject, $message, $headers, $envelope);
    } catch (Throwable $exception) {
        $lastError = $exception->getMessage();
    }

    if (!$sent) {
        // Retry once using PHP's default sendmail path (some hosts reject -f).
        try {
            $sent = @mail($to, $subject, $message, $headers);
        } catch (Throwable $exception) {
            $lastError = $exception->getMessage();
        }
    }

    app_log('mail', $sent ? 'Contact notification accepted by the mail server' : 'Contact notification failed', [
        'to'    => $to,
        'error' => $lastError,
    ]);

    if (!$sent) {
        return [
            'ok'    => false,
            'error' => 'The server could not deliver the email right now.',
        ];
    }

    return ['ok' => true, 'error' => ''];
}
