<?php
/**
 * End-to-end HTTP test suite.
 *
 *   php tools/http-tests.php http://127.0.0.1:8081
 *
 * Runs against a throwaway database (set PORTFOLIO_STORAGE + capture transport
 * for both the server and this script). It creates, edits, publishes, uploads
 * to and deletes a test project, exercises the contact form, checks every
 * authentication and CSRF gate, and verifies the database afterwards.
 *
 * Development only — tools/ is blocked in production.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8081', '/');
$fixtures = __DIR__ . '/fixtures';

/* -------------------------------------------------------------------------
 * Tiny assertion harness
 * ---------------------------------------------------------------------- */

$passed = 0;
$failed = 0;
$group = '';

function group(string $name): void
{
    global $group;
    $group = $name;
    echo "\n" . strtoupper($name) . "\n" . str_repeat('-', strlen($name)) . "\n";
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  [PASS] " . $label . "\n";

        return;
    }

    $failed++;
    echo "  [FAIL] " . $label . ($detail !== '' ? "\n         → " . $detail : '') . "\n";
}

/**
 * Markup-level assertions for a rendered page: unique ids, working labels,
 * CSRF on POST forms and alt text on images.
 */
function structure(string $label, string $html): void
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $ids = [];
    $duplicates = [];
    foreach ($xpath->query('//*[@id]') as $node) {
        $id = $node->getAttribute('id');
        if (isset($ids[$id])) {
            $duplicates[] = $id;
        }
        $ids[$id] = true;
    }
    check($label . ': unique element ids', $duplicates === [], implode(', ', array_unique($duplicates)));

    $broken = [];
    foreach ($xpath->query('//label[@for]') as $labelNode) {
        $target = $labelNode->getAttribute('for');
        if ($xpath->query('//*[@id="' . $target . '"]')->length === 0) {
            $broken[] = $target;
        }
    }
    check($label . ': labels resolve to fields', $broken === [], implode(', ', $broken));

    $missingCsrf = 0;
    foreach ($xpath->query('//form[@method]') as $form) {
        if (strtolower($form->getAttribute('method')) !== 'post') {
            continue;
        }
        if ($xpath->query('.//input[@name="csrf_token"]', $form)->length === 0) {
            $missingCsrf++;
        }
    }
    check($label . ': every POST form has CSRF', $missingCsrf === 0, $missingCsrf . ' missing');

    $missingAlt = 0;
    foreach ($xpath->query('//img') as $img) {
        if (!$img->hasAttribute('alt')) {
            $missingAlt++;
        }
    }
    check($label . ': images have alt text', $missingAlt === 0, $missingAlt . ' missing');
}

/* -------------------------------------------------------------------------
 * HTTP client with its own cookie jar
 * ---------------------------------------------------------------------- */

final class Http
{
    private string $jar;

    public function __construct(private string $base)
    {
        $jar = tempnam(sys_get_temp_dir(), 'tj-jar-');
        $this->jar = is_string($jar) ? $jar : sys_get_temp_dir() . '/tj-jar';
        @touch($this->jar);
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    /**
     * @param array<string, mixed> $fields
     * @return array{status:int, headers:array<string,string>, body:string, location:string}
     */
    public function request(string $method, string $path, array $fields = []): array
    {
        $url = preg_match('#^https?://#', $path) === 1 ? $path : $this->base . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Cannot initialise cURL.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_TIMEOUT        => 40,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'TijaniDev-TestSuite/1.0',
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $fields;
        } else {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr((string) $response, 0, $headerSize);
        $body = substr((string) $response, $headerSize);

        $headers = [];
        foreach (preg_split('/\r\n/', $rawHeaders) ?: [] as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }

        return [
            'status'   => $status,
            'headers'  => $headers,
            'body'     => $body,
            'location' => $headers['location'] ?? '',
        ];
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /** @param array<string, mixed> $fields */
    public function post(string $path, array $fields): array
    {
        return $this->request('POST', $path, $fields);
    }

    /** Follow a redirect once and return the resulting response. */
    public function follow(array $response): array
    {
        $location = $response['location'];
        if ($location === '') {
            return $response;
        }
        $location = (string) parse_url($location, PHP_URL_PATH) . (
            parse_url($location, PHP_URL_QUERY) ? '?' . parse_url($location, PHP_URL_QUERY) : ''
        );

        return $this->get($location === '' ? '/' : $location);
    }
}

function csrf_from(string $html): string
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $matches) === 1) {
        return $matches[1];
    }

    return '';
}

function form_token_from(string $html): string
{
    if (preg_match('/name="form_token"\s+value="([^"]+)"/', $html, $matches) === 1) {
        return $matches[1];
    }

    return '';
}

/* -------------------------------------------------------------------------
 * Setup
 * ---------------------------------------------------------------------- */

$anonymous = new Http($base);
$admin = new Http($base);
$suffix = bin2hex(random_bytes(3));
$slug = 'test-project-' . $suffix;
$password = 'TijaniTest' . random_int(100000, 999999);
$categoryId = (int) db_value('SELECT id FROM project_categories ORDER BY sort_order LIMIT 1');
$techIds = array_map(static fn (array $row): int => (int) $row['id'], db_all('SELECT id FROM technologies ORDER BY id LIMIT 2'));

echo 'Testing ' . $base . ' against ' . Database::databasePath() . "\n";

/* -------------------------------------------------------------------------
 * 1. Public site
 * ---------------------------------------------------------------------- */

group('public pages');

$home = $anonymous->get('/');
check('home responds 200', $home['status'] === 200, 'status ' . $home['status']);
check('home contains the brand', str_contains($home['body'], 'Apatira Tijani Adewale'));
check('home contains the tagline', str_contains($home['body'], 'move businesses forward'));
check('home lists project cards', substr_count($home['body'], 'project-card__link') >= 3);
check('home has canonical + OG tags', str_contains($home['body'], 'rel="canonical"') && str_contains($home['body'], 'og:title'));
check('home has Person structured data', str_contains($home['body'], '"@type":"Person"'));
check('home has no PHP notices', preg_match('/Warning:|Fatal error|Notice:|Deprecated:/', $home['body']) !== 1);

$projectsPage = $anonymous->get('/projects');
check('/projects 200', $projectsPage['status'] === 200);
check('projects page shows seeded work', str_contains($projectsPage['body'], 'Gift Card Storefront'));

