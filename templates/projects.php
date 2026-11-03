<?php
/**
 * Projects index.
 *
 * @var list<array<string, mixed>> $projects
 * @var list<array<string, mixed>> $categories
 * @var string                     $activeCategory
 * @var string                     $activeCategoryName
 * @var int                        $total
 * @var int                        $pageNumber
 * @var int                        $pageCount
 * @var int                        $publishedTotal
 * @var array<string, mixed>       $meta
 */

if (!defined('APP_BOOTSTRAP')) {
    http_response_code(404);
    exit;
}

$pageUrl = static function (int $number) use ($activeCategory): string {
    $base = projects_url($activeCategory !== '' ? $activeCategory : null);
    $separator = str_contains($base, '?') ? '&' : '?';

    return $base . $separator . 'page=' . $number;
};

site_header($meta);
?>

<?= alerts_html() ?>

<main id="main">
    <section class="page-hero">
        <div class="container">
            <?php breadcrumbs([
                ['name' => 'Home', 'url' => home_url()],
                ['name' => 'Work', 'url' => projects_url(), 'current' => $activeCategory === ''],
            ]); ?>

            <p class="page-hero__eyebrow">Portfolio</p>
            <h1 class="page-hero__title">
                Work that solves <span class="accent">real problems</span>
            </h1>
            <p class="page-hero__lead">
                Websites, web applications and backend systems — each one built around a specific job.
                Open a project to see what it does, why it exists and how it was put together.
            </p>

            <div class="page-hero__meta">
                <div class="hero-meta__item">
                    <span class="hero-meta__label">Published projects</span>
                    <span class="hero-meta__value"><?= e((string) $publishedTotal) ?></span>
                </div>
                <div class="hero-meta__item">
                    <span class="hero-meta__label">Categories</span>
                    <span class="hero-meta__value"><?= e((string) count($categories)) ?></span>
                </div>
                <div class="hero-meta__item">
                    <span class="hero-meta__label">Current view</span>
                    <span class="hero-meta__value"><?= e($activeCategoryName) ?></span>
                </div>
            </div>
        </div>
    </section>

    <section class="section section--tight">
        <div class="container">
            <div class="filter-bar">
                <div class="filter-bar__list" role="navigation" aria-label="Filter projects by category">
                    <a class="filter-pill<?= $activeCategory === '' ? ' is-active' : '' ?>"
                       href="<?= e(projects_url()) ?>"
                       <?= $activeCategory === '' ? 'aria-current="page"' : '' ?>>
                        All
                        <span class="filter-pill__count"><?= e((string) $publishedTotal) ?></span>
                    </a>
                    <?php foreach ($categories as $category): ?>
                        <?php $slug = (string) $category['slug']; ?>
                        <a class="filter-pill<?= $activeCategory === $slug ? ' is-active' : '' ?>"
                           href="<?= e(projects_url($slug)) ?>"
                           <?= $activeCategory === $slug ? 'aria-current="page"' : '' ?>>
                            <?= e((string) $category['name']) ?>
                            <span class="filter-pill__count"><?= e((string) $category['project_count']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <p class="filter-bar__count">
                    <?= e((string) $total) ?> project<?= $total === 1 ? '' : 's' ?><?= $activeCategory !== '' ? ' in ' . $activeCategoryName : '' ?>
                </p>
            </div>

            <?php if ($projects !== []): ?>
                <div class="project-grid">
                    <?php foreach ($projects as $index => $project): ?>
                        <div data-reveal style="--reveal-delay: <?= e((string) (($index % 3) * 70)) ?>ms">
                            <?php project_card($project); ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($pageCount > 1): ?>
                    <nav class="pagination" aria-label="Project pages">
                        <a class="pagination__link<?= $pageNumber <= 1 ? ' is-disabled' : '' ?>"
                           href="<?= e($pageUrl(max(1, $pageNumber - 1))) ?>"
                           <?= $pageNumber <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                           aria-label="Previous page"><?= icon('arrow-left', 'icon', 16) ?></a>

                        <?php for ($i = 1; $i <= $pageCount; $i++): ?>
                            <a class="pagination__link<?= $i === $pageNumber ? ' is-active' : '' ?>"
                               href="<?= e($pageUrl($i)) ?>"
                               <?= $i === $pageNumber ? 'aria-current="page"' : '' ?>><?= e((string) $i) ?></a>
                        <?php endfor; ?>

                        <a class="pagination__link<?= $pageNumber >= $pageCount ? ' is-disabled' : '' ?>"
                           href="<?= e($pageUrl(min($pageCount, $pageNumber + 1))) ?>"
                           <?= $pageNumber >= $pageCount ? 'aria-disabled="true" tabindex="-1"' : '' ?>
                           aria-label="Next page"><?= icon('arrow-right', 'icon', 16) ?></a>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <?php empty_state(
                    $activeCategory !== '' ? 'Nothing here yet' : 'Projects are being added',
                    $activeCategory !== ''
                        ? 'No published projects in this category yet. Try another category or view everything.'
                        : 'New work is being documented right now. Check back soon, or get in touch to talk about yours.',
                    $activeCategory !== '' ? 'View all projects' : "Let's talk",
                    $activeCategory !== '' ? projects_url() : section_url('contact')
                ); ?>
            <?php endif; ?>

            <div class="cta-band" data-reveal>
                <div class="cta-band__inner">
                    <div>
                        <h2 class="cta-band__title">Have something similar in mind?</h2>
                        <p class="cta-band__text">
                            Tell me about the problem and I'll tell you what it would take to solve it properly.
                        </p>
                    </div>
                    <div class="cta-band__actions">
                        <a class="btn btn--primary" href="<?= e(section_url('contact')) ?>">
                            Start a conversation <?= icon('arrow-up-right', 'icon', 16) ?>
                        </a>
                        <a class="btn btn--ghost" href="<?= e(whatsapp_url('Hi Tijani, I saw your work and would like to talk.')) ?>"
                           target="_blank" rel="noopener noreferrer">
                            <?= icon('whatsapp', 'icon', 16) ?> WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php site_footer($meta); ?>
