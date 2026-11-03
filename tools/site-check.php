<?php
/**
 * Structural, SEO, link and stylesheet checks.
 *
 *   php tools/site-check.php http://127.0.0.1:8080
 *
 * Complements http-tests.php: it parses the rendered HTML instead of asserting
 * behaviour, so markup mistakes (duplicate IDs, missing alt text, broken local
 * links, class names with no CSS rule) are caught too.
 *
 * Development only — tools/ is blocked in production.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$root = dirname(__DIR__);

$passed = 0;
$failed = 0;
$warnings = [];

function ok(string $label): void
{
    global $passed;
    $passed++;
    echo "  [PASS] " . $label . "\n";
}

function bad(string $label, string $detail = ''): void
{
    global $failed;
    $failed++;
    echo "  [FAIL] " . $label . ($detail !== '' ? " — " . $detail : '') . "\n";
}

function check(bool $condition, string $label, string $detail = ''): void
{
    $condition ? ok($label) : bad($label, $detail);
}

function warn(string $message): void
{
    global $warnings;
    $warnings[] = $message;
}

/**
 * @param array<string, mixed> $fields
 * @return array{status:int, body:string, headers:array<string,string>}
 */
function fetch(string $url, string $method = 'GET', array $fields = []): array
{
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_USERAGENT      => 'TijaniDev-SiteCheck/1.0',
        CURLOPT_ENCODING       => '',
    ];
    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $fields;
    }
    curl_setopt_array($ch, $options);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headers = [];
    foreach (preg_split('/\r\n/', substr($raw, 0, $size)) ?: [] as $line) {
        if (str_contains($line, ':')) {
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }
    }

    return ['status' => $status, 'body' => substr($raw, $size), 'headers' => $headers];
}

/** @return array{0:DOMDocument, 1:DOMXPath} */
function parse_dom(string $html): array
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    return [$dom, new DOMXPath($dom)];
}

echo "Structural checks against " . $base . "\n";

/* -------------------------------------------------------------------------
 * Pages to inspect
 * ---------------------------------------------------------------------- */

$pages = [
    '/'                       => 'Apatira Tijani Adewale',
    '/projects'               => 'Work that solves',
    '/projects?category=website' => 'Website',
    '/project/portfolio-website' => 'Portfolio Website',
    '/project/gift-card-storefront' => 'Gift Card Storefront',
    '/thank-you'              => 'Thank you',
    '/admin/login.php'        => 'Sign in',
    '/does-not-exist'         => 'doesn\'t exist',
];

$css = '';
foreach (['assets/css/premium.css', 'assets/css/admin.css'] as $file) {
    if (!is_file($root . '/' . $file)) {
        bad('stylesheet exists: ' . $file);
        continue;
    }
    $css .= file_get_contents($root . '/' . $file) . "\n";
}

$allInternalLinks = [];