$detail = $anonymous->get('/project/portfolio-website');
check('project detail 200', $detail['status'] === 200);
check('detail shows problem section', str_contains($detail['body'], 'The problem'));
check('detail has CreativeWork schema', str_contains($detail['body'], '"@type":"CreativeWork"'));

$missing = $anonymous->get('/project/definitely-not-a-project');
check('unknown project returns 404', $missing['status'] === 404, 'status ' . $missing['status']);
check('404 page is branded', str_contains($missing['body'], 'This page doesn\'t exist'));

$sitemap = $anonymous->get('/sitemap.xml');
check('sitemap 200 + xml', $sitemap['status'] === 200 && str_contains($sitemap['headers']['content-type'] ?? '', 'xml'));
check('sitemap includes projects', str_contains($sitemap['body'], '/project/portfolio-website'));

$robots = $anonymous->get('/robots.txt');
check('robots.txt served', $robots['status'] === 200 && str_contains($robots['body'], 'Sitemap:'));

$thankYou = $anonymous->get('/thank-you');
check('thank-you page 200 + noindex', $thankYou['status'] === 200 && str_contains($thankYou['body'], 'noindex'));

group('private paths are not served');
foreach (['/includes/bootstrap.php', '/includes/config.php', '/storage/portfolio.sqlite', '/templates/home.php', '/admin/includes/layout.php'] as $path) {
    $response = $anonymous->get($path);
    check($path . ' blocked', in_array($response['status'], [403, 404], true), 'status ' . $response['status']);
}

/* -------------------------------------------------------------------------
 * 2. Contact form
 * ---------------------------------------------------------------------- */

group('contact form');

$getContact = $anonymous->get('/contact.php');
check('GET contact redirects to the section', $getContact['status'] === 302 && str_contains($getContact['location'], '#contact'));

$noCsrf = $anonymous->post('/contact.php', ['name' => 'Test', 'email' => 'x@example.test', 'subject' => 'No csrf', 'message' => str_repeat('a', 30)]);
check('POST without CSRF → 419', $noCsrf['status'] === 419, 'status ' . $noCsrf['status']);
check('419 page explains the problem', str_contains($noCsrf['body'], 'Session expired'));

// Invalid email: passes spam checks (after the delay) then fails validation.
$page = $anonymous->get('/');
$token = form_token_from($page['body']);
$csrf = csrf_from($page['body']);
sleep(3);
$invalid = $anonymous->post('/contact.php', [
    'csrf_token' => $csrf,
    'form_token' => $token,
    'name'       => 'Validation Bot',
    'email'      => 'not-an-email',
    'subject'    => 'Invalid email test',
    'message'    => 'This message is long enough to pass the minimum length rule.',
]);
check('invalid email is rejected', $invalid['status'] === 302 && str_contains($invalid['location'], '#contact'), $invalid['location']);
$invalidFollow = $anonymous->follow($invalid);
check('validation error is shown back on the page', str_contains($invalidFollow['body'], 'does not look valid'));
$storedInvalid = (int) db_value("SELECT COUNT(*) FROM contact_messages WHERE subject = 'Invalid email test'");
check('invalid submission is not stored', $storedInvalid === 0);

// Honeypot.
$page = $anonymous->get('/');
$csrf = csrf_from($page['body']);
$token = form_token_from($page['body']);
sleep(3);
$spam = $anonymous->post('/contact.php', [
    'csrf_token' => $csrf,
    'form_token' => $token,
    'website'    => 'http://spam.example',
    'name'       => 'Spam Bot',
    'email'      => 'spam@example.test',
    'subject'    => 'Honeypot',
    'message'    => 'Buy cheap things at a website you should not visit.',
]);
check('honeypot submission looks successful', $spam['status'] === 302 && str_contains($spam['location'], 'thank-you'));
check('honeypot message stored as spam', (int) db_value("SELECT COUNT(*) FROM contact_messages WHERE mail_status = 'spam'") >= 1);

// Too fast.
$page = $anonymous->get('/');
$csrf = csrf_from($page['body']);
$token = form_token_from($page['body']);
$fast = $anonymous->post('/contact.php', [
    'csrf_token' => $csrf,
    'form_token' => $token,
    'name'       => 'Fast Bot',
    'email'      => 'fast@example.test',
    'subject'    => 'Too fast',
    'message'    => 'Submitted instantly, which no human can reasonably do.',
]);
check('instant submission is treated as spam', $fast['status'] === 302 && str_contains($fast['location'], 'thank-you'));

// Valid submission (after the minimum delay).
$page = $anonymous->get('/');
$csrf = csrf_from($page['body']);
$token = form_token_from($page['body']);
sleep(3);
$validEmail = 'tester+' . $suffix . '@example.test';
$valid = $anonymous->post('/contact.php', [
    'csrf_token' => $csrf,
    'form_token' => $token,
    'name'       => 'Test Visitor',
    'email'      => $validEmail,
    'subject'    => 'Valid enquiry ' . $suffix,
    'message'    => 'Hello, I would like to discuss a Django application for my business.',
]);
check('valid submission redirects to thank-you', $valid['status'] === 302 && str_contains($valid['location'], 'thank-you'), $valid['location']);
$stored = db_one('SELECT * FROM contact_messages WHERE email = :email ORDER BY id DESC LIMIT 1', [':email' => $validEmail]);
check('message stored in database', is_array($stored));
check('mail delivery reported as sent (capture transport)', is_array($stored) && $stored['mail_status'] === 'sent', (string) ($stored['mail_status'] ?? 'missing'));
check('message is linked to the client IP', is_array($stored) && $stored['ip_address'] !== '');

// Rate limit (3 messages per hour, we already stored spam + spam + valid).
$page = $anonymous->get('/');
$csrf = csrf_from($page['body']);
$token = form_token_from($page['body']);
sleep(3);
$limited = $anonymous->post('/contact.php', [
    'csrf_token' => $csrf,
    'form_token' => $token,
    'name'       => 'Persistent Visitor',
    'email'      => 'limited@example.test',
    'subject'    => 'Rate limit test',
    'message'    => 'This is the fourth attempt within the allowed window, so it should be refused.',
]);
$limitedFollow = $anonymous->follow($limited);
check('fourth submission in an hour is throttled', str_contains($limitedFollow['body'], 'several messages in a short time'));

/* -------------------------------------------------------------------------
 * 3. Admin authentication
 * ---------------------------------------------------------------------- */

group('authentication');

