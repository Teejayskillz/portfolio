<?php
/**
 * Shared admin bootstrap. Every dashboard route starts here.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

ensure_installed();
send_noindex_header();

require_once __DIR__ . '/includes/layout.php';
