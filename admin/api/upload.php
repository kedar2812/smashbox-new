<?php

declare(strict_types=1);

/**
 * Receive one image, verify it really is an image, and file it in /uploads.
 * Returns the site-relative path the content endpoint will store.
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/render.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonFail('POST only.', 405);
}

requireCsrf();

if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'] ?? '')) {
    $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $msg = match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That file is bigger than the 10 MB limit.',
        UPLOAD_ERR_PARTIAL   => 'The upload was interrupted. Try again.',
        UPLOAD_ERR_NO_FILE   => 'No file arrived.',
        default              => 'The upload failed.',
    };
    jsonFail($msg);
}

$file = $_FILES['file'];

if ($file['size'] > MAX_UPLOAD_BYTES) {
    jsonFail('That file is bigger than the 10 MB limit.');
}

// trust the bytes, not the filename or the browser's content-type
$info = @getimagesize($file['tmp_name']);
if ($info === false) {
    jsonFail('That file is not an image the site can display.');
}

$mime = $info['mime'] ?? '';
if (!isset(ALLOWED_IMAGE_TYPES[$mime])) {
    jsonFail('Use a JPG, PNG, WEBP, GIF or AVIF.');
}

$ext = ALLOWED_IMAGE_TYPES[$mime];

if (!is_dir(UPLOAD_DIR) && !@mkdir(UPLOAD_DIR, 0755, true)) {
    jsonFail('The uploads folder is missing and could not be created.', 500);
}
if (!is_writable(UPLOAD_DIR)) {
    jsonFail('The uploads folder is not writable.', 500);
}

// readable slug from the original name, plus enough randomness to never collide
$base = pathinfo($file['name'], PATHINFO_FILENAME);
$slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $base) ?? '');
$slug = trim($slug, '-');
if ($slug === '') {
    $slug = 'image';
}
$slug = substr($slug, 0, 48);

$filename = $slug . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest = UPLOAD_DIR . '/' . $filename;

if (!@move_uploaded_file($file['tmp_name'], $dest)) {
    jsonFail('Could not save the file. Check folder permissions.', 500);
}

@chmod($dest, 0644);

try {
    db()->prepare(
        'INSERT INTO media (filename, original, mime, bytes, width, height)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $filename,
        substr($file['name'], 0, 250),
        $mime,
        (int) $file['size'],
        (int) ($info[0] ?? 0),
        (int) ($info[1] ?? 0),
    ]);
} catch (Throwable $e) {
    // the file is on disk and usable; the catalogue row is a nicety
    error_log('media row failed: ' . $e->getMessage());
}

jsonOut([
    'ok'     => true,
    'path'   => UPLOAD_URL . $filename,
    // the dashboard is its own subdomain, so previews go to the site's origin
    'url'    => publicSrc(UPLOAD_URL . $filename),
    'name'   => $file['name'],
    'width'  => (int) ($info[0] ?? 0),
    'height' => (int) ($info[1] ?? 0),
    'size'   => formatBytes((int) $file['size']),
]);