foreach ($pages as $path => $expected) {
    $url = $base . $path;
    $response = fetch($url);

    echo "\n" . $path . "\n" . str_repeat('-', strlen($path)) . "\n";

    if ($path === '/does-not-exist') {
        check($response['status'] === 404, '404 status returned', 'status ' . $response['status']);
    } else {
        check($response['status'] === 200, 'HTTP 200', 'status ' . $response['status']);
    }

    $body = $response['body'];
    check(!preg_match('/Fatal error|Warning:|Notice:|Deprecated:/', $body), 'no PHP errors in output');
    check(str_contains($body, $expected), 'expected content present', 'missing: ' . $expected);

    [$dom, $xpath] = parse_dom($body);

    // Title + meta description + canonical.
    $titles = $xpath->query('//head/title');
    $title = $titles->length > 0 ? trim($titles->item(0)->textContent) : '';
    check($title !== '' && mb_strlen($title) >= 10 && mb_strlen($title) <= 75, 'title length sane', mb_strlen($title) . ' chars: ' . $title);

    $description = '';
    foreach ($xpath->query('//head/meta[@name="description"]') as $node) {
        $description = trim($node->getAttribute('content'));
    }
    check($description !== '' && mb_strlen($description) >= 50 && mb_strlen($description) <= 170, 'meta description sane', mb_strlen($description) . ' chars');

    $canonical = '';
    foreach ($xpath->query('//head/link[@rel="canonical"]') as $node) {
        $canonical = $node->getAttribute('href');
    }
    check(str_starts_with($canonical, 'http'), 'canonical URL absolute', $canonical);

    // Headings.
    $h1Count = $xpath->query('//h1')->length;
    check($h1Count === 1, 'exactly one h1', $h1Count . ' found');

    // Duplicate IDs.
    $ids = [];
    $duplicates = [];
    foreach ($xpath->query('//*[@id]') as $node) {
        $id = $node->getAttribute('id');
        if (isset($ids[$id])) {
            $duplicates[] = $id;
        }
        $ids[$id] = true;
    }
    check($duplicates === [], 'no duplicate ids', implode(', ', array_unique($duplicates)));

    // Images need alt + dimensions where they are content.
    $missingAlt = [];
    $missingDimensions = [];
    foreach ($xpath->query('//img') as $img) {
        $src = $img->getAttribute('src');
        if (!$img->hasAttribute('alt')) {
            $missingAlt[] = $src;
        }
        if (!$img->hasAttribute('width') || !$img->hasAttribute('height')) {
            $missingDimensions[] = $src;
        }
    }
    check($missingAlt === [], 'all images have alt text', implode(', ', $missingAlt));
    check($missingDimensions === [], 'content images declare dimensions', implode(', ', array_slice($missingDimensions, 0, 3)));

    // Labels must point at real fields.
    $brokenLabels = [];
    foreach ($xpath->query('//label[@for]') as $label) {
        $target = $label->getAttribute('for');
        if ($xpath->query('//*[@id="' . $target . '"]')->length === 0) {
            $brokenLabels[] = $target;
        }
    }
    check($brokenLabels === [], 'labels point at existing fields', implode(', ', $brokenLabels));

    // Forms: POST forms must carry a CSRF token.
    $missingCsrf = 0;
    foreach ($xpath->query('//form[@method]') as $form) {
        if (strtolower($form->getAttribute('method')) !== 'post') {
            continue;
        }
        if ($xpath->query('.//input[@name="csrf_token"]', $form)->length === 0) {
            $missingCsrf++;
        }
    }
    check($missingCsrf === 0, 'every POST form has a CSRF field', $missingCsrf . ' without');

    // JSON-LD must be valid JSON.
    $jsonErrors = 0;
    foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
        json_decode(trim($script->textContent), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonErrors++;
        }
    }
    check($jsonErrors === 0, 'structured data is valid JSON', $jsonErrors . ' invalid block(s)');

    // Collect internal links + assets.
    foreach ($xpath->query('//a[@href]') as $anchor) {
        $href = trim($anchor->getAttribute('href'));
        if ($href === '' || str_starts_with($href, '#') || preg_match('#^(mailto:|tel:|javascript:)#i', $href)) {
            continue;
        }
        if (preg_match('#^https?://#i', $href)) {
            if (!str_starts_with($href, $base)) {
                continue;
            }
            $href = (string) parse_url($href, PHP_URL_PATH);
        }
        $allInternalLinks[$href] = true;
    }

    foreach ($xpath->query('//link[@rel="stylesheet"][@href] | //script[@src] | //img[@src]') as $node) {
        $ref = $node->hasAttribute('href') ? $node->getAttribute('href') : $node->getAttribute('src');
        if ($ref === '' || preg_match('#^(https?:)?//#i', $ref)) {
            continue;
        }
        $asset = fetch($base . '/' . ltrim((string) parse_url($ref, PHP_URL_PATH), '/'));
        check($asset['status'] === 200, 'asset loads: ' . parse_url($ref, PHP_URL_PATH), 'status ' . $asset['status']);
    }

    // Class names should have a CSS rule (catches typos).
    $unknown = [];
    foreach ($xpath->query('//*[@class]') as $node) {
        foreach (preg_split('/\s+/', trim($node->getAttribute('class'))) ?: [] as $className) {
            if ($className === '' || preg_match('/^(is-|js-|has-js)/', $className)) {
                continue;
            }
            if (!str_contains($css, '.' . $className)) {
                $unknown[$className] = true;
            }
        }
    }
    foreach (array_keys($unknown) as $className) {
        warn($path . ': class without CSS rule → .' . $className);
    }
}

