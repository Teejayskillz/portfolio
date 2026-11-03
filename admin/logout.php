<?php
/**
 * Sign out (POST + CSRF only, to prevent logout CSRF).
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

if (!is_post()) {
    redirect(admin_url('index.php'));
}

require_csrf();

sign_out_admin();

flash('success', 'You have been signed out.');
redirect(admin_url('login.php'));
