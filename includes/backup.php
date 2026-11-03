<?php
/**
 * Database backup + restore.
 *
 * Restore is intentionally conservative:
 *  1. the upload must really be a SQLite file (magic bytes, not extension);
 *  2. it is opened read-only, integrity-checked and schema-checked against the
 *     version this application expects;
 *  3. a safety copy of the live database is written first;
 *  4. the swap happens with renames, and any failure rolls the old file back;
 *  5. the restored file is integrity-checked again before the request continues.
 *
 * Only a signed-in admin can reach this code (see admin/restore.php).
 */

declare(strict_types=1);

/** Where automatic pre-restore safety copies live. */
function backup_directory(): string
{
    return APP_STORAGE . '/backups';
}

/** Staging area for uploaded restore files. */
function restore_directory(): string
{
    return APP_STORAGE . '/restore';
}

/** Remove abandoned uploads/rollback files older than a day. */
function prune_restore_staging(): void
{
    $directory = restore_directory();
    if (!is_dir($directory)) {
        return;
    }

    foreach (glob($directory . '/*') ?: [] as $file) {
        if (is_file($file) && (int) @filemtime($file) < time() - 86400) {
            @unlink($file);
        }
    }
}

/** Fold the SQLite write-ahead log into the main database file. */
function checkpoint_database(): void
{
    try {
        db()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    } catch (Throwable $exception) {
        app_log('backup', 'WAL checkpoint failed', ['error' => $exception->getMessage()]);
    }
}

function is_sqlite_file(string $path): bool
{
    if (!is_file($path)) {
        return false;
    }

    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return false;
    }
    $magic = (string) fread($handle, 16);
    fclose($handle);

    return $magic === "SQLite format 3\x00";
}

function database_upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE   => 'The backup is larger than this server allows.',
        UPLOAD_ERR_FORM_SIZE  => 'The backup is larger than the form allows.',
        UPLOAD_ERR_PARTIAL    => 'The upload did not finish. Please try again.',
        UPLOAD_ERR_NO_FILE    => 'Choose a backup file to restore.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary upload directory.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
        UPLOAD_ERR_EXTENSION  => 'A server extension stopped the upload.',
        default               => 'The upload failed (error ' . $code . ').',
    };
}

/* -------------------------------------------------------------------------
 * Inspecting a candidate database
 * ---------------------------------------------------------------------- */

/**
 * Validate a SQLite file before it is allowed near the live site.
 *
 * @return array{ok:bool, error:string, meta:array<string, int|string>}
 */
