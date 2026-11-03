<?php
/**
 * Admin shell: sidebar, top bar, footer.
 */

declare(strict_types=1);

if (!defined('APP_BOOTSTRAP')) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/includes/icons.php';

/** @return array{projects:int, messages:int} */
function admin_nav_counts(): array
{
    static $counts = null;
    if (is_array($counts)) {
        return $counts;
    }

    try {
        $counts = [
            'projects' => (int) db_value('SELECT COUNT(*) FROM projects'),
            'messages' => (int) db_value('SELECT COUNT(*) FROM contact_messages'),
        ];
    } catch (Throwable) {
        $counts = ['projects' => 0, 'messages' => 0];
    }

    return $counts;
}

/**
 * @param array{title:string, subtitle?:string, active?:string, actions?:string} $page
 */
function admin_header(array $page): void
{
    $active = (string) ($page['active'] ?? '');
    $title = (string) ($page['title'] ?? 'Dashboard');
    $subtitle = (string) ($page['subtitle'] ?? '');
    $actions = (string) ($page['actions'] ?? '');
    $owner = config('owner');
    $counts = admin_nav_counts();
    $user = admin_user();

    $nav = [
        'overview' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => admin_url('index.php'), 'icon' => 'grid'],
        ],
        'content' => [
            ['key' => 'projects', 'label' => 'Projects', 'href' => admin_url('projects.php'), 'icon' => 'folder', 'badge' => $counts['projects']],
            ['key' => 'categories', 'label' => 'Categories', 'href' => admin_url('categories.php'), 'icon' => 'tag'],
            ['key' => 'technologies', 'label' => 'Technologies', 'href' => admin_url('technologies.php'), 'icon' => 'layers'],
        ],
        'inbox' => [
            ['key' => 'messages', 'label' => 'Messages', 'href' => admin_url('messages.php'), 'icon' => 'message', 'badge' => $counts['messages']],
        ],
        'account' => [
            ['key' => 'system', 'label' => 'System &amp; backup', 'href' => admin_url('system.php'), 'icon' => 'shield'],
            ['key' => 'account', 'label' => 'Account security', 'href' => admin_url('change-password.php'), 'icon' => 'lock'],
        ],
    ];
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="description" content="Private portfolio dashboard for <?= e($owner['name']) ?>.">
    <link rel="canonical" href="<?= e(absolute_url(admin_url())) ?>">
    <script>document.documentElement.classList.add('has-js');</script>
    <title><?= e($title) ?> — <?= e($owner['name']) ?> dashboard</title>
    <meta name="theme-color" content="#050807">
    <meta name="color-scheme" content="dark">
    <link rel="icon" type="image/png" href="<?= e(asset_url('img/brand-favicon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap">
    <link rel="stylesheet" href="<?= e(asset_url('css/premium.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/admin.css')) ?>">
    <noscript>
        <style>
            .admin-shell { grid-template-columns: 1fr; }
            .admin-sidebar { position: static; width: auto; height: auto; transform: none; box-shadow: none;
                border-right: 0; border-bottom: 1px solid rgba(255,255,255,.08); }
            .admin-menu-btn { display: none; }
        </style>
    </noscript>
