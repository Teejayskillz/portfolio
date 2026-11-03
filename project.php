<?php
/**
 * Project detail controller.
 *
 * Accepts ?slug=... (and the clean /project/{slug} rewrite). Signed-in admins
 * may preview a draft with ?preview=1.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

ensure_installed();

$slug = slugify(query_string('slug'), '');
$preview = isset($_GET['preview']) && admin_session_active();

$project = $slug !== '' ? project_by_slug($slug, true) : null;

if ($project === null && $preview) {
    $project = project_by_slug($slug, false);
}

if ($project === null) {
    http_response_code(404);
    $meta = [
        'title'       => 'Project not found — ' . config('owner.name'),
        'description' => 'That project could not be found. Browse the full portfolio instead.',
        'robots'      => 'noindex, follow',
        'active'      => 'work',
    ];
    require APP_TEMPLATES . '/404.php';

    return;
}

$project['gallery'] = project_gallery((int) $project['id']);
$related = related_projects($project, 3);

$canonical = absolute_url(project_url($project));
$projectTitle = trim((string) ($project['seo_title'] ?? ''));
if ($projectTitle === '') {
    $projectTitle = $project['title'] . ' — ' . ($project['category_name'] ?: 'Project') . ' by ' . config('owner.name');
}

$projectDescription = trim((string) ($project['seo_description'] ?? ''));
if ($projectDescription === '') {
    $projectDescription = trim((string) $project['short_description']) !== ''
        ? (string) $project['short_description']
        : truncate_text((string) $project['description'], 155);
}
if ($projectDescription === '') {
    $projectDescription = 'A project by ' . config('owner.name') . ', software and web developer in Lagos, Nigeria.';
}

$ogImage = trim((string) $project['cover_image']) !== ''
    ? absolute_url(upload_url((string) $project['cover_image']))
    : site_url('assets/img/og-default.png');

$meta = [
    'title'           => $projectTitle,
    'description'     => $projectDescription,
    'canonical'       => $canonical,
    'robots'          => $preview ? 'noindex, nofollow' : 'index, follow',
    'og_type'         => 'article',
    'og_image'        => $ogImage,
    'active'          => 'work',
    'body_class'      => 'project-page',
    'preload_image'   => trim((string) $project['cover_image']) !== '' ? absolute_url(upload_url((string) $project['cover_image'])) : '',
    'structured_data' => [
        project_schema($project, $canonical),
        breadcrumb_schema([
            ['name' => 'Home', 'url' => site_url('')],
            ['name' => 'Work', 'url' => site_url('projects')],
            ['name' => (string) $project['title'], 'url' => $canonical],
        ]),
    ],
];

require APP_TEMPLATES . '/project.php';
