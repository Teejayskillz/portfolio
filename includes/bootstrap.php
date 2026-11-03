<?php
/**
 * Application bootstrap.
 *
 * Every public and admin entry point starts here. It only wires things up:
 * configuration, error handling, hardened sessions, and the module includes.
 * No output is produced and no redirects happen unless the code is unreadable.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '3.0.0');

if (!defined('APP_BOOTSTRAP')) {
    define('APP_BOOTSTRAP', true);
}

/* -------------------------------------------------------------------------
 * Configuration (defaults + optional local override)
 * ---------------------------------------------------------------------- */

$config = require __DIR__ . '/config.php';

$localConfigFile = __DIR__ . '/config.local.php';
if (is_file($localConfigFile)) {
    $override = require $localConfigFile;
    if (is_array($override)) {
        $config = array_replace_recursive($config, $override);
    }
}

$debugEnv = getenv('APP_DEBUG');
if ($debugEnv !== false && $debugEnv !== '') {
    $config['debug'] = filter_var($debugEnv, FILTER_VALIDATE_BOOLEAN);
}

$GLOBALS['app_config'] = $config;

define('APP_DEBUG', (bool) ($config['debug'] ?? false));
define('APP_ENV', (string) ($config['env'] ?? 'production'));
define('APP_STORAGE', rtrim((string) ($config['storage']['path'] ?? (APP_ROOT . '/storage')), '/\\'));
define('APP_TEMPLATES', APP_ROOT . '/templates');

/* -------------------------------------------------------------------------
 * Runtime defaults
 * ---------------------------------------------------------------------- */

date_default_timezone_set((string) ($config['timezone'] ?? 'Africa/Lagos'));
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/helpers.php';

ensure_dir(APP_STORAGE);
ensure_dir(APP_STORAGE . '/logs');

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

if (is_dir(APP_STORAGE . '/logs') && is_writable(APP_STORAGE . '/logs')) {
    ini_set('error_log', APP_STORAGE . '/logs/php-error.log');
}

/* -------------------------------------------------------------------------
 * Error + exception handling
 * ---------------------------------------------------------------------- */

set_exception_handler(static function (Throwable $exception): void {
    app_log('exceptions', $exception->getMessage(), [
        'type'  => get_class($exception),
        'file'  => $exception->getFile(),
        'line'  => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
    ]);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . ' @ '
            . $exception->getFile() . ':' . $exception->getLine() . PHP_EOL);
        exit(1);
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    if (APP_DEBUG) {
        echo '<!doctype html><meta charset="utf-8"><title>Application error</title>'
            . '<body style="background:#050807;color:#f2f6f3;font-family:ui-monospace,monospace;padding:2rem;line-height:1.5">'
            . '<h1 style="color:#ff6b6b;font-size:1.1rem">' . e($exception->getMessage()) . '</h1>'
            . '<p style="color:#9aa8a0">' . e($exception->getFile()) . ':' . (int) $exception->getLine() . '</p>'
            . '<pre style="white-space:pre-wrap;color:#c9d3cd">' . e($exception->getTraceAsString()) . '</pre></body>';
        exit;
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>Something went wrong</title></head>'
        . '<body style="background:#050807;color:#f2f6f3;font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0">'
        . '<main style="max-width:32rem;padding:2rem;text-align:center">'
        . '<p style="color:#08d665;letter-spacing:.2em;font-size:.72rem;margin:0">ERROR 500</p>'
        . '<h1 style="font-size:1.9rem;margin:.6rem 0 1rem">Something went wrong</h1>'
        . '<p style="color:#9aa8a0;line-height:1.65">The page could not be loaded right now. '
        . 'Please try again in a moment.</p>'
        . '<p><a href="' . e(home_url()) . '" style="color:#08d665">Return to the homepage</a></p>'
        . '</main></body></html>';
    exit;
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }
    if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    app_log('fatal', (string) $error['message'], [
        'file' => (string) $error['file'],
        'line' => (int) $error['line'],
    ]);

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'FATAL: ' . $error['message'] . ' @ ' . $error['file'] . ':' . $error['line'] . PHP_EOL);
    }
});

/* -------------------------------------------------------------------------
 * Session + modules
 * ---------------------------------------------------------------------- */

require_once __DIR__ . '/session.php';

if (PHP_SAPI !== 'cli') {
    start_app_session();
    send_security_headers();
}

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/uploads.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/contact.php';
require_once __DIR__ . '/repositories.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/seed.php';
require_once __DIR__ . '/layout.php';
