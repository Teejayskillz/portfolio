<?php
/**
 * Shared helper functions.
 *
 * Deliberately framework-free: the site runs on shared cPanel hosting where
 * Composer installs are awkward, so everything here is plain PHP 8.
 */

declare(strict_types=1);

/* -------------------------------------------------------------------------
 * Configuration
 * ---------------------------------------------------------------------- */

/**
 * Read a dotted configuration value, e.g. config('owner.email').
 *
 * @return mixed
 */
function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['app_config'] ?? [];
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

/* -------------------------------------------------------------------------
 * Output escaping
 * ---------------------------------------------------------------------- */

/** Escape a value for HTML text and attribute contexts. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape a string for use inside a JS/JSON context. */
function json_attr(mixed $value): string
{
    return e((string) json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES));
}

/** Print escaped output (shortcut for templates). */
function ee(mixed $value): void
{
    echo e($value);
}

/* -------------------------------------------------------------------------
 * Filesystem / logging
 * ---------------------------------------------------------------------- */

/** Create a directory (recursively) if it does not exist yet. */
function ensure_dir(string $path, int $mode = 0755): bool
{
    if ($path === '') {
        return false;
    }
    if (is_dir($path)) {
        return true;
    }

    return @mkdir($path, $mode, true) || is_dir($path);
}

/** Append a structured line to storage/logs/<channel>.log, rotating at 2 MB. */
function app_log(string $channel, string $message, array $context = []): void
{
    $dir = APP_STORAGE . '/logs';
    if (!ensure_dir($dir)) {
        return;
    }

    $channel = preg_replace('/[^a-z0-9_-]+/i', '-', $channel) ?: 'app';
    $file = $dir . '/' . $channel . '.log';

    if (is_file($file) && (int) @filesize($file) > 2 * 1024 * 1024) {
        @rename($file, $file . '.1');
    }

    $line = json_encode([
        'time'    => gmdate('c'),
        'channel' => $channel,
        'message' => $message,
        'context' => $context,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Stable application secret used for signed form tokens.
 * Stored privately in storage/secret.key (never committed).
 */
function app_secret(): string
{
    static $secret = null;
    if (is_string($secret) && $secret !== '') {
        return $secret;
    }

    $configured = (string) config('security.secret', '');
    if ($configured !== '') {
        return $secret = $configured;
    }

    $file = APP_STORAGE . '/secret.key';
    if (is_file($file)) {
        $stored = trim((string) @file_get_contents($file));
        if ($stored !== '') {
            return $secret = $stored;
        }
    }

    try {
        $generated = bin2hex(random_bytes(32));
    } catch (Throwable) {
        $generated = hash('sha256', APP_ROOT . '|' . (string) config('site_url'));
    }

    if (ensure_dir(APP_STORAGE)) {
        @file_put_contents($file, $generated, LOCK_EX);
        @chmod($file, 0600);
    }

    return $secret = $generated;
}

/* -------------------------------------------------------------------------
 * Request helpers
 * ---------------------------------------------------------------------- */

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function is_post(): bool
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
}

/** Normalised request path, without the base path and query string. */
function request_path(): string
{
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }

    return '/' . ltrim(rawurldecode($path), '/');
}

/** Current script filename, e.g. index.php or admin/projects.php. */
function current_script(): string
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
}

function client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function user_agent(): string
{
    return mb_substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255);
}

