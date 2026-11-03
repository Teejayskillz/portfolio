<?php
/**
 * Initial data: reference taxonomies, the first admin account and a small set
 * of real, publicly available starter projects taken from the owner's GitHub.
 *
 * The seeded project copy is intentionally short and factual. It is meant to be
 * reviewed and enriched from the admin dashboard — never invented case studies.
 */

declare(strict_types=1);

/**
 * Categories shown in the public filter and the admin select.
 *
 * @return list<array{name:string, slug:string, sort:int}>
 */
function starter_categories(): array
{
    return [
        ['name' => 'Website', 'slug' => 'website', 'sort' => 10],
        ['name' => 'Web Application', 'slug' => 'web-application', 'sort' => 20],
        ['name' => 'E-commerce', 'slug' => 'e-commerce', 'sort' => 30],
        ['name' => 'Backend & API', 'slug' => 'backend-api', 'sort' => 40],
        ['name' => 'Business Automation', 'slug' => 'business-automation', 'sort' => 50],
        ['name' => 'Mobile App', 'slug' => 'mobile-app', 'sort' => 60],
    ];
}

/**
 * Technologies, grouped for display. No proficiency scores — just what is used.
 *
 * @return list<array{name:string, group:string, sort:int}>
 */
function starter_technologies(): array
{
    $groups = [
        'Backend & Frameworks' => ['Python', 'Django', 'PHP', 'Laravel'],
        'Frontend'             => ['JavaScript', 'HTMX', 'HTML', 'CSS', 'Bootstrap'],
        'Databases'            => ['MySQL', 'PostgreSQL', 'SQLite'],
        'CMS & Platforms'      => ['WordPress'],
    ];

    $technologies = [];
    $sort = 10;
    foreach ($groups as $group => $names) {
        foreach ($names as $name) {
            $technologies[] = ['name' => $name, 'group' => $group, 'sort' => $sort];
            $sort += 10;
        }
    }

    return $technologies;
}

/** Insert the starter categories/technologies when they are missing. */
function seed_reference_data(PDO $pdo): void
{
    $category = $pdo->prepare('INSERT OR IGNORE INTO project_categories (name, slug, sort_order) VALUES (:name, :slug, :sort)');
    foreach (starter_categories() as $row) {
        $category->execute([':name' => $row['name'], ':slug' => $row['slug'], ':sort' => $row['sort']]);
    }

    $technology = $pdo->prepare('INSERT OR IGNORE INTO technologies (name, slug, group_name, sort_order) VALUES (:name, :slug, :group, :sort)');
    foreach (starter_technologies() as $row) {
        $technology->execute([
            ':name'  => $row['name'],
            ':slug'  => slugify($row['name']),
            ':group' => $row['group'],
            ':sort'  => $row['sort'],
        ]);
    }
}

