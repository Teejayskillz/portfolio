<?php
/**
 * Development router for PHP's built-in server.
 *
 *   php -S 127.0.0.1:8080 tools/dev-router.php
 *
 * It emulates the production .htaccess (clean URLs, blocked private folders)
 * so local behaviour matches cPanel. Never use this on a live server.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = '/' . ltrim(rawurldecode($path), '/');

// Block application internals (mirrors .htaccess).
if (preg_match('#^/(includes|storage|templates|tools|admin/includes)(/|$)#', $path) === 1) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    return true;
}

if (preg_match('#(^|/)\.(?!well-known)#', $path) === 1) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    return true;
}

/** Load a controller while keeping SCRIPT_NAME meaningful. */
$dispatch = static function (string $script, array $query = []) use ($root): bool {
    $file = $root . '/' . ltrim($script, '/');
    if (!is_file($file)) {
        http_response_code(404);
        require $root . '/404.php';

        return true;
    }

    foreach ($query as $key => $value) {
        $_GET[$key] = $value;
    }
    $_SERVER['SCRIPT_NAME'] = '/' . ltrim($script, '/');
    $_SERVER['PHP_SELF'] = '/' . ltrim($script, '/');
    $_SERVER['SCRIPT_FILENAME'] = $file;

    require $file;

    return true;
};

// Clean URLs handled by .htaccess in production.
if (preg_match('#^/projects/?$#', $path) === 1) {
    return $dispatch('projects.php');
}

if (preg_match('#^/project/([A-Za-z0-9_-]+)/?$#', $path, $matches) === 1) {
    return $dispatch('project.php', ['slug' => $matches[1]]);
}

if (preg_match('#^/contact/?$#', $path) === 1) {
    return $dispatch('contact.php');
}

if (preg_match('#^/thank-you/?$#', $path) === 1) {
    return $dispatch('thank-you.php');
}

if ($path === '/sitemap.xml') {
    return $dispatch('sitemap.php');
}

if ($path === '/admin') {
    header('Location: /admin/', true, 301);

    return true;
}

// Existing files and directories are served by the built-in server.
$target = realpath($root . $path);
$rootReal = realpath($root);

if ($target !== false && $rootReal !== false && str_starts_with($target, $rootReal)) {
    if (is_file($target)) {
        return false;
    }
    if (is_dir($target) && $path !== '/') {
        $index = rtrim($target, '/\\') . '/index.php';
        if (is_file($index)) {
            $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php';
            require $index;

            return true;
        }
    }
}

if ($path === '/') {
    return $dispatch('index.php');
}

http_response_code(404);
$_SERVER['SCRIPT_NAME'] = '/404.php';
require $root . '/404.php';

return true;
