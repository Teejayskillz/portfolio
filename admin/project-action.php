<?php
/**
 * Publish / unpublish / feature / unfeature a project.
 * POST only, CSRF protected.
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

require_admin();

if (!is_post()) {
    redirect(admin_url('projects.php'));
}

require_csrf();

$id = to_int($_POST['id'] ?? 0);
$action = post_string('action');
$project = $id > 0 ? project_by_id($id) : null;

if ($project === null) {
    flash('error', 'That project could not be found.');
    redirect(admin_url('projects.php'));
}

$title = (string) $project['title'];

try {
    switch ($action) {
        case 'publish':
            project_set_status($id, 'published');
            flash('success', '“' . $title . '” is now published.');
            break;

        case 'unpublish':
            project_set_status($id, 'draft');
            flash('success', '“' . $title . '” was moved back to drafts.');
            break;

        case 'feature':
            project_set_featured($id, true);
            flash('success', '“' . $title . '” will be prioritised on the home page.');
            break;

        case 'unfeature':
            project_set_featured($id, false);
            flash('success', '“' . $title . '” is no longer featured.');
            break;

        default:
            flash('error', 'Unknown action.');
            break;
    }
} catch (DomainException $exception) {
    flash('error', $exception->getMessage());
}

redirect_back(admin_url('projects.php'));
