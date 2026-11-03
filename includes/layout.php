<?php
/**
 * Public site layout + reusable view components.
 *
 * The visual language is the one already established by the original site:
 * near-black surfaces, a single green accent, Poppins, pill-shaped buttons.
 * It is expressed here through semantic markup so templates stay readable.
 */

declare(strict_types=1);

require_once __DIR__ . '/icons.php';

function default_meta_description(): string
{
    return 'Apatira Tijani Adewale is a software and web developer in Lagos, Nigeria, '
        . 'building websites, web applications and backend systems for businesses.';
}

/**
 * Render the document head, skip link and site header.
 *
 * @param array{
 *   title?:string, description?:string, canonical?:string, robots?:string,
 *   og_type?:string, og_image?:string, active?:string, body_class?:string,
 *   structured_data?:list<array<string,mixed>>, preload_image?:string
 * } $page
 */
function site_header(array $page = []): void
{
    $owner = config('owner');
    $title = trim((string) ($page['title'] ?? ''));
    if ($title === '') {
        $title = $owner['name'] . ' — ' . $owner['role'];
    }

    $description = trim((string) ($page['description'] ?? ''));
    if ($description === '') {
        $description = default_meta_description();
    }

    $canonical = trim((string) ($page['canonical'] ?? ''));
    if ($canonical === '') {
        $canonical = site_url('');
    }

    $robots = trim((string) ($page['robots'] ?? 'index, follow'));
    $ogType = trim((string) ($page['og_type'] ?? 'website'));
    $ogImage = trim((string) ($page['og_image'] ?? site_url('assets/img/og-default.png')));
    $active = (string) ($page['active'] ?? '');
    $bodyClass = trim((string) ($page['body_class'] ?? ''));
    $structuredData = $page['structured_data'] ?? [];

    $navItems = [
        ['key' => 'about', 'label' => 'About', 'href' => section_url('about')],
        ['key' => 'work', 'label' => 'Work', 'href' => projects_url()],
        ['key' => 'services', 'label' => 'Services', 'href' => section_url('services')],
        ['key' => 'stack', 'label' => 'Stack', 'href' => section_url('stack')],
        ['key' => 'process', 'label' => 'Process', 'href' => section_url('process')],
    ];
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>document.documentElement.classList.add('has-js');</script>
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="robots" content="<?= e($robots) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta name="author" content="<?= e($owner['name']) ?>">
    <meta name="theme-color" content="#050807">
    <meta name="color-scheme" content="dark">

    <meta property="og:site_name" content="<?= e($owner['name']) ?> — <?= e($owner['role']) ?>">
    <meta property="og:type" content="<?= e($ogType) ?>">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="<?= e($owner['name']) ?> — <?= e($owner['role']) ?>">
    <meta property="og:locale" content="en_NG">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($title) ?>">
    <meta name="twitter:description" content="<?= e($description) ?>">
    <meta name="twitter:image" content="<?= e($ogImage) ?>">

    <link rel="icon" type="image/png" href="<?= e(asset_url('img/brand-favicon.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset_url('img/apple-touch-icon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap">
    <link rel="stylesheet" href="<?= e(asset_url('css/premium.css')) ?>">
    <noscript>
        <style>
            .site-header { position: static; background: #040706; border-bottom: 1px solid rgba(255,255,255,.08); }
            .site-header__inner { flex-wrap: wrap; gap: .6rem; }
            .site-nav { position: static; display: flex; flex-wrap: wrap; align-items: center; gap: 1rem;
                opacity: 1; visibility: visible; transform: none; padding: .7rem 0 0;
                background: transparent; border: 0; }
            .site-nav__list { display: flex; flex-wrap: wrap; gap: 1rem; }
            .site-nav__cta, .nav-toggle { display: none; }
            .hero, .page-hero { padding-top: 2.5rem; }
        </style>
    </noscript>
    <?php if (!empty($page['preload_image'])): ?>
    <link rel="preload" as="image" href="<?= e((string) $page['preload_image']) ?>" fetchpriority="high">
    <?php endif; ?>
    <?php foreach ($structuredData as $schema): ?>
        <?= json_ld($schema) ?>

    <?php endforeach; ?>
</head>
<body id="top" class="<?= e(trim('site ' . $bodyClass)) ?>" data-page="<?= e($active) ?>">
<a class="skip-link" href="#main">Skip to main content</a>

<div class="scroll-progress" aria-hidden="true"><span data-scroll-progress></span></div>

<header class="site-header" data-header>
    <div class="container site-header__inner">
        <a class="brand" href="<?= e(home_url()) ?>" aria-label="<?= e($owner['name']) ?> — home">
            <span class="brand__mark" aria-hidden="true">TA</span>
            <span class="brand__text">
                <strong>Tijani<span class="brand__accent">.</span>dev</strong>
                <small>Software &amp; Web Developer</small>
            </span>
        </a>

        <nav class="site-nav" id="site-nav" aria-label="Primary">
            <ul class="site-nav__list">
                <?php foreach ($navItems as $item): ?>
                    <?php $isActive = $active === $item['key']; ?>
                    <li>
                        <a class="site-nav__link<?= $isActive ? ' is-active' : '' ?>"
                           href="<?= e($item['href']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                            <?= e($item['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a class="btn btn--primary btn--sm site-nav__cta" href="<?= e(section_url('contact')) ?>">
                Let's talk
                <?= icon('arrow-up-right', 'icon', 16) ?>
            </a>
        </nav>

        <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="site-nav">
            <span class="nav-toggle__text">Menu</span>
            <span class="nav-toggle__bars" aria-hidden="true"><span></span><span></span></span>
        </button>
    </div>
</header>
    <?php
}

/**
 * Close the page: footer, script tags, end of document.
 *
 * @param array<string, mixed> $page
 */
function site_footer(array $page = []): void
{
    $owner = config('owner');
    $year = date('Y');
    ?>
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a class="brand brand--footer" href="<?= e(home_url()) ?>" aria-label="<?= e($owner['name']) ?> — home">
                    <span class="brand__mark" aria-hidden="true">TA</span>
                    <span class="brand__text">
                        <strong>Tijani<span class="brand__accent">.</span>dev</strong>
                        <small>Software &amp; Web Developer</small>
                    </span>
                </a>
                <p class="footer-brand__text">
                    Building digital solutions that move businesses forward. Based in Lagos, Nigeria,
                    working with businesses wherever they are.
                </p>
                <p class="status-pill">
                    <span class="status-pill__dot" aria-hidden="true"></span>
                    Available for new projects
                </p>
            </div>

            <nav class="footer-col" aria-label="Footer navigation">
                <h2 class="footer-col__title">Explore</h2>
                <ul class="footer-links">
                    <li><a href="<?= e(home_url()) ?>">Home</a></li>
                    <li><a href="<?= e(section_url('about')) ?>">About</a></li>
                    <li><a href="<?= e(projects_url()) ?>">Projects</a></li>
                    <li><a href="<?= e(section_url('services')) ?>">Services</a></li>
                    <li><a href="<?= e(section_url('process')) ?>">How I work</a></li>
                    <li><a href="<?= e(section_url('contact')) ?>">Contact</a></li>
                </ul>
            </nav>

            <div class="footer-col">
                <h2 class="footer-col__title">Connect</h2>
                <ul class="footer-links footer-links--icons">
                    <li>
                        <a href="<?= e(email_url('Project enquiry')) ?>">
                            <?= icon('mail', 'icon', 17) ?><span><?= e($owner['email']) ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= e(whatsapp_url('Hi Tijani, I would like to discuss a project.')) ?>" target="_blank" rel="noopener noreferrer">
                            <?= icon('whatsapp', 'icon', 17) ?><span>WhatsApp</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= e($owner['github']) ?>" target="_blank" rel="noopener noreferrer">
                            <?= icon('github', 'icon', 17) ?><span>GitHub</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= e($owner['linkedin']) ?>" target="_blank" rel="noopener noreferrer">
                            <?= icon('linkedin', 'icon', 17) ?><span>LinkedIn</span>
                        </a>
                    </li>
                </ul>
                <p class="footer-location"><?= icon('map-pin', 'icon', 16) ?> Lagos, Nigeria</p>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= e($year) ?> <?= e($owner['name']) ?>. All rights reserved.</p>
            <p class="footer-bottom__meta">
                Built with PHP &amp; SQLite
                <span aria-hidden="true">·</span>
                <a href="<?= e(admin_url('login.php')) ?>">Admin</a>
            </p>
            <a class="footer-top" href="#top">Back to top <?= icon('arrow-up', 'icon', 15) ?></a>
        </div>
    </div>
</footer>

<a class="to-top" href="#top" data-to-top aria-label="Back to top"><?= icon('arrow-up', 'icon', 18) ?></a>

<script src="<?= e(asset_url('js/site.js')) ?>" defer></script>
</body>
</html>
    <?php
}

/* -------------------------------------------------------------------------
 * Components
 * ---------------------------------------------------------------------- */

/**
 * Section heading with an eyebrow and optional accent word.
 */
function section_head(string $eyebrow, string $title, string $accent = '', string $lead = '', string $align = ''): void
{
    $classes = 'section-head' . ($align === 'center' ? ' section-head--center' : '');
    ?>
<header class="<?= e($classes) ?>">
    <p class="eyebrow"><span class="eyebrow__rule" aria-hidden="true"></span><?= e($eyebrow) ?></p>
    <h2 class="section-head__title">
        <?= e($title) ?><?php if ($accent !== ''): ?> <span class="accent"><?= e($accent) ?></span><?php endif; ?>
    </h2>
    <?php if ($lead !== ''): ?>
        <p class="section-head__lead"><?= e($lead) ?></p>
    <?php endif; ?>
</header>
    <?php
}

/**
 * A project card, linking to the project detail page.
 *
 * @param array<string, mixed> $project
 */
function project_card(array $project, string $variant = ''): void
{
    $url = project_url($project);
    $title = (string) ($project['title'] ?? 'Untitled project');
    $cover = trim((string) ($project['cover_image'] ?? ''));
    $category = trim((string) ($project['category_name'] ?? ''));
    $short = truncate_text((string) ($project['short_description'] ?? ''), 132);
    $technologies = array_slice(is_array($project['technologies'] ?? null) ? $project['technologies'] : [], 0, 4);
    $isFeatured = !empty($project['is_featured']);
    $classes = 'project-card' . ($variant !== '' ? ' project-card--' . $variant : '');
    ?>
<article class="<?= e($classes) ?>">
    <a class="project-card__link" href="<?= e($url) ?>" aria-label="<?= e('Open project: ' . $title) ?>">
        <div class="project-card__media">
            <?php if ($cover !== ''): ?>
                <img src="<?= e(upload_url($cover)) ?>"
                     alt="<?= e($title) ?> — project cover image"
                     width="1200" height="750" loading="lazy" decoding="async">
            <?php else: ?>
                <div class="project-cover" aria-hidden="true">
                    <span class="project-cover__grid"></span>
                    <span class="project-cover__initials"><?= e(initials($title)) ?></span>
                    <span class="project-cover__label"><?= e($category !== '' ? $category : 'Project') ?></span>
                </div>
            <?php endif; ?>
            <span class="project-card__cat"><?= e($category !== '' ? $category : 'Project') ?></span>
            <?php if ($isFeatured): ?>
                <span class="project-card__star" title="Featured project"><?= icon('star', 'icon', 13) ?></span>
            <?php endif; ?>
        </div>

        <div class="project-card__body">
            <h3 class="project-card__title"><?= e($title) ?></h3>
            <?php if ($short !== ''): ?>
                <p class="project-card__desc"><?= e($short) ?></p>
            <?php endif; ?>

            <div class="project-card__foot">
                <?php if ($technologies !== []): ?>
                    <ul class="tech-pills" aria-label="Technologies used">
                        <?php foreach ($technologies as $technology): ?>
                            <li class="tech-pills__item"><?= e((string) $technology['name']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <span class="project-card__cta" aria-hidden="true"><?= icon('arrow-up-right', 'icon', 18) ?></span>
            </div>
        </div>
    </a>
</article>
    <?php
}

/**
 * @param list<array<string, mixed>> $technologies
 */
function tech_pills(array $technologies, int $limit = 6): void
{
    if ($technologies === []) {
        return;
    }
    $shown = array_slice($technologies, 0, $limit);
    $remaining = count($technologies) - count($shown);
    ?>
<ul class="tech-pills">
    <?php foreach ($shown as $technology): ?>
        <li class="tech-pills__item"><?= e((string) $technology['name']) ?></li>
    <?php endforeach; ?>
    <?php if ($remaining > 0): ?>
        <li class="tech-pills__item tech-pills__item--muted">+<?= e((string) $remaining) ?></li>
    <?php endif; ?>
</ul>
    <?php
}

/**
 * @param list<array{name:string,url:string,current?:bool}> $items
 */
function breadcrumbs(array $items): void
{
    ?>
<nav class="breadcrumbs" aria-label="Breadcrumb">
    <ol>
        <?php foreach ($items as $item): ?>
            <li>
                <?php if (!empty($item['current'])): ?>
                    <span aria-current="page"><?= e($item['name']) ?></span>
                <?php else: ?>
                    <a href="<?= e($item['url']) ?>"><?= e($item['name']) ?></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
    <?php
}

function empty_state(string $title, string $message, string $ctaLabel = '', string $ctaHref = ''): void
{
    ?>
<div class="empty-state">
    <span class="empty-state__icon" aria-hidden="true"><?= icon('layers', 'icon', 26) ?></span>
    <h3><?= e($title) ?></h3>
    <p><?= e($message) ?></p>
    <?php if ($ctaLabel !== '' && $ctaHref !== ''): ?>
        <a class="btn btn--ghost" href="<?= e($ctaHref) ?>"><?= e($ctaLabel) ?></a>
    <?php endif; ?>
</div>
    <?php
}

/** Wrapping element for queued flash messages. */
function alerts_html(): string
{
    $html = flash_html();

    return $html === '' ? '' : '<div class="alerts" data-alerts>' . $html . '</div>';
}
