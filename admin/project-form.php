<?php
/**
 * Add / edit a project.
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

require_admin();

$id = to_int(query_string('id')) ?: to_int(post_string('id'));
$project = $id > 0 ? project_by_id($id) : null;

if ($id > 0 && $project === null) {
    abort_page(404, 'Project not found', 'That project does not exist, or it was deleted.', 'Return to the project list and try again.');
}

$categories = categories_all();
$technologyGroups = technologies_grouped();
$coverPath = trim((string) ($project['cover_image'] ?? ''));
$galleryCount = $project !== null ? gallery_count((int) $project['id']) : 0;

$form = [
    'title'             => (string) ($project['title'] ?? ''),
    'slug'              => (string) ($project['slug'] ?? ''),
    'short_description' => (string) ($project['short_description'] ?? ''),
    'description'       => (string) ($project['description'] ?? ''),
    'problem'           => (string) ($project['problem'] ?? ''),
    'solution'          => (string) ($project['solution'] ?? ''),
    'category_id'       => (int) ($project['category_id'] ?? 0),
    'live_url'          => (string) ($project['live_url'] ?? ''),
    'github_url'        => (string) ($project['github_url'] ?? ''),
    'completion_date'   => (string) ($project['completion_date'] ?? ''),
    'status'            => (string) ($project['status'] ?? 'draft'),
    'is_featured'       => (int) ($project['is_featured'] ?? 0),
    'seo_title'         => (string) ($project['seo_title'] ?? ''),
    'seo_description'   => (string) ($project['seo_description'] ?? ''),
    'technologies'      => $project !== null ? project_technology_ids((int) $project['id']) : [],
];

$errors = [];

if (is_post()) {
    require_csrf();

    $validated = validate_project($_POST, $id);
    $errors = $validated['errors'];

    if ($errors === []) {
        try {
            $warnings = [];
            $newId = project_save($validated['data'], $id);
            $existingCover = $coverPath;

            $coverFile = $_FILES['cover'] ?? null;
            $hasUpload = is_array($coverFile) && (int) ($coverFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

            if ($hasUpload) {
                $stored = store_image_upload($coverFile);
                if ($stored['ok']) {
                    if ($existingCover !== '') {
                        remove_image_file($existingCover);
                    }
                    project_set_cover($newId, $stored['path']);
                    log_activity(current_actor_id(), 'image.uploaded', 'project', $newId, 'Updated the project cover image');
                } else {
                    $warnings[] = 'The cover image was not saved: ' . $stored['error'];
                }
            } elseif (isset($_POST['cover_remove']) && $existingCover !== '') {
                remove_image_file($existingCover);
                project_set_cover($newId, null);
                log_activity(current_actor_id(), 'image.deleted', 'project', $newId, 'Removed the project cover image');
            }

            flash('success', $id > 0 ? 'Project updated.' : 'Project created.');
            foreach ($warnings as $warning) {
                flash('warning', $warning);
            }

            redirect(admin_url('project-form.php?id=' . $newId));
        } catch (Throwable $exception) {
            app_log('admin', 'Project save failed', [
                'error' => $exception->getMessage(),
                'id'    => $id,
            ]);
            $errors['general'] = APP_DEBUG
                ? 'The project could not be saved: ' . $exception->getMessage()
                : 'The project could not be saved. Please try again.';
        }
    }

    // Re-render with the submitted values so nothing is lost.
    $form = [
        'title'             => post_string('title'),
        'slug'              => post_string('slug'),
        'short_description' => post_string('short_description'),
        'description'       => post_string('description'),
        'problem'           => post_string('problem'),
        'solution'          => post_string('solution'),
        'category_id'       => to_int($_POST['category_id'] ?? 0),
        'live_url'          => post_string('live_url'),
        'github_url'        => post_string('github_url'),
        'completion_date'   => post_string('completion_date'),
        'status'            => post_string('status', 'draft') === 'published' ? 'published' : 'draft',
        'is_featured'       => isset($_POST['is_featured']) ? 1 : 0,
        'seo_title'         => post_string('seo_title'),
        'seo_description'   => post_string('seo_description'),
        'technologies'      => array_map('intval', (array) ($_POST['technologies'] ?? [])),
    ];
}

$isNew = $project === null;
$previewUrl = $project !== null
    ? project_url($project) . (str_contains(project_url($project), '?') ? '&' : '?') . 'preview=1'
    : '';

$page = [
    'title'    => $isNew ? 'New project' : 'Edit project',
    'subtitle' => $isNew ? 'Add a project to your portfolio' : (string) $project['title'],
    'active'   => 'projects',
    'actions'  => $isNew
        ? '<a class="btn btn--ghost btn--sm" href="' . e(admin_url('projects.php')) . '">Back to projects</a>'
        : '<a class="btn btn--ghost btn--sm" href="' . e((string) $previewUrl) . '" target="_blank" rel="noopener">'
            . icon('eye', 'icon', 14) . ' Preview</a>',
];

admin_header($page);
?>

<?php if (isset($errors['general'])): ?>
    <div class="alerts">
        <div class="alert alert--error" role="alert">
            <span class="alert__icon" aria-hidden="true"></span>
            <p><?= e($errors['general']) ?></p>
        </div>
    </div>
<?php elseif ($errors !== []): ?>
    <div class="alerts">
        <div class="alert alert--error" role="alert">
            <span class="alert__icon" aria-hidden="true"></span>
            <p>Some fields need attention. The highlighted items below must be fixed before publishing.</p>
        </div>
    </div>
<?php endif; ?>

<div class="page-head">
    <div>
        <h2 class="page-head__title"><?= $isNew ? 'Project details' : 'Edit “' . e((string) $project['title']) . '”' ?></h2>
        <p class="page-head__sub">
            <?php if ($isNew): ?>
                Drafts can be saved with just a title. Publishing requires a short description, a full description and a category.
            <?php else: ?>
                Last updated <?= e(format_datetime((string) $project['updated_at'])) ?> ·
                <a href="<?= e(admin_url('project-media.php?id=' . (int) $project['id'])) ?>">Manage screenshots (<?= e((string) $galleryCount) ?>)</a>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(admin_url('projects.php')) ?>">Back to list</a>
        <?php if (!$isNew): ?>
            <a class="btn btn--ghost btn--sm" href="<?= e(admin_url('project-media.php?id=' . (int) $project['id'])) ?>">
                <?= icon('image', 'icon', 14) ?> Screenshots (<?= e((string) $galleryCount) ?>)
            </a>
        <?php endif; ?>
    </div>
</div>

<form method="post" action="<?= e(admin_url('project-form.php')) ?>" enctype="multipart/form-data" class="admin-form" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e((string) ($project['id'] ?? 0)) ?>">

    <div class="form-layout">
        <div>
            <section class="form-section">
                <div class="form-section__head">
                    <h2 class="form-section__title">Content</h2>
                    <p class="form-section__hint">Write for a business reader first; the technical detail comes after.</p>
                </div>

                <div class="field<?= isset($errors['title']) ? ' field--error' : '' ?>">
                    <label for="project-title">Project title *</label>
                    <input type="text" id="project-title" name="title" value="<?= e($form['title']) ?>"
                           required maxlength="160" data-slug-source autofocus>
                    <?php if (isset($errors['title'])): ?>
                        <p class="field__error"><?= e($errors['title']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['slug']) ? ' field--error' : '' ?>" style="margin-top: .8rem">
                    <label for="project-slug">URL slug</label>
                    <input type="text" id="project-slug" name="slug" value="<?= e($form['slug']) ?>"
                           maxlength="80" data-slug-target autocomplete="off" spellcheck="false">
                    <p class="field__hint">
                        Generated from the title. Public URL:
                        <code><?= e(rtrim((string) config('site_url'), '/')) ?>/project/<?= e($form['slug'] !== '' ? $form['slug'] : 'your-project') ?></code>
                        · <button type="button" class="btn btn--quiet" data-slug-reset>regenerate</button>
                    </p>
                    <?php if (isset($errors['slug'])): ?>
                        <p class="field__error"><?= e($errors['slug']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['short_description']) ? ' field--error' : '' ?>" style="margin-top: .8rem">
                    <label for="project-short">
                        Short description *
                        <span class="field__hint" data-count-for="project-short"></span>
                    </label>
                    <textarea id="project-short" name="short_description" rows="3" maxlength="300"
                              placeholder="One or two sentences for cards and search results."><?= e($form['short_description']) ?></textarea>
                    <?php if (isset($errors['short_description'])): ?>
                        <p class="field__error"><?= e($errors['short_description']) ?></p>
                    <?php else: ?>
                        <p class="field__hint">Shown on project cards. 300 characters maximum.</p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['description']) ? ' field--error' : '' ?>" style="margin-top: .8rem">
                    <label for="project-description">Full description *</label>
                    <textarea id="project-description" name="description" rows="9" maxlength="12000" class="textarea--tall"
                              placeholder="What it does, who it is for and how it works. Blank lines separate paragraphs."><?= e($form['description']) ?></textarea>
                    <?php if (isset($errors['description'])): ?>
                        <p class="field__error"><?= e($errors['description']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['problem']) ? ' field--error' : '' ?>" style="margin-top: .8rem">
                    <label for="project-problem">Problem statement</label>
                    <textarea id="project-problem" name="problem" rows="5" maxlength="5000"
                              placeholder="The business problem this project addresses."><?= e($form['problem']) ?></textarea>
                    <?php if (isset($errors['problem'])): ?>
                        <p class="field__error"><?= e($errors['problem']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['solution']) ? ' field--error' : '' ?>" style="margin-top: .8rem">
                    <label for="project-solution">Solution provided</label>
                    <textarea id="project-solution" name="solution" rows="5" maxlength="5000"
                              placeholder="What you built and the decisions behind it."><?= e($form['solution']) ?></textarea>
                    <?php if (isset($errors['solution'])): ?>
                        <p class="field__error"><?= e($errors['solution']) ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="form-section">
                <div class="form-section__head">
                    <h2 class="form-section__title">Technologies used</h2>
                    <p class="form-section__hint">Select everything the project actually uses.</p>
                </div>

                <div class="tech-picker">
                    <?php foreach ($technologyGroups as $group => $technologies): ?>
                        <div>
                            <p class="tech-picker__group-title"><?= e((string) $group) ?></p>
                            <div class="tech-picker__items">
                                <?php foreach ($technologies as $technology): ?>
                                    <?php $techId = (int) $technology['id']; ?>
                                    <label class="tech-option">
                                        <input type="checkbox" name="technologies[]" value="<?= e((string) $techId) ?>"
                                            <?= in_array($techId, $form['technologies'], true) ? 'checked' : '' ?>>
                                        <?= e((string) $technology['name']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="field__hint" style="margin-top: .8rem">
                    Need another technology? <a href="<?= e(admin_url('technologies.php')) ?>">Add it in Technologies</a>.
                </p>
            </section>

            <section class="form-section">
                <div class="form-section__head">
                    <h2 class="form-section__title">Search engines</h2>
                    <p class="form-section__hint">Optional. Leave blank to use the title and short description.</p>
                </div>

                <div class="field<?= isset($errors['seo_title']) ? ' field--error' : '' ?>">
                    <label for="project-seo-title">
                        Meta title
                        <span class="field__hint" data-count-for="project-seo-title"></span>
                    </label>
                    <input type="text" id="project-seo-title" name="seo_title" value="<?= e($form['seo_title']) ?>" maxlength="160">
                    <?php if (isset($errors['seo_title'])): ?>
                        <p class="field__error"><?= e($errors['seo_title']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['seo_description']) ? ' field--error' : '' ?>" style="margin-top: .8rem">
                    <label for="project-seo-description">
                        Meta description
                        <span class="field__hint" data-count-for="project-seo-description"></span>
                    </label>
                    <textarea id="project-seo-description" name="seo_description" rows="3" maxlength="300"><?= e($form['seo_description']) ?></textarea>
                    <?php if (isset($errors['seo_description'])): ?>
                        <p class="field__error"><?= e($errors['seo_description']) ?></p>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <aside class="form-aside">
            <section class="form-section">
                <div class="form-section__head">
                    <h2 class="form-section__title">Publishing</h2>
                    <p class="form-section__hint">Draft projects are only visible to you.</p>
                </div>

                <div class="field<?= isset($errors['status']) ? ' field--error' : '' ?>">
                    <label for="project-status">Status</label>
                    <select id="project-status" name="status">
                        <option value="draft"<?= $form['status'] === 'draft' ? ' selected' : '' ?>>Draft — hidden</option>
                        <option value="published"<?= $form['status'] === 'published' ? ' selected' : '' ?>>Published — live</option>
                    </select>
                </div>

                <label class="check-card" style="margin-top: .8rem">
                    <input type="checkbox" name="is_featured" value="1"<?= $form['is_featured'] === 1 ? ' checked' : '' ?>>
                    <span>
                        Featured project
                        <small>Featured projects appear first on the home page.</small>
                    </span>
                </label>

                <div class="field" style="margin-top: .8rem">
                    <label for="project-category">Category *</label>
                    <select id="project-category" name="category_id" class="<?= isset($errors['category_id']) ? 'is-invalid' : '' ?>">
                        <option value="0">— Uncategorised —</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) $category['id']) ?>"<?= (int) $form['category_id'] === (int) $category['id'] ? ' selected' : '' ?>>
                                <?= e((string) $category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['category_id'])): ?>
                        <p class="field__error"><?= e($errors['category_id']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['completion_date']) ? ' field--error' : '' ?>" style="margin-top: .8rem">
                    <label for="project-completion">Completion date</label>
                    <input type="date" id="project-completion" name="completion_date" value="<?= e($form['completion_date']) ?>">
                    <?php if (isset($errors['completion_date'])): ?>
                        <p class="field__error"><?= e($errors['completion_date']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button class="btn btn--primary btn--sm" type="submit">
                        <?= icon('check', 'icon', 14) ?> <?= $isNew ? 'Create project' : 'Save changes' ?>
                    </button>
                    <a class="btn btn--ghost btn--sm" href="<?= e(admin_url('projects.php')) ?>">Cancel</a>
                </div>
            </section>

            <section class="form-section">
                <div class="form-section__head">
                    <h2 class="form-section__title">Links</h2>
                    <p class="form-section__hint">Both optional. Must start with https://</p>
                </div>

                <div class="field<?= isset($errors['live_url']) ? ' field--error' : '' ?>">
                    <label for="project-live">Live project URL</label>
                    <input type="url" id="project-live" name="live_url" value="<?= e($form['live_url']) ?>"
                           placeholder="https://example.com" maxlength="500" inputmode="url">
                    <?php if (isset($errors['live_url'])): ?>
                        <p class="field__error"><?= e($errors['live_url']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= isset($errors['github_url']) ? ' field--error' : '' ?>" style="margin-top: .8rem">
                    <label for="project-github">Source code URL</label>
                    <input type="url" id="project-github" name="github_url" value="<?= e($form['github_url']) ?>"
                           placeholder="https://github.com/…" maxlength="500" inputmode="url">
                    <?php if (isset($errors['github_url'])): ?>
                        <p class="field__error"><?= e($errors['github_url']) ?></p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="form-section">
                <div class="form-section__head">
                    <h2 class="form-section__title">Cover image</h2>
                    <p class="form-section__hint">JPG, PNG or WebP · up to <?= e(strtoupper(human_filesize(upload_max_bytes()))) ?></p>
                </div>

                <div class="media-preview" data-preview-target>
                    <?php if ($coverPath !== ''): ?>
                        <img src="<?= e(upload_url($coverPath)) ?>" alt="Current cover image" width="800" height="500">
                    <?php else: ?>
                        <div class="media-preview__empty">No cover image yet<br>a designed fallback is used</div>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label for="project-cover">Upload / replace</label>
                    <input class="file-input" type="file" id="project-cover" name="cover"
                           accept="image/jpeg,image/png,image/webp" data-preview-input="[data-preview-target]">
                    <p class="upload-note">Uploading a new image replaces the current cover.</p>
                </div>

                <?php if ($coverPath !== ''): ?>
                    <label class="check-card" style="margin-top: .7rem">
                        <input type="checkbox" name="cover_remove" value="1">
                        <span>Remove the current cover image</span>
                    </label>
                <?php endif; ?>
            </section>

            <?php if (!$isNew): ?>
                <section class="form-section">
                    <div class="form-section__head">
                        <h2 class="form-section__title">Screenshots</h2>
                        <p class="form-section__hint"><?= e((string) $galleryCount) ?> uploaded</p>
                    </div>
                    <a class="btn btn--ghost btn--sm" href="<?= e(admin_url('project-media.php?id=' . (int) $project['id'])) ?>">
                        <?= icon('image', 'icon', 14) ?> Manage screenshots
                    </a>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</form>

<?php admin_footer(); ?>
