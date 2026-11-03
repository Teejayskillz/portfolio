<?php
/**
 * Project categories: create, rename, reorder, delete.
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

require_admin();

$editingId = to_int(query_string('edit'));
$editing = $editingId > 0 ? category_by_id($editingId) : null;
$errors = [];
$form = [
    'name'       => (string) ($editing['name'] ?? ''),
    'slug'       => (string) ($editing['slug'] ?? ''),
    'sort_order' => (int) ($editing['sort_order'] ?? 0),
];

if (is_post()) {
    require_csrf();
    $action = post_string('action', 'create');

    if ($action === 'delete') {
        $id = to_int($_POST['id'] ?? 0);
        try {
            if (category_delete($id)) {
                flash('success', 'Category deleted.');
            } else {
                flash('error', 'That category could not be found.');
            }
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
        }

        redirect(admin_url('categories.php'));
    }

    $targetId = to_int($_POST['id'] ?? 0);
    $validated = validate_taxonomy($_POST, 'category');
    $errors = $validated['errors'];

    if ($errors === []) {
        $name = (string) $validated['data']['name'];
        $slug = (string) $validated['data']['slug'];
        $sort = to_int($_POST['sort_order'] ?? 0);

        if ($targetId > 0) {
            if (category_update($targetId, $name, $slug, $sort)) {
                flash('success', 'Category updated.');
                redirect(admin_url('categories.php'));
            }
            $errors['general'] = 'That category could not be updated.';
        } else {
            category_create($name, $slug, $sort);
            flash('success', 'Category added.');
            redirect(admin_url('categories.php'));
        }
    }

    $form = [
        'name'       => post_string('name'),
        'slug'       => post_string('slug'),
        'sort_order' => to_int($_POST['sort_order'] ?? 0),
    ];
    $editingId = $targetId;
    $editing = $targetId > 0 ? category_by_id($targetId) : null;
}

$categories = categories_all(true);
$totalProjects = (int) array_sum(array_map(static fn (array $row): int => (int) $row['project_count'], $categories));

$page = [
    'title'    => 'Categories',
    'subtitle' => count($categories) . ' categories categorising ' . $totalProjects . ' projects',
    'active'   => 'categories',
];

admin_header($page);
?>

<div class="page-head">
    <div>
        <h2 class="page-head__title">Project categories</h2>
        <p class="page-head__sub">Categories drive the public portfolio filter and the “Category” field on each project.</p>
    </div>
</div>

<div class="form-layout">
    <div>
        <section class="panel">
            <header class="panel__head">
                <h2 class="panel__title">All categories</h2>
                <p class="panel__hint">Lower sort numbers appear first</p>
            </header>

            <?php if ($categories === []): ?>
                <div class="panel__body">
                    <?php empty_state('No categories yet', 'Add your first category using the form beside this panel.', '', ''); ?>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table table--stack">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Slug</th>
                                <th scope="col">Order</th>
                                <th scope="col">Projects</th>
                                <th scope="col"><span class="visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <?php $categoryId = (int) $category['id']; ?>
                                <tr>
                                    <td data-label="Name"><span class="table__title"><?= e((string) $category['name']) ?></span></td>
                                    <td data-label="Slug"><span class="table__meta"><?= e((string) $category['slug']) ?></span></td>
                                    <td data-label="Order"><?= e((string) $category['sort_order']) ?></td>
                                    <td data-label="Projects"><?= e((string) $category['project_count']) ?></td>
                                    <td data-label="Actions">
                                        <div class="table__actions">
                                            <a class="icon-btn" href="<?= e(admin_url('categories.php?edit=' . $categoryId)) ?>"
                                               title="Edit category" aria-label="Edit <?= e((string) $category['name']) ?>">
                                                <?= icon('pencil', 'icon', 15) ?>
                                            </a>
                                            <form method="post" action="<?= e(admin_url('categories.php')) ?>"
                                                  data-confirm="Delete the category “<?= e((string) $category['name']) ?>”? Categories in use cannot be deleted.">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= e((string) $categoryId) ?>">
                                                <button class="icon-btn icon-btn--danger" type="submit" title="Delete category"
                                                        aria-label="Delete <?= e((string) $category['name']) ?>">
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
            <?php endif; ?>
        </section>
    </div>

    <div>
        <section class="form-section">
            <div class="form-section__head">
                <h2 class="form-section__title"><?= $editing !== null ? 'Edit category' : 'Add a category' ?></h2>
                <p class="form-section__hint">
                    <?= $editing !== null ? 'Update the name, URL slug or display order.' : 'Keep names short — they appear as filter pills.' ?>
                </p>
            </div>

            <?php if (isset($errors['general'])): ?>
                <div class="alert alert--error" role="alert">
                    <span class="alert__icon" aria-hidden="true"></span>
                    <p><?= e($errors['general']) ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(admin_url('categories.php')) ?>" class="admin-form" data-guard>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editing !== null ? 'update' : 'create' ?>">
                <input type="hidden" name="id" value="<?= e((string) ($editing['id'] ?? 0)) ?>">

                <div class="field<?= isset($errors['name']) ? ' field--error' : '' ?>">
                    <label for="category-name">Name *</label>
                    <input type="text" id="category-name" name="name" value="<?= e($form['name']) ?>" required maxlength="60">
                    <?php if (isset($errors['name'])): ?>
                        <p class="field__error"><?= e($errors['name']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field" style="margin-top: .7rem">
                    <label for="category-slug">Slug</label>
                    <input type="text" id="category-slug" name="slug" value="<?= e($form['slug']) ?>" maxlength="60"
                           placeholder="generated from the name" autocomplete="off" spellcheck="false">
                </div>

                <div class="field" style="margin-top: .7rem">
                    <label for="category-sort">Sort order</label>
                    <input type="number" id="category-sort" name="sort_order" value="<?= e((string) $form['sort_order']) ?>" min="0" max="999">
                </div>

                <div class="form-actions">
                    <?php if ($editing !== null): ?>
                        <a class="btn btn--ghost btn--sm" href="<?= e(admin_url('categories.php')) ?>">Cancel</a>
                    <?php endif; ?>
                    <button class="btn btn--primary btn--sm" type="submit">
                        <?= $editing !== null ? 'Save category' : 'Add category' ?>
                    </button>
                </div>
            </form>
        </section>
    </div>
</div>

<?php admin_footer(); ?>