function inspect_sqlite_backup(string $path): array
{
    $fail = static fn (string $message): array => ['ok' => false, 'error' => $message, 'meta' => []];

    if (!is_file($path)) {
        return $fail('The uploaded file could not be read.');
    }

    $size = (int) filesize($path);
    $maxBytes = max(1024 * 1024, (int) config('backup.max_restore_bytes', 100 * 1024 * 1024));

    if ($size <= 0) {
        return $fail('That file is empty.');
    }
    if ($size > $maxBytes) {
        return $fail('Backups must be ' . strtoupper(human_filesize($maxBytes)) . ' or smaller.');
    }
    if (!is_sqlite_file($path)) {
        return $fail('That is not a SQLite database. Upload the .sqlite file downloaded from this site.');
    }

    $pdo = null;
    try {
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec('PRAGMA query_only = ON');

        $integrity = $pdo->query('PRAGMA integrity_check')->fetchColumn();
        if (strtolower(trim((string) $integrity)) !== 'ok') {
            return $fail('The database is corrupted (integrity check failed).');
        }

        $tables = array_column(
            $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(),
            'name'
        );

        $requiredTables = [
            'admin_users', 'projects', 'project_images', 'project_categories',
            'technologies', 'project_technologies', 'contact_messages',
            'activity_log', 'login_attempts', 'app_meta',
        ];
        $missing = array_values(array_diff($requiredTables, $tables));
        if ($missing !== []) {
            return $fail('That database is not a backup of this portfolio (missing tables: ' . implode(', ', $missing) . ').');
        }

        $triggerCount = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'trigger'")->fetchColumn();
        if ($triggerCount > 0) {
            return $fail('That database contains triggers, which this application never creates. It was rejected for safety.');
        }

        $foreignKeyProblem = $pdo->query('PRAGMA foreign_key_check')->fetch();
        if ($foreignKeyProblem !== false) {
            return $fail('The database has broken foreign keys, so it was not restored.');
        }

        $schemaVersion = (int) $pdo->query("SELECT value FROM app_meta WHERE key = 'schema_version'")->fetchColumn();
        $expectedVersion = Database::schemaVersion();
        if ($schemaVersion !== $expectedVersion) {
            return $fail(
                'This backup uses schema version ' . $schemaVersion . ', but the site expects version '
                . $expectedVersion . '. Use a backup from the same version of the portfolio.'
            );
        }

        $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM admin_users WHERE password_hash <> ''")->fetchColumn();
        if ($adminCount < 1) {
            return $fail('That database has no usable admin account, so you would be locked out.');
        }

        $projectCount = (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
        $messageCount = (int) $pdo->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn();
        $categoryCount = (int) $pdo->query('SELECT COUNT(*) FROM project_categories')->fetchColumn();

        return [
            'ok'    => true,
            'error' => '',
            'meta'  => [
                'size'       => $size,
                'projects'   => $projectCount,
                'categories' => $categoryCount,
                'messages'   => $messageCount,
                'admins'     => $adminCount,
                'schema'     => $schemaVersion,
            ],
        ];
    } catch (Throwable $exception) {
        app_log('backup', 'SQLite inspection failed', ['error' => $exception->getMessage()]);

        return $fail('That file could not be opened as a SQLite database.');
    } finally {
        $pdo = null;
    }
}

/* -------------------------------------------------------------------------
 * Safety copies
 * ---------------------------------------------------------------------- */

/**
 * Copy the live database into storage/backups.
 *
 * @return array{ok:bool, name:string, path:string, error:string}
 */
function create_database_backup(string $prefix = 'pre-restore'): array
{
    checkpoint_database();

    $directory = backup_directory();
    if (!ensure_dir($directory, 0700)) {
        return ['ok' => false, 'name' => '', 'path' => '', 'error' => 'The backup directory is not writable.'];
    }

    $source = Database::databasePath();
    if (!is_file($source) || !is_readable($source)) {
        return ['ok' => false, 'name' => '', 'path' => '', 'error' => 'The live database file could not be read.'];
    }

    $safePrefix = preg_replace('/[^a-z0-9-]+/i', '-', $prefix) ?: 'backup';
    $name = $safePrefix . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.sqlite';
    $target = $directory . '/' . $name;

    if (!@copy($source, $target)) {
        app_log('backup', 'Could not copy database for safety backup', ['source' => $source, 'target' => $target]);

        return ['ok' => false, 'name' => '', 'path' => '', 'error' => 'The safety copy could not be written.'];
    }

    @chmod($target, 0600);
    clearstatcache(true, $target);

    if ((int) @filesize($target) <= 0 || !is_sqlite_file($target)) {
        @unlink($target);

        return ['ok' => false, 'name' => '', 'path' => '', 'error' => 'The safety copy was not a valid database.'];
    }

    prune_database_backups();

    return ['ok' => true, 'name' => $name, 'path' => $target, 'error' => ''];
}

/** Keep only the newest N safety copies. */
function prune_database_backups(): void
{
    $keep = max(1, (int) config('backup.keep', 10));
    $files = glob(backup_directory() . '/*.sqlite') ?: [];
    if ($files === []) {
        return;
    }

    usort($files, static fn (string $a, string $b): int => (int) @filemtime($b) <=> (int) @filemtime($a));

    foreach (array_slice($files, $keep) as $old) {
        @unlink($old);
    }
}

/**
 * @return list<array{name:string, size:int, modified:string}>
 */
function list_database_backups(int $limit = 10): array
{
    $directory = backup_directory();
    if (!is_dir($directory)) {
        return [];
    }

    $files = glob($directory . '/*.sqlite') ?: [];
    usort($files, static fn (string $a, string $b): int => (int) @filemtime($b) <=> (int) @filemtime($a));

    $backups = [];
    foreach (array_slice($files, 0, max(1, $limit)) as $file) {
        $mtime = (int) @filemtime($file);
        $backups[] = [
            'name'     => basename($file),
            'size'     => (int) @filesize($file),
            'modified' => $mtime > 0 ? gmdate('Y-m-d H:i:s', $mtime) : '',
        ];
    }

    return $backups;
}

/* -------------------------------------------------------------------------
 * Restore
 * ---------------------------------------------------------------------- */

/** Rename with a few retries: Windows and antivirus scanners can hold locks briefly. */
function move_file_with_retry(string $from, string $to): bool
{
    for ($attempt = 0; $attempt < 6; $attempt++) {
        clearstatcache(true, $from);
        clearstatcache(true, $to);
        if (@rename($from, $to)) {
            return true;
        }
        usleep(150000);
    }

    return false;
}

/**
 * Verify the file that is now live.
 *
 * @param array<string, int|string> $expected
 * @return array{ok:bool, error:string}
 */
function verify_restored_database(string $path, array $expected): array
{
    try {
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA query_only = ON');

        $integrity = $pdo->query('PRAGMA integrity_check')->fetchColumn();
        if (strtolower(trim((string) $integrity)) !== 'ok') {
            return ['ok' => false, 'error' => 'The restored database failed its integrity check.'];
        }

        $tables = array_column($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(), 'name');
        if (!in_array('admin_users', $tables, true) || !in_array('projects', $tables, true)) {
            return ['ok' => false, 'error' => 'The restored database is missing required tables.'];
        }

        $schema = (int) $pdo->query("SELECT value FROM app_meta WHERE key = 'schema_version'")->fetchColumn();
        if ($schema !== Database::schemaVersion()) {
            return ['ok' => false, 'error' => 'The restored database has an unexpected schema version.'];
        }

        $projects = (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
        if (isset($expected['projects']) && (int) $expected['projects'] !== $projects) {
            return ['ok' => false, 'error' => 'The restored database does not contain the expected project count.'];
        }

        $admins = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ($admins < 1) {
            return ['ok' => false, 'error' => 'The restored database has no admin account.'];
        }

        $pdo = null;

        return ['ok' => true, 'error' => ''];
    } catch (Throwable $exception) {
        app_log('backup', 'Restored database verification failed', ['error' => $exception->getMessage()]);

        return ['ok' => false, 'error' => 'The restored database could not be verified.'];
    }
}

/**
 * Replace the live database with an uploaded backup.
 *
 * @param array<string, mixed> $file a single $_FILES entry
 * @return array{
 *   ok:bool, error:string, pre_backup:string, meta:array<string,int|string>,
 *   projects:int, safety_path:string
 * }
 */
function restore_database_from_upload(array $file, ?int $actorId = null): array
{
    $failure = static fn (string $message): array => [
        'ok' => false, 'error' => $message, 'pre_backup' => '',
        'meta' => [], 'projects' => 0, 'safety_path' => '',
    ];

    if (!isset($file['error'], $file['tmp_name'], $file['size'])) {
        return $failure('Choose a backup file to restore.');
    }
    if (is_array($file['error'])) {
        return $failure('Upload one backup file at a time.');
    }

    $uploadError = (int) $file['error'];
    if ($uploadError !== UPLOAD_ERR_OK) {
        return $failure(database_upload_error_message($uploadError));
    }

    $tmp = (string) $file['tmp_name'];
    $size = (int) $file['size'];
    $originalName = (string) ($file['name'] ?? 'backup.sqlite');

    if ($size <= 0) {
        return $failure('That file is empty.');
    }
    if ($size > max(1024 * 1024, (int) config('backup.max_restore_bytes', 100 * 1024 * 1024))) {
        return $failure('That backup is too large for this server.');
    }
    if (!is_uploaded_file($tmp)) {
        return $failure('The upload could not be verified.');
    }

    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, ['sqlite', 'sqlite3', 'db'], true)) {
        return $failure('Upload the .sqlite backup file (or .db/.sqlite3).');
    }
    if (!is_sqlite_file($tmp)) {
        return $failure('That file is not a SQLite database. Upload the file downloaded from System & backup.');
    }

    if (!ensure_dir(restore_directory(), 0700)) {
        return $failure('The restore staging directory is not writable.');
    }

    prune_restore_staging();

    $stage = restore_directory() . '/upload-' . bin2hex(random_bytes(8)) . '.sqlite';
    if (!@move_uploaded_file($tmp, $stage)) {
        app_log('backup', 'Could not stage uploaded database', ['name' => $originalName]);

        return $failure('The uploaded file could not be staged for restore.');
    }
    @chmod($stage, 0600);
    clearstatcache(true, $stage);

    $inspection = inspect_sqlite_backup($stage);
    if (!$inspection['ok']) {
        @unlink($stage);
        @unlink($stage . '-wal');
        @unlink($stage . '-shm');

        return $failure($inspection['error']);
    }

    // Inspection may leave SQLite sidecars next to the staged copy.
    @unlink($stage . '-wal');
    @unlink($stage . '-shm');

    // The live file is never touched until a safety copy exists.
    $safety = create_database_backup('pre-restore');
    if (!$safety['ok']) {
        @unlink($stage);

        return $failure('Restore stopped: ' . $safety['error']);
    }

    checkpoint_database();
    Database::resetConnection();
    gc_collect_cycles();

    $dbPath = Database::databasePath();
    $rollback = restore_directory() . '/rollback-' . bin2hex(random_bytes(8)) . '.sqlite';

    foreach ([$dbPath . '-wal', $dbPath . '-shm'] as $sidecar) {
        if (is_file($sidecar)) {
            @unlink($sidecar);
        }
    }

    if (!is_file($dbPath) || !move_file_with_retry($dbPath, $rollback)) {
        @unlink($stage);

        return $failure('The live database is locked by another request. Wait a moment and try again.');
    }

    if (!move_file_with_retry($stage, $dbPath)) {
        // Put the original file back; nothing should have been lost.
        $rolledBack = move_file_with_retry($rollback, $dbPath);
        @unlink($stage);

        if (!$rolledBack) {
            app_log('backup', 'Critical: rollback failed after swap failure', [
                'safety' => $safety['name'],
            ]);

            return [
                'ok'          => false,
                'error'       => 'The uploaded file could not be moved into place and the automatic rollback also failed. '
                    . 'Restore the safety copy ' . $safety['name'] . ' from storage/backups manually.',
                'pre_backup'  => $safety['name'],
                'meta'        => [],
                'projects'    => 0,
                'safety_path' => $safety['path'],
            ];
        }

        return $failure('The uploaded database could not be moved into place. The original database is still live.');
    }

    @chmod($dbPath, 0600);
    Database::resetConnection();

    $verification = verify_restored_database($dbPath, $inspection['meta']);
    if (!$verification['ok']) {
        // Roll back to the file we moved aside.
        @unlink($dbPath);
        $rolledBack = move_file_with_retry($rollback, $dbPath);
        Database::resetConnection();
        app_log('backup', 'Restore rolled back after verification failed', [
            'error'  => $verification['error'],
            'safety' => $safety['name'],
            'rolled_back' => $rolledBack,
        ]);

        return [
            'ok'          => false,
            'error'       => $verification['error'] . ($rolledBack
                ? ' The previous database was put back.'
                : ' The automatic rollback also failed; restore the safety copy '
                    . $safety['name'] . ' from storage/backups manually.'),
            'pre_backup'  => $safety['name'],
            'meta'        => [],
            'projects'    => 0,
            'safety_path' => $safety['path'],
        ];
    }

    @unlink($rollback);

    if (!empty($inspection['meta']['projects'])) {
        $projectCount = (int) $inspection['meta']['projects'];
    } else {
        $projectCount = 0;
    }

    log_activity(
        $actorId,
        'system.restored',
        'system',
        null,
        'Restored the database from ' . mb_substr(preg_replace('/[^\w.\- ]+/', '', $originalName) ?: 'a backup', 0, 120)
    );

    app_log('backup', 'Database restored from upload', [
        'original' => $originalName,
        'safety'   => $safety['name'],
        'projects' => $projectCount,
    ]);

    return [
        'ok'          => true,
        'error'       => '',
        'pre_backup'  => $safety['name'],
        'meta'        => $inspection['meta'],
        'projects'    => $projectCount,
        'safety_path' => $safety['path'],
    ];
}