/** Read a POST value as a trimmed string. */
function post_string(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

/** Read a GET value as a trimmed string. */
function query_string(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

/** Cast a request value to a positive integer (0 when invalid). */
function to_int(mixed $value): int
{
    if (is_int($value)) {
        return max(0, $value);
    }
    if (is_string($value) && preg_match('/^\d+$/', trim($value))) {
        return (int) $value;
    }

    return 0;
}

function is_ajax(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
}

/* -------------------------------------------------------------------------
 * URLs
 * ---------------------------------------------------------------------- */

/** Configured base path, normalised to '' or '/subdir'. */
function base_path(): string
{
    static $base = null;
    if (is_string($base)) {
        return $base;
    }

    $configured = trim((string) config('base_path', ''));
    $base = $configured === '' ? '' : '/' . trim($configured, '/');

    return $base;
}

function site_origin(): string
{
    return rtrim((string) config('site_url', ''), '/');
}

/** Root-relative URL, e.g. public_url('assets/css/site.css'). */
function public_url(string $path = ''): string
{
    $path = ltrim($path, '/');

    return base_path() . ($path === '' ? '/' : '/' . $path);
}

/** Absolute URL including scheme + host. */
function site_url(string $path = ''): string
{
    $origin = site_origin();
    $relative = public_url($path);

    return $origin === '' ? $relative : $origin . $relative;
}

/** Turn a root-relative URL into an absolute one (canonical/OG tags). */
function absolute_url(string $path = ''): string
{
    if (preg_match('#^https?://#i', $path) === 1) {
        return $path;
    }

    $origin = site_origin();
    if ($origin === '') {
        return $path;
    }

    return $origin . '/' . ltrim($path, '/');
}

/** Versioned asset URL. */
function asset_url(string $path): string
{
    return public_url('assets/' . ltrim($path, '/')) . '?v=' . rawurlencode(APP_VERSION);
}

/** Public URL for a stored upload path, or '' when there is no image. */
function upload_url(?string $storedPath): string
{
    $storedPath = trim((string) $storedPath);
    if ($storedPath === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $storedPath)) {
        return $storedPath;
    }

    return public_url(ltrim($storedPath, '/'));
}

/** Public URL for the home page. */
function home_url(): string
{
    return public_url('');
}

/** Anchor to a home-page section, safe to use from any page. */
function section_url(string $hash): string
{
    return public_url('') . '#' . ltrim($hash, '#');
}

function projects_url(?string $categorySlug = null): string
{
    $clean = (bool) config('clean_urls', true);
    $base = public_url($clean ? 'projects' : 'projects.php');
    if ($categorySlug !== null && $categorySlug !== '') {
        $base .= '?category=' . rawurlencode($categorySlug);
    }

    return $base;
}

/** URL for a project: array row or slug string. */
function project_url(array|string $projectOrSlug): string
{
    $slug = is_array($projectOrSlug) ? (string) ($projectOrSlug['slug'] ?? '') : $projectOrSlug;
    if ($slug === '') {
        return projects_url();
    }

    return (bool) config('clean_urls', true)
        ? public_url('project/' . rawurlencode($slug))
        : public_url('project.php?slug=' . rawurlencode($slug));
}

function admin_url(string $path = ''): string
{
    $path = ltrim($path, '/');

    return public_url('admin/' . $path);
}

/** Pre-filled WhatsApp conversation link for the owner's number. */
function whatsapp_url(string $message = ''): string
{
    $number = preg_replace('/\D+/', '', (string) config('owner.whatsapp', ''));
    if ($number === '') {
        return '#';
    }

    $url = 'https://wa.me/' . $number;

    return $message !== '' ? $url . '?text=' . rawurlencode($message) : $url;
}

function email_url(string $subject = '', string $body = ''): string
{
    $email = (string) config('owner.email', '');
    $query = [];
    if ($subject !== '') {
        $query['subject'] = $subject;
    }
    if ($body !== '') {
        $query['body'] = $body;
    }

    return 'mailto:' . $email . ($query === [] ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
}

/** Remember validation errors for the next request. */
function remember_errors(array $errors): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $_SESSION['_errors'] = $errors;
}

/** @return array<string, string> */
function pull_errors(): array
{
    $errors = $_SESSION['_errors'] ?? [];
    unset($_SESSION['_errors']);

    return is_array($errors) ? $errors : [];
}

/** Error message for a field, or '' when the field is valid. */
function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? (string) $errors[$field] : '';
}

function has_errors(array $errors): bool
{
    return $errors !== [];
}

/** Redirect and stop execution. Rejects header-injection attempts. */
function redirect(string $location, int $status = 302): void
{
    $location = str_replace(["\r", "\n", "\0"], '', $location);
    if ($location === '') {
        $location = home_url();
    }
    if (!headers_sent()) {
        header('Location: ' . $location, true, $status);
    }
    exit;
}