$adminHome = $admin->get('/admin/');
check('dashboard redirects when signed out', $adminHome['status'] === 302 && str_contains($adminHome['location'], 'login.php'));

$login = $admin->get('/admin/login.php');
check('login page renders', $login['status'] === 200 && str_contains($login['body'], 'Restricted area'));
$loginCsrf = csrf_from($login['body']);
check('login form has CSRF token', $loginCsrf !== '');

$wrong = $admin->post('/admin/login.php', ['csrf_token' => $loginCsrf, 'username' => 'admin', 'password' => 'wrong-password']);
check('wrong password is rejected', $wrong['status'] === 200 && str_contains($wrong['body'], 'do not match our records'));

$login = $admin->get('/admin/login.php');
$loginCsrf = csrf_from($login['body']);
$signedIn = $admin->post('/admin/login.php', ['csrf_token' => $loginCsrf, 'username' => 'admin', 'password' => 'admin']);
check('temporary admin/admin works', $signedIn['status'] === 302 && str_contains($signedIn['location'], 'change-password.php'), $signedIn['location']);

$blocked = $admin->get('/admin/projects.php');
check('forced password change blocks other admin pages', $blocked['status'] === 302 && str_contains($blocked['location'], 'change-password.php'));

$change = $admin->get('/admin/change-password.php');
check('change-password page renders', $change['status'] === 200);
$changeCsrf = csrf_from($change['body']);

$weak = $admin->post('/admin/change-password.php', [
    'csrf_token'       => $changeCsrf,
    'current_password' => 'admin',
    'new_password'     => 'short',
    'confirm_password' => 'short',
]);
check('weak password rejected', $weak['status'] === 200 && str_contains($weak['body'], 'at least 10 characters'));

$change = $admin->get('/admin/change-password.php');
$changeCsrf = csrf_from($change['body']);
$strong = $admin->post('/admin/change-password.php', [
    'csrf_token'       => $changeCsrf,
    'current_password' => 'admin',
    'new_password'     => $password,
    'confirm_password' => $password,
]);
check('password change accepted', $strong['status'] === 302 && str_contains($strong['location'], 'index.php'), $strong['location']);

$dashboard = $admin->get('/admin/index.php');
check('dashboard renders after password change', $dashboard['status'] === 200 && str_contains($dashboard['body'], 'Dashboard'));
check('dashboard shows stats', str_contains($dashboard['body'], 'Total projects'));
structure('dashboard', $dashboard['body']);
check('must-change flag cleared', (int) db_value('SELECT must_change_password FROM admin_users WHERE username = :u', [':u' => 'admin']) === 0);

$system = $admin->get('/admin/system.php');
check('system page renders', $system['status'] === 200 && str_contains($system['body'], 'PDO SQLite'));

$adminProjects = $admin->get('/admin/projects.php');
check('projects list renders', $adminProjects['status'] === 200 && str_contains($adminProjects['body'], 'All projects'));
structure('projects list', $adminProjects['body']);

/* -------------------------------------------------------------------------
 * 4. Project lifecycle
 * ---------------------------------------------------------------------- */

group('projects');

$newForm = $admin->get('/admin/project-form.php');
check('new project form renders', $newForm['status'] === 200 && str_contains($newForm['body'], 'New project'));
structure('project form', $newForm['body']);
$formCsrf = csrf_from($newForm['body']);

$createPayload = [
    'csrf_token'        => $formCsrf,
    'id'                => '0',
    'title'             => 'Test Project ' . $suffix,
    'slug'              => $slug,
    'short_description' => 'A test project created by the automated suite to verify the whole workflow.',
    'description'       => "This project exists to test the admin workflow end to end.\n\nIt is created, edited, illustrated, published and deleted by tools/http-tests.php.",
    'problem'           => 'Automated tests need a real project to exercise the publishing pipeline.',
    'solution'          => 'Create one, drive it through every state, then delete it.',
    'category_id'       => (string) $categoryId,
    'status'            => 'draft',
    'seo_title'         => 'Test project SEO title',
    'seo_description'   => 'Test project SEO description for the automated suite.',
    'live_url'          => '',
    'github_url'        => '',
    'completion_date'   => '',
];

// cURL needs explicit bracket keys for repeated multipart fields.
$techFields = [];
foreach ($techIds as $index => $techId) {
    $techFields['technologies[' . $index . ']'] = (string) $techId;
}
$createPayload += $techFields;
$created = $admin->post('/admin/project-form.php', $createPayload);
check('project created (redirect to edit)', $created['status'] === 302 && str_contains($created['location'], 'project-form.php?id='), $created['location']);
$projectId = 0;
if (preg_match('/id=(\d+)/', $created['location'], $matches) === 1) {
    $projectId = (int) $matches[1];
}
check('project id returned', $projectId > 0);
$row = db_one('SELECT * FROM projects WHERE id = :id', [':id' => $projectId]);
check('project row stored as draft', is_array($row) && $row['status'] === 'draft');
check('project slug stored', is_array($row) && $row['slug'] === $slug);
check('project technologies linked', (int) db_value('SELECT COUNT(*) FROM project_technologies WHERE project_id = :id', [':id' => $projectId]) === count($techIds));

$draftPublic = $anonymous->get('/project/' . $slug);
check('draft is not public', $draftPublic['status'] === 404, 'status ' . $draftPublic['status']);
$draftPreview = $admin->get('/project/' . $slug . '?preview=1');
check('signed-in preview of draft works', $draftPreview['status'] === 200 && str_contains($draftPreview['body'], 'Draft preview'));
$sitemapBefore = $anonymous->get('/sitemap.xml');
check('draft kept out of the sitemap', !str_contains($sitemapBefore['body'], $slug));

// Upload a cover image.
$editForm = $admin->get('/admin/project-form.php?id=' . $projectId);
$editCsrf = csrf_from($editForm['body']);
$editPayload = $createPayload;
$editPayload['csrf_token'] = $editCsrf;
$editPayload['id'] = (string) $projectId;
$editPayload['cover'] = new CURLFile($fixtures . '/cover.png', 'image/png', 'cover.png');
$coverUpload = $admin->post('/admin/project-form.php', $editPayload);
check('cover upload accepted', $coverUpload['status'] === 302, 'status ' . $coverUpload['status']);
$row = db_one('SELECT cover_image FROM projects WHERE id = :id', [':id' => $projectId]);
$coverPath = (string) ($row['cover_image'] ?? '');
check('cover path saved', $coverPath !== '' && str_starts_with($coverPath, 'assets/uploads/projects/'));
check('cover file exists on disk', $coverPath !== '' && is_file(APP_ROOT . '/' . $coverPath));

