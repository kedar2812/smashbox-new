<?php

declare(strict_types=1);

/**
 * Called by tools/deploy.sh once a new template.html is uploaded: rebuilds
 * index.html from it with every saved edit applied.
 *
 * Takes no input and only does work when the template is newer than the
 * published page, so there is nothing here worth protecting with a password.
 */

require_once __DIR__ . '/../admin/inc/config.php';
require_once __DIR__ . '/../admin/inc/render.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

try {
    $did = republishIfTemplateChanged();
    echo json_encode(['ok' => true, 'rebuilt' => $did]);
} catch (Throwable $e) {
    error_log('rebuild failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
