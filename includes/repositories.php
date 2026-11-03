<?php
/**
 * Data access for projects, taxonomies, messages and the dashboard.
 *
 * All SQL lives here and uses prepared statements; templates never talk to PDO.
 */

declare(strict_types=1);

/* -------------------------------------------------------------------------
 * Activity log
 * ---------------------------------------------------------------------- */

function current_actor_id(): ?int
{
    if (PHP_SAPI === 'cli') {
        return null;
    }
    $id = (int) ($_SESSION['admin_user_id'] ?? 0);

    return $id > 0 ? $id : null;
}

function log_activity(?int $actorId, string $action, string $entityType = '', ?int $entityId = null, string $description = ''): void
{
    try {
        db_run(
            'INSERT INTO activity_log (actor_id, action, entity_type, entity_id, description, created_at)
             VALUES (:actor, :action, :entity_type, :entity_id, :description, :created)',
            [
                ':actor'       => $actorId,
                ':action'      => mb_substr($action, 0, 60),
                ':entity_type' => mb_substr($entityType, 0, 40),
                ':entity_id'   => $entityId,
                ':description' => mb_substr($description, 0, 255),
                ':created'     => now_utc(),
            ]
        );

        if (random_int(1, 10) === 1) {
            db_run('DELETE FROM activity_log WHERE id NOT IN (SELECT id FROM activity_log ORDER BY id DESC LIMIT 500)');
        }
    } catch (Throwable $exception) {
        app_log('activity', 'Could not write activity entry', ['error' => $exception->getMessage()]);
    }
}

/** @return list<array<string, mixed>> */
function recent_activity(int $limit = 8): array
{
    $limit = max(1, min(50, $limit));

    return db_all(
        'SELECT a.*, u.username FROM activity_log a
         LEFT JOIN admin_users u ON u.id = a.actor_id
         ORDER BY a.id DESC LIMIT ' . $limit
    );
}

/* -------------------------------------------------------------------------
 * Project queries
 * ---------------------------------------------------------------------- */

/**
 * Build the shared WHERE clause for project listings.
 *
 * @param array<string, mixed> $options
 * @return array{sql:string, params:array<string,mixed>}
 */
function project_filter_sql(array $options): array
{
    $sql = ' WHERE 1 = 1';
    $params = [];

    $status = $options['status'] ?? null;
    if ($status === 'published' || $status === 'draft') {
        $sql .= ' AND p.status = :status';
        $params[':status'] = $status;
    }

    if (($options['featured'] ?? false) === true) {
        $sql .= ' AND p.is_featured = 1';
    }

    if (($options['published_only'] ?? false) === true) {
        $sql .= " AND p.status = 'published' AND p.published_at IS NOT NULL";
    }

    $category = trim((string) ($options['category'] ?? ''));
    if ($category !== '') {
        $sql .= ' AND c.slug = :category';
        $params[':category'] = $category;
    }

    $categoryId = to_int($options['category_id'] ?? 0);
    if ($categoryId > 0) {
        $sql .= ' AND p.category_id = :category_id';
        $params[':category_id'] = $categoryId;
    }

    $search = trim((string) ($options['search'] ?? ''));
    if ($search !== '') {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $sql .= " AND (p.title LIKE :search ESCAPE '\\' OR p.short_description LIKE :search ESCAPE '\\' OR p.description LIKE :search ESCAPE '\\')";
        $params[':search'] = '%' . $escaped . '%';
    }

    $exclude = to_int($options['exclude'] ?? 0);
    if ($exclude > 0) {
        $sql .= ' AND p.id <> :exclude';
        $params[':exclude'] = $exclude;
    }

    return ['sql' => $sql, 'params' => $params];
}

function project_order_sql(string $order): string
{
    return match ($order) {
        'title'      => ' ORDER BY p.title COLLATE NOCASE ASC, p.id DESC',
        'featured'   => ' ORDER BY p.is_featured DESC, COALESCE(p.published_at, p.created_at) DESC, p.id DESC',
        'recent'     => ' ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.id DESC',
        'sort_order' => ' ORDER BY p.is_featured DESC, p.completion_date DESC, p.id DESC',
        default      => ' ORDER BY p.updated_at DESC, p.id DESC',
    };
}

