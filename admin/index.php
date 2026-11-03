<?php
/**
 * Dashboard overview.
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

require_admin();

$stats = dashboard_stats();
$recentProjects = admin_recent_projects(5);
$activity = recent_activity(7);
$transport = mail_transport();
$warnings = [];

if ($transport !== 'native') {
    $warnings[] = 'Mail transport is set to "' . $transport . '". Contact messages are not being emailed. Set it to native in includes/config.local.php before launch.';
}
if (!is_writable(APP_STORAGE)) {
    $warnings[] = 'The storage directory is not writable, so uploads and the database may fail.';
}
if (!is_writable(upload_root())) {
    $warnings[] = 'The uploads directory is not writable. Images cannot be saved.';
}
if ($stats['messages_failed'] > 0) {
    $warnings[] = $stats['messages_failed'] . ' contact message(s) could not be emailed. They are saved under Messages — reply manually.';
}

$page = [
    'title'    => 'Dashboard',
    'subtitle' => 'Overview of your portfolio',
    'active'   => 'dashboard',
    'actions'  => '<a class="btn btn--ghost btn--sm" href="' . e(projects_url()) . '" target="_blank" rel="noopener">'
        . icon('external', 'icon', 14) . ' View portfolio</a>',
];

admin_header($page);
?>

<?php if ($warnings !== []): ?>
    <div class="alerts">
        <?php foreach ($warnings as $warning): ?>
            <div class="alert alert--warning" role="status">
                <span class="alert__icon" aria-hidden="true"></span>
                <p><?= e($warning) ?></p>
                <button type="button" class="alert__close" data-dismiss-alert aria-label="Dismiss message">&times;</button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="stat-grid">
    <article class="stat-card stat-card--accent">
        <p class="stat-card__label"><?= icon('folder', 'icon', 13) ?> Total projects</p>
        <p class="stat-card__value"><?= e((string) $stats['total']) ?></p>
        <p class="stat-card__hint"><?= e((string) $stats['images']) ?> screenshots uploaded</p>
    </article>
    <article class="stat-card">
        <p class="stat-card__label"><?= icon('eye', 'icon', 13) ?> Published</p>
        <p class="stat-card__value"><?= e((string) $stats['published']) ?></p>
        <p class="stat-card__hint">Visible on the public site</p>
    </article>
    <article class="stat-card">
        <p class="stat-card__label"><?= icon('pencil', 'icon', 13) ?> Drafts</p>
        <p class="stat-card__value"><?= e((string) $stats['draft']) ?></p>
        <p class="stat-card__hint">Only visible to you</p>
    </article>
    <article class="stat-card">
        <p class="stat-card__label"><?= icon('star', 'icon', 13) ?> Featured</p>
        <p class="stat-card__value"><?= e((string) $stats['featured']) ?></p>
        <p class="stat-card__hint">Prioritised on the home page</p>
    </article>
</div>

<div class="dash-grid">
    <div>
        <section class="panel">
            <header class="panel__head">
                <h2 class="panel__title">Recent projects</h2>
                <a class="link-arrow" href="<?= e(admin_url('projects.php')) ?>">Manage all <?= icon('arrow-right', 'icon', 14) ?></a>
            </header>

            <?php if ($recentProjects === []): ?>
                <div class="panel__body">
                    <?php empty_state(
                        'No projects yet',
                        'Add your first project to start building the portfolio.',
                        'Add a project',
                        admin_url('project-form.php')
                    ); ?>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table table--stack">
                        <thead>
                            <tr>
                                <th scope="col">Project</th>
                                <th scope="col">Status</th>
                                <th scope="col">Updated</th>
                                <th scope="col"><span class="visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentProjects as $project): ?>
                                <?php $editUrl = admin_url('project-form.php?id=' . (int) $project['id']); ?>
                                <tr>
                                    <td data-label="Project">
                                        <a class="table__title" href="<?= e($editUrl) ?>"><?= e((string) $project['title']) ?></a>
                                        <span class="table__meta"><?= e((string) ($project['category_name'] ?? 'Uncategorised')) ?> · /<?= e((string) $project['slug']) ?></span>
                                    </td>
                                    <td data-label="Status"><?= admin_status_badge((string) $project['status']) ?></td>
                                    <td data-label="Updated"><?= e(time_ago((string) $project['updated_at'])) ?></td>
                                    <td data-label="Actions">
                                        <div class="table__actions">
                                            <a class="icon-btn" href="<?= e($editUrl) ?>" title="Edit project" aria-label="Edit <?= e((string) $project['title']) ?>">
                                                <?= icon('pencil', 'icon', 15) ?>
                                            </a>
                                            <a class="icon-btn" href="<?= e(project_url($project)) ?>?preview=1" target="_blank" rel="noopener" title="Preview project">
                                                <?= icon('eye', 'icon', 15) ?>
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

        <section class="panel">
            <header class="panel__head">
                <h2 class="panel__title">Recent activity</h2>
                <p class="panel__hint">Last <?= e((string) count($activity)) ?> actions</p>
            </header>
            <?php if ($activity === []): ?>
                <div class="panel__body">
                    <p class="panel__hint">Nothing logged yet. Actions such as publishing a project appear here.</p>
                </div>
            <?php else: ?>
                <div class="activity-list">
                    <?php foreach ($activity as $entry): ?>
                        <div class="activity-item">
                            <span class="activity-item__dot" aria-hidden="true"></span>
                            <div class="activity-item__body">
                                <span class="activity-item__text"><?= e((string) $entry['description']) ?></span>
                                <span class="activity-item__time">
                                    <?= e(time_ago((string) $entry['created_at'])) ?>
                                    <?php if (!empty($entry['username'])): ?>· <?= e((string) $entry['username']) ?><?php endif; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div>
        <section class="panel">
            <header class="panel__head">
                <h2 class="panel__title">Quick actions</h2>
            </header>
            <div class="quick-actions">
                <a href="<?= e(admin_url('project-form.php')) ?>"><?= icon('plus', 'icon', 16) ?> Add a new project</a>
                <a href="<?= e(admin_url('categories.php')) ?>"><?= icon('tag', 'icon', 16) ?> Manage categories</a>
                <a href="<?= e(admin_url('technologies.php')) ?>"><?= icon('layers', 'icon', 16) ?> Manage technologies</a>
                <a href="<?= e(admin_url('messages.php')) ?>"><?= icon('message', 'icon', 16) ?> Read contact messages</a>
                <a href="<?= e(admin_url('system.php')) ?>"><?= icon('shield', 'icon', 16) ?> System check &amp; backup</a>
            </div>
        </section>

        <section class="panel">
            <header class="panel__head">
                <h2 class="panel__title">At a glance</h2>
            </header>
            <div class="panel__body">
                <dl class="system-list">
                    <div class="system-row">
                        <dt>Categories</dt>
                        <dd><?= e((string) $stats['categories']) ?></dd>
                    </div>
                    <div class="system-row">
                        <dt>Technologies</dt>
                        <dd><?= e((string) $stats['technologies']) ?></dd>
                    </div>
                    <div class="system-row">
                        <dt>Contact messages</dt>
                        <dd><?= e((string) $stats['messages']) ?></dd>
                    </div>
                    <div class="system-row">
                        <dt>Mail transport</dt>
                        <dd class="<?= $transport === 'native' ? 'is-ok' : 'is-fail' ?>"><?= e($transport) ?></dd>
                    </div>
                    <div class="system-row">
                        <dt>Database</dt>
                        <dd>SQLite</dd>
                    </div>
                    <div class="system-row">
                        <dt>Data location</dt>
                        <dd><?= e(is_dir(APP_STORAGE) && str_starts_with(APP_STORAGE, APP_ROOT) ? 'public_html/storage' : APP_STORAGE) ?></dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="panel">
            <header class="panel__head">
                <h2 class="panel__title">Publishing checklist</h2>
            </header>
            <div class="panel__body">
                <ul class="system-list">
                    <li class="system-row">
                        <dt>Project has a cover image</dt>
                        <dd><?= icon('check', 'icon', 14) ?> optional</dd>
                    </li>
                    <li class="system-row">
                        <dt>Problem &amp; solution written</dt>
                        <dd>recommended</dd>
                    </li>
                    <li class="system-row">
                        <dt>SEO title &amp; description</dt>
                        <dd>recommended</dd>
                    </li>
                    <li class="system-row">
                        <dt>Live or source link</dt>
                        <dd>recommended</dd>
                    </li>
                </ul>
            </div>
        </section>
    </div>
</div>

<?php admin_footer(); ?>
