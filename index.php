<?php
/**
 * Home page controller.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

ensure_installed();

$featuredProjects = featured_projects((int) config('content.featured_limit', 3));
$publishedTotal = count_projects(['published_only' => true]);

// Public stack section + hero marquee read the same taxonomy the dashboard edits.
$technologyGroups = technologies_grouped();
$marqueeTechnologies = [];
foreach ($technologyGroups as $groupTechnologies) {
    foreach ($groupTechnologies as $technology) {
        $marqueeTechnologies[] = (string) $technology['name'];
    }
}
if ($marqueeTechnologies === []) {
    $marqueeTechnologies = ['Python', 'Django', 'PHP', 'Laravel', 'JavaScript', 'HTMX', 'MySQL', 'PostgreSQL'];
}

$formErrors = pull_errors();
$oldInput = old_input_array();

$meta = [
    'title'           => owner_name() . ' — Software & Web Developer in Lagos, Nigeria',
    'description'     => default_meta_description(),
    'canonical'       => site_url(''),
    'robots'          => 'index, follow',
    'active'          => 'home',
    'body_class'      => 'home',
    'structured_data' => [person_schema(), website_schema()],
];

require APP_TEMPLATES . '/home.php';
