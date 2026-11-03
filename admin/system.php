<?php
/**
 * System status, configuration summary and backup entry point.
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

require_admin();

$dbPath = Database::databasePath();
$dbSize = is_file($dbPath) ? (int) filesize($dbPath) : 0;
$dbWal = is_file($dbPath . '-wal') ? (int) filesize($dbPath . '-wal') : 0;
$diskFree = @disk_free_space(APP_STORAGE);
$installedAt = Database::meta('installed_at');
$transport = mail_transport();
$installerExists = is_file(APP_ROOT . '/install.php');

$guards = [
    '.htaccess (root)'       => APP_ROOT . '/.htaccess',
    'includes/.htaccess'     => APP_ROOT . '/includes/.htaccess',
    'storage/.htaccess'      => APP_ROOT . '/storage/.htaccess',
    'templates/.htaccess'    => APP_ROOT . '/templates/.htaccess',
    'tools/.htaccess'        => APP_ROOT . '/tools/.htaccess',
];

$adminCount = (int) db_value('SELECT COUNT(*) FROM admin_users');
$stats = dashboard_stats();

$page = [
    'title'    => 'System & backup',
    'subtitle' => 'Server checks and data safety',
    'active'   => 'system',
    'actions'  => '<a class="btn btn--primary btn--sm" href="' . e(admin_url('backup.php')) . '">'
        . icon('download', 'icon', 14) . ' Download database backup</a>',
];

admin_header($page);
?>

<div class="page-head">
    <div>
        <h2 class="page-head__title">System status</h2>
        <p class="page-head__sub">Everything the portfolio needs, and how to keep it healthy.</p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(admin_url('backup.php')) ?>">
            <?= icon('download', 'icon', 14) ?> Backup the database
        </a>
    </div>
</div>

<?php if ($transport !== 'native'): ?>
    <div class="alert alert--warning" role="status">
        <span class="alert__icon" aria-hidden="true"></span>
        <p>
            The mail transport is <strong><?= e($transport) ?></strong>, so contact-form emails are
            <strong>not</strong> being delivered. Set <code>'transport' =&gt; 'native'</code> in
            <code>includes/config.local.php</code> on the live server.
        </p>
    </div>
<?php endif; ?>

<?php if ($installerExists): ?>
    <div class="alert alert--warning" role="status">
        <span class="alert__icon" aria-hidden="true"></span>
        <p>
            <code>install.php</code> is still on the server. It refuses to reinstall while an admin
            exists, but deleting the file is safer.
        </p>
    </div>
<?php endif; ?>

<div class="system-grid">
    <section class="panel">
        <header class="panel__head">
            <h2 class="panel__title">Environment</h2>
        </header>
        <div class="panel__body">
            <dl class="system-list">
                <div class="system-row">
                    <dt>PHP version</dt>
                    <dd class="<?= version_compare(PHP_VERSION, '8.0.0', '>=') ? 'is-ok' : 'is-fail' ?>"><?= e(PHP_VERSION) ?></dd>
                </div>
                <div class="system-row">
                    <dt>PDO SQLite</dt>
                    <dd class="<?= extension_loaded('pdo_sqlite') ? 'is-ok' : 'is-fail' ?>">
                        <?= extension_loaded('pdo_sqlite') ? 'enabled' : 'missing' ?>
                    </dd>
                </div>
                <div class="system-row">
                    <dt>Fileinfo</dt>
                    <dd class="<?= extension_loaded('fileinfo') ? 'is-ok' : 'is-fail' ?>">
                        <?= extension_loaded('fileinfo') ? 'enabled' : 'missing' ?>
                    </dd>
                </div>
                <div class="system-row">
                    <dt>MBString</dt>
                    <dd class="<?= extension_loaded('mbstring') ? 'is-ok' : 'is-fail' ?>">
                        <?= extension_loaded('mbstring') ? 'enabled' : 'missing' ?>
                    </dd>
                </div>
                <div class="system-row">
                    <dt>Debug mode</dt>
                    <dd class="<?= APP_DEBUG ? 'is-fail' : 'is-ok' ?>"><?= APP_DEBUG ? 'ON (disable in production)' : 'off' ?></dd>
                </div>
                <div class="system-row">
                    <dt>Disk free</dt>
                    <dd><?= $diskFree !== false ? e(format_bytes((int) $diskFree)) : 'unknown' ?></dd>
                </div>
            </dl>
        </div>
    </section>

    <section class="panel">
        <header class="panel__head">
            <h2 class="panel__title">Data</h2>
        </header>
        <div class="panel__body">
            <dl class="system-list">
                <div class="system-row">
                    <dt>Database</dt>
                    <dd><?= e($dbPath) ?></dd>
                </div>
                <div class="system-row">
                    <dt>Size</dt>
                    <dd><?= e(format_bytes($dbSize)) ?></dd>
                </div>
                <div class="system-row">
                    <dt>Write-ahead log</dt>
                    <dd><?= $dbWal > 0 ? e(format_bytes($dbWal)) : 'empty' ?></dd>
                </div>
                <div class="system-row">
                    <dt>Storage writable</dt>
                    <dd class="<?= is_writable(APP_STORAGE) ? 'is-ok' : 'is-fail' ?>">
                        <?= is_writable(APP_STORAGE) ? 'yes' : 'no' ?>
                    </dd>
                </div>
                <div class="system-row">
                    <dt>Uploads writable</dt>
                    <dd class="<?= is_writable(upload_root()) ? 'is-ok' : 'is-fail' ?>">
                        <?= is_writable(upload_root()) ? 'yes' : 'no' ?>
                    </dd>
                </div>
                <div class="system-row">
                    <dt>Installed at</dt>
                    <dd><?= e(format_datetime($installedAt) ?: 'unknown') ?></dd>
                </div>
            </dl>
        </div>
    </section>

    <section class="panel">
        <header class="panel__head">
            <h2 class="panel__title">Content</h2>
        </header>
        <div class="panel__body">
            <dl class="system-list">
                <div class="system-row"><dt>Projects</dt><dd><?= e((string) $stats['total']) ?></dd></div>
                <div class="system-row"><dt>Published / drafts</dt><dd><?= e((string) $stats['published']) ?> / <?= e((string) $stats['draft']) ?></dd></div>
                <div class="system-row"><dt>Featured</dt><dd><?= e((string) $stats['featured']) ?></dd></div>
                <div class="system-row"><dt>Screenshots</dt><dd><?= e((string) $stats['images']) ?></dd></div>
                <div class="system-row"><dt>Categories</dt><dd><?= e((string) $stats['categories']) ?></dd></div>
                <div class="system-row"><dt>Technologies</dt><dd><?= e((string) $stats['technologies']) ?></dd></div>
                <div class="system-row"><dt>Admin users</dt><dd><?= e((string) $adminCount) ?></dd></div>
            </dl>
        </div>
    </section>

    <section class="panel">
        <header class="panel__head">
            <h2 class="panel__title">Private file protection</h2>
        </header>
        <div class="panel__body">
            <dl class="system-list">
                <?php foreach ($guards as $label => $path): ?>
                    <div class="system-row">
                        <dt><?= e($label) ?></dt>
                        <dd class="<?= is_file($path) ? 'is-ok' : 'is-fail' ?>">
                            <?= is_file($path) ? 'present' : 'missing' ?>
                        </dd>
                    </div>
                <?php endforeach; ?>
                <div class="system-row">
                    <dt>Storage outside web root</dt>
                    <dd class="<?= str_starts_with(APP_STORAGE, APP_ROOT) ? '' : 'is-ok' ?>">
                        <?= str_starts_with(APP_STORAGE, APP_ROOT) ? 'no — move it above public_html' : 'yes' ?>
                    </dd>
                </div>
            </dl>
        </div>
    </section>

    <section class="panel">
        <header class="panel__head">
            <h2 class="panel__title">Mail</h2>
        </header>
        <div class="panel__body">
            <dl class="system-list">
                <div class="system-row">
                    <dt>Transport</dt>
                    <dd class="<?= $transport === 'native' ? 'is-ok' : 'is-fail' ?>"><?= e($transport) ?></dd>
                </div>
                <div class="system-row"><dt>Sends to</dt><dd><?= e((string) config('mail.to')) ?></dd></div>
                <div class="system-row"><dt>From address</dt><dd><?= e((string) config('mail.from')) ?></dd></div>
                <div class="system-row"><dt>Reply-To</dt><dd>the visitor's address</dd></div>
            </dl>
            <p class="field__hint" style="margin-top: .8rem">
                If your host does not allow <code>mail()</code>, create the From address as a mailbox in
                cPanel and point <code>mail.from</code> at it. Messages are still stored in the dashboard
                if delivery fails.
            </p>
        </div>
    </section>

    <section class="panel">
        <header class="panel__head">
            <h2 class="panel__title">Backup</h2>
        </header>
        <div class="panel__body">
            <p class="field__hint">
                Download the SQLite database to keep an offline copy. To restore, stop using the site and
                replace <code>storage/portfolio.sqlite</code> (delete the <code>-wal</code> and
                <code>-shm</code> files first).
            </p>
            <a class="btn btn--primary btn--sm" href="<?= e(admin_url('backup.php')) ?>" style="margin-top: .8rem">
                <?= icon('download', 'icon', 14) ?> Download backup now
            </a>
            <p class="field__hint" style="margin-top: .8rem">
                Screenshots live in <code>assets/uploads/</code> — include that folder in your cPanel
                account backups as well.
            </p>
        </div>
    </section>
</div>

<?php admin_footer(); ?>
