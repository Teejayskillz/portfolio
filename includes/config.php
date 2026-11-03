<?php
/**
 * -----------------------------------------------------------------------------
 * Application configuration — safe defaults.
 * -----------------------------------------------------------------------------
 * This file is committed to Git and must not contain secrets.
 *
 * Anything machine-specific (database path, mail transport, base path) belongs
 * in includes/config.local.php, which is created by install.php and ignored by
 * Git. Environment variables take precedence when present, which makes the
 * same codebase work on cPanel, in staging and in the local test harness.
 *
 *   PORTFOLIO_STORAGE         absolute path for the private storage directory
 *   PORTFOLIO_DB              SQLite filename (or absolute path)
 *   PORTFOLIO_MAIL_TRANSPORT  native | log
 *   APP_DEBUG                 "1" to show detailed errors
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

$root = dirname(__DIR__);

return [
    'env'   => 'production',
    'debug' => false,

    // Public origin, without a trailing slash and without the base path.
    'site_url'  => 'https://tijanidev.com.ng',
    // Leave empty when the project lives at the domain root (cPanel public_html).
    // Set to '/portfolio' when it lives in a sub-directory.
    'base_path' => '',
    // Clean URLs need Apache mod_rewrite (see .htaccess). Set false if the host
    // does not support it; links then fall back to project.php?slug=...
    'clean_urls' => true,

    'timezone' => 'Africa/Lagos',

    'owner' => [
        'name'         => 'Apatira Tijani Adewale',
        'first_name'   => 'Tijani',
        'role'         => 'Software & Web Developer',
        'tagline'      => 'Building Digital Solutions That Move Businesses Forward.',
        'location'     => 'Lagos, Nigeria',
        'email'        => 'tijaniapatira@gmail.com',
        'phone'        => '+2349027717380',
        'phone_display'=> '+234 902 771 7380',
        'whatsapp'     => '2349027717380',
        'github'       => 'https://github.com/Teejayskillz',
        'linkedin'     => 'https://ng.linkedin.com/in/tijani-apatira-13b358367',
    ],

    'storage' => [
        // Keep this outside public_html on production when possible.
        'path'     => getenv('PORTFOLIO_STORAGE') ?: $root . '/storage',
        'database' => getenv('PORTFOLIO_DB') ?: 'portfolio.sqlite',
    ],

    'uploads' => [
        'path'        => $root . '/assets/uploads',
        'max_bytes'   => 5 * 1024 * 1024, // 5 MB per image
        'max_gallery' => 8,               // screenshots per project
    ],

    'mail' => [
        // native = PHP mail() via the host MTA. log = write to storage/logs
        // (local preview only — never claim delivery with the log transport).
        'transport' => getenv('PORTFOLIO_MAIL_TRANSPORT') ?: 'native',
        'to'        => 'tijaniapatira@gmail.com',
        'from'      => 'no-reply@tijanidev.com.ng',
        'from_name' => 'Tijani Dev Website',
        'log_file'  => 'mail.log',
    ],

    'security' => [
        'session_name'       => 'tjportfolio_sid',
        'session_idle'       => 1800,   // 30 minutes
        'session_max'        => 43200,  // 12 hours
        'login_max_attempts' => 5,      // per username + IP
        'login_window'       => 900,    // 15 minutes
        'contact_max'        => 3,      // messages per IP
        'contact_window'     => 3600,   // per hour
        'min_form_seconds'   => 3,      // ignore instant submissions
        'max_form_seconds'   => 10800,  // 3 hours
        'password_min'       => 10,
    ],

    'content' => [
        'projects_per_page' => 9,
        'featured_limit'    => 3,
        'home_recent_limit' => 6,
    ],
];
