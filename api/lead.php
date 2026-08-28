<?php

declare(strict_types=1);

/**
 * Public lead capture. The only PHP the public site touches.
 *
 * POST JSON or form-encoded: email (required), name, phone, message, source
 * Honeypot field "company" must stay empty.
 */

require_once __DIR__ . '/../admin/inc/config.php';
require_once __DIR__ . '/../admin/inc/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function out(array $d, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['ok' => false, 'error' => 'POST only.'], 405);
}

/* ---- read the body, either shape ---- */
$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$field = static fn(string $k): string => trim((string) ($data[$k] ?? ''));

// honeypot — real people never fill this in
if ($field('company') !== '') {
    out(['ok' => true]);
}

$email = mb_strtolower($field('email'));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    out(['ok' => false, 'error' => 'That email address needs another look.'], 422);
}

$name    = mb_substr(strip_tags($field('name')), 0, 190);
$phone   = mb_substr(strip_tags($field('phone')), 0, 60);
$message = mb_substr(strip_tags($field('message')), 0, 4000);

$source = preg_replace('/[^a-z0-9_-]/i', '', $field('source')) ?: 'hero';
$source = mb_substr($source, 0, 60);

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ipBin = $ip !== '' ? @inet_pton($ip) : null;
$ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);

try {
    $pdo = db();

    /* ---- throttle: 6 submissions per IP per hour ---- */
    if ($ipBin !== false && $ipBin !== null) {
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM leads WHERE ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $st->execute([$ipBin]);
        if ((int) $st->fetchColumn() >= 6) {
            out(['ok' => false, 'error' => 'That is a lot of messages. Try again in a little while.'], 429);
        }
    }

    /* ---- collapse a repeat submission of the same address ---- */
    $st = $pdo->prepare(
        'SELECT id FROM leads
         WHERE email = ? AND source = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
         LIMIT 1'
    );
    $st->execute([$email, $source]);
    if ($st->fetchColumn()) {
        out(['ok' => true, 'duplicate' => true]);
    }

    $st = $pdo->prepare(
        'INSERT INTO leads (email, name, phone, message, source, ip, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $st->execute([$email, $name, $phone, $message, $source, $ipBin ?: null, $ua]);

    out(['ok' => true]);
} catch (Throwable $e) {
    error_log('lead capture failed: ' . $e->getMessage());
    out(['ok' => false, 'error' => 'Something went wrong at our end. Please email us directly.'], 500);
}
