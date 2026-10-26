<?php
/**
 * Router for `php -S` that serves the public site the way the server does:
 * index.html and /uploads come from the dev publish folder (see
 * admin/inc/config.local.php), everything else from the repo.
 *
 *   php -S 127.0.0.1:8412 tools/dev/site-router.php
 */

$out = getenv('SBX_DEV_OUT') ?: (sys_get_temp_dir() . '/sbx-dev');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

if ($path === '/' || $path === '/index.html') {
    $file = is_file($out . '/index.html') ? $out . '/index.html' : dirname(__DIR__, 2) . '/template.html';
    header('Content-Type: text/html; charset=utf-8');
    readfile($file);
    return true;
}

if (str_starts_with($path, '/uploads/')) {
    $file = $out . '/uploads/' . basename($path);
    if (!is_file($file)) {
        http_response_code(404);
        return true;
    }
    header('Content-Type: ' . (mime_content_type($file) ?: 'application/octet-stream'));
    readfile($file);
    return true;
}

return false;
