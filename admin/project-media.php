<?php
/**
 * Project screenshots: upload, caption and delete.
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

require_admin();

$id = to_int(query_string('id')) ?: to_int(post_string('project_id'));
$project = $id > 0 ? project_by_id($id) : null;

if ($project === null) {
    abort_page(404, 'Project not found', 'That project does not exist, or it was deleted.', 'Return to the project list and try again.');
}

$maxGallery = max(1, (int) config('uploads.max_gallery', 8));
$errors = [];

if (is_post()) {
    require_csrf();
    $action = post_string('action', 'upload');

    if ($action === 'upload') {
        $existing = gallery_count($id);
        $slots = max(0, $maxGallery - $existing);
        $files = normalise_upload_array($_FILES['screenshots'] ?? []);
        $caption = post_string('caption');
        $uploaded = 0;

        if ($files === []) {
            $errors[] = 'Choose at least one image to upload.';
        } elseif ($slots === 0) {
            $errors[] = 'This project already has ' . $maxGallery . ' screenshots. Remove one before uploading another.';
        } else {
            foreach ($files as $file) {
                if ($uploaded >= $slots) {
                    $errors[] = 'Only ' . $slots . ' more image(s) could be added (limit ' . $maxGallery . ').';
                    break;
                }
                if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $stored = store_image_upload($file);
                if (!$stored['ok']) {
                    $errors[] = ($file['name'] !== '' ? $file['name'] . ': ' : '') . $stored['error'];
                    continue;
                }

                gallery_add($id, $stored['path'], $caption !== '' ? $caption : (string) $project['title'] . ' screenshot');
                $uploaded++;
            }
        }

        if ($uploaded > 0) {
            flash('success', $uploaded . ' screenshot' . ($uploaded === 1 ? '' : 's') . ' uploaded.');
        }
        foreach ($errors as $error) {
            flash('error', $error);
        }

        redirect(admin_url('project-media.php?id=' . $id));
    }

    if ($action === 'caption') {
        $imageId = to_int($_POST['image_id'] ?? 0);
        $caption = post_string('alt_text');
        $image = gallery_image($imageId);

        if ($image === null || (int) $image['project_id'] !== $id) {
            flash('error', 'That screenshot could not be found.');
        } elseif (gallery_set_alt($imageId, $caption)) {
            flash('success', 'Caption updated.');
        }

        redirect(admin_url('project-media.php?id=' . $id));
    }

    if ($action === 'delete') {
        $imageId = to_int($_POST['image_id'] ?? 0);
        $image = gallery_image($imageId);

        if ($image === null || (int) $image['project_id'] !== $id) {
            flash('error', 'That screenshot could not be found.');
        } elseif (gallery_delete($imageId)) {
            flash('success', 'Screenshot deleted.');
        } else {
            flash('error', 'The screenshot could not be deleted.');
        }

        redirect(admin_url('project-media.php?id=' . $id));
    }

    flash('error', 'Unknown media action.');
    redirect(admin_url('project-media.php?id=' . $id));
}

$gallery = project_gallery($id);
$remaining = max(0, $maxGallery - count($gallery));

$page = [
    'title'    => 'Screenshots',
    'subtitle' => (string) $project['title'],
    'active'   => 'projects',
    'actions'  => '<a class="btn btn--ghost btn--sm" href="' . e(admin_url('project-form.php?id=' . $id)) . '">'
        . icon('pencil', 'icon', 14) . ' Edit project</a>',
];

admin_header($page);
?>

<div class="page-head">
    <div>
        <h2 class="page-head__title">Screenshots for “<?= e((string) $project['title']) ?>”</h2>
        <p class="page-head__sub">
            <?= e((string) count($gallery)) ?> of <?= e((string) $maxGallery) ?> used ·
            <a href="<?= e(admin_url('projects.php')) ?>">Back to projects</a>
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(project_url($project)) ?>?preview=1" target="_blank" rel="noopener">
            <?= icon('eye', 'icon', 14) ?> Preview project
        </a>
    </div>
</div>

<section class="panel">
    <header class="panel__head">
        <h2 class="panel__title">Upload screenshots</h2>
        <p class="panel__hint">JPG, PNG or WebP · max <?= e(strtoupper(human_filesize(upload_max_bytes()))) ?> each · <?= e((string) $remaining) ?> slot<?= $remaining === 1 ? '' : 's' ?> left</p>
    </header>

    <div class="panel__body">
        <form method="post" action="<?= e(admin_url('project-media.php')) ?>" enctype="multipart/form-data" class="admin-form" data-guard>
            <?= csrf_field() ?>
            <input type="hidden" name="project_id" value="<?= e((string) $id) ?>">
            <input type="hidden" name="action" value="upload">

            <div class="field">
                <label for="media-files">Images</label>
                <input class="file-input" type="file" id="media-files" name="screenshots[]"
                       accept="image/jpeg,image/png,image/webp" multiple<?= $remaining === 0 ? ' disabled' : '' ?>>
                <p class="upload-note">Select multiple files at once. Files are renamed safely on upload. Use WebP or JPG around 1600 px wide to keep pages fast.</p>
            </div>

            <div class="field" style="margin-top: .8rem">
                <label for="media-caption">Caption / alt text <span class="optional">optional, applied to this batch</span></label>
                <input type="text" id="media-caption" name="caption" maxlength="200"
                       placeholder="e.g. Checkout screen on mobile">
                <p class="field__hint">Describe what the screenshot shows — it becomes the alt text for accessibility.</p>
            </div>

            <div class="form-actions">
                <p class="field__hint">Uploads are validated by real MIME type, not by file extension.</p>
                <button class="btn btn--primary btn--sm" type="submit"<?= $remaining === 0 ? ' disabled' : '' ?>>
                    <?= icon('upload', 'icon', 14) ?> Upload
                </button>
            </div>
        </form>
    </div>
</section>

<?php if ($gallery !== []): ?>
    <div class="thumb-grid" style="margin-top: 1rem">
        <?php foreach ($gallery as $index => $image): ?>
            <figure class="thumb">
                <a href="<?= e(upload_url((string) $image['file_path'])) ?>" data-lightbox
                   data-caption="<?= e((string) $image['alt_text']) ?>" aria-label="Preview screenshot">
                    <img src="<?= e(upload_url((string) $image['file_path'])) ?>"
                         alt="<?= e((string) $image['alt_text']) ?>" loading="lazy" width="600" height="450">
                </a>
                <figcaption class="thumb__caption"><?= e((string) ($image['alt_text'] !== '' ? $image['alt_text'] : 'Screenshot ' . ($index + 1))) ?></figcaption>
                <div class="thumb__delete">
                    <form method="post" action="<?= e(admin_url('project-media.php')) ?>"
                          data-confirm="Delete this screenshot permanently?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="project_id" value="<?= e((string) $id) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="image_id" value="<?= e((string) $image['id']) ?>">
                        <button class="icon-btn icon-btn--danger" type="submit" title="Delete screenshot" aria-label="Delete screenshot">
                            <?= icon('trash', 'icon', 14) ?>
                        </button>
                    </form>
                </div>
            </figure>
        <?php endforeach; ?>
    </div>

    <section class="panel">
        <header class="panel__head">
            <h2 class="panel__title">Captions</h2>
            <p class="panel__hint">Alt text is used by screen readers and search engines.</p>
        </header>
        <div class="panel__body">
            <div class="system-list">
                <?php foreach ($gallery as $index => $image): ?>
                    <form method="post" action="<?= e(admin_url('project-media.php')) ?>" class="system-row" style="align-items: center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="project_id" value="<?= e((string) $id) ?>">
                        <input type="hidden" name="action" value="caption">
                        <input type="hidden" name="image_id" value="<?= e((string) $image['id']) ?>">
                        <label class="visually-hidden" for="caption-<?= e((string) $image['id']) ?>">Caption for screenshot <?= e((string) ($index + 1)) ?></label>
                        <input type="text" id="caption-<?= e((string) $image['id']) ?>" name="alt_text"
                               value="<?= e((string) $image['alt_text']) ?>" maxlength="200"
                               style="flex: 1; min-width: 0; padding: .4rem .6rem; border: 1px solid var(--line); border-radius: 8px; background: rgba(0,0,0,.25); color: var(--text); font-size: .82rem">
                        <button class="btn btn--ghost btn--sm" type="submit">Save</button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php else: ?>
    <div style="margin-top: 1rem">
        <?php empty_state(
            'No screenshots yet',
            'Screenshots show the work in context. Upload two or three views of the project.',
            '',
            ''
        ); ?>
    </div>
<?php endif; ?>

<?php admin_footer(); ?>
