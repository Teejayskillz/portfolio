<?php
/**
 * Technologies: create, rename, regroup, delete.
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

require_admin();

$editingId = to_int(query_string('edit'));
$editing = $editingId > 0 ? technology_by_id($editingId) : null;
$errors = [];
$form = [
    'name'       => (string) ($editing['name'] ?? ''),
    'group_name' => (string) ($editing['group_name'] ?? 'Backend & Frameworks'),
    'sort_order' => (int) ($editing['sort_order'] ?? 0),
];

if (is_post()) {
    require_csrf();
    $action = post_string('action', 'create');

    if ($action === 'delete') {
        $id = to_int($_POST['id'] ?? 0);
        if (technology_delete($id)) {
            flash('success', 'Technology deleted and removed from all projects.');
        } else {
            flash('error', 'That technology could not be found.');
        }

        redirect(admin_url('technologies.php'));
    }

    $targetId = to_int($_POST['id'] ?? 0);
    $validated = validate_taxonomy($_POST, 'technology');
    $errors = $validated['errors'];

    if ($errors === []) {
        $name = (string) $validated['data']['name'];
        $group = (string) ($validated['data']['group_name'] ?? 'Other');
        $sort = to_int($_POST['sort_order'] ?? 0);

        if ($targetId > 0) {
            if (technology_update($targetId, $name, $group, $sort)) {
                flash('success', 'Technology updated.');
                redirect(admin_url('technologies.php'));
            }
            $errors['general'] = 'That technology could not be updated.';
        } else {
            technology_create($name, $group, $sort);
            flash('success', 'Technology added.');
            redirect(admin_url('technologies.php'));
        }
    }

    $form = [
        'name'       => post_string('name'),
        'group_name' => post_string('group_name', 'Other'),
        'sort_order' => to_int($_POST['sort_order'] ?? 0),
    ];
    $editingId = $targetId;
    $editing = $targetId > 0 ? technology_by_id($targetId) : null;
}

$technologies = technologies_all();
$groups = [];
foreach ($technologies as $technology) {
    $groups[(string) $technology['group_name']][] = $technology;
}
$groupNames = array_keys($groups);

$page = [
    'title'    => 'Technologies',
    'subtitle' => count($technologies) . ' technologies in ' . count($groups) . ' groups',
    'active'   => 'technologies',
];

admin_header($page);
?>

<div class="page-head">
    <div>
        <h2 class="page-head__title">Technology stack</h2>
        <p class="page-head__sub">These are the tags attached to projects and shown in the public stack section.</p>
    </div>
</div>

<div class="form-layout">
    <div>
        <section class="panel">
            <header class="panel__head">
                <h2 class="panel__title">All technologies</h2>
                <p class="panel__hint">Grouped exactly as they appear on the home page</p>
            </header>

            <?php if ($technologies === []): ?>
                <div class="panel__body">
                    <?php empty_state('No technologies yet', 'Add the tools you work with using the form beside this panel.', '', ''); ?>
                </div>
            <?php else: ?>
                <div class="panel__body">
                    <?php foreach ($groups as $group => $items): ?>
                        <div style="margin-bottom: 1.2rem">
                            <h3 class="tech-picker__group-title"><?= e((string) $group) ?> (<?= e((string) count($items)) ?>)</h3>
                            <div class="tech-picker__items">
                                <?php foreach ($items as $technology): ?>
                                    <?php $techId = (int) $technology['id']; ?>
                                    <div class="tech-option" style="cursor: default">
                                        <a href="<?= e(admin_url('technologies.php?edit=' . $techId)) ?>"
                                           style="color: inherit" title="Edit <?= e((string) $technology['name']) ?>">
                                            <?= e((string) $technology['name']) ?>
                                        </a>
                                        <form method="post" action="<?= e(admin_url('technologies.php')) ?>"
                                              data-confirm="Delete “<?= e((string) $technology['name']) ?>”? It will be removed from every project."
                                              style="display: inline-flex; margin-left: .2rem">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= e((string) $techId) ?>">
                                            <button type="submit" aria-label="Delete <?= e((string) $technology['name']) ?>"
                                                    style="border: 0; background: none; padding: 0 .1rem; color: var(--muted-2); font-size: .9rem; line-height: 1">
                                                ×
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div>
        <section class="form-section">
            <div class="form-section__head">
                <h2 class="form-section__title"><?= $editing !== null ? 'Edit technology' : 'Add a technology' ?></h2>
                <p class="form-section__hint">Group names control how the stack is presented publicly.</p>
            </div>

            <?php if (isset($errors['general'])): ?>
                <div class="alert alert--error" role="alert">
                    <span class="alert__icon" aria-hidden="true"></span>
                    <p><?= e($errors['general']) ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(admin_url('technologies.php')) ?>" class="admin-form" data-guard>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editing !== null ? 'update' : 'create' ?>">
                <input type="hidden" name="id" value="<?= e((string) ($editing['id'] ?? 0)) ?>">

                <div class="field<?= isset($errors['name']) ? ' field--error' : '' ?>">
                    <label for="tech-name">Name *</label>
                    <input type="text" id="tech-name" name="name" value="<?= e($form['name']) ?>" required maxlength="60"
                           placeholder="e.g. Django">
                    <?php if (isset($errors['name'])): ?>
                        <p class="field__error"><?= e($errors['name']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field" style="margin-top: .7rem">
                    <label for="tech-group">Group</label>
                    <input type="text" id="tech-group" name="group_name" value="<?= e($form['group_name']) ?>"
                           maxlength="60" list="tech-groups" placeholder="Backend &amp; Frameworks">
                    <datalist id="tech-groups">
                        <?php foreach ($groupNames as $groupName): ?>
                            <option value="<?= e((string) $groupName) ?>"></option>
                        <?php endforeach; ?>
                        <option value="Backend &amp; Frameworks"></option>
                        <option value="Frontend"></option>
                        <option value="Databases"></option>
                        <option value="CMS &amp; Platforms"></option>
                        <option value="Tooling"></option>
                    </datalist>
                </div>

                <div class="field" style="margin-top: .7rem">
                    <label for="tech-sort">Sort order</label>
                    <input type="number" id="tech-sort" name="sort_order" value="<?= e((string) $form['sort_order']) ?>" min="0" max="999">
                </div>

                <div class="form-actions">
                    <?php if ($editing !== null): ?>
                        <a class="btn btn--ghost btn--sm" href="<?= e(admin_url('technologies.php')) ?>">Cancel</a>
                    <?php endif; ?>
                    <button class="btn btn--primary btn--sm" type="submit">
                        <?= $editing !== null ? 'Save technology' : 'Add technology' ?>
                    </button>
                </div>
            </form>
        </section>
    </div>
</div>

<?php admin_footer(); ?>
