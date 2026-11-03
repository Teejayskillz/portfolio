<?php
/**
 * PDO/SQLite access layer.
 *
 * - One connection per request (lazy, so static pages never open the DB twice).
 * - Exceptions on error, real prepared statements, foreign keys enforced.
 * - The database file lives in APP_STORAGE, which is never web-readable.
 */

declare(strict_types=1);

final class Database
{
    private const SCHEMA_VERSION = 1;

    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('The PDO SQLite extension is not enabled on this server.');
        }

        if (!ensure_dir(APP_STORAGE)) {
            throw new RuntimeException('The storage directory is missing and could not be created: ' . APP_STORAGE);
        }

        $dbPath = self::databasePath();
        if (!ensure_dir(dirname($dbPath))) {
            throw new RuntimeException('The database directory could not be created: ' . dirname($dbPath));
        }

        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        try {
            $pdo->query('PRAGMA journal_mode = WAL')->fetchColumn();
            $pdo->exec('PRAGMA synchronous = NORMAL');
        } catch (PDOException) {
            // Read-only filesystems simply keep SQLite's defaults.
        }

        // Keep the database readable only by the hosting account.
        if (is_file($dbPath)) {
            @chmod($dbPath, 0600);
        }

        self::$pdo = $pdo;

        return $pdo;
    }

    public static function databasePath(): string
    {
        $configured = trim((string) config('storage.database', 'portfolio.sqlite'));
        if ($configured === '') {
            $configured = 'portfolio.sqlite';
        }

        $isAbsolute = str_starts_with($configured, '/')
            || str_starts_with($configured, '\\')
            || preg_match('#^[A-Za-z]:[\\\\/]#', $configured) === 1;

        return $isAbsolute ? $configured : APP_STORAGE . '/' . $configured;
    }

    /** True once the schema exists and at least one admin account can sign in. */
    public static function isInstalled(): bool
    {
        try {
            $pdo = self::connection();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'admin_users'");
            if ($stmt === false) {
                return false;
            }
            $stmt->execute();
            if ((int) $stmt->fetchColumn() === 0) {
                return false;
            }

            $admins = $pdo->query('SELECT COUNT(*) FROM admin_users');

            return $admins !== false && (int) $admins->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /** Create every table/index that is missing. Safe to call repeatedly. */
    public static function migrate(): void
    {
        $pdo = self::connection();
        $statements = require __DIR__ . '/schema.php';

        $pdo->beginTransaction();
        try {
            foreach ($statements as $sql) {
                $pdo->exec($sql);
            }
            $meta = $pdo->prepare('INSERT INTO app_meta (key, value) VALUES (:key, :value)
                ON CONFLICT(key) DO UPDATE SET value = excluded.value');
            $meta->execute([':key' => 'schema_version', ':value' => (string) self::SCHEMA_VERSION]);
            $meta->execute([':key' => 'installed_at', ':value' => now_utc()]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function meta(string $key, string $default = ''): string
    {
        try {
            $stmt = db()->prepare('SELECT value FROM app_meta WHERE key = :key LIMIT 1');
            $stmt->execute([':key' => $key]);
            $value = $stmt->fetchColumn();

            return is_string($value) ? $value : $default;
        } catch (Throwable) {
            return $default;
        }
    }

    public static function resetConnection(): void
    {
        self::$pdo = null;
    }
}

function db(): PDO
{
    return Database::connection();
}

/**
 * Prepare and execute a statement, returning the statement itself.
 *
 * @param array<string|int, mixed> $params
 */
function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt;
}

/**
 * Fetch a single row or null.
 *
 * @param array<string|int, mixed> $params
 * @return array<string, mixed>|null
 */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();

    return is_array($row) ? $row : null;
}

/**
 * Fetch all rows.
 *
 * @param array<string|int, mixed> $params
 * @return list<array<string, mixed>>
 */
function db_all(string $sql, array $params = []): array
{
    $rows = db_run($sql, $params)->fetchAll();

    return is_array($rows) ? $rows : [];
}

/**
 * Fetch a single scalar value.
 *
 * @param array<string|int, mixed> $params
 */
function db_value(string $sql, array $params = []): mixed
{
    $value = db_run($sql, $params)->fetchColumn();

    return $value === false ? null : $value;
}
