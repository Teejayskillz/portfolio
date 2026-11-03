<?php
/**
 * Dynamic XML sitemap.
 *
 * Served at /sitemap.xml through .htaccess, and reachable directly at
 * /sitemap.php when mod_rewrite is unavailable.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');

if (!Database::isInstalled()) {
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>' . "\n";
    exit;
}

$projects = fetch_projects([
    'published_only' => true,
    'order'          => 'recent',
    'limit'          => 100,
]);

$urls = [];

$urls[] = [
    'loc'        => absolute_url('/'),
    'lastmod'    => gmdate('Y-m-d'),
    'changefreq' => 'weekly',
    'priority'   => '1.0',
];

$urls[] = [
    'loc'        => absolute_url(projects_url()),
    'lastmod'    => gmdate('Y-m-d'),
    'changefreq' => 'weekly',
    'priority'   => '0.9',
];

foreach ($projects as $project) {
    $lastmod = trim((string) ($project['updated_at'] ?? ''));
    $timestamp = $lastmod !== '' ? strtotime($lastmod . ' UTC') : false;

    $urls[] = [
        'loc'        => absolute_url(project_url($project)),
        'lastmod'    => $timestamp !== false ? gmdate('Y-m-d', $timestamp) : gmdate('Y-m-d'),
        'changefreq' => 'monthly',
        'priority'   => !empty($project['is_featured']) ? '0.8' : '0.7',
    ];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
    <url>
        <loc><?= e($url['loc']) ?></loc>
        <lastmod><?= e($url['lastmod']) ?></lastmod>
        <changefreq><?= e($url['changefreq']) ?></changefreq>
        <priority><?= e($url['priority']) ?></priority>
    </url>
<?php endforeach; ?>
</urlset>