// Reject a PHP script disguised as an image.
$editForm = $admin->get('/admin/project-form.php?id=' . $projectId);
$badPayload = $createPayload;
$badPayload['csrf_token'] = csrf_from($editForm['body']);
$badPayload['id'] = (string) $projectId;
$badPayload['cover'] = new CURLFile($fixtures . '/evil.php.jpg', 'image/jpeg', 'evil.php.jpg');
$badUpload = $admin->post('/admin/project-form.php', $badPayload);
$badFollow = $admin->follow($badUpload);
check('executable disguised as an image is rejected', str_contains($badFollow['body'], 'not a readable image') || str_contains($badFollow['body'], 'Only JPG'));
$rowAfter = db_one('SELECT cover_image FROM projects WHERE id = :id', [':id' => $projectId]);
check('existing cover kept after rejected upload', (string) $rowAfter['cover_image'] === $coverPath);

// Screenshot uploads.
$media = $admin->get('/admin/project-media.php?id=' . $projectId);
check('media page renders', $media['status'] === 200);
$mediaCsrf = csrf_from($media['body']);
$upload = $admin->post('/admin/project-media.php', [
    'csrf_token' => $mediaCsrf,
    'project_id' => (string) $projectId,
    'action'     => 'upload',
    'caption'    => 'Test screenshot',
    'screenshots[0]' => new CURLFile($fixtures . '/shot-1.png', 'image/png', 'shot-1.png'),
    'screenshots[1]' => new CURLFile($fixtures . '/shot-2.jpg', 'image/jpeg', 'shot-2.jpg'),
    'screenshots[2]' => new CURLFile($fixtures . '/shot-3.webp', 'image/webp', 'shot-3.webp'),
]);
check('three screenshots uploaded', $upload['status'] === 302);
check('screenshot rows saved', (int) db_value('SELECT COUNT(*) FROM project_images WHERE project_id = :id', [':id' => $projectId]) === 3);
$images = db_all('SELECT * FROM project_images WHERE project_id = :id ORDER BY id', [':id' => $projectId]);
$allImagesExist = $images !== [];
foreach ($images as $image) {
    $allImagesExist = $allImagesExist && is_file(APP_ROOT . '/' . $image['file_path']);
}
check('screenshot files exist', $allImagesExist);

$media = $admin->get('/admin/project-media.php?id=' . $projectId);
$mediaCsrf = csrf_from($media['body']);
$fakeUpload = $admin->post('/admin/project-media.php', [
    'csrf_token' => $mediaCsrf,
    'project_id' => (string) $projectId,
    'action'     => 'upload',
    'caption'    => 'Bad file',
    'screenshots[0]' => new CURLFile($fixtures . '/fake.jpg', 'image/jpeg', 'fake.jpg'),
]);
check('non-image upload rejected without a new row', (int) db_value('SELECT COUNT(*) FROM project_images WHERE project_id = :id', [':id' => $projectId]) === 3);
$fakeFollow = $admin->follow($fakeUpload);
check('upload error surfaced to the admin', str_contains($fakeFollow['body'], 'not a readable image') || str_contains($fakeFollow['body'], 'Only JPG'));

$firstImageId = (int) $images[0]['id'];
$firstImagePath = (string) $images[0]['file_path'];
$media = $admin->get('/admin/project-media.php?id=' . $projectId);
$mediaCsrf = csrf_from($media['body']);
$deletedImage = $admin->post('/admin/project-media.php', [
    'csrf_token' => $mediaCsrf,
    'project_id' => (string) $projectId,
    'action'     => 'delete',
    'image_id'   => (string) $firstImageId,
]);
check('screenshot deleted', $deletedImage['status'] === 302 && (int) db_value('SELECT COUNT(*) FROM project_images WHERE project_id = :id', [':id' => $projectId]) === 2);
check('screenshot file removed from disk', !is_file(APP_ROOT . '/' . $firstImagePath));

// Publish.
$list = $admin->get('/admin/projects.php');
$listCsrf = csrf_from($list['body']);
$publish = $admin->post('/admin/project-action.php', ['csrf_token' => $listCsrf, 'id' => (string) $projectId, 'action' => 'publish']);
check('publish action redirects', $publish['status'] === 302);
check('project now published', (string) db_value('SELECT status FROM projects WHERE id = :id', [':id' => $projectId]) === 'published');
$live = $anonymous->get('/project/' . $slug);
check('published project is public', $live['status'] === 200 && str_contains($live['body'], 'Test Project ' . $suffix));
check('live page shows the cover image', str_contains($live['body'], 'project-cover-large'));
$sitemapAfter = $anonymous->get('/sitemap.xml');
check('published project appears in sitemap', str_contains($sitemapAfter['body'], $slug));

// CSRF on state-changing admin endpoints.
$noToken = $admin->post('/admin/project-action.php', ['id' => (string) $projectId, 'action' => 'unpublish']);
check('admin POST without CSRF → 419', $noToken['status'] === 419, 'status ' . $noToken['status']);

// Feature.
$list = $admin->get('/admin/projects.php');
$listCsrf = csrf_from($list['body']);
$feature = $admin->post('/admin/project-action.php', ['csrf_token' => $listCsrf, 'id' => (string) $projectId, 'action' => 'feature']);
check('feature action accepted', $feature['status'] === 302);
check('project marked featured', (int) db_value('SELECT is_featured FROM projects WHERE id = :id', [':id' => $projectId]) === 1);

// Unpublish.
$list = $admin->get('/admin/projects.php');
$listCsrf = csrf_from($list['body']);
$unpublish = $admin->post('/admin/project-action.php', ['csrf_token' => $listCsrf, 'id' => (string) $projectId, 'action' => 'unpublish']);
check('unpublish action accepted', $unpublish['status'] === 302 && (string) db_value('SELECT status FROM projects WHERE id = :id', [':id' => $projectId]) === 'draft');
check('unpublished project is no longer public', $anonymous->get('/project/' . $slug)['status'] === 404);
check('unpublished project removed from sitemap', !str_contains($anonymous->get('/sitemap.xml')['body'], $slug));

