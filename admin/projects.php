<?php
/**
 * Project list: search, filters, publishing actions.
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

require_admin();

$search = trim(query_string('q'));
$statusFilter = query_string('status', 'all');
if (!in_array($statusFilter, ['all', 'published', 'draft'], true)) {
    $statusFilter = 'all';
}
$pageNumber = max(1, to_int(query_string('page')) ?: 1);
$perPage = 12;

$result = admin_projects_page($search, $statusFilter, $pageNumber, $perPage);
$total = $result['total'];
$projects = $result['rows'];
$pageCount = max(1, (int) ceil($total / $perPage));

$page = [
    'title'    => 'Projects',
    'subtitle' => $total . ' total · ' . count($projects) . ' on this page',
    'active'   => 'projects',
];

admin_header($page);
?>

<div class="page-head">
    <div>
        <h2 class="page-head__title">All projects</h2>
        <p class="page-head__sub">Create, publish and organise the work shown on your portfolio.</p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--primary btn--sm" href="<?= e(admin_url('project-form.php')) ?>">
            <?= icon('plus', 'icon', 14) ?> Add project
        </a>
    </div>
</div>

<section class="panel">
    <form class="toolbar" method="get" action="<?= e(admin_url('projects.php')) ?>">
        <div class="toolbar__group">
            <label class="toolbar__search">
                <span class="visually-hidden">Search projects</span>
                <?= icon('search', 'icon', 16) ?>
                <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search projects…" maxlength="120">
            </label>
            <label>
                <span class="visually-hidden">Filter by status</span>
                <select name="status">
                    <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>All statuses</option>
                    <option value="published"<?= $statusFilter === 'published' ? ' selected' : '' ?>>Published</option>
                    <option value="draft"<?= $statusFilter === 'draft' ? ' selected' : '' ?>>Drafts</option>
                </select>
            </label>
            <button class="btn btn--ghost btn--sm" type="submit">Filter</button>
            <?php if ($search !== '' || $statusFilter !== 'all'): ?>
                <a class="btn btn--ghost btn--sm" href="<?= e(admin_url('projects.php')) ?>">Reset</a>
            <?php endif; ?>
        </div>
        <p class="panel__hint"><?= e((string) $total) ?> project<?= $total === 1 ? '' : 's' ?></p>
    </form>

    <?php if ($projects === []): ?>
        <div class="panel__body">
            <?php empty_state(
                $search !== '' || $statusFilter !== 'all' ? 'No matching projects' : 'No projects yet',
                $search !== '' || $statusFilter !== 'all'
                    ? 'Try a different search term or reset the filters.'
                    : 'Add the first project — title, description, images and technologies are all managed from here.',
                $search !== '' || $statusFilter !== 'all' ? 'Reset filters' : 'Add a project',
                $search !== '' || $statusFilter !== 'all' ? admin_url('projects.php') : admin_url('project-form.php')
            ); ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead>
                    <tr>
                        <th scope="col">Project</th>
                        <th scope="col">Category</th>
                        <th scope="col">Status</th>
                        <th scope="col">Updated</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                        <?php
                        $id = (int) $project['id'];
                        $isPublished = ($project['status'] ?? 'draft') === 'published';
                        $isFeatured = !empty($project['is_featured']);
                        $editUrl = admin_url('project-form.php?id=' . $id);
                        $previewUrl = project_url($project) . (str_contains(project_url($project), '?') ? '&' : '?') . 'preview=1';
                        ?>
                        <tr>
                            <td data-label="Project">
                                <a class="table__title" href="<?= e($editUrl) ?>"><?= e((string) $project['title']) ?></a>
                                <span class="table__meta">/<?= e((string) $project['slug']) ?></span>
                            </td>
                            <td data-label="Category"><?= e((string) ($project['category_name'] ?? '—')) ?></td>
                            <td data-label="Status">
                                <?= admin_status_badge((string) $project['status']) ?>
                                <?php if ($isFeatured): ?>
                                    <span class="badge"><?= icon('star', 'icon', 11) ?> Featured</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Updated"><?= e(time_ago((string) $project['updated_at'])) ?></td>
                            <td data-label="Actions">
                                <div class="table__actions">
                                    <form method="post" action="<?= e(admin_url('project-action.php')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) $id) ?>">
                                        <input type="hidden" name="action" value="<?= $isPublished ? 'unpublish' : 'publish' ?>">
                                        <button class="icon-btn<?= $isPublished ? '' : ' icon-btn--on' ?>"
                                                type="submit"
                                                title="<?= $isPublished ? 'Unpublish project' : 'Publish project' ?>"
                                                aria-label="<?= $isPublished ? 'Unpublish' : 'Publish' ?> <?= e((string) $project['title']) ?>">
                                            <?= icon($isPublished ? 'lock' : 'unlock', 'icon', 15) ?>
                                        </button>
                                    </form>

                                    <form method="post" action="<?= e(admin_url('project-action.php')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) $id) ?>">
                                        <input type="hidden" name="action" value="<?= $isFeatured ? 'unfeature' : 'feature' ?>">
                                        <button class="icon-btn<?= $isFeatured ? ' icon-btn--on' : '' ?>"
                                                type="submit"
                                                title="<?= $isFeatured ? 'Remove from featured' : 'Mark as featured' ?>"
                                                aria-label="<?= $isFeatured ? 'Unfeature' : 'Feature' ?> <?= e((string) $project['title']) ?>">
                                            <?= icon('star', 'icon', 15) ?>
                                        </button>
                                    </form>

                                    <a class="icon-btn" href="<?= e($previewUrl) ?>" target="_blank" rel="noopener"
                                       title="Preview project" aria-label="Preview <?= e((string) $project['title']) ?>">
                                        <?= icon('eye', 'icon', 15) ?>
                                    </a>

                                    <a class="icon-btn" href="<?= e($editUrl) ?>" title="Edit project"
                                       aria-label="Edit <?= e((string) $project['title']) ?>">
                                        <?= icon('pencil', 'icon', 15) ?>
                                    </a>

                                    <form method="post" action="<?= e(admin_url('project-delete.php')) ?>"
                                          data-confirm="Delete “<?= e((string) $project['title']) ?>” permanently? Its images will be removed too. This cannot be undone.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) $id) ?>">
                                        <button class="icon-btn icon-btn--danger" type="submit" title="Delete project"
                                                aria-label="Delete <?= e((string) $project['title']) ?>">
                                            <?= icon('trash', 'icon', 15) ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="panel__body">
            <?php admin_pagination($pageNumber, $pageCount, admin_url('projects.php'), array_filter([
                'q'      => $search !== '' ? $search : null,
                'status' => $statusFilter !== 'all' ? $statusFilter : null,
            ])); ?>
        </div>
    <?php endif; ?>
</section>

<?php admin_footer(); ?>
