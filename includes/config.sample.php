<?php
/**
 * Sample local configuration.
 * -----------------------------------------------------------------------------
 * Copy this file to includes/config.local.php and edit the values for the
 * server. config.local.php is ignored by Git and is never committed.
 *
 * Anything omitted here falls back to includes/config.php.
 */

declare(strict_types=1);

return [
    // Public origin (scheme + host), no trailing slash.
    'site_url' => 'https://tijanidev.com.ng',

    // '' when installed at the domain root; '/portfolio' for a sub-directory.
    'base_path' => '',

    // Set to false if the host cannot rewrite URLs (project links fall back
    // to project.php?slug=...).
    'clean_urls' => true,

    // Never leave this true on a public server: error pages reveal internals.
    'debug' => false,

    'storage' => [
        // Recommended: a directory outside public_html, e.g.
        // '/home/cpaneluser/portfolio-storage'. It must be writable by PHP.
        'path'     => dirname(__DIR__) . '/storage',
        'database' => 'portfolio.sqlite',
    ],

    'mail' => [
        // Keep "native" in production. "log"/"capture" are development aids and
        // never deliver mail.
        'transport' => 'native',
        // Fallback defaults. Once you save Admin → Email settings, the database
        // values take precedence over these.
        'to'        => 'tijaniapatira@gmail.com',
        'from'      => 'no-reply@tijanidev.com.ng',
        'from_name' => 'TijaniDev',
    ],

    'security' => [
        'session_name' => 'tjportfolio_sid',
        'session_idle' => 1800,
        'session_max'  => 43200,
        'login_max_attempts' => 5,
        'login_window'       => 900,
        'contact_max'        => 3,
        'contact_window'     => 3600,
        'password_min'       => 10,
    ],

    'backup' => [
        // Largest .sqlite file accepted by Admin → Restore.
        'max_restore_bytes' => 100 * 1024 * 1024,
        // Automatic pre-restore safety copies kept in storage/backups.
        'keep' => 10,
    ],
];