// XSS safety: publish a project whose title contains script markup.
$editForm = $admin->get('/admin/project-form.php?id=' . $projectId);
$editCsrf = csrf_from($editForm['body']);
$xssTitle = '<script>alert("xss")</script> Test Project';
$xssPayload = $createPayload;
$xssPayload['csrf_token'] = $editCsrf;
$xssPayload['id'] = (string) $projectId;
$xssPayload['title'] = $xssTitle;
$xssPayload['status'] = 'published';
$edited = $admin->post('/admin/project-form.php', $xssPayload);
check('project edited', $edited['status'] === 302);
$xssPage = $anonymous->get('/project/' . $slug);
check('stored title is HTML-escaped', !str_contains($xssPage['body'], '<script>alert("xss")</script>') && str_contains($xssPage['body'], '&lt;script&gt;'));
$xssAdmin = $admin->get('/admin/projects.php');
check('title escaped in the dashboard too', !str_contains($xssAdmin['body'], '<script>alert("xss")</script>'));

// Delete + cascade + file cleanup.
$list = $admin->get('/admin/projects.php');
$listCsrf = csrf_from($list['body']);
$delete = $admin->post('/admin/project-delete.php', ['csrf_token' => $listCsrf, 'id' => (string) $projectId]);
check('delete accepted', $delete['status'] === 302);
check('project row deleted', db_value('SELECT id FROM projects WHERE id = :id', [':id' => $projectId]) === null);
check('technology links cascaded', (int) db_value('SELECT COUNT(*) FROM project_technologies WHERE project_id = :id', [':id' => $projectId]) === 0);
check('image rows cascaded', (int) db_value('SELECT COUNT(*) FROM project_images WHERE project_id = :id', [':id' => $projectId]) === 0);
check('cover file deleted with the project', !is_file(APP_ROOT . '/' . $coverPath));

/* -------------------------------------------------------------------------
 * 5. Publishing guard for incomplete drafts
 * ---------------------------------------------------------------------- */

group('publish guard');

$incompleteForm = $admin->get('/admin/project-form.php');
$incompleteSlug = 'test-incomplete-' . $suffix;
$incomplete = $admin->post('/admin/project-form.php', [
    'csrf_token'        => csrf_from($incompleteForm['body']),
    'id'                => '0',
    'title'             => 'Incomplete draft ' . $suffix,
    'slug'              => $incompleteSlug,
    'short_description' => '',
    'description'       => '',
    'problem'           => '',
    'solution'          => '',
    'category_id'       => '0',
    'status'            => 'draft',
    'seo_title'         => '',
    'seo_description'   => '',
    'live_url'          => '',
    'github_url'        => '',
    'completion_date'   => '',
]);
$incompleteId = 0;
if (preg_match('/id=(\d+)/', $incomplete['location'], $matches) === 1) {
    $incompleteId = (int) $matches[1];
}
check('incomplete draft can be saved', $incomplete['status'] === 302 && $incompleteId > 0);

$list = $admin->get('/admin/projects.php');
$blockedPublish = $admin->post('/admin/project-action.php', [
    'csrf_token' => csrf_from($list['body']),
    'id'         => (string) $incompleteId,
    'action'     => 'publish',
]);
$blockedFollow = $admin->follow($blockedPublish);
check('incomplete draft cannot be published from the list', str_contains($blockedFollow['body'], 'This draft is incomplete'));
check('incomplete draft stayed a draft', (string) db_value('SELECT status FROM projects WHERE id = :id', [':id' => $incompleteId]) === 'draft');

$list = $admin->get('/admin/projects.php');
$admin->post('/admin/project-delete.php', ['csrf_token' => csrf_from($list['body']), 'id' => (string) $incompleteId]);
check('incomplete draft cleaned up', db_value('SELECT id FROM projects WHERE id = :id', [':id' => $incompleteId]) === null);

/* -------------------------------------------------------------------------
 * 6. Taxonomy management + messages
 * ---------------------------------------------------------------------- */

group('categories and technologies');

$categoriesPage = $admin->get('/admin/categories.php');
check('categories page renders', $categoriesPage['status'] === 200 && str_contains($categoriesPage['body'], 'Project categories'));
structure('categories', $categoriesPage['body']);
$categoriesCsrf = csrf_from($categoriesPage['body']);
$catName = 'Test Category ' . $suffix;
$createdCategory = $admin->post('/admin/categories.php', [
    'csrf_token' => $categoriesCsrf,
    'action'     => 'create',
    'name'       => $catName,
    'slug'       => '',
    'sort_order' => '99',
]);
check('category created', $createdCategory['status'] === 302 && (int) db_value('SELECT COUNT(*) FROM project_categories WHERE name = :name', [':name' => $catName]) === 1);
$catRow = db_one('SELECT * FROM project_categories WHERE name = :name', [':name' => $catName]);
$catEdit = $admin->get('/admin/categories.php?edit=' . (int) $catRow['id']);
check('category edit form loads', $catEdit['status'] === 200 && str_contains($catEdit['body'], 'Edit category'));
$catUpdated = $admin->post('/admin/categories.php', [
    'csrf_token' => csrf_from($catEdit['body']),
    'action'     => 'update',
    'id'         => (string) $catRow['id'],
    'name'       => $catName . ' Updated',
    'slug'       => '',
    'sort_order' => '98',
]);
check('category updated', $catUpdated['status'] === 302 && str_contains((string) db_value('SELECT name FROM project_categories WHERE id = :id', [':id' => (int) $catRow['id']]), 'Updated'));
$catDeletePage = $admin->get('/admin/categories.php');
$catDeleted = $admin->post('/admin/categories.php', [
    'csrf_token' => csrf_from($catDeletePage['body']),
    'action'     => 'delete',
    'id'         => (string) $catRow['id'],
]);
check('unused category deleted', $catDeleted['status'] === 302 && db_value('SELECT id FROM project_categories WHERE id = :id', [':id' => (int) $catRow['id']]) === null);

$techPage = $admin->get('/admin/technologies.php');
check('technologies page renders', $techPage['status'] === 200 && str_contains($techPage['body'], 'Technology stack'));
structure('technologies', $techPage['body']);
$techName = 'TestTech' . $suffix;
$techCreated = $admin->post('/admin/technologies.php', [
    'csrf_token' => csrf_from($techPage['body']),
    'action'     => 'create',
    'name'       => $techName,
    'group_name' => 'Testing',
    'sort_order' => '99',
]);
check('technology created', $techCreated['status'] === 302 && (int) db_value('SELECT COUNT(*) FROM technologies WHERE name = :name', [':name' => $techName]) === 1);
$techPage = $admin->get('/admin/technologies.php');
$techId = (int) db_value('SELECT id FROM technologies WHERE name = :name', [':name' => $techName]);
$techDeleted = $admin->post('/admin/technologies.php', [
    'csrf_token' => csrf_from($techPage['body']),
    'action'     => 'delete',
    'id'         => (string) $techId,
]);
check('technology deleted', $techDeleted['status'] === 302 && db_value('SELECT id FROM technologies WHERE id = :id', [':id' => $techId]) === null);

