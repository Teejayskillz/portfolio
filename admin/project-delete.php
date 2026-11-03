<?php
/**
 * Delete a project and its image files.
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

require_admin();

if (!is_post()) {
    redirect(admin_url('projects.php'));
}

require_csrf();

$id = to_int($_POST['id'] ?? 0);
$project = $id > 0 ? project_by_id($id) : null;

if ($project === null) {
    flash('error', 'That project could not be found.');
    redirect(admin_url('projects.php'));
}

$title = (string) $project['title'];

if (project_delete($id)) {
    flash('success', '“' . $title . '” and its images were deleted.');
} else {
    flash('error', 'The project could not be deleted.');
}

redirect(admin_url('projects.php'));
