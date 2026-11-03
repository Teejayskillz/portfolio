<?php
/**
 * Thank-you page controller.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

ensure_installed();

$meta = [
    'title'       => 'Message sent — ' . config('owner.name'),
    'description' => 'Your message was sent. Apatira Tijani Adewale will reply by email.',
    'robots'      => 'noindex, nofollow',
    'active'      => '',
    'body_class'  => 'thank-you-page',
];

require APP_TEMPLATES . '/thank-you.php';