group('messages inbox');

$storedMessage = db_one("SELECT * FROM contact_messages WHERE mail_status = 'sent' ORDER BY id DESC LIMIT 1");
$messagesPage = $admin->get('/admin/messages.php');
check('messages list renders', $messagesPage['status'] === 200 && str_contains($messagesPage['body'], 'Contact messages'));
structure('messages', $messagesPage['body']);
check('stored message appears in the inbox', is_array($storedMessage) && str_contains($messagesPage['body'], (string) $storedMessage['subject']));
$messageView = $admin->get('/admin/message.php?id=' . (int) $storedMessage['id']);
check('message detail renders', $messageView['status'] === 200 && str_contains($messageView['body'], 'Hello, I would like to discuss'));
structure('message detail', $messageView['body']);
$messageDelete = $admin->post('/admin/message.php', [
    'csrf_token' => csrf_from($messageView['body']),
    'action'     => 'delete',
    'id'         => (string) $storedMessage['id'],
]);
check('message deleted', $messageDelete['status'] === 302 && db_value('SELECT id FROM contact_messages WHERE id = :id', [':id' => (int) $storedMessage['id']]) === null);

/* -------------------------------------------------------------------------
 * 7. Email settings + owner/visitor notifications
 * ---------------------------------------------------------------------- */

group('email settings');

$emailPage = $admin->get('/admin/email.php');
check('email settings page renders', $emailPage['status'] === 200 && str_contains($emailPage['body'], 'Email configuration'));
structure('email settings', $emailPage['body']);
$emailCsrf = csrf_from($emailPage['body']);

// The pre-existing cPanel sending address must remain the shipped default.
$mailDefaults = mail_settings();
check('default sender stays no-reply@tijanidev.com.ng', $mailDefaults['from'] === 'no-reply@tijanidev.com.ng', $mailDefaults['from']);
check('default sender name is set', $mailDefaults['from_name'] !== '');
check('default notification address is valid', filter_var($mailDefaults['to'], FILTER_VALIDATE_EMAIL) !== false, $mailDefaults['to']);

$ownerEmail = 'owner+' . $suffix . '@example.test';
$visitorEmail = 'visitor+' . $suffix . '@example.test';
$confirmationSubject = 'We received your message — ' . $suffix;

$settingsPayload = [
    'csrf_token'           => $emailCsrf,
    'action'               => 'save',
    'to'                   => $ownerEmail,
    'from'                 => 'no-reply@tijanidev.com.ng',
    'from_name'            => 'TijaniDev Test',
    'confirmation_enabled' => '1',
    'confirmation_subject' => $confirmationSubject,
    'confirmation_body'    => "Hi {name},\n\nThanks for contacting {site_name}. Your subject was: {subject}.\n\n{owner_name}",
];
$saveSettings = $admin->post('/admin/email.php', $settingsPayload);
check('email settings saved', $saveSettings['status'] === 302 && str_contains($saveSettings['location'], 'email.php'), $saveSettings['location']);
check('notification recipient stored', setting_get('mail.to') === $ownerEmail);
check('confirmation toggle stored', setting_get('mail.confirmation_enabled') === '1');
check('confirmation template stored', str_contains((string) setting_get('mail.confirmation_body'), '{site_name}'));

$emailPage = $admin->get('/admin/email.php');
$invalidSettings = $admin->post('/admin/email.php', array_merge($settingsPayload, [
    'csrf_token' => csrf_from($emailPage['body']),
    'to'         => 'not-an-email',
]));
check('invalid recipient rejected', $invalidSettings['status'] === 200 && str_contains($invalidSettings['body'], 'valid email address'));
check('invalid save did not overwrite settings', setting_get('mail.to') === $ownerEmail);

$noCsrfSettings = $admin->post('/admin/email.php', ['action' => 'save', 'to' => 'x@example.test']);
check('email settings POST without CSRF → 419', $noCsrfSettings['status'] === 419);

$anonymousEmail = $anonymous->get('/admin/email.php');
check('email settings page requires authentication', $anonymousEmail['status'] === 302 && str_contains($anonymousEmail['location'], 'login.php'));

$captureDirectory = APP_STORAGE . '/logs/captured';
$capturedFiles = static function () use ($captureDirectory): array {
    $files = glob($captureDirectory . '/*.eml') ?: [];
    usort($files, static function (string $a, string $b): int {
        $time = (int) @filemtime($b) <=> (int) @filemtime($a);

        return $time !== 0 ? $time : strcmp($b, $a);
    });

    return $files;
};
$captureSnapshot = static function () use ($captureDirectory): array {
    return array_map('basename', glob($captureDirectory . '/*.eml') ?: []);
};
$newCaptureFiles = static function (array $before) use ($captureDirectory, $captureSnapshot): array {
    $names = array_values(array_diff($captureSnapshot(), $before));
    $paths = [];
    foreach ($names as $name) {
        $paths[] = $captureDirectory . '/' . $name;
    }

    return $paths;
};

$beforeTest = $captureSnapshot();
$emailPage = $admin->get('/admin/email.php');
$testEmail = $admin->post('/admin/email.php', [
    'csrf_token' => csrf_from($emailPage['body']),
    'action'     => 'test',
]);
check('test email accepted by transport', $testEmail['status'] === 302);
$testFiles = $newCaptureFiles($beforeTest);
check('test email was generated', count($testFiles) === 1);
$testFileContents = $testFiles !== [] ? (string) @file_get_contents($testFiles[0]) : '';
check('test email goes to the configured recipient', str_contains($testFileContents, 'To: ' . $ownerEmail));
check('test email body identifies the site', str_contains($testFileContents, 'test email from your portfolio dashboard'));

