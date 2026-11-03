<?php
/**
 * Projects index controller.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

ensure_installed();

$categories = categories_for_public();
$publishedTotal = count_projects(['published_only' => true]);

$activeCategory = trim(query_string('category'));
$activeCategoryName = 'All projects';

if ($activeCategory !== '') {
    $matched = null;
    foreach ($categories as $category) {
        if ($category['slug'] === $activeCategory) {
            $matched = $category;
            break;
        }
    }

    if ($matched === null) {
        http_response_code(404);
        $meta = [
            'title'      => 'Category not found — ' . config('owner.name'),
            'description' => 'That project category does not exist. Browse all projects instead.',
            'robots'     => 'noindex, follow',
            'active'     => 'work',
        ];
        require APP_TEMPLATES . '/404.php';

        return;
    }

    $activeCategoryName = (string) $matched['name'];
}

$perPage = max(1, (int) config('content.projects_per_page', 9));
$total = count_projects([
    'published_only' => true,
    'category'       => $activeCategory !== '' ? $activeCategory : null,
]);
$pageCount = max(1, (int) ceil($total / $perPage));
$pageNumber = max(1, to_int(query_string('page')) ?: 1);

if ($pageNumber > $pageCount && $total > 0) {
    $target = projects_url($activeCategory !== '' ? $activeCategory : null);
    redirect($pageCount > 1 ? $target . (str_contains($target, '?') ? '&' : '?') . 'page=' . $pageCount : $target);
}

$projects = fetch_projects([
    'published_only' => true,
    'category'       => $activeCategory !== '' ? $activeCategory : null,
    'order'          => 'featured',
    'limit'          => $perPage,
    'offset'         => ($pageNumber - 1) * $perPage,
]);

$title = $activeCategory !== ''
    ? $activeCategoryName . ' projects — ' . config('owner.name')
    : 'Projects — ' . config('owner.name') . ', Software & Web Developer';

$description = $activeCategory !== ''
    ? 'Published ' . strtolower($activeCategoryName) . ' projects by Apatira Tijani Adewale, a software and web developer in Lagos, Nigeria.'
    : 'Websites, web applications, e-commerce builds and backend systems by Apatira Tijani Adewale — with the problem, the solution and the stack behind each one.';

$canonical = site_url('projects');
if ($activeCategory !== '') {
    $canonical .= '?category=' . rawurlencode($activeCategory);
}
if ($pageNumber > 1) {
    $canonical .= (str_contains($canonical, '?') ? '&' : '?') . 'page=' . $pageNumber;
}

$meta = [
    'title'           => $title,
    'description'     => $description,
    'canonical'       => $canonical,
    'robots'          => 'index, follow',
    'active'          => 'work',
    'body_class'      => 'projects-page',
    'structured_data' => [
        projects_collection_schema($projects, $canonical, $activeCategoryName . ' — ' . config('owner.name'), $description),
        breadcrumb_schema([
            ['name' => 'Home', 'url' => site_url('')],
            ['name' => 'Work', 'url' => site_url('projects')],
        ]),
    ],
];

require APP_TEMPLATES . '/projects.php';
