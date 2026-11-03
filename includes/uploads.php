<?php
/**
 * Image upload handling.
 *
 * Defence in depth:
 * 1. size limits + PHP upload error checks;
 * 2. finfo MIME sniffing (never the client-provided type);
 * 3. getimagesize() must agree that the bytes are a real image;
 * 4. extension is derived from the sniffed MIME type only;
 * 5. random filename in a dated folder, permissions 0644;
 * 6. .htaccess in the uploads tree disables script execution.
 */

declare(strict_types=1);

/** @return array<string, string> mime => extension */
function allowed_image_types(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
}

function upload_root(): string
{
    return rtrim((string) config('uploads.path', APP_ROOT . '/assets/uploads'), '/\\');
}

function upload_max_bytes(): int
{
    return max(1024, (int) config('uploads.max_bytes', 5 * 1024 * 1024));
}

/** Create the uploads tree and protect it from script execution. */
function ensure_upload_directory(): void
{
    $root = upload_root();
    ensure_dir($root, 0755);
    ensure_dir($root . '/projects', 0755);

    $htaccess = $root . '/.htaccess';
    if (!is_file($htaccess)) {
        $rules = <<<'HTACCESS'
# Deny execution of any script placed in the uploads tree.
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<FilesMatch "\.(php|php3|php4|php5|php7|php8|phtml|phar|pl|py|cgi|sh|shtml|htaccess|ini)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>
Options -Indexes
HTACCESS;
        @file_put_contents($htaccess, $rules . PHP_EOL, LOCK_EX);
        @chmod($htaccess, 0644);
    }

    if (!is_file($root . '/index.html')) {
        @file_put_contents($root . '/index.html', '');
    }
}

/**
 * Inspect an uploaded file without moving it.
 *
 * @param array<string, mixed> $file  a single $_FILES entry
 * @return array{ok:bool, error:string, extension:string, mime:string, size:int}
 */
function inspect_image_upload(array $file, bool $trustedSource = false): array
{
    $fail = static fn (string $message): array => [
        'ok' => false, 'error' => $message, 'extension' => '', 'mime' => '', 'size' => 0,
    ];

    if (!isset($file['error'], $file['tmp_name'], $file['size'])) {
        return $fail('No file was uploaded.');
    }
    if (is_array($file['error'])) {
        return $fail('Upload one image at a time.');
    }

    $errorCode = (int) $file['error'];
    if ($errorCode !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'The image is larger than this server allows.',
            UPLOAD_ERR_FORM_SIZE  => 'The image is larger than the form allows.',
            UPLOAD_ERR_PARTIAL    => 'The upload did not finish. Please try again.',
            UPLOAD_ERR_NO_FILE    => 'No file was selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary upload directory.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
            UPLOAD_ERR_EXTENSION  => 'A server extension stopped the upload.',
        ];

        return $fail($messages[$errorCode] ?? 'The upload failed (error ' . $errorCode . ').');
    }

    $tmp = (string) $file['tmp_name'];
    $size = (int) $file['size'];

    if ($size <= 0) {
        return $fail('That file is empty.');
    }
    if ($size > upload_max_bytes()) {
        return $fail('Images must be ' . strtoupper(human_filesize(upload_max_bytes())) . ' or smaller.');
    }
    if (!$trustedSource && !is_uploaded_file($tmp)) {
        return $fail('That upload could not be verified.');
    }
    if (!is_file($tmp)) {
        return $fail('The temporary upload disappeared. Please try again.');
    }

    if (!class_exists('finfo')) {
        app_log('uploads', 'Fileinfo extension unavailable during upload');

        return $fail('This server is missing the fileinfo extension, so images cannot be verified.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    $allowed = allowed_image_types();
    if (!isset($allowed[$mime])) {
        return $fail('Only JPG, PNG or WebP images are accepted.');
    }

    $info = @getimagesize($tmp);
    if (!is_array($info) || empty($info[0]) || empty($info[1])) {
        return $fail('That file is not a readable image.');
    }

    $width = (int) $info[0];
    $height = (int) $info[1];
    if ($width < 32 || $height < 32) {
        return $fail('The image is too small (32 × 32 pixels minimum).');
    }
    if ($width > 9000 || $height > 9000) {
        return $fail('The image dimensions are too large.');
    }

    $expected = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG  => 'image/png',
        IMAGETYPE_WEBP => 'image/webp',
    ];
    $detectedType = (int) ($info[2] ?? 0);
    if (!isset($expected[$detectedType]) || $expected[$detectedType] !== $mime) {
        return $fail('The image format could not be confirmed.');
    }

    return [
        'ok'        => true,
        'error'     => '',
        'extension' => $allowed[$mime],
        'mime'      => $mime,
        'size'      => $size,
    ];
}

