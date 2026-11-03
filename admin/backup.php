<?php
/**
 * Download a consistent copy of the SQLite database.
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

require_admin();

$dbPath = Database::databasePath();

try {
    db()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
} catch (Throwable $exception) {
    app_log('system', 'WAL checkpoint before backup failed', ['error' => $exception->getMessage()]);
}

if (!is_file($dbPath) || !is_readable($dbPath)) {
    abort_page(500, 'Backup unavailable', 'The database file could not be read. Check the storage directory permissions.');
}

log_activity(current_actor_id(), 'system.backup', 'system', null, 'Downloaded a database backup');

$filename = 'portfolio-' . date('Ymd-His') . '.sqlite';
$size = (int) filesize($dbPath);

header('Content-Type: application/vnd.sqlite3');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $size);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

while (ob_get_level() > 0) {
    ob_end_clean();
}

readfile($dbPath);
exit;
