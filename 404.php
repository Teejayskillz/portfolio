<?php
/**
 * 404 handler (also used as Apache ErrorDocument).
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

http_response_code(404);

$meta = [
    'title'       => 'Page not found — ' . config('owner.name'),
    'description' => 'The page you were looking for does not exist. Browse the portfolio or get in touch.',
    'robots'      => 'noindex, follow',
    'active'      => '',
    'body_class'  => 'error-page',
];

require APP_TEMPLATES . '/404.php';
