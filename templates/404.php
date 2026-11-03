<?php
/**
 * 404 page.
 *
 * @var array<string, mixed> $meta
 */

if (!defined('APP_BOOTSTRAP')) {
    http_response_code(404);
    exit;
}

site_header($meta);
?>

<main id="main" class="status-page">
    <div class="container container--narrow">
        <div class="status-page__card">
            <p class="status-page__code">Error 404</p>
            <h1 class="status-page__title">This page doesn't exist.</h1>
            <p class="status-page__text">
                The link may be out of date, or the page may have moved. The work is still here — start
                from the project list or get in touch.
            </p>
            <div class="status-page__actions">
                <a class="btn btn--primary" href="<?= e(home_url()) ?>">
                    <?= icon('arrow-left', 'icon', 16) ?> Back to home
                </a>
                <a class="btn btn--ghost" href="<?= e(projects_url()) ?>">View projects</a>
                <a class="btn btn--ghost" href="<?= e(section_url('contact')) ?>">Contact</a>
            </div>
        </div>
    </div>
</main>

<?php site_footer($meta); ?>