/* -------------------------------------------------------------------------
 * Link crawl
 * ---------------------------------------------------------------------- */

echo "\ninternal links\n" . str_repeat('-', 14) . "\n";

$skip = ['/admin/logout.php', '/admin/backup.php', '/contact.php', '/thank-you.php'];
$broken = 0;
$checked = 0;
foreach (array_keys($allInternalLinks) as $link) {
    if (in_array($link, $skip, true)) {
        continue;
    }
    $checked++;
    $response = fetch($base . $link);
    if ($response['status'] >= 400) {
        $broken++;
        echo '  [FAIL] ' . $link . ' → ' . $response['status'] . "\n";
    }
}
check($broken === 0, $checked . ' internal links resolve');
check(!isset($allInternalLinks['javascript:void(0)']), 'no javascript: links');

/* -------------------------------------------------------------------------
 * CSS syntax
 * ---------------------------------------------------------------------- */

echo "\nstylesheet syntax\n" . str_repeat('-', 17) . "\n";

foreach (['assets/css/premium.css', 'assets/css/admin.css'] as $file) {
    $content = (string) @file_get_contents($root . '/' . $file);
    if ($content === '') {
        bad($file . ' readable');
        continue;
    }

    $stripped = preg_replace('#/\*.*?\*/#s', '', $content) ?? '';
    $stripped = preg_replace('/"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'/s', '""', $stripped) ?? '';
    $open = substr_count($stripped, '{');
    $close = substr_count($stripped, '}');
    check($open === $close, $file . ' braces balanced', $open . ' open / ' . $close . ' close');

    $depth = 0;
    $maxDepth = 0;
    for ($i = 0, $len = strlen($stripped); $i < $len; $i++) {
        if ($stripped[$i] === '{') {
            $depth++;
            $maxDepth = max($maxDepth, $depth);
        } elseif ($stripped[$i] === '}') {
            $depth--;
            if ($depth < 0) {
                bad($file . ' has an unmatched closing brace');
                break;
            }
        }
    }
    check($depth === 0, $file . ' rules close correctly');
    check($maxDepth < 6, $file . ' selector nesting sane', 'depth ' . $maxDepth);

    if (preg_match_all('/url\(([^)]+)\)/', $stripped, $matches)) {
        foreach (array_unique($matches[1]) as $ref) {
            $ref = trim($ref, " \t\n\r\0\x0B\"'");
            if ($ref === '' || preg_match('#^(data:|https?:|//|#)#i', $ref)) {
                continue;
            }
            $path = realpath(dirname($root . '/' . $file) . '/' . $ref);
            if ($path === false || !is_file($path)) {
                bad($file . ' references a missing file: ' . $ref);
            }
        }
    }
}

/* -------------------------------------------------------------------------
 * Summary
 * ---------------------------------------------------------------------- */

if ($warnings !== []) {
    echo "\nwarnings (" . count($warnings) . ")\n" . str_repeat('-', 20) . "\n";
    foreach (array_slice(array_unique($warnings), 0, 40) as $warning) {
        echo '  · ' . $warning . "\n";
    }
}

echo "\n" . str_repeat('=', 56) . "\n";
echo 'PASSED: ' . $passed . '   FAILED: ' . $failed . '   WARNINGS: ' . count(array_unique($warnings)) . "\n";
echo str_repeat('=', 56) . "\n";

exit($failed === 0 ? 0 : 1);
