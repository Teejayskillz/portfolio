<?php
/**
 * Admin authentication.
 *
 * - password_hash / password_verify only, never plain text;
 * - session id regenerated on login, CSRF token rotated;
 * - idle + absolute session timeouts;
 * - login throttling stored in SQLite (works on shared hosting, no Redis);
 * - a forced password change after the first (temporary) login.
 */

declare(strict_types=1);

function admin_session_active(): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }

    return !empty($_SESSION['admin_user_id']);
}

/** The signed-in admin row, or null. */
function admin_user(): ?array
{
    static $user = null;
    if (is_array($user)) {
        return $user;
    }

    if (PHP_SAPI === 'cli') {
        return null;
    }

    $id = (int) ($_SESSION['admin_user_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $row = db_one(
        'SELECT id, username, display_name, must_change_password, last_login_at, created_at
         FROM admin_users WHERE id = :id LIMIT 1',
        [':id' => $id]
    );

    return $user = $row;
}

function admin_display_name(): string
{
    $user = admin_user();
    $name = trim((string) ($user['display_name'] ?? ''));

    return $name !== '' ? $name : 'Administrator';
}

/** @return array{limited:bool, message:string, retry_after:int} */
function login_throttle(string $username, string $ip): array
{
    $max = max(1, (int) config('security.login_max_attempts', 5));
    $window = max(60, (int) config('security.login_window', 900));
    $since = gmdate('Y-m-d H:i:s', time() - $window);

    $pairCount = (int) db_value(
        'SELECT COUNT(*) FROM login_attempts
         WHERE succeeded = 0 AND attempted_at >= :since AND username = :username AND ip_address = :ip',
        [':since' => $since, ':username' => $username, ':ip' => $ip]
    );

    $ipCount = (int) db_value(
        'SELECT COUNT(*) FROM login_attempts
         WHERE succeeded = 0 AND attempted_at >= :since AND ip_address = :ip',
        [':since' => $since, ':ip' => $ip]
    );

    if ($pairCount < $max && $ipCount < $max * 2) {
        return ['limited' => false, 'message' => '', 'retry_after' => 0];
    }

    $oldest = db_value(
        'SELECT MIN(attempted_at) FROM login_attempts
         WHERE succeeded = 0 AND attempted_at >= :since AND (username = :username OR ip_address = :ip)',
        [':since' => $since, ':username' => $username, ':ip' => $ip]
    );

    $retry = $window;
    if (is_string($oldest) && $oldest !== '') {
        $timestamp = strtotime($oldest . ' UTC');
        if ($timestamp !== false) {
            $retry = max(30, $window - (time() - $timestamp));
        }
    }

    $minutes = max(1, (int) ceil($retry / 60));

    return [
        'limited'     => true,
        'message'     => 'Too many sign-in attempts. Please try again in about ' . $minutes . ' minute'
            . ($minutes === 1 ? '' : 's') . '.',
        'retry_after' => $retry,
    ];
}

function record_login_attempt(string $username, string $ip, bool $succeeded): void
{
    db_run(
        'INSERT INTO login_attempts (username, ip_address, succeeded, attempted_at) VALUES (:username, :ip, :succeeded, :attempted)',
        [
            ':username'  => mb_substr($username, 0, 60),
            ':ip'        => $ip,
            ':succeeded' => $succeeded ? 1 : 0,
            ':attempted' => now_utc(),
        ]
    );

    if ($succeeded) {
        db_run('DELETE FROM login_attempts WHERE succeeded = 0 AND username = :username AND ip_address = :ip', [
            ':username' => $username,
            ':ip'       => $ip,
        ]);
    }

    // Opportunistic housekeeping: keep the table from growing forever.
    if (random_int(1, 20) === 1) {
        db_run('DELETE FROM login_attempts WHERE attempted_at < :cutoff', [
            ':cutoff' => gmdate('Y-m-d H:i:s', time() - 2 * 86400),
        ]);
    }
}

/**
 * Verify credentials and return the user row.
 *
 * @return array{ok:bool, user:array<string,mixed>|null, message:string}
 */
function attempt_login(string $username, string $password, string $ip): array
{
    $throttle = login_throttle($username, $ip);
    if ($throttle['limited']) {
        app_log('security', 'Login throttled', ['username' => $username, 'ip' => $ip]);
        record_login_attempt($username, $ip, false);

        return ['ok' => false, 'user' => null, 'message' => $throttle['message']];
    }

    $user = db_one('SELECT * FROM admin_users WHERE username = :username LIMIT 1', [':username' => $username]);

    // Constant-ish time whether or not the username exists.
    $hash = is_array($user) ? (string) $user['password_hash'] : '$2y$10$CqMzgOox762AOTD42lP0Xexd2001dGmZ7fktx6/MIEUQW83tuA7N.';
    $valid = password_verify($password, $hash);

    if (!is_array($user) || !$valid) {
        record_login_attempt($username, $ip, false);
        app_log('security', 'Failed login', ['username' => $username, 'ip' => $ip]);
        usleep(350000);

        return ['ok' => false, 'user' => null, 'message' => 'Those credentials do not match our records.'];
    }

    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        if (is_string($newHash)) {
            db_run('UPDATE admin_users SET password_hash = :hash, updated_at = :updated WHERE id = :id', [
                ':hash'    => $newHash,
                ':updated' => now_utc(),
                ':id'      => (int) $user['id'],
            ]);
        }
    }

    record_login_attempt($username, $ip, true);
    log_activity((int) $user['id'], 'auth.login', 'admin', (int) $user['id'], 'Signed in to the dashboard');

    return ['ok' => true, 'user' => $user, 'message' => ''];
}

