<?php
/**
 * Branded error page used by abort_page().
 *
 * @var int    $status
 * @var string $title
 * @var string $message
 * @var string $suggestion
 */

if (!defined('APP_BOOTSTRAP')) {
    http_response_code(404);
    exit;
}

$status = (int) ($status ?? 500);
$title = (string) ($title ?? 'Something went wrong');
$message = (string) ($message ?? 'The request could not be completed.');
$suggestion = (string) ($suggestion ?? '');

$meta = [
    'title'       => $status . ' — ' . $title . ' | ' . config('owner.name'),
    'description' => $message,
    'robots'      => 'noindex, follow',
    'active'      => '',
];

site_header($meta);
?>

<main id="main" class="status-page">
    <div class="container container--narrow">
        <div class="status-page__card">
            <p class="status-page__code">Error <?= e((string) $status) ?></p>
            <h1 class="status-page__title"><?= e($title) ?></h1>
            <p class="status-page__text"><?= e($message) ?></p>
            <?php if ($suggestion !== ''): ?>
                <p class="form-note" style="margin-bottom: 1.4rem"><?= e($suggestion) ?></p>
            <?php endif; ?>
            <div class="status-page__actions">
                <a class="btn btn--primary" href="<?= e(home_url()) ?>">Back to home</a>
                <a class="btn btn--ghost" href="<?= e(projects_url()) ?>">View projects</a>
                <a class="btn btn--ghost" href="<?= e(section_url('contact')) ?>">Contact</a>
            </div>
        </div>
    </div>
</main>

<?php site_footer($meta); ?>
