<?php
/**
 * Hardened PHP session handling + baseline HTTP security headers.
 */

declare(strict_types=1);

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $name = (string) config('security.session_name', 'tjportfolio_sid');
    if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $name) === 1) {
        session_name($name);
    }

    // Keep session files inside the private storage directory when possible.
    $savePath = APP_STORAGE . '/sessions';
    if (ensure_dir($savePath, 0700) && is_writable($savePath)) {
        session_save_path($savePath);
    }

    $cookiePath = base_path() === '' ? '/' : base_path() . '/';
    $maxLifetime = (int) config('security.session_max', 43200);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $cookiePath,
        'domain'   => '',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', (string) max(3600, $maxLifetime));
    ini_set('session.sid_bits_per_character', '6');

    session_start();
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Permitted-Cross-Domain-Policies: none');
    header_remove('X-Powered-By');

    if (is_https()) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

/** Ensure the SQLite database has been initialised before rendering a page. */
function ensure_installed(): void
{
    if (Database::isInstalled()) {
        return;
    }

    if (PHP_SAPI === 'cli') {
        throw new RuntimeException('Database is not initialised. Run install.php or tools/install.php first.');
    }

    redirect(public_url('install.php'));
}

/** Mark a page as private so search engines never index the admin area. */
function send_noindex_header(): void
{
    if (!headers_sent()) {
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}
