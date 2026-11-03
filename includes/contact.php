<?php
/**
 * Contact form processing: spam checks, validation, storage and delivery.
 *
 * Guard order matters — the cheap checks run before any database write.
 */

declare(strict_types=1);

/** Signed timestamp so a bot cannot backdate the hidden "elapsed" field. */
function contact_form_token(): string
{
    $time = (string) time();

    return $time . '.' . hash_hmac('sha256', $time, app_secret());
}

/** Seconds since the form was rendered, or null when the token is invalid. */
function contact_token_age(?string $token): ?int
{
    $token = trim((string) $token);
    if ($token === '' || substr_count($token, '.') !== 1) {
        return null;
    }

    [$time, $signature] = explode('.', $token, 2);
    if (!preg_match('/^\d{10}$/', $time)) {
        return null;
    }

    $expected = hash_hmac('sha256', $time, app_secret());
    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $age = time() - (int) $time;
    if ($age < 0) {
        return null;
    }

    return $age > 172800 ? null : $age;
}

/**
 * Per-IP submission limit.
 *
 * @return array{limited:bool, retry_after:int}
 */
function contact_rate_limited(string $ip): array
{
    $max = max(1, (int) config('security.contact_max', 3));
    $window = max(60, (int) config('security.contact_window', 3600));
    $since = gmdate('Y-m-d H:i:s', time() - $window);

    $count = (int) db_value(
        'SELECT COUNT(*) FROM contact_messages WHERE ip_address = :ip AND created_at >= :since',
        [':ip' => $ip, ':since' => $since]
    );

    if ($count < $max) {
        return ['limited' => false, 'retry_after' => 0];
    }

    $oldest = db_value(
        'SELECT MIN(created_at) FROM contact_messages WHERE ip_address = :ip AND created_at >= :since',
        [':ip' => $ip, ':since' => $since]
    );

    $retry = $window;
    if (is_string($oldest) && $oldest !== '') {
        $timestamp = strtotime($oldest . ' UTC');
        if ($timestamp !== false) {
            $retry = max(60, $window - (time() - $timestamp));
        }
    }

    return ['limited' => true, 'retry_after' => $retry];
}

/**
 * Persist a submission (always stored so nothing is silently lost).
 *
 * @param array{name:string,email:string,subject:string,message:string} $data
 */
function store_contact_message(array $data, string $status, string $ip, string $userAgent): int
{
    db_run(
        'INSERT INTO contact_messages (name, email, subject, message, ip_address, user_agent, mail_status, created_at)
         VALUES (:name, :email, :subject, :message, :ip, :ua, :status, :created)',
        [
            ':name'    => $data['name'],
            ':email'   => $data['email'],
            ':subject' => $data['subject'],
            ':message' => $data['message'],
            ':ip'      => $ip,
            ':ua'      => mb_substr($userAgent, 0, 255),
            ':status'  => $status,
            ':created' => now_utc(),
        ]
    );

    return (int) db()->lastInsertId();
}

/**
 * Full submission pipeline.
 *
 * @param array<string, mixed> $input raw $_POST
 * @return array{ok:bool, spam:bool, status:string, errors:array<string,string>, message:string, warnings:list<string>}
 */
function contact_submit(array $input, string $ip, string $userAgent): array
{
    $result = [
        'ok' => false, 'spam' => false, 'status' => 'pending',
        'errors' => [], 'message' => '', 'warnings' => [],
    ];

    $honeypot = trim((string) ($input['website'] ?? ''));
    if ($honeypot !== '') {
        store_contact_message(
            ['name' => 'Spam trap', 'email' => 'spam@invalid', 'subject' => 'Honeypot', 'message' => mb_substr($honeypot, 0, 500)],
            'spam',
            $ip,
            $userAgent
        );
        app_log('contact', 'Honeypot triggered', ['ip' => $ip]);

        return ['ok' => true, 'spam' => true, 'status' => 'spam', 'errors' => [], 'message' => ''];
    }

    $age = contact_token_age((string) ($input['form_token'] ?? ''));
    $minAge = max(1, (int) config('security.min_form_seconds', 3));
    $maxAge = max($minAge, (int) config('security.max_form_seconds', 10800));

    if ($age === null) {
        $result['errors']['form'] = 'The form session could not be verified. Please reload the page and try again.';

        return $result;
    }

    if ($age < $minAge) {
        // Submitted faster than a person plausibly could: drop it quietly.
        store_contact_message(
            ['name' => 'Spam trap', 'email' => 'spam@invalid', 'subject' => 'Too fast', 'message' => 'Submitted in ' . $age . 's'],
            'spam',
            $ip,
            $userAgent
        );
        app_log('contact', 'Submission too fast', ['ip' => $ip, 'age' => $age]);

        return ['ok' => true, 'spam' => true, 'status' => 'spam', 'errors' => [], 'message' => ''];
    }

    if ($age > $maxAge) {
        $result['errors']['form'] = 'This form expired. Please reload the page and send your message again.';

        return $result;
    }

    $throttle = contact_rate_limited($ip);
    if ($throttle['limited']) {
        app_log('contact', 'Contact rate limit hit', ['ip' => $ip]);
        $minutes = max(1, (int) ceil($throttle['retry_after'] / 60));
        $result['errors']['form'] = 'You have sent several messages in a short time. Please try again in about '
            . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ', or reach me on WhatsApp.';

        return $result;
    }

    $validated = validate_contact($input);
    if ($validated['errors'] !== []) {
        $result['errors'] = $validated['errors'];

        return $result;
    }

    $data = $validated['data'];
    $settings = mail_settings();
    $delivery = send_contact_notification($data, $ip, $userAgent, $settings);

    $confirmation = null;
    if ($delivery['ok'] && !empty($settings['confirmation_enabled'])) {
        $confirmation = send_visitor_confirmation($data, $settings);
        if (!$confirmation['ok']) {
            app_log('contact', 'Visitor confirmation could not be sent', [
                'email' => $data['email'],
                'error' => $confirmation['error'],
            ]);
            $result['warnings'][] = 'Your message was delivered, but the automatic confirmation email '
                . 'could not be sent to you.';
        }
    }

    $status = $delivery['ok'] ? 'sent' : 'failed';
    $messageId = store_contact_message($data, $status, $ip, $userAgent);

    app_log('contact', 'Contact submission processed', [
        'id'               => $messageId,
        'status'           => $status,
        'mail_error'       => $delivery['error'],
        'confirmation'     => is_array($confirmation) ? ($confirmation['ok'] ? 'sent' : 'failed') : 'disabled',
        'confirmation_err' => is_array($confirmation) ? $confirmation['error'] : '',
    ]);

    if (!$delivery['ok']) {
        $result['status'] = 'failed';
        $result['errors']['form'] = 'Your message was saved, but the email could not be delivered right now. '
            . 'Please use WhatsApp or email me directly so nothing is missed.';

        return $result;
    }

    return [
        'ok'       => true,
        'spam'     => false,
        'status'   => 'sent',
        'errors'   => [],
        'message'  => '',
        'warnings' => $result['warnings'],
    ];
}
