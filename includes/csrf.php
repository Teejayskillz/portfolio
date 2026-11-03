<?php
/**
 * CSRF protection: one strong per-session token, compared in constant time.
 * Every state-changing request (POST) must present it.
 */

declare(strict_types=1);

function csrf_token(): string
{
    if (PHP_SAPI === 'cli') {
        return 'cli-token';
    }

    $token = $_SESSION['_csrf'] ?? '';
    if (!is_string($token) || strlen($token) < 32) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['_csrf'] = $token;
    }

    return $token;
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(?string $token): bool
{
    $sessionToken = $_SESSION['_csrf'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    return is_string($token) && $token !== '' && hash_equals($sessionToken, $token);
}

function rotate_csrf_token(): void
{
    if (PHP_SAPI !== 'cli') {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
}

/**
 * Validate the CSRF token for the current POST request. Aborts with 419 when
 * the token is missing, stale or from another session.
 */
function require_csrf(): void
{
    if (is_post() && csrf_valid(post_string('csrf_token'))) {
        return;
    }

    app_log('security', 'CSRF validation failed', [
        'path'   => request_path(),
        'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
        'ip'     => client_ip(),
    ]);

    abort_request(
        419,
        'Session expired',
        'Your session has expired or this form was submitted from an unrecognised origin. Reload the page and try again.'
    );
}
