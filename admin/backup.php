<?php
/**
 * Download the live database or an automatic safety copy.
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

require_admin();

/**
 * Stream a SQLite file with download headers. Never exposes arbitrary paths.
 */
function download_sqlite_file(string $path, string $filename): void
{
    if (!is_file($path) || !is_readable($path)) {
        abort_page(404, 'Backup not found', 'That backup file no longer exists on the server.');
    }

    $size = (int) filesize($path);
    $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'portfolio.sqlite';

    header('Content-Type: application/vnd.sqlite3');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $size);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    readfile($path);
    exit;
}

$requested = trim(query_string('file'));

if ($requested !== '') {
    // Safety copies only: basename must match exactly and the file must live
    // inside storage/backups.
    if ($requested !== basename($requested) || preg_match('/^[A-Za-z0-9._-]+\.sqlite$/', $requested) !== 1) {
        abort_page(400, 'Invalid backup name', 'That backup name is not valid.');
    }

    $directory = realpath(backup_directory());
    $path = realpath(backup_directory() . '/' . $requested);

    if ($directory === false || $path === false) {
        abort_page(404, 'Backup not found', 'That safety copy does not exist on the server.');
    }

    $normalisedDir = rtrim(str_replace('\\', '/', $directory), '/') . '/';
    $normalisedPath = str_replace('\\', '/', $path);
    if (!str_starts_with($normalisedPath, $normalisedDir)) {
        abort_page(403, 'Not allowed', 'That file is outside the backup directory.');
    }

    log_activity(current_actor_id(), 'system.backup_downloaded', 'system', null, 'Downloaded safety copy ' . $requested);
    download_sqlite_file($path, $requested);
}

// Default: a consistent copy of the live database.
$dbPath = Database::databasePath();
if (!is_file($dbPath) || !is_readable($dbPath)) {
    abort_page(500, 'Backup unavailable', 'The database file could not be read. Check the storage directory permissions.');
}

log_activity(current_actor_id(), 'system.backup', 'system', null, 'Downloaded a database backup');

// Fold the log write (and any pending WAL pages) into the downloaded file.
checkpoint_database();

$filename = 'portfolio-' . date('Ymd-His') . '.sqlite';
download_sqlite_file($dbPath, $filename);
