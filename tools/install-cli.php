<?php
/**
 * Command-line installer (development + optional cPanel cron use).
 *
 *   php tools/install-cli.php [username] [password]
 *
 * Uses the same functions as install.php. Safe to run only once.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require dirname(__DIR__) . '/includes/bootstrap.php';

if (Database::isInstalled()) {
    echo "Already installed — nothing to do.\n";
    exit(0);
}

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? 'admin';

if (preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username) !== 1) {
    fwrite(STDERR, "Invalid username.\n");
    exit(1);
}

if ($password !== 'admin') {
    $policy = password_policy_errors($password, $username);
    if ($policy !== []) {
        fwrite(STDERR, "Password rejected: " . implode(' ', $policy) . "\n");
        exit(1);
    }
}

ensure_dir(APP_STORAGE);
ensure_dir(APP_STORAGE . '/logs');
ensure_upload_directory();

Database::migrate();
seed_reference_data(db());
create_admin(db(), $username, $password, true, (string) config('owner.name'));
$seeded = seed_owner_projects(db());

file_put_contents(APP_STORAGE . '/install.lock', json_encode([
    'installed_at' => now_utc(),
    'php'          => PHP_VERSION,
    'seeded'       => $seeded,
], JSON_PRETTY_PRINT));

log_activity(null, 'system.installed', 'system', null, 'Installed from CLI');

echo "Installed.\n";
echo 'Database: ' . Database::databasePath() . "\n";
echo 'Admin:    ' . $username . " (temporary password: " . ($password === 'admin' ? 'admin' : 'as provided') . ")\n";
echo 'Seeded:   ' . $seeded . " project(s)\n";
echo "Next: sign in at /admin/login.php and change the password.\n";