// Fresh contact submission with the new settings.
db_run('DELETE FROM contact_messages WHERE ip_address = :ip', [':ip' => '127.0.0.1']);
$beforeContact = $captureSnapshot();
$page = $anonymous->get('/');
$csrf = csrf_from($page['body']);
$token = form_token_from($page['body']);
sleep(3);
$contact = $anonymous->post('/contact.php', [
    'csrf_token' => $csrf,
    'form_token' => $token,
    'name'       => 'Visitor ' . $suffix,
    'email'      => $visitorEmail,
    'subject'    => 'Dual email test ' . $suffix,
    'message'    => 'Please send both an owner notification and a visitor confirmation for this message.',
]);
check('contact submission succeeds with email settings', $contact['status'] === 302 && str_contains($contact['location'], 'thank-you'));
$contactFiles = $newCaptureFiles($beforeContact);
check('owner notification and visitor confirmation both sent', count($contactFiles) === 2, 'captured ' . count($contactFiles));

$ownerMail = '';
$visitorMail = '';
foreach ($contactFiles as $file) {
    $content = (string) @file_get_contents($file);
    if (str_contains($content, 'To: ' . $ownerEmail)) {
        $ownerMail = $content;
    }
    if (str_contains($content, 'To: ' . $visitorEmail)) {
        $visitorMail = $content;
    }
}
check('owner copy addressed to the notification email', $ownerMail !== '');
check('visitor copy addressed to the visitor', $visitorMail !== '');
check('owner copy includes the enquiry', str_contains($ownerMail, 'Dual email test ' . $suffix));
check('confirmation renders placeholders', str_contains($visitorMail, 'Thanks for contacting TijaniDev') && str_contains($visitorMail, 'Visitor ' . $suffix));
check('confirmation subject is applied', str_contains($visitorMail, $confirmationSubject));

// Turn the confirmation off and verify only the owner copy goes out.
$emailPage = $admin->get('/admin/email.php');
$disabledPayload = $settingsPayload;
$disabledPayload['csrf_token'] = csrf_from($emailPage['body']);
unset($disabledPayload['confirmation_enabled']);
$disableSettings = $admin->post('/admin/email.php', $disabledPayload);
check('confirmation can be disabled', $disableSettings['status'] === 302 && setting_get('mail.confirmation_enabled') === '0');

db_run('DELETE FROM contact_messages WHERE ip_address = :ip', [':ip' => '127.0.0.1']);
$beforeSingle = $captureSnapshot();
$page = $anonymous->get('/');
$csrf = csrf_from($page['body']);
$token = form_token_from($page['body']);
sleep(3);
$single = $anonymous->post('/contact.php', [
    'csrf_token' => $csrf,
    'form_token' => $token,
    'name'       => 'Single Mail ' . $suffix,
    'email'      => 'single+' . $suffix . '@example.test',
    'subject'    => 'Owner only ' . $suffix,
    'message'    => 'Confirmation is disabled, so only the owner should receive this message.',
]);
$singleFiles = $newCaptureFiles($beforeSingle);
check('disabled confirmation sends only the owner copy', $single['status'] === 302 && count($singleFiles) === 1, 'captured ' . count($singleFiles));

/* -------------------------------------------------------------------------
 * 8. Backup download + restore from upload
 * ---------------------------------------------------------------------- */

group('backup and restore');

$restorePage = $admin->get('/admin/restore.php');
check('restore page renders', $restorePage['status'] === 200 && str_contains($restorePage['body'], 'Restore from a database backup'));
structure('restore page', $restorePage['body']);
$restoreCsrf = csrf_from($restorePage['body']);

$backup = $admin->get('/admin/backup.php');
check('live database backup downloads', $backup['status'] === 200 && str_starts_with($backup['body'], "SQLite format 3\x00"));
check('backup downloads as an attachment', str_contains(strtolower($backup['headers']['content-type'] ?? ''), 'sqlite') && str_contains(strtolower($backup['headers']['content-disposition'] ?? ''), 'attachment'));

$backupFile = sys_get_temp_dir() . '/tj-backup-' . $suffix . '.sqlite';
file_put_contents($backupFile, $backup['body']);
check('backup file saved for upload', is_file($backupFile) && filesize($backupFile) > 0);

// Change the database after the backup, so a restore is observable.
$markerForm = $admin->get('/admin/project-form.php');
$markerSlug = 'test-restore-marker-' . $suffix;
$marker = $admin->post('/admin/project-form.php', [
    'csrf_token'        => csrf_from($markerForm['body']),
    'id'                => '0',
    'title'             => 'Restore marker ' . $suffix,
    'slug'              => $markerSlug,
    'short_description' => '',
    'description'       => '',
    'problem'           => '',
    'solution'          => '',
    'category_id'       => '0',
    'status'            => 'draft',
    'seo_title'         => '',
    'seo_description'   => '',
    'live_url'          => '',
    'github_url'        => '',
    'completion_date'   => '',
]);
check('marker project created after the backup', $marker['status'] === 302 && project_by_slug($markerSlug, false) !== null);

$noCsrfRestore = $admin->post('/admin/restore.php', ['confirm_restore' => '1']);
check('restore without CSRF → 419', $noCsrfRestore['status'] === 419);

$noConfirm = $admin->post('/admin/restore.php', [
    'csrf_token' => $restoreCsrf,
    'backup_file' => new CURLFile($backupFile, 'application/octet-stream', 'portfolio.sqlite'),
]);
$noConfirmFollow = $admin->follow($noConfirm);
check('restore requires explicit confirmation', str_contains($noConfirmFollow['body'], 'Tick the confirmation box'));
check('unconfirmed restore left the database alone', project_by_slug($markerSlug, false) !== null);

$invalidFile = sys_get_temp_dir() . '/tj-invalid-' . $suffix . '.sqlite';
file_put_contents($invalidFile, 'this is definitely not a database');
$restorePage = $admin->get('/admin/restore.php');
$badUpload = $admin->post('/admin/restore.php', [
    'csrf_token'      => csrf_from($restorePage['body']),
    'confirm_restore' => '1',
    'backup_file'     => new CURLFile($invalidFile, 'application/octet-stream', 'broken.sqlite'),
]);
$badFollow = $admin->follow($badUpload);
check('non-database upload is rejected', str_contains($badFollow['body'], 'not a SQLite database'));
check('rejected upload left the database alone', project_by_slug($markerSlug, false) !== null);