/** Regenerate the session and mark the admin as signed in. */
function establish_admin_session(array $user): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    session_regenerate_id(true);
    rotate_csrf_token();

    $_SESSION['admin_user_id'] = (int) $user['id'];
    $_SESSION['admin_username'] = (string) $user['username'];
    $_SESSION['admin_login_time'] = time();
    $_SESSION['admin_last_activity'] = time();

    db_run('UPDATE admin_users SET last_login_at = :now, updated_at = :now WHERE id = :id', [
        ':now' => now_utc(),
        ':id'  => (int) $user['id'],
    ]);
}

/** Destroy the admin session entirely. */
function destroy_admin_session(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    unset(
        $_SESSION['admin_user_id'],
        $_SESSION['admin_username'],
        $_SESSION['admin_login_time'],
        $_SESSION['admin_last_activity']
    );

    session_regenerate_id(true);
    rotate_csrf_token();
}

function sign_out_admin(): void
{
    $user = admin_user();
    if (is_array($user)) {
        log_activity((int) $user['id'], 'auth.logout', 'admin', (int) $user['id'], 'Signed out');
    }

    destroy_admin_session();
}

/** @return array{title:string, message:string}|null */
function admin_session_expiry(): ?array
{
    if (!admin_session_active()) {
        return null;
    }

    $now = time();
    $last = (int) ($_SESSION['admin_last_activity'] ?? 0);
    $started = (int) ($_SESSION['admin_login_time'] ?? 0);
    $idle = max(300, (int) config('security.session_idle', 1800));
    $max = max($idle, (int) config('security.session_max', 43200));

    if ($last > 0 && ($now - $last) > $idle) {
        return ['title' => 'Signed out', 'message' => 'Your session expired after a period of inactivity. Please sign in again.'];
    }
    if ($started > 0 && ($now - $started) > $max) {
        return ['title' => 'Signed out', 'message' => 'Your session reached its maximum age. Please sign in again.'];
    }

    return null;
}

/**
 * Gate every protected admin page. Handles expiry, missing users and the
 * mandatory first-login password change.
 */
function require_admin(): void
{
    if (!admin_session_active()) {
        flash('warning', 'Please sign in to open the dashboard.');
        redirect(admin_url('login.php'));
    }

    $expiry = admin_session_expiry();
    if ($expiry !== null) {
        destroy_admin_session();
        flash('warning', $expiry['message']);
        redirect(admin_url('login.php'));
    }

    $user = admin_user();
    if ($user === null) {
        destroy_admin_session();
        flash('error', 'Your account could not be loaded. Please sign in again.');
        redirect(admin_url('login.php'));
    }

    $_SESSION['admin_last_activity'] = time();

    $script = current_script();
    if ((int) $user['must_change_password'] === 1 && !in_array($script, ['change-password.php', 'logout.php'], true)) {
        flash('info', 'For security, choose a new password before continuing.');
        redirect(admin_url('change-password.php'));
    }
}

/** Change the signed-in admin's password and clear the must-change flag. */
function change_admin_password(array $user, string $newPassword): void
{
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') {
        throw new RuntimeException('Password hashing failed.');
    }

    db_run(
        'UPDATE admin_users SET password_hash = :hash, must_change_password = 0, updated_at = :updated WHERE id = :id',
        [':hash' => $hash, ':updated' => now_utc(), ':id' => (int) $user['id']]
    );

    db_run('DELETE FROM login_attempts WHERE username = :username', [':username' => (string) $user['username']]);
    log_activity((int) $user['id'], 'auth.password_changed', 'admin', (int) $user['id'], 'Password updated');
}