/** Redirect back to a safe, same-site referrer, or to a fallback URL. */
function redirect_back(string $fallback): void
{
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $host = (string) parse_url($referer, PHP_URL_HOST);
    $ownHost = (string) parse_url(site_origin(), PHP_URL_HOST);

    if ($referer !== '' && $host !== '' && strcasecmp($host, $ownHost) === 0) {
        $path = (string) parse_url($referer, PHP_URL_PATH);
        $query = (string) parse_url($referer, PHP_URL_QUERY);
        $safe = $path . ($query !== '' ? '?' . $query : '');
        if ($safe !== '') {
            redirect($safe);
        }
    }

    redirect($fallback);
}

/* -------------------------------------------------------------------------
 * Flash messages + old input
 * ---------------------------------------------------------------------- */

function flash(string $type, string $message): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type:string,message:string}> */
function take_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);

    return is_array($flashes) ? $flashes : [];
}

/** Render queued flash messages (escaped). */
function flash_html(): string
{
    $html = '';
    foreach (take_flashes() as $flash) {
        $type = in_array($flash['type'], ['success', 'error', 'warning', 'info'], true) ? $flash['type'] : 'info';
        $role = $type === 'error' ? 'alert' : 'status';
        $html .= '<div class="alert alert--' . $type . '" role="' . $role . '">'
            . '<span class="alert__icon" aria-hidden="true"></span>'
            . '<p>' . e($flash['message']) . '</p>'
            . '<button type="button" class="alert__close" data-dismiss-alert aria-label="Dismiss message">&times;</button>'
            . '</div>';
    }

    return $html;
}

/** Remember submitted values so a failed form can be re-rendered. */
function remember_input(array $input): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    unset($input['password'], $input['password_confirm'], $input['current_password'], $input['csrf_token']);
    $_SESSION['_old'] = $input;
}

/** @return array<string,mixed> */
function old_input_array(): array
{
    $old = $_SESSION['_old'] ?? [];
    unset($_SESSION['_old']);

    return is_array($old) ? $old : [];
}

/* -------------------------------------------------------------------------
 * Strings, slugs, dates
 * ---------------------------------------------------------------------- */

function slugify(string $text, string $fallback = 'item'): string
{
    $text = trim($text);
    if ($text === '') {
        return $fallback;
    }

    if (function_exists('iconv')) {
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($translit) && $translit !== '') {
            $text = $translit;
        }
    }

    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    $text = preg_replace('/-{2,}/', '-', $text) ?? '';
    $text = mb_substr($text, 0, 80);

    return trim($text, '-') !== '' ? trim($text, '-') : $fallback;
}

/** Ensure a slug is unique inside a table/column. */
function unique_slug(PDO $pdo, string $table, string $column, string $slug, ?int $ignoreId = null): string
{
    $allowed = ['projects' => 'projects', 'project_categories' => 'project_categories', 'technologies' => 'technologies'];
    if (!isset($allowed[$table]) || !in_array($column, ['slug'], true)) {
        throw new InvalidArgumentException('Unsupported table for slug generation.');
    }

    $base = $slug !== '' ? $slug : 'item';
    $candidate = $base;
    $i = 2;

    $sql = 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' = :slug';
    if ($ignoreId !== null) {
        $sql .= ' AND id <> :id';
    }
    $stmt = $pdo->prepare($sql);

    while (true) {
        $params = [':slug' => $candidate];
        if ($ignoreId !== null) {
            $params[':id'] = $ignoreId;
        }
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() === 0) {
            return $candidate;
        }
        $candidate = $base . '-' . $i;
        $i++;
        if ($i > 200) {
            return $base . '-' . bin2hex(random_bytes(3));
        }
    }
}

function truncate_text(string $text, int $limit = 160, string $suffix = 'â€¦'): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    $cut = mb_substr($text, 0, max(1, $limit - mb_strlen($suffix)));
    $lastSpace = mb_strrpos($cut, ' ');
    if ($lastSpace !== false && $lastSpace > (int) ($limit * 0.6)) {
        $cut = mb_substr($cut, 0, $lastSpace);
    }

    return rtrim($cut, " ,.;:") . $suffix;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        if (mb_strlen($letters) >= 2) {
            break;
        }
    }

    return $letters !== '' ? $letters : 'TA';
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

/** Convert a stored UTC timestamp into the configured timezone. */
function local_datetime(string $utc): ?DateTimeImmutable
{
    if (trim($utc) === '') {
        return null;
    }
    try {
        $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));

        return $date->setTimezone(new DateTimeZone((string) config('timezone', 'Africa/Lagos')));
    } catch (Throwable) {
        return null;
    }
}

