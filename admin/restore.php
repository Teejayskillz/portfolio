<?php
/**
 * Restore the database from an uploaded .sqlite backup.
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

require_admin();

if (is_post()) {
    require_csrf();

    if (empty($_POST['confirm_restore'])) {
        flash('error', 'Tick the confirmation box before restoring a database.');
        redirect(admin_url('restore.php'));
    }

    $result = restore_database_from_upload($_FILES['backup_file'] ?? [], current_actor_id());

    if (!$result['ok']) {
        flash('error', $result['error']);
        redirect(admin_url('restore.php'));
    }

    $projectCount = (int) $result['projects'];
    $safetyName = (string) $result['pre_backup'];

    // A backup may come from another installation, so the current session's
    // admin row might not exist in the restored data.
    $adminId = (int) ($_SESSION['admin_user_id'] ?? 0);
    $stillExists = $adminId > 0
        ? db_one('SELECT id FROM admin_users WHERE id = :id', [':id' => $adminId])
        : null;

    if ($stillExists === null) {
        destroy_admin_session();
        flash(
            'success',
            'Database restored (' . $projectCount . ' projects). Please sign in with the admin '
            . 'credentials that belong to that backup.'
        );
        redirect(admin_url('login.php'));
    }

    flash(
        'success',
        'Database restored successfully — ' . $projectCount . ' project' . ($projectCount === 1 ? '' : 's')
        . ' now live. A safety copy of the previous database was saved as ' . $safetyName . '.'
    );
    redirect(admin_url('system.php'));
}

$stats = dashboard_stats();
$dbPath = Database::databasePath();
$dbSize = is_file($dbPath) ? (int) filesize($dbPath) : 0;
$safetyCopies = list_database_backups(5);
$maxBytes = max(1024 * 1024, (int) config('backup.max_restore_bytes', 100 * 1024 * 1024));

$page = [
    'title'    => 'Restore backup',
    'subtitle' => 'Replace the live database from an uploaded backup',
    'active'   => 'system',
    'actions'  => '<a class="btn btn--ghost btn--sm" href="' . e(admin_url('system.php')) . '">'
        . icon('arrow-left', 'icon', 14) . ' System</a>',
];

admin_header($page);
?>

<div class="page-head">
    <div>
        <h2 class="page-head__title">Restore from a database backup</h2>
        <p class="page-head__sub">
            Upload a <code>.sqlite</code> file downloaded from this site. The current database is
            copied to <code>storage/backups</code> first, and the upload is validated before anything
            is replaced.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(admin_url('backup.php')) ?>">
            <?= icon('download', 'icon', 14) ?> Download current backup first
        </a>
    </div>
</div>

<div class="form-layout">
    <div>
        <section class="form-section">
            <div class="form-section__head">
                <h2 class="form-section__title">Upload a backup</h2>
                <p class="form-section__hint">
                    Accepted: SQLite databases up to <?= e(strtoupper(human_filesize($maxBytes))) ?>.
                    The file is verified by content, not by its extension.
                </p>
            </div>

            <div class="alert alert--warning" role="status" style="margin-bottom: 1rem">
                <span class="alert__icon" aria-hidden="true"></span>
                <p>
                    Restoring replaces projects, categories, technologies, activity and contact
                    messages with the contents of the backup. Uploaded images are <strong>not</strong>
                    in the database — they live in <code>assets/uploads/</code> and must be restored
                    separately.
                </p>
            </div>

            <form method="post" action="<?= e(admin_url('restore.php')) ?>" enctype="multipart/form-data"
                  class="admin-form" data-guard
                  data-confirm="Replace the live database with the uploaded file? A safety copy of the current database will be saved first.">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="backup-file">Database file *</label>
                    <input class="file-input" type="file" id="backup-file" name="backup_file" required
                           accept=".sqlite,.sqlite3,.db,application/vnd.sqlite3,application/octet-stream">
                    <p class="upload-note">
                        Typical name: <code>portfolio-YYYYMMDD-HHMMSS.sqlite</code> from System &amp; backup.
                    </p>
                </div>

                <label class="check-card" style="margin-top: 1rem">
                    <input type="checkbox" name="confirm_restore" value="1" required>
                    <span>
                        I understand this replaces the live database
                        <small>Projects and messages created after the backup will disappear. The safety copy can be downloaded from this page afterwards.</small>
                    </span>
                </label>

                <div class="form-actions">
                    <p class="field__hint">
                        If the upload is invalid, or any step fails, the current database stays live.
                    </p>
                    <button class="btn btn--danger" type="submit">
                        <?= icon('upload', 'icon', 14) ?> Validate &amp; restore
                    </button>
                </div>
            </form>
        </section>

        <section class="panel">
            <header class="panel__head">
                <h2 class="panel__title">Automatic safety copies</h2>
                <p class="panel__hint">Created before every restore · newest first</p>
            </header>

            <?php if ($safetyCopies === []): ?>
                <div class="panel__body">
                    <p class="panel__hint">
                        No safety copies yet. The first one is created automatically when you restore
                        a database.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table table--stack">
                        <thead>
                            <tr>
                                <th scope="col">File</th>
                                <th scope="col">Created</th>
                                <th scope="col">Size</th>
                                <th scope="col"><span class="visually-hidden">Download</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($safetyCopies as $copy): ?>
                                <tr>
                                    <td data-label="File"><span class="table__meta"><?= e($copy['name']) ?></span></td>
                                    <td data-label="Created"><?= e(format_datetime($copy['modified']) ?: '—') ?></td>
                                    <td data-label="Size"><?= e(format_bytes($copy['size'])) ?></td>
                                    <td data-label="Download">
                                        <div class="table__actions">
                                            <a class="icon-btn" href="<?= e(admin_url('backup.php?file=' . rawurlencode($copy['name']))) ?>"
                                               title="Download safety copy" aria-label="Download <?= e($copy['name']) ?>">
                                                <?= icon('download', 'icon', 15) ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div>
        <section class="form-section">
            <div class="form-section__head">
                <h2 class="form-section__title">Current database</h2>
                <p class="form-section__hint">This is what would be replaced.</p>
            </div>
            <dl class="system-list">
                <div class="system-row"><dt>Projects</dt><dd><?= e((string) $stats['total']) ?></dd></div>
                <div class="system-row"><dt>Categories</dt><dd><?= e((string) $stats['categories']) ?></dd></div>
                <div class="system-row"><dt>Technologies</dt><dd><?= e((string) $stats['technologies']) ?></dd></div>
                <div class="system-row"><dt>Messages</dt><dd><?= e((string) $stats['messages']) ?></dd></div>
                <div class="system-row"><dt>Size</dt><dd><?= e(format_bytes($dbSize)) ?></dd></div>
                <div class="system-row"><dt>Schema</dt><dd>v<?= e((string) Database::schemaVersion()) ?></dd></div>
                <div class="system-row"><dt>Path</dt><dd><?= e($dbPath) ?></dd></div>
            </dl>
        </section>

        <section class="form-section">
            <div class="form-section__head">
                <h2 class="form-section__title">How restore works</h2>
            </div>
            <ol class="system-list" style="counter-reset: restore-step">
                <li class="system-row">
                    <dt>1. Validate</dt>
                    <dd>SQLite header, integrity, tables, schema version, admin account, no triggers</dd>
                </li>
                <li class="system-row">
                    <dt>2. Safety copy</dt>
                    <dd>Current database copied to storage/backups</dd>
                </li>
                <li class="system-row">
                    <dt>3. Swap</dt>
                    <dd>Old file moved aside, upload moved in</dd>
                </li>
                <li class="system-row">
                    <dt>4. Verify</dt>
                    <dd>Integrity + project count re-checked; automatic rollback on failure</dd>
                </li>
            </ol>
            <p class="field__hint" style="margin-top: .9rem">
                A backup created by a different portfolio version or a different application is
                refused rather than half-restored.
            </p>
        </section>
    </div>
</div>

<?php admin_footer(); ?>
