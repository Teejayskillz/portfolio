<?php
/**
 * Project detail.
 *
 * @var array<string, mixed>       $project
 * @var list<array<string, mixed>> $related
 * @var bool                       $preview
 * @var array<string, mixed>       $meta
 */

if (!defined('APP_BOOTSTRAP')) {
    http_response_code(404);
    exit;
}

$title = (string) $project['title'];
$cover = trim((string) ($project['cover_image'] ?? ''));
$category = trim((string) ($project['category_name'] ?? ''));
$technologies = $project['technologies'] ?? [];
$gallery = $project['gallery'] ?? [];
$hasLive = is_safe_url($project['live_url'] ?? '');
$hasGithub = is_safe_url($project['github_url'] ?? '');
$completion = format_date($project['completion_date'] ?? '');
$isFeatured = !empty($project['is_featured']);
$owner = config('owner');

site_header($meta);
?>

<?= alerts_html() ?>

<main id="main">
    <section class="page-hero">
        <div class="container">
            <?php breadcrumbs([
                ['name' => 'Home', 'url' => home_url()],
                ['name' => 'Work', 'url' => projects_url()],
                ['name' => $title, 'url' => project_url($project), 'current' => true],
            ]); ?>

            <?php if ($preview): ?>
                <div class="alert alert--warning" role="status">
                    <span class="alert__icon" aria-hidden="true"></span>
                    <p>Draft preview. This project is not published and is only visible to you while signed in.</p>
                </div>
            <?php endif; ?>

            <div class="project-hero__meta">
                <span class="badge badge--live"><?= e($category !== '' ? $category : 'Project') ?></span>
                <?php if ($isFeatured): ?>
                    <span class="badge"><?= icon('star', 'icon', 12) ?> Featured</span>
                <?php endif; ?>
                <?php if (!empty($project['completion_date'])): ?>
                    <span class="badge"><?= icon('clock', 'icon', 12) ?> <?= e($completion) ?></span>
                <?php endif; ?>
                <span class="badge"><?= e((string) count($technologies)) ?> technolog<?= count($technologies) === 1 ? 'y' : 'ies' ?></span>
            </div>

            <h1 class="project-hero__title"><?= e($title) ?></h1>

            <?php if (trim((string) $project['short_description']) !== ''): ?>
                <p class="project-hero__lead"><?= e((string) $project['short_description']) ?></p>
            <?php endif; ?>

            <?php if ($hasLive || $hasGithub): ?>
                <div class="project-hero__actions">
                    <?php if ($hasLive): ?>
                        <a class="btn btn--primary" href="<?= e((string) $project['live_url']) ?>" target="_blank" rel="noopener noreferrer">
                            Visit live project <?= icon('arrow-up-right', 'icon', 16) ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($hasGithub): ?>
                        <a class="btn btn--ghost" href="<?= e((string) $project['github_url']) ?>" target="_blank" rel="noopener noreferrer">
                            <?= icon('github', 'icon', 16) ?> Source code
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <figure class="project-cover-large">
                <?php if ($cover !== ''): ?>
                    <img src="<?= e(upload_url($cover)) ?>" alt="<?= e($title) ?> — cover image"
                         width="1600" height="900" fetchpriority="high" decoding="async">
                <?php else: ?>
                    <div class="project-cover" role="img" aria-label="<?= e($title) ?>">
                        <span class="project-cover__grid"></span>
                        <span class="project-cover__initials"><?= e(initials($title)) ?></span>
                        <span class="project-cover__label"><?= e($category !== '' ? $category : 'Project') ?></span>
                    </div>
                <?php endif; ?>
            </figure>
        </div>
    </section>

    <div class="container">
        <div class="project-body">
            <div class="prose" data-reveal>
                <?php if (trim((string) $project['description']) !== ''): ?>
                    <section class="content-block">
                        <h2 class="content-block__title">Overview</h2>
                        <?= paragraphs((string) $project['description']) ?>
                    </section>
                <?php endif; ?>

                <?php if (trim((string) $project['problem']) !== ''): ?>
                    <section class="content-block">
                        <h2 class="content-block__title">The problem</h2>
                        <?= paragraphs((string) $project['problem']) ?>
                    </section>
                <?php endif; ?>

                <?php if (trim((string) $project['solution']) !== ''): ?>
                    <section class="content-block">
                        <h2 class="content-block__title">The solution</h2>
                        <?= paragraphs((string) $project['solution']) ?>
                    </section>
                <?php endif; ?>

                <?php if ($gallery !== []): ?>
                    <section class="content-block">
                        <h2 class="content-block__title">Screenshots</h2>
                        <div class="gallery">
                            <?php foreach ($gallery as $index => $image): ?>
                                <?php
                                $caption = trim((string) $image['alt_text']);
                                if ($caption === '') {
                                    $caption = $title . ' — screenshot ' . ($index + 1);
                                }
                                ?>
                                <figure class="gallery__item">
                                    <a class="gallery__button"
                                       href="<?= e(upload_url((string) $image['file_path'])) ?>"
                                       data-lightbox
                                       data-caption="<?= e($caption) ?>"
                                       aria-label="<?= e('Open larger: ' . $caption) ?>">
                                        <img src="<?= e(upload_url((string) $image['file_path'])) ?>"
                                             alt="<?= e($caption) ?>" loading="lazy" decoding="async"
                                             width="1200" height="750">
                                    </a>
                                    <figcaption class="gallery__caption"><?= e($caption) ?></figcaption>
                                </figure>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>

            <aside class="project-sidebar">
                <div class="info-card" data-reveal>
                    <h2 class="info-card__title">Project details</h2>
                    <div class="info-list">
                        <div class="info-list__row">
                            <span class="info-list__label">Category</span>
                            <span class="info-list__value"><?= e($category !== '' ? $category : 'Uncategorised') ?></span>
                        </div>
                        <?php if ($completion !== ''): ?>
                            <div class="info-list__row">
                                <span class="info-list__label">Completed</span>
                                <span class="info-list__value"><?= e($completion) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="info-list__row">
                            <span class="info-list__label">Featured</span>
                            <span class="info-list__value"><?= $isFeatured ? 'Yes' : 'No' ?></span>
                        </div>
                        <div class="info-list__row">
                            <span class="info-list__label">Technologies</span>
                            <div class="info-list__value">
                                <?php if ($technologies !== []): ?>
                                    <ul class="tech-pills">
                                        <?php foreach ($technologies as $technology): ?>
                                            <li class="tech-pills__item"><?= e((string) $technology['name']) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    Not listed
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($hasLive): ?>
                            <div class="info-list__row">
                                <span class="info-list__label">Live URL</span>
                                <span class="info-list__value">
                                    <a href="<?= e((string) $project['live_url']) ?>" target="_blank" rel="noopener noreferrer">
                                        <?= e(parse_url((string) $project['live_url'], PHP_URL_HOST) ?: 'Visit project') ?>
                                    </a>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if ($hasGithub): ?>
                            <div class="info-list__row">
                                <span class="info-list__label">Source</span>
                                <span class="info-list__value">
                                    <a href="<?= e((string) $project['github_url']) ?>" target="_blank" rel="noopener noreferrer">
                                        <?= e(parse_url((string) $project['github_url'], PHP_URL_HOST) ?: 'Repository') ?>
                                    </a>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($hasLive || $hasGithub): ?>
                        <div class="info-card__actions">
                            <?php if ($hasLive): ?>
                                <a class="btn btn--primary btn--sm" href="<?= e((string) $project['live_url']) ?>" target="_blank" rel="noopener noreferrer">
                                    Open live site <?= icon('external', 'icon', 14) ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($hasGithub): ?>
                                <a class="btn btn--ghost btn--sm" href="<?= e((string) $project['github_url']) ?>" target="_blank" rel="noopener noreferrer">
                                    View repository <?= icon('github', 'icon', 14) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="info-card" data-reveal>
                    <h2 class="info-card__title">Need something like this?</h2>
                    <p class="project-card__desc" style="margin-bottom: 1rem">
                        I'm available for new projects. Tell me what you're trying to build and we can work
                        out whether it's a fit.
                    </p>
                    <div class="info-card__actions">
                        <a class="btn btn--primary btn--sm" href="<?= e(section_url('contact')) ?>">
                            Start a conversation <?= icon('arrow-up-right', 'icon', 14) ?>
                        </a>
                        <a class="btn btn--ghost btn--sm" href="<?= e(whatsapp_url('Hi Tijani, I would like to discuss a project like ' . $title . '.')) ?>"
                           target="_blank" rel="noopener noreferrer">
                            <?= icon('whatsapp', 'icon', 14) ?> WhatsApp
                        </a>
                    </div>
                </div>
            </aside>
        </div>

        <?php if ($related !== []): ?>
            <section class="related" aria-labelledby="related-title">
                <header class="work__head">
                    <div class="section-head">
                        <p class="eyebrow">Keep exploring</p>
                        <h2 class="section-head__title" id="related-title">More <span class="accent">projects</span></h2>
                    </div>
                    <a class="link-arrow work__more" href="<?= e(projects_url()) ?>">
                        All projects <?= icon('arrow-right', 'icon', 16) ?>
                    </a>
                </header>

                <div class="project-grid">
                    <?php foreach ($related as $index => $relatedProject): ?>
                        <div data-reveal style="--reveal-delay: <?= e((string) ($index * 70)) ?>ms">
                            <?php project_card($relatedProject); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <div class="cta-band" data-reveal>
            <div class="cta-band__inner">
                <div>
                    <h2 class="cta-band__title">Let's talk about your project</h2>
                    <p class="cta-band__text">
                        <?= e($owner['name']) ?> · <?= e($owner['role']) ?> · <?= e($owner['location']) ?>
                    </p>
                </div>
                <div class="cta-band__actions">
                    <a class="btn btn--primary" href="<?= e(section_url('contact')) ?>">
                        Get in touch <?= icon('arrow-up-right', 'icon', 16) ?>
                    </a>
                    <a class="btn btn--ghost" href="<?= e(email_url('Project enquiry: ' . $title)) ?>">
                        <?= icon('mail', 'icon', 16) ?> Email me
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php site_footer($meta); ?>