function format_datetime(?string $utc, string $format = 'M j, Y \a\t H:i'): string
{
    if ($utc === null || trim($utc) === '') {
        return '';
    }
    $date = local_datetime($utc);

    return $date === null ? '' : $date->format($format);
}

function format_date(?string $date, string $format = 'M j, Y'): string
{
    if ($date === null || trim($date) === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($date))->format($format);
    } catch (Throwable) {
        return '';
    }
}

function time_ago(?string $utc): string
{
    $date = $utc === null ? null : local_datetime($utc);
    if ($date === null) {
        return '';
    }

    $seconds = time() - $date->getTimestamp();
    if ($seconds < 60) {
        return 'just now';
    }

    $units = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];

    foreach ($units as [$label, $length]) {
        if ($seconds >= $length) {
            $value = (int) floor($seconds / $length);

            return $value . ' ' . $label . ($value === 1 ? '' : 's') . ' ago';
        }
    }

    return 'just now';
}

function human_filesize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0) . ' KB';
    }

    return $bytes . ' B';
}

/* -------------------------------------------------------------------------
 * URLs in user-supplied data
 * ---------------------------------------------------------------------- */

/** Only http/https URLs may ever reach an href attribute. */
function is_safe_url(?string $url): bool
{
    $url = trim((string) $url);
    if ($url === '') {
        return false;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return false;
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));

    return in_array($scheme, ['http', 'https'], true);
}

/** Escaped, safe external URL â€” empty string when invalid. */
function safe_href(?string $url): string
{
    return is_safe_url($url) ? e(trim((string) $url)) : '';
}

/* -------------------------------------------------------------------------
 * Structured data + page state
 * ---------------------------------------------------------------------- */

function json_ld(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

    return $json === false ? '' : '<script type="application/ld+json">' . $json . '</script>';
}

/* -------------------------------------------------------------------------
 * Errors
 * ---------------------------------------------------------------------- */

/**
 * Render a branded error page and stop. Falls back to minimal HTML when the
 * templates are not available (e.g. a failure very early in bootstrap).
 */
function abort_page(int $status, string $title, string $message, string $suggestion = ''): void
{
    http_response_code($status);

    $template = APP_ROOT . '/templates/error.php';
    if (is_file($template) && function_exists('site_header')) {
        $error = [
            'status'     => $status,
            'title'      => $title,
            'message'    => $message,
            'suggestion' => $suggestion,
        ];
        include $template;
        exit;
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>' . e($status . ' â€” ' . $title) . '</title></head>'
        . '<body style="background:#050807;color:#f2f6f3;font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0">'
        . '<main style="max-width:34rem;padding:2rem;text-align:center">'
        . '<p style="color:#08d665;letter-spacing:.2em;font-size:.75rem">ERROR ' . e((string) $status) . '</p>'
        . '<h1 style="font-size:2rem;margin:.5rem 0 1rem">' . e($title) . '</h1>'
        . '<p style="color:#9aa8a0;line-height:1.6">' . e($message) . '</p>'
        . '<p><a href="' . e(home_url()) . '" style="color:#08d665">Back to home</a></p>'
        . '</main></body></html>';
    exit;
}

/**
 * Abort with a JSON body when the caller expects JSON, otherwise a page.
 */
function abort_request(int $status, string $title, string $message): void
{
    if (is_ajax()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_SLASHES);
        exit;
    }

    abort_page($status, $title, $message);
}

/* -------------------------------------------------------------------------
 * Small view helpers
 * ---------------------------------------------------------------------- */

/** Return ' is-active' when the given script matches the current one. */
function active_class(string $script): string
{
    return current_script() === $script ? ' is-active' : '';
}

/** Split a textarea value into paragraphs for display. */
function paragraphs(string $text): string
{
    $blocks = preg_split('/(?:\r\n|\r|\n){2,}/u', trim($text)) ?: [];
    $html = '';
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }
        $html .= '<p>' . nl2br(e($block), false) . '</p>';
    }

    return $html;
}

/** Bump a view counter, used only for marketing-free "popular projects". */
function reading_time(string $text): int
{
    $words = str_word_count(strip_tags($text));

    return max(1, (int) ceil($words / 200));
}
