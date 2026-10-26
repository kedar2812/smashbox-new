<?php

declare(strict_types=1);

/**
 * The picture library: everything uploaded so far, newest first, so a
 * picture can be reused without uploading it again.
 *
 * GET → { ok, items: [{ path, url, name, width, height, size, when }] }
 */

require_once __DIR__ . '/../inc/auth.php';

requireLogin();

try {
    $rows = db()->query(
        'SELECT filename, original, bytes, width, height, uploaded_at
         FROM media ORDER BY uploaded_at DESC, id DESC LIMIT 300'
    )->fetchAll();
} catch (Throwable $e) {
    error_log('media list failed: ' . $e->getMessage());
    jsonFail('Could not load the library.', 500);
}

$items = [];
foreach ($rows as $r) {
    // a file removed from the server by hand should not show as a blank tile
    if (!is_file(UPLOAD_DIR . '/' . $r['filename'])) {
        continue;
    }
    $items[] = [
        'path'   => UPLOAD_URL . $r['filename'],
        'url'    => publicSrc(UPLOAD_URL . $r['filename']),
        'name'   => $r['original'] !== '' ? $r['original'] : $r['filename'],
        'width'  => (int) $r['width'],
        'height' => (int) $r['height'],
        'size'   => formatBytes((int) $r['bytes']),
        'when'   => timeAgo($r['uploaded_at']),
    ];
}

jsonOut(['ok' => true, 'items' => $items]);
