<?php
/**
 * Dashboard-managed settings.
 *
 * Stored in SQLite so the owner can change email addresses, the visitor
 * confirmation and other copy without touching includes/config.php or
 * redeploying. Settings survive backups/restores because they live in the DB.
 *
 * The table is created lazily for installations that predate this feature, so
 * an older backup can still be opened and restored.
 */

declare(strict_types=1);

function ensure_settings_table(): bool
{
    static $ready = false;
    if ($ready) {
        return true;
    }

    try {
        db()->exec(
            'CREATE TABLE IF NOT EXISTS app_settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL DEFAULT \'\',
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $ready = true;

        return true;
    } catch (Throwable $exception) {
        app_log('settings', 'Could not create the app_settings table', ['error' => $exception->getMessage()]);

        return false;
    }
}

/** Read a dashboard setting, or the supplied default when it is not set. */
function setting_get(string $key, ?string $default = null): ?string
{
    if (!ensure_settings_table()) {
        return $default;
    }

    try {
        $stmt = db()->prepare('SELECT value FROM app_settings WHERE key = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $value = $stmt->fetchColumn();

        return $value === false ? $default : (string) $value;
    } catch (Throwable $exception) {
        app_log('settings', 'Could not read a setting', ['key' => $key, 'error' => $exception->getMessage()]);

        return $default;
    }
}

function setting_set(string $key, string $value): bool
{
    if (!ensure_settings_table()) {
        return false;
    }

    try {
        $stmt = db()->prepare(
            'INSERT INTO app_settings (key, value, updated_at) VALUES (:key, :value, :updated)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at'
        );
        $stmt->execute([
            ':key'     => $key,
            ':value'   => $value,
            ':updated' => now_utc(),
        ]);

        return true;
    } catch (Throwable $exception) {
        app_log('settings', 'Could not save a setting', ['key' => $key, 'error' => $exception->getMessage()]);

        return false;
    }
}

/** Remove every setting in a namespace, e.g. "mail." to restore defaults. */
function setting_delete_prefix(string $prefix): bool
{
    if (!ensure_settings_table()) {
        return false;
    }

    try {
        db()->prepare('DELETE FROM app_settings WHERE key LIKE :prefix')->execute([
            ':prefix' => $prefix . '%',
        ]);

        return true;
    } catch (Throwable $exception) {
        app_log('settings', 'Could not reset settings', ['prefix' => $prefix, 'error' => $exception->getMessage()]);

        return false;
    }
}

/* -------------------------------------------------------------------------
 * Mail configuration
 * ---------------------------------------------------------------------- */

/**
 * Defaults come from config.php so a fresh install works before the owner
 * opens the Email settings page.
 *
 * @return array<string, mixed>
 */
function mail_settings_defaults(): array
{
    return [
        'to'                   => (string) config('mail.to', ''),
        'from'                 => (string) config('mail.from', ''),
        'from_name'            => (string) config('mail.from_name', 'Website'),
        'confirmation_enabled' => true,
        'confirmation_subject' => 'Your message to {site_name}',
        'confirmation_body'    => "Hi {name},\n\n"
            . "Thank you for getting in touch through {site_name}. This is an automatic confirmation that "
            . "your message was received — {owner_name} will reply to you directly.\n\n"
            . "Your message:\n\"{message}\"\n\n"
            . "If you need to add anything, just reply to this email.\n\n"
            . "{owner_name}\n{site_name}\n{site_url}",
    ];
}

/**
 * Effective mail settings: dashboard values layered over config defaults.
 *
 * @return array{
 *   to:string, from:string, from_name:string, confirmation_enabled:bool,
 *   confirmation_subject:string, confirmation_body:string
 * }
 */
function mail_settings(): array
{
    $defaults = mail_settings_defaults();

    $to = trim((string) setting_get('mail.to', $defaults['to']));
    $from = trim((string) setting_get('mail.from', $defaults['from']));
    $fromName = trim((string) setting_get('mail.from_name', $defaults['from_name']));
    $enabledRaw = setting_get('mail.confirmation_enabled', $defaults['confirmation_enabled'] ? '1' : '0');
    $subject = (string) setting_get('mail.confirmation_subject', $defaults['confirmation_subject']);
    $body = (string) setting_get('mail.confirmation_body', $defaults['confirmation_body']);

    return [
        'to'                   => $to !== '' ? $to : $defaults['to'],
        'from'                 => $from !== '' ? $from : $defaults['from'],
        'from_name'            => $fromName !== '' ? $fromName : $defaults['from_name'],
        'confirmation_enabled' => $enabledRaw === '1',
        'confirmation_subject' => trim($subject) !== '' ? $subject : $defaults['confirmation_subject'],
        'confirmation_body'    => trim($body) !== '' ? $body : $defaults['confirmation_body'],
    ];
}

/** True once the owner has saved something from the dashboard. */
function mail_settings_are_custom(): bool
{
    foreach (['mail.to', 'mail.from', 'mail.from_name', 'mail.confirmation_enabled', 'mail.confirmation_subject', 'mail.confirmation_body'] as $key) {
        if (setting_get($key) !== null) {
            return true;
        }
    }

    return false;
}