/** Attach technologies to a list of project rows (one extra query, not N). */
function attach_project_technologies(array $projects): array
{
    if ($projects === []) {
        return [];
    }

    $ids = [];
    foreach ($projects as $project) {
        $id = to_int($project['id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    if ($ids === []) {
        return $projects;
    }

    $rows = db_all(
        'SELECT pt.project_id, t.id, t.name, t.slug, t.group_name
         FROM project_technologies pt
         JOIN technologies t ON t.id = pt.technology_id
         WHERE pt.project_id IN (' . implode(',', array_map('intval', $ids)) . ')
         ORDER BY t.sort_order ASC, t.name ASC'
    );

    $byProject = [];
    foreach ($rows as $row) {
        $byProject[(int) $row['project_id']][] = [
            'id'    => (int) $row['id'],
            'name'  => (string) $row['name'],
            'slug'  => (string) $row['slug'],
        ];
    }

    foreach ($projects as $index => $project) {
        $technologies = $byProject[(int) $project['id']] ?? [];
        $projects[$index]['technologies'] = $technologies;
        $projects[$index]['tech_names'] = implode(', ', array_column($technologies, 'name'));
    }

    return $projects;
}

/**
 * Fetch project cards.
 *
 * @param array<string, mixed> $options status, published_only, featured, category,
 *                                      category_id, search, exclude, order, limit, offset
 * @return list<array<string, mixed>>
 */
function fetch_projects(array $options = []): array
{
    $filter = project_filter_sql($options);
    $order = project_order_sql((string) ($options['order'] ?? 'recent'));
    $limit = max(1, min(100, to_int($options['limit'] ?? 12) ?: 12));
    $offset = max(0, to_int($options['offset'] ?? 0));

    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM projects p
            LEFT JOIN project_categories c ON c.id = p.category_id'
        . $filter['sql'] . $order . ' LIMIT ' . $limit . ' OFFSET ' . $offset;

    $rows = db_all($sql, $filter['params']);

    return attach_project_technologies($rows);
}

/** Count projects matching the same filters (for pagination and badges). */
function count_projects(array $options = []): int
{
    $filter = project_filter_sql($options);
    $sql = 'SELECT COUNT(*) FROM projects p LEFT JOIN project_categories c ON c.id = p.category_id' . $filter['sql'];

    return (int) db_value($sql, $filter['params']);
}

/** Fetch one project by slug. */
function project_by_slug(string $slug, bool $publishedOnly = true): ?array
{
    if ($slug === '') {
        return null;
    }

    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM projects p LEFT JOIN project_categories c ON c.id = p.category_id
            WHERE p.slug = :slug';
    if ($publishedOnly) {
        $sql .= " AND p.status = 'published'";
    }
    $sql .= ' LIMIT 1';

    $project = db_one($sql, [':slug' => $slug]);
    if ($project === null) {
        return null;
    }

    $withTech = attach_project_technologies([$project]);

    return $withTech[0];
}

/** Fetch one project by id. */
function project_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $project = db_one(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM projects p LEFT JOIN project_categories c ON c.id = p.category_id
         WHERE p.id = :id LIMIT 1',
        [':id' => $id]
    );

    if ($project === null) {
        return null;
    }

    $project['technologies'] = project_technologies($id);
    $project['tech_names'] = implode(', ', array_column($project['technologies'], 'name'));
    $project['gallery'] = project_gallery($id);

    return $project;
}

/** @return list<array<string, mixed>> */
function project_technologies(int $projectId): array
{
    return db_all(
        'SELECT t.id, t.name, t.slug, t.group_name
         FROM project_technologies pt JOIN technologies t ON t.id = pt.technology_id
         WHERE pt.project_id = :id ORDER BY t.sort_order, t.name',
        [':id' => $projectId]
    );
}

/** @return list<int> */
function project_technology_ids(int $projectId): array
{
    $ids = [];
    foreach (project_technologies($projectId) as $technology) {
        $ids[] = (int) $technology['id'];
    }

    return $ids;
}

/** @return list<array<string, mixed>> */
function project_gallery(int $projectId): array
{
    return db_all(
        'SELECT id, project_id, file_path, alt_text, sort_order
         FROM project_images WHERE project_id = :id ORDER BY sort_order ASC, id ASC',
        [':id' => $projectId]
    );
}

/** Featured/published projects for the home page. */
function featured_projects(int $limit = 3): array
{
    $projects = fetch_projects([
        'published_only' => true,
        'featured'       => true,
        'order'          => 'featured',
        'limit'          => $limit,
    ]);

    if (count($projects) >= $limit) {
        return $projects;
    }

    $fallback = fetch_projects([
        'published_only' => true,
        'order'          => 'recent',
        'limit'          => $limit,
        'exclude'        => 0,
    ]);

    $seen = array_column($projects, 'id');
    foreach ($fallback as $project) {
        if (count($projects) >= $limit) {
            break;
        }
        if (!in_array($project['id'], $seen, true)) {
            $projects[] = $project;
            $seen[] = $project['id'];
        }
    }

    return $projects;
}

/** Related projects for the detail page: same category first, then recent. */
function related_projects(array $project, int $limit = 3): array
{
    $categoryId = to_int($project['category_id'] ?? 0);
    $rows = [];

    if ($categoryId > 0) {
        $rows = fetch_projects([
            'published_only' => true,
            'category_id'    => $categoryId,
            'exclude'        => (int) $project['id'],
            'order'          => 'recent',
            'limit'          => $limit,
        ]);
    }

    if (count($rows) < $limit) {
        $seen = array_merge([(int) $project['id']], array_map('intval', array_column($rows, 'id')));
        $fill = fetch_projects(['published_only' => true, 'order' => 'recent', 'limit' => $limit + count($seen)]);
        foreach ($fill as $row) {
            if (count($rows) >= $limit) {
                break;
            }
            if (!in_array((int) $row['id'], $seen, true)) {
                $rows[] = $row;
                $seen[] = (int) $row['id'];
            }
        }
    }

    return $rows;
}

function next_project_url(array $project): ?string
{
    return project_url($project);
}

/* -------------------------------------------------------------------------
 * Project writes
 * ---------------------------------------------------------------------- */

/**
 * Create or update a project and its technology relations.
 *
 * @param array<string, mixed> $data validated data from validate_project()
 */
function project_save(array $data, ?int $id = null): int
{
    if ($id !== null && $id <= 0) {
        $id = null;
    }

    $pdo = db();
    $now = now_utc();

    $pdo->beginTransaction();
    try {
        $categoryId = to_int($data['category_id'] ?? 0);
        if ($categoryId > 0) {
            $exists = db_value('SELECT id FROM project_categories WHERE id = :id', [':id' => $categoryId]);
            if ($exists === null) {
                $categoryId = 0;
            }
        }

        $slug = unique_slug($pdo, 'projects', 'slug', (string) ($data['slug'] ?? 'project'), $id);
        $status = ($data['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

        if ($id === null) {
            $stmt = $pdo->prepare(
                'INSERT INTO projects
                 (title, slug, short_description, description, problem, solution, category_id, live_url, github_url,
                  completion_date, is_featured, status, seo_title, seo_description, created_at, updated_at, published_at)
                 VALUES
                 (:title, :slug, :short, :description, :problem, :solution, :category_id, :live_url, :github_url,
                  :completion, :featured, :status, :seo_title, :seo_description, :created, :updated, :published)'
            );
            $stmt->execute([
                ':title'          => $data['title'],
                ':slug'           => $slug,
                ':short'          => $data['short_description'],
                ':description'    => $data['description'],
                ':problem'        => $data['problem'],
                ':solution'       => $data['solution'],
                ':category_id'    => $categoryId > 0 ? $categoryId : null,
                ':live_url'       => $data['live_url'],
                ':github_url'     => $data['github_url'],
                ':completion'     => $data['completion_date'],
                ':featured'       => (int) $data['is_featured'],
                ':status'         => $status,
                ':seo_title'      => $data['seo_title'],
                ':seo_description'=> $data['seo_description'],
                ':created'        => $now,
                ':updated'        => $now,
                ':published'      => $status === 'published' ? $now : null,
            ]);
            $id = (int) $pdo->lastInsertId();
            $action = 'project.created';
            $description = 'Created project "' . $data['title'] . '"';
        } else {
            $existing = db_one('SELECT id, published_at, status FROM projects WHERE id = :id', [':id' => $id]);
            if ($existing === null) {
                throw new RuntimeException('Project not found.');
            }

            $publishedAt = null;
            if ($status === 'published') {
                $publishedAt = $existing['published_at'] !== null && $existing['published_at'] !== ''
                    ? (string) $existing['published_at']
                    : $now;
            }

            $stmt = $pdo->prepare(
                'UPDATE projects SET
                    title = :title, slug = :slug, short_description = :short, description = :description,
                    problem = :problem, solution = :solution, category_id = :category_id, live_url = :live_url,
                    github_url = :github_url, completion_date = :completion, is_featured = :featured,
                    status = :status, seo_title = :seo_title, seo_description = :seo_description,
                    updated_at = :updated, published_at = :published
                 WHERE id = :id'
            );
            $stmt->execute([
                ':title'          => $data['title'],
                ':slug'           => $slug,
                ':short'          => $data['short_description'],
                ':description'    => $data['description'],
                ':problem'        => $data['problem'],
                ':solution'       => $data['solution'],
                ':category_id'    => $categoryId > 0 ? $categoryId : null,
                ':live_url'       => $data['live_url'],
                ':github_url'     => $data['github_url'],
                ':completion'     => $data['completion_date'],
                ':featured'       => (int) $data['is_featured'],
                ':status'         => $status,
                ':seo_title'      => $data['seo_title'],
                ':seo_description'=> $data['seo_description'],
                ':updated'        => $now,
                ':published'      => $publishedAt,
                ':id'             => $id,
            ]);
            $action = 'project.updated';
            $description = 'Updated project "' . $data['title'] . '"';
        }

        // Sync technologies (delete + insert keeps ordering and avoids duplicates).
        $pdo->prepare('DELETE FROM project_technologies WHERE project_id = :id')->execute([':id' => $id]);
        $attach = $pdo->prepare('INSERT OR IGNORE INTO project_technologies (project_id, technology_id) VALUES (:project_id, :technology_id)');
        $validTech = $pdo->prepare('SELECT id FROM technologies WHERE id = :id');
        foreach ((array) ($data['technologies'] ?? []) as $technologyId) {
            $technologyId = to_int($technologyId);
            if ($technologyId <= 0) {
                continue;
            }
            $validTech->execute([':id' => $technologyId]);
            if ($validTech->fetchColumn() !== false) {
                $attach->execute([':project_id' => $id, ':technology_id' => $technologyId]);
            }
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    log_activity(current_actor_id(), $action, 'project', $id, $description);

    return $id;
}

function project_set_status(int $id, string $status): bool
{
    if (!in_array($status, ['published', 'draft'], true)) {
        throw new InvalidArgumentException('Unknown project status.');
    }

    $project = db_one(
        'SELECT id, title, published_at, short_description, description, category_id
         FROM projects WHERE id = :id',
        [':id' => $id]
    );
    if ($project === null) {
        return false;
    }

    // Publishing from the list must meet the same content bar as the form.
    if ($status === 'published') {
        if (
            mb_strlen(trim((string) $project['short_description'])) < 20
            || trim((string) $project['description']) === ''
            || (int) ($project['category_id'] ?? 0) === 0
        ) {
            throw new DomainException(
                'This draft is incomplete. Add a short description, a full description and a category '
                . 'before publishing it.'
            );
        }
    }

    $publishedAt = $status === 'published'
        ? ((string) ($project['published_at'] ?? '') !== '' ? (string) $project['published_at'] : now_utc())
        : null;

    db_run('UPDATE projects SET status = :status, published_at = :published, updated_at = :updated WHERE id = :id', [
        ':status'    => $status,
        ':published' => $publishedAt,
        ':updated'   => now_utc(),
        ':id'        => $id,
    ]);

    log_activity(
        current_actor_id(),
        $status === 'published' ? 'project.published' : 'project.unpublished',
        'project',
        $id,
        ($status === 'published' ? 'Published "' : 'Unpublished "') . $project['title'] . '"'
    );

    return true;
}

function project_set_featured(int $id, bool $featured): bool
{
    $project = db_one('SELECT id, title FROM projects WHERE id = :id', [':id' => $id]);
    if ($project === null) {
        return false;
    }

    db_run('UPDATE projects SET is_featured = :featured, updated_at = :updated WHERE id = :id', [
        ':featured' => $featured ? 1 : 0,
        ':updated'  => now_utc(),
        ':id'       => $id,
    ]);

    log_activity(
        current_actor_id(),
        $featured ? 'project.featured' : 'project.unfeatured',
        'project',
        $id,
        ($featured ? 'Featured "' : 'Removed from featured: "') . $project['title'] . '"'
    );

    return true;
}

function project_delete(int $id): bool
{
    $project = db_one('SELECT id, title, cover_image FROM projects WHERE id = :id', [':id' => $id]);
    if ($project === null) {
        return false;
    }

    $images = project_gallery($id);

    db_run('DELETE FROM projects WHERE id = :id', [':id' => $id]);

    remove_image_file((string) ($project['cover_image'] ?? ''));
    foreach ($images as $image) {
        remove_image_file((string) $image['file_path']);
    }

    log_activity(current_actor_id(), 'project.deleted', 'project', $id, 'Deleted project "' . $project['title'] . '"');

    return true;
}

function project_set_cover(int $id, ?string $coverPath): void
{
    db_run('UPDATE projects SET cover_image = :cover, updated_at = :updated WHERE id = :id', [
        ':cover'   => $coverPath,
        ':updated' => now_utc(),
        ':id'      => $id,
    ]);
}

/* -------------------------------------------------------------------------
 * Gallery images
 * ---------------------------------------------------------------------- */

function gallery_add(int $projectId, string $path, string $alt = ''): int
{
    $next = (int) db_value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM project_images WHERE project_id = :id', [':id' => $projectId]);

    db_run(
        'INSERT INTO project_images (project_id, file_path, alt_text, sort_order, created_at)
         VALUES (:project_id, :path, :alt, :sort, :created)',
        [
            ':project_id' => $projectId,
            ':path'       => $path,
            ':alt'        => mb_substr(clean_single_line($alt), 0, 200),
            ':sort'       => $next,
            ':created'    => now_utc(),
        ]
    );

    log_activity(current_actor_id(), 'image.uploaded', 'project', $projectId, 'Added a project screenshot');

    return (int) db()->lastInsertId();
}

function gallery_image(int $imageId): ?array
{
    return db_one('SELECT * FROM project_images WHERE id = :id', [':id' => $imageId]);
}

function gallery_delete(int $imageId): bool
{
    $image = gallery_image($imageId);
    if ($image === null) {
        return false;
    }

    db_run('DELETE FROM project_images WHERE id = :id', [':id' => $imageId]);
    remove_image_file((string) $image['file_path']);
    log_activity(current_actor_id(), 'image.deleted', 'project', (int) $image['project_id'], 'Removed a project screenshot');

    return true;
}

/** Update the caption/alt text for a screenshot. */
function gallery_set_alt(int $imageId, string $alt): bool
{
    $image = gallery_image($imageId);
    if ($image === null) {
        return false;
    }

    db_run('UPDATE project_images SET alt_text = :alt WHERE id = :id', [
        ':alt' => mb_substr(clean_single_line($alt), 0, 200),
        ':id'  => $imageId,
    ]);

    return true;
}

function gallery_count(int $projectId): int
{
    return (int) db_value('SELECT COUNT(*) FROM project_images WHERE project_id = :id', [':id' => $projectId]);
}

/* -------------------------------------------------------------------------
 * Categories
 * ---------------------------------------------------------------------- */

/** @return list<array<string, mixed>> */
function categories_all(bool $withCounts = false): array
{
    if (!$withCounts) {
        return db_all('SELECT * FROM project_categories ORDER BY sort_order ASC, name ASC');
    }

    return db_all(
        'SELECT c.*, COUNT(p.id) AS project_count
         FROM project_categories c
         LEFT JOIN projects p ON p.category_id = c.id
         GROUP BY c.id ORDER BY c.sort_order ASC, c.name ASC'
    );
}

function category_by_id(int $id): ?array
{
    return db_one('SELECT * FROM project_categories WHERE id = :id', [':id' => $id]);
}

/** Published categories with at least one published project, for public filters. */
function categories_for_public(): array
{
    return db_all(
        "SELECT c.*, COUNT(p.id) AS project_count
         FROM project_categories c
         INNER JOIN projects p ON p.category_id = c.id AND p.status = 'published'
         GROUP BY c.id ORDER BY c.sort_order ASC, c.name ASC"
    );
}

function category_create(string $name, string $slug, int $sortOrder = 0): int
{
    $pdo = db();
    $slug = unique_slug($pdo, 'project_categories', 'slug', $slug !== '' ? $slug : slugify($name), null);

    db_run(
        'INSERT INTO project_categories (name, slug, sort_order, created_at) VALUES (:name, :slug, :sort, :created)',
        [':name' => $name, ':slug' => $slug, ':sort' => $sortOrder, ':created' => now_utc()]
    );

    $id = (int) $pdo->lastInsertId();
    log_activity(current_actor_id(), 'category.created', 'category', $id, 'Added category "' . $name . '"');

    return $id;
}

function category_update(int $id, string $name, string $slug, int $sortOrder): bool
{
    if (category_by_id($id) === null) {
        return false;
    }

    $slug = unique_slug(db(), 'project_categories', 'slug', $slug !== '' ? $slug : slugify($name), $id);
    db_run('UPDATE project_categories SET name = :name, slug = :slug, sort_order = :sort WHERE id = :id', [
        ':name' => $name,
        ':slug' => $slug,
        ':sort' => $sortOrder,
        ':id'   => $id,
    ]);
    log_activity(current_actor_id(), 'category.updated', 'category', $id, 'Updated category "' . $name . '"');

    return true;
}

/** Deleting a category in use is refused so no project is orphaned silently. */
function category_delete(int $id): bool
{
    $category = category_by_id($id);
    if ($category === null) {
        return false;
    }

    $used = (int) db_value('SELECT COUNT(*) FROM projects WHERE category_id = :id', [':id' => $id]);
    if ($used > 0) {
        throw new DomainException(
            'This category is used by ' . $used . ' project' . ($used === 1 ? '' : 's')
            . '. Move those projects to another category before deleting it.'
        );
    }

    db_run('DELETE FROM project_categories WHERE id = :id', [':id' => $id]);
    log_activity(current_actor_id(), 'category.deleted', 'category', $id, 'Deleted category "' . $category['name'] . '"');

    return true;
}

/* -------------------------------------------------------------------------
 * Technologies
 * ---------------------------------------------------------------------- */

/** @return list<array<string, mixed>> */
function technologies_all(): array
{
    return db_all('SELECT * FROM technologies ORDER BY group_name ASC, sort_order ASC, name ASC');
}

/** @return array<string, list<array<string, mixed>>> group => rows */
function technologies_grouped(): array
{
    $grouped = [];
    foreach (technologies_all() as $technology) {
        $grouped[(string) $technology['group_name']][] = $technology;
    }

    return $grouped;
}

function technology_by_id(int $id): ?array
{
    return db_one('SELECT * FROM technologies WHERE id = :id', [':id' => $id]);
}

function technology_create(string $name, string $group, int $sortOrder = 0): int
{
    $pdo = db();
    $slug = unique_slug($pdo, 'technologies', 'slug', slugify($name), null);

    db_run(
        'INSERT INTO technologies (name, slug, group_name, sort_order, created_at)
         VALUES (:name, :slug, :group, :sort, :created)',
        [':name' => $name, ':slug' => $slug, ':group' => $group, ':sort' => $sortOrder, ':created' => now_utc()]
    );

    $id = (int) $pdo->lastInsertId();
    log_activity(current_actor_id(), 'technology.created', 'technology', $id, 'Added technology "' . $name . '"');

    return $id;
}

function technology_update(int $id, string $name, string $group, int $sortOrder): bool
{
    if (technology_by_id($id) === null) {
        return false;
    }

    $slug = unique_slug(db(), 'technologies', 'slug', slugify($name), $id);
    db_run('UPDATE technologies SET name = :name, slug = :slug, group_name = :group, sort_order = :sort WHERE id = :id', [
        ':name'  => $name,
        ':slug'  => $slug,
        ':group' => $group,
        ':sort'  => $sortOrder,
        ':id'    => $id,
    ]);
    log_activity(current_actor_id(), 'technology.updated', 'technology', $id, 'Updated technology "' . $name . '"');

    return true;
}

function technology_delete(int $id): bool
{
    $technology = technology_by_id($id);
    if ($technology === null) {
        return false;
    }

    db_run('DELETE FROM technologies WHERE id = :id', [':id' => $id]);
    log_activity(current_actor_id(), 'technology.deleted', 'technology', $id, 'Deleted technology "' . $technology['name'] . '"');

    return true;
}

/* -------------------------------------------------------------------------
 * Dashboard + messages
 * ---------------------------------------------------------------------- */

/** @return array<string, int> */
function dashboard_stats(): array
{
    return [
        'total'      => (int) db_value('SELECT COUNT(*) FROM projects'),
        'published'  => (int) db_value("SELECT COUNT(*) FROM projects WHERE status = 'published'"),
        'draft'      => (int) db_value("SELECT COUNT(*) FROM projects WHERE status = 'draft'"),
        'featured'   => (int) db_value('SELECT COUNT(*) FROM projects WHERE is_featured = 1'),
        'categories' => (int) db_value('SELECT COUNT(*) FROM project_categories'),
        'technologies' => (int) db_value('SELECT COUNT(*) FROM technologies'),
        'messages'   => (int) db_value('SELECT COUNT(*) FROM contact_messages'),
        'messages_failed' => (int) db_value("SELECT COUNT(*) FROM contact_messages WHERE mail_status = 'failed'"),
        'images'     => (int) db_value('SELECT COUNT(*) FROM project_images'),
    ];
}

function admin_recent_projects(int $limit = 5): array
{
    $limit = max(1, min(20, $limit));

    return db_all(
        "SELECT p.id, p.title, p.slug, p.status, p.is_featured, p.updated_at, c.name AS category_name
         FROM projects p LEFT JOIN project_categories c ON c.id = p.category_id
         ORDER BY p.updated_at DESC, p.id DESC LIMIT " . $limit
    );
}

/** @return array{rows:list<array<string,mixed>>, total:int} */
function messages_page(int $page = 1, int $perPage = 20, string $search = ''): array
{
    $hasWhatsapp = ensure_contact_whatsapp_column();
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = '';
    $params = [];
    if (trim($search) !== '') {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($search));
        $conditions = [
            "name LIKE :search ESCAPE '\\'",
            "email LIKE :search ESCAPE '\\'",
            "subject LIKE :search ESCAPE '\\'",
        ];
        if ($hasWhatsapp) {
            $conditions[] = "whatsapp LIKE :search ESCAPE '\\'";
        }
        $where = ' WHERE (' . implode(' OR ', $conditions) . ')';
        $params[':search'] = '%' . $escaped . '%';
    }

    $total = (int) db_value('SELECT COUNT(*) FROM contact_messages' . $where, $params);
    $rows = db_all(
        'SELECT * FROM contact_messages' . $where . ' ORDER BY created_at DESC, id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
        $params
    );

    return ['rows' => $rows, 'total' => $total];
}

function message_by_id(int $id): ?array
{
    ensure_contact_whatsapp_column();

    return db_one('SELECT * FROM contact_messages WHERE id = :id', [':id' => $id]);
}

function message_delete(int $id): bool
{
    if (message_by_id($id) === null) {
        return false;
    }

    db_run('DELETE FROM contact_messages WHERE id = :id', [':id' => $id]);
    log_activity(current_actor_id(), 'message.deleted', 'message', $id, 'Deleted a contact message');

    return true;
}

function message_set_status(int $id, string $status): void
{
    if (!in_array($status, ['sent', 'failed', 'spam', 'pending'], true)) {
        return;
    }

    db_run('UPDATE contact_messages SET mail_status = :status WHERE id = :id', [':id' => $id, ':status' => $status]);
}

/** Admin list of projects with search/status filters and pagination. */
/** @return array{rows:list<array<string,mixed>>, total:int} */
function admin_projects_page(string $search = '', string $statusFilter = 'all', int $page = 1, int $perPage = 12): array
{
    $conditions = [];
    $params = [];

    if (in_array($statusFilter, ['published', 'draft'], true)) {
        $conditions[] = 'p.status = :status';
        $params[':status'] = $statusFilter;
    }

    if (trim($search) !== '') {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($search));
        $conditions[] = "(p.title LIKE :search ESCAPE '\\' OR p.short_description LIKE :search ESCAPE '\\')";
        $params[':search'] = '%' . $escaped . '%';
    }

    $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;

    $total = (int) db_value('SELECT COUNT(*) FROM projects p' . $where, $params);
    $rows = db_all(
        'SELECT p.*, c.name AS category_name
         FROM projects p LEFT JOIN project_categories c ON c.id = p.category_id'
        . $where . ' ORDER BY p.updated_at DESC, p.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
        $params
    );

    return ['rows' => $rows, 'total' => $total];
}