</head>
<body class="site admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Dashboard navigation">
        <div class="admin-sidebar__inner">
            <a class="admin-brand" href="<?= e(admin_url('index.php')) ?>" aria-label="Dashboard home">
                <span class="brand__mark" aria-hidden="true">TA</span>
                <span class="admin-brand__text">
                    <strong>Dashboard</strong>
                    <small>Tijani.dev</small>
                </span>
            </a>

            <nav class="admin-nav" aria-label="Admin sections">
                <div class="admin-nav__section">
                    <p class="admin-nav__label">Overview</p>
                    <?php foreach ($nav['overview'] as $item): ?>
                        <a class="admin-nav__link<?= $active === $item['key'] ? ' is-active' : '' ?>"
                           href="<?= e($item['href']) ?>"<?= $active === $item['key'] ? ' aria-current="page"' : '' ?>>
                            <?= icon($item['icon'], 'icon', 17) ?>
                            <span><?= $item['label'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="admin-nav__section">
                    <p class="admin-nav__label">Content</p>
                    <?php foreach ($nav['content'] as $item): ?>
                        <a class="admin-nav__link<?= $active === $item['key'] ? ' is-active' : '' ?>"
                           href="<?= e($item['href']) ?>"<?= $active === $item['key'] ? ' aria-current="page"' : '' ?>>
                            <?= icon($item['icon'], 'icon', 17) ?>
                            <span><?= $item['label'] ?></span>
                            <?php if (!empty($item['badge'])): ?>
                                <span class="admin-nav__badge"><?= e((string) $item['badge']) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="admin-nav__section">
                    <p class="admin-nav__label">Inbox</p>
                    <?php foreach ($nav['inbox'] as $item): ?>
                        <a class="admin-nav__link<?= $active === $item['key'] ? ' is-active' : '' ?>"
                           href="<?= e($item['href']) ?>"<?= $active === $item['key'] ? ' aria-current="page"' : '' ?>>
                            <?= icon($item['icon'], 'icon', 17) ?>
                            <span><?= $item['label'] ?></span>
                            <?php if (!empty($item['badge'])): ?>
                                <span class="admin-nav__badge"><?= e((string) $item['badge']) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="admin-nav__section">
                    <p class="admin-nav__label">Account</p>
                    <?php foreach ($nav['account'] as $item): ?>
                        <a class="admin-nav__link<?= $active === $item['key'] ? ' is-active' : '' ?>"
                           href="<?= e($item['href']) ?>"<?= $active === $item['key'] ? ' aria-current="page"' : '' ?>>
                            <?= icon($item['icon'], 'icon', 17) ?>
                            <span><?= $item['label'] ?></span>
                        </a>
                    <?php endforeach; ?>
                    <a class="admin-nav__link" href="<?= e(home_url()) ?>" target="_blank" rel="noopener">
                        <?= icon('external', 'icon', 17) ?>
                        <span>View site</span>
                    </a>
                </div>
            </nav>

            <div class="admin-sidebar__foot">
                <div class="admin-user">
                    <span class="admin-user__avatar" aria-hidden="true"><?= e(initials(admin_display_name())) ?></span>
                    <span class="admin-user__meta">
                        <strong><?= e(admin_display_name()) ?></strong>
                        <small><?= e((string) ($user['username'] ?? 'admin')) ?></small>
                    </span>
                </div>
                <form method="post" action="<?= e(admin_url('logout.php')) ?>">
                    <?= csrf_field() ?>
                    <button class="admin-logout" type="submit">
                        <?= icon('logout', 'icon', 16) ?> Sign out
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <div class="admin-topbar__left">
                <button class="admin-menu-btn" type="button" data-sidebar-toggle aria-expanded="false" aria-controls="admin-sidebar" aria-label="Toggle dashboard navigation">
                    <?= icon('menu', 'icon', 18) ?>
                </button>
                <div class="admin-topbar__title">
                    <h1><?= e($title) ?></h1>
                    <p><?= e($subtitle !== '' ? $subtitle : $owner['name'] . ' · ' . $owner['role']) ?></p>
                </div>
            </div>
            <div class="admin-topbar__actions">
                <a class="btn btn--ghost btn--sm" href="<?= e(admin_url('project-form.php')) ?>">
                    <?= icon('plus', 'icon', 14) ?> New project
                </a>
                <?= $actions ?>
            </div>
        </header>

        <main class="admin-content" id="main">
            <?= alerts_html() ?>
    <?php
}

function admin_footer(): void
{
    $user = admin_user();
    ?>
        </main>

        <footer class="admin-footer">
            <?= e(config('owner.name')) ?> · portfolio dashboard ·
            PHP <?= e(PHP_VERSION) ?> ·
            Signed in as <?= e((string) ($user['username'] ?? 'admin')) ?> ·
            <?= e(date('D, j M Y')) ?>
        </footer>
    </div>
</div>
<script src="<?= e(asset_url('js/admin.js')) ?>" defer></script>
</body>
</html>
    <?php
}

/** Small helper: status pill for a project row. */
function admin_status_badge(string $status): string
{
    return $status === 'published'
        ? '<span class="badge badge--live">Published</span>'
        : '<span class="badge badge--draft">Draft</span>';
}

function admin_message_badge(string $status): string
{
    return match ($status) {
        'sent'   => '<span class="badge badge--live">Delivered</span>',
        'failed' => '<span class="badge badge--draft">Mail failed</span>',
        'spam'   => '<span class="badge">Blocked</span>',
        default  => '<span class="badge">Pending</span>',
    };
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' B';
}

/** Build pagination links that preserve the current query string. */
function admin_pagination(int $page, int $pageCount, string $baseUrl, array $query = []): void
{
    if ($pageCount <= 1) {
        return;
    }

    $url = static function (int $number) use ($baseUrl, $query): string {
        $query['page'] = $number;
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . http_build_query($query);
    };

    echo '<nav class="pagination" aria-label="Pagination">';
    echo '<a class="pagination__link' . ($page <= 1 ? ' is-disabled' : '') . '" href="' . e($url(max(1, $page - 1))) . '" aria-label="Previous page">' . icon('arrow-left', 'icon', 15) . '</a>';

    $start = max(1, $page - 2);
    $end = min($pageCount, $page + 2);

    if ($start > 1) {
        echo '<a class="pagination__link" href="' . e($url(1)) . '">1</a>';
        if ($start > 2) {
            echo '<span class="pagination__link is-disabled">…</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        echo '<a class="pagination__link' . ($i === $page ? ' is-active' : '') . '" href="' . e($url($i)) . '"'
            . ($i === $page ? ' aria-current="page"' : '') . '>' . $i . '</a>';
    }

    if ($end < $pageCount) {
        if ($end < $pageCount - 1) {
            echo '<span class="pagination__link is-disabled">…</span>';
        }
        echo '<a class="pagination__link" href="' . e($url($pageCount)) . '">' . $pageCount . '</a>';
    }

    echo '<a class="pagination__link' . ($page >= $pageCount ? ' is-disabled' : '') . '" href="' . e($url(min($pageCount, $page + 1))) . '" aria-label="Next page">' . icon('arrow-right', 'icon', 15) . '</a>';
    echo '</nav>';
}