/**
 * Validate and move an upload into assets/uploads/projects/YYYY/MM.
 *
 * @param array<string, mixed> $file
 * @return array{ok:bool, path:string, error:string}
 */
function store_image_upload(array $file, bool $trustedSource = false): array
{
    $inspection = inspect_image_upload($file, $trustedSource);
    if (!$inspection['ok']) {
        return ['ok' => false, 'path' => '', 'error' => $inspection['error']];
    }

    ensure_upload_directory();
    $subDir = 'projects/' . date('Y') . '/' . date('m');
    $absoluteDir = upload_root() . '/' . $subDir;
    if (!ensure_dir($absoluteDir, 0755)) {
        app_log('uploads', 'Could not create upload directory', ['dir' => $absoluteDir]);

        return ['ok' => false, 'path' => '', 'error' => 'The upload folder is not writable on this server.'];
    }

    $filename = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $inspection['extension'];
    $target = $absoluteDir . '/' . $filename;

    $moved = $trustedSource
        ? @copy((string) $file['tmp_name'], $target)
        : @move_uploaded_file((string) $file['tmp_name'], $target);

    if (!$moved || !is_file($target)) {
        app_log('uploads', 'Failed to move uploaded file', ['target' => $target]);

        return ['ok' => false, 'path' => '', 'error' => 'The image could not be saved. Please try again.'];
    }

    @chmod($target, 0644);
    clearstatcache(true, $target);

    $relative = 'assets/uploads/' . $subDir . '/' . $filename;

    return ['ok' => true, 'path' => $relative, 'error' => ''];
}

/**
 * Delete a stored upload. Only paths inside the uploads tree are eligible.
 */
function remove_image_file(?string $relativePath): bool
{
    $relativePath = trim((string) $relativePath);
    if ($relativePath === '' || preg_match('#^https?://#i', $relativePath)) {
        return false;
    }

    $root = realpath(upload_root());
    if ($root === false) {
        return false;
    }

    $candidate = realpath(APP_ROOT . '/' . ltrim($relativePath, '/'));
    if ($candidate === false || !is_file($candidate)) {
        return false;
    }

    $rootPrefix = rtrim(str_replace('\\', '/', $root), '/') . '/';
    $normalised = str_replace('\\', '/', $candidate);
    if (!str_starts_with($normalised, $rootPrefix)) {
        app_log('uploads', 'Refused to delete a file outside the uploads directory', ['path' => $relativePath]);

        return false;
    }

    return @unlink($candidate);
}

/** Normalise a PHP $_FILES multi-upload into a list of single entries. */
function normalise_upload_array(array $files): array
{
    if (!isset($files['name'])) {
        return [];
    }

    if (!is_array($files['name'])) {
        return [$files];
    }

    $normalised = [];
    foreach (array_keys($files['name']) as $index) {
        $normalised[] = [
            'name'     => $files['name'][$index],
            'type'     => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error'    => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $files['size'][$index] ?? 0,
        ];
    }

    return $normalised;
}