/** Create an admin account. The password is hashed, never stored plain. */
function create_admin(
    PDO $pdo,
    string $username,
    string $plainPassword,
    bool $mustChangePassword = true,
    string $displayName = 'Apatira Tijani Adewale'
): int {
    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') {
        throw new RuntimeException('Password hashing is unavailable on this server.');
    }

    $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash, display_name, must_change_password, created_at, updated_at)
        VALUES (:username, :hash, :display_name, :must_change, :created, :updated)');
    $stmt->execute([
        ':username'      => $username,
        ':hash'          => $hash,
        ':display_name'  => $displayName,
        ':must_change'   => $mustChangePassword ? 1 : 0,
        ':created'       => now_utc(),
        ':updated'       => now_utc(),
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Starter projects, drawn from the owner's public GitHub repositories.
 * Descriptions stay deliberately factual; problem/solution copy is left to the
 * owner so the site never claims work that was not done.
 *
 * @return list<array<string, mixed>>
 */
function starter_projects(): array
{
    return [
        [
            'title'             => 'Portfolio Website',
            'slug'              => 'portfolio-website',
            'category'          => 'website',
            'short_description' => 'A personal developer portfolio with a PHP + SQLite project manager and a fast, accessible front end.',
            'description'       => "This site. A custom PHP application with an SQLite content store and a private admin area.\n\nProjects, screenshots, categories and technologies are managed from the dashboard. The public pages render from the same records, with clean URLs, structured metadata and an interface designed to stay readable on small screens.",
            'problem'           => 'A portfolio goes stale quickly when every new project needs a code change. Adding a project used to mean editing PHP and HTML by hand — slow, easy to break, and easy to postpone.',
            'solution'          => "A lightweight content system on top of SQLite: add a project, upload its screenshots and publish it from the dashboard. The public site reads the same records, so the two can never drift apart. No framework, no build step, no database server to administer.",
            'live_url'          => 'https://tijanidev.com.ng',
            'github_url'        => 'https://github.com/Teejayskillz/portfolio',
            'completion_date'   => '2026-11-03',
            'is_featured'       => 1,
            'status'            => 'published',
            'technologies'      => ['PHP', 'JavaScript', 'HTML', 'CSS', 'SQLite'],
            'seo_title'         => 'Portfolio Website — PHP + SQLite project platform',
            'seo_description'   => 'A custom PHP portfolio with an SQLite-backed project manager, clean URLs and a responsive, accessible front end.',
        ],
        [
            'title'             => 'Gift Card Storefront',
            'slug'              => 'gift-card-storefront',
            'category'          => 'e-commerce',
            'short_description' => 'An online storefront for selling digital gift cards, built around a clear product catalogue and a simple buying flow.',
            'description'       => "A web storefront for selling gift cards online. The build focuses on clean product presentation, a straightforward path from browsing to purchase, and layouts that stay usable on phones.\n\nThe source is public on GitHub.",
            'problem'           => '',
            'solution'          => '',
            'live_url'          => null,
            'github_url'        => 'https://github.com/Teejayskillz/giftcard',
            'completion_date'   => null,
            'is_featured'       => 1,
            'status'            => 'published',
            'technologies'      => ['JavaScript', 'HTML', 'CSS'],
            'seo_title'         => null,
            'seo_description'   => null,
        ],
        [
            'title'             => 'Django Blog Platform',
            'slug'              => 'django-blog-platform',
            'category'          => 'web-application',
            'short_description' => 'A blog application built on Django, covering article publishing, content management and server-rendered pages for readers.',
            'description'       => "A blog implementation built with Django: models and views for posts, a publishing workflow, and templates that render articles for readers.\n\nIt was built as a hands-on Django project and is available on GitHub.",
            'problem'           => '',
            'solution'          => '',
            'live_url'          => null,
            'github_url'        => 'https://github.com/Teejayskillz/djangoblog2',
            'completion_date'   => null,
            'is_featured'       => 1,
            'status'            => 'published',
            'technologies'      => ['Python', 'Django', 'HTML', 'CSS', 'JavaScript'],
            'seo_title'         => null,
            'seo_description'   => null,
        ],
    ];
}

/** Insert the starter projects, their images-technologies relations and activity. */
function seed_owner_projects(PDO $pdo): int
{
    $stamp = now_utc();
    $created = 0;

    $findSlug = $pdo->prepare('SELECT id FROM projects WHERE slug = :slug');
    $insert = $pdo->prepare('INSERT INTO projects
        (title, slug, short_description, description, problem, solution, category_id, cover_image,
         live_url, github_url, completion_date, is_featured, status, seo_title, seo_description,
         created_at, updated_at, published_at)
        VALUES
        (:title, :slug, :short, :description, :problem, :solution, :category_id, NULL,
         :live_url, :github_url, :completion_date, :is_featured, :status, :seo_title, :seo_description,
         :created_at, :updated_at, :published_at)');

    $findCategory = $pdo->prepare('SELECT id FROM project_categories WHERE slug = :slug');
    $findTech = $pdo->prepare('SELECT id FROM technologies WHERE name = :name');
    $attachTech = $pdo->prepare('INSERT OR IGNORE INTO project_technologies (project_id, technology_id) VALUES (:project_id, :technology_id)');

    foreach (starter_projects() as $project) {
        $findSlug->execute([':slug' => $project['slug']]);
        if ($findSlug->fetchColumn() !== false) {
            continue;
        }

        $findCategory->execute([':slug' => $project['category']]);
        $categoryId = $findCategory->fetchColumn();
        $categoryId = $categoryId === false ? null : (int) $categoryId;

        $insert->execute([
            ':title'            => $project['title'],
            ':slug'             => $project['slug'],
            ':short'            => $project['short_description'],
            ':description'      => $project['description'],
            ':problem'          => $project['problem'],
            ':solution'         => $project['solution'],
            ':category_id'      => $categoryId,
            ':live_url'         => $project['live_url'],
            ':github_url'       => $project['github_url'],
            ':completion_date'  => $project['completion_date'],
            ':is_featured'      => (int) $project['is_featured'],
            ':status'           => $project['status'],
            ':seo_title'        => $project['seo_title'],
            ':seo_description'  => $project['seo_description'],
            ':created_at'       => $stamp,
            ':updated_at'       => $stamp,
            ':published_at'     => $project['status'] === 'published' ? $stamp : null,
        ]);

        $projectId = (int) $pdo->lastInsertId();
        if ($projectId <= 0) {
            continue;
        }

        foreach ($project['technologies'] as $techName) {
            $findTech->execute([':name' => $techName]);
            $techId = $findTech->fetchColumn();
            if ($techId !== false) {
                $attachTech->execute([':project_id' => $projectId, ':technology_id' => (int) $techId]);
            }
        }

        log_activity(null, 'project.created', 'project', $projectId, 'Starter project added: ' . $project['title']);
        $created++;
    }

    return $created;
}