$wrongSchemaFile = sys_get_temp_dir() . '/tj-wrong-schema-' . $suffix . '.sqlite';
$wrongPdo = new PDO('sqlite:' . $wrongSchemaFile);
$wrongPdo->exec('CREATE TABLE unrelated (id INTEGER PRIMARY KEY)');
$wrongPdo = null;
$restorePage = $admin->get('/admin/restore.php');
$wrongUpload = $admin->post('/admin/restore.php', [
    'csrf_token'      => csrf_from($restorePage['body']),
    'confirm_restore' => '1',
    'backup_file'     => new CURLFile($wrongSchemaFile, 'application/octet-stream', 'wrong.sqlite'),
]);
$wrongFollow = $admin->follow($wrongUpload);
check('database from another application is rejected', str_contains($wrongFollow['body'], 'missing tables'));
check('wrong-schema upload left the database alone', project_by_slug($markerSlug, false) !== null);

// Windows will not rename a database while this test process holds a handle.
Database::resetConnection();
gc_collect_cycles();

$restorePage = $admin->get('/admin/restore.php');
$validRestore = $admin->post('/admin/restore.php', [
    'csrf_token'      => csrf_from($restorePage['body']),
    'confirm_restore' => '1',
    'backup_file'     => new CURLFile($backupFile, 'application/vnd.sqlite3', 'portfolio.sqlite'),
]);
check('valid backup restores', $validRestore['status'] === 302 && str_contains($validRestore['location'], 'system.php'), $validRestore['location']);

// The test process still holds a connection to the replaced file.
Database::resetConnection();
gc_collect_cycles();

$afterRestore = $admin->get('/admin/index.php');
check('admin session survives a same-site restore', $afterRestore['status'] === 200);
check('database returned to the backup state', project_by_slug($markerSlug, false) === null);
check('restored project count is correct', (int) db_value('SELECT COUNT(*) FROM projects') === 3);
check('restore is recorded in the activity log', (int) db_value("SELECT COUNT(*) FROM activity_log WHERE action = 'system.restored'") >= 1);

$safetyCopies = list_database_backups(5);
check('pre-restore safety copy was created', $safetyCopies !== []);
$safetyName = (string) ($safetyCopies[0]['name'] ?? '');
$safetyPath = backup_directory() . '/' . $safetyName;
check('safety copy is a valid SQLite file', $safetyName !== '' && is_sqlite_file($safetyPath));

$safetyDownload = $admin->get('/admin/backup.php?file=' . rawurlencode($safetyName));
check('safety copy can be downloaded', $safetyDownload['status'] === 200 && str_starts_with($safetyDownload['body'], "SQLite format 3\x00"));
$traversal = $admin->get('/admin/backup.php?file=' . rawurlencode('../portfolio.sqlite'));
check('backup download refuses path traversal', in_array($traversal['status'], [400, 403, 404], true), 'status ' . $traversal['status']);

$anonymousBackup = $anonymous->get('/admin/backup.php');
check('backup download requires authentication', $anonymousBackup['status'] === 302 && str_contains($anonymousBackup['location'], 'login.php'));
$anonymousRestore = $anonymous->get('/admin/restore.php');
check('restore page requires authentication', $anonymousRestore['status'] === 302 && str_contains($anonymousRestore['location'], 'login.php'));

@unlink($backupFile);
@unlink($invalidFile);
@unlink($wrongSchemaFile);

/* -------------------------------------------------------------------------
 * 9. Login throttling + logout
 * ---------------------------------------------------------------------- */

group('login throttling');

$intruder = new Http($base);
$limitedMessage = '';
for ($i = 1; $i <= 6; $i++) {
    $page = $intruder->get('/admin/login.php');
    $token = csrf_from($page['body']);
    $attempt = $intruder->post('/admin/login.php', ['csrf_token' => $token, 'username' => 'intruder', 'password' => 'guess-' . $i]);
    $limitedMessage = $attempt['body'];
    if (str_contains($attempt['body'], 'Too many sign-in attempts')) {
        break;
    }
}
check('login throttling kicks in', str_contains($limitedMessage, 'Too many sign-in attempts'));

group('logout');

$dashboard = $admin->get('/admin/index.php');
$logoutCsrf = csrf_from($dashboard['body']);
$logout = $admin->post('/admin/logout.php', ['csrf_token' => $logoutCsrf]);
check('logout redirects to login', $logout['status'] === 302 && str_contains($logout['location'], 'login.php'));
$afterLogout = $admin->get('/admin/index.php');
check('dashboard locked after logout', $afterLogout['status'] === 302 && str_contains($afterLogout['location'], 'login.php'));

/* -------------------------------------------------------------------------
 * 10. Database integrity
 * ---------------------------------------------------------------------- */

group('database');

$tables = array_column(db_all("SELECT name FROM sqlite_master WHERE type = 'table'"), 'name');
$expected = ['admin_users', 'projects', 'project_images', 'project_categories', 'technologies', 'project_technologies', 'login_attempts', 'contact_messages', 'activity_log'];
$missingTables = array_diff($expected, $tables);
check('all required tables exist', $missingTables === [], implode(', ', $missingTables));
check('foreign keys enabled', (int) db_value('PRAGMA foreign_keys') === 1);
check('seeded projects intact', (int) db_value('SELECT COUNT(*) FROM projects') === 3, (string) db_value('SELECT COUNT(*) FROM projects'));

/* -------------------------------------------------------------------------
 * Cleanup: remove test data, restore the temporary password state
 * ---------------------------------------------------------------------- */

group('cleanup');

db_run("DELETE FROM contact_messages WHERE email LIKE 'tester+%' OR email IN ('spam@example.test', 'fast@example.test', 'limited@example.test')");
db_run("DELETE FROM login_attempts WHERE username IN ('intruder', 'admin')");
db_run('UPDATE admin_users SET password_hash = :hash, must_change_password = 1 WHERE username = :username', [
    ':hash'     => password_hash('admin', PASSWORD_DEFAULT),
    ':username' => 'admin',
]);
$restored = password_verify('admin', (string) db_value('SELECT password_hash FROM admin_users WHERE username = :u', [':u' => 'admin']));
check('temporary admin password restored for local preview', $restored);
check('no test projects left behind', (int) db_value('SELECT COUNT(*) FROM projects WHERE slug LIKE :slug', [':slug' => 'test-project-%']) === 0);

/* -------------------------------------------------------------------------
 * Summary
 * ---------------------------------------------------------------------- */

echo "\n" . str_repeat('=', 56) . "\n";
echo 'PASSED: ' . $passed . '   FAILED: ' . $failed . "\n";
echo str_repeat('=', 56) . "\n";

exit($failed === 0 ? 0 : 1);
