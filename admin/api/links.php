<?php

declare(strict_types=1);

/**
 * Save the contact details and social links, then republish.
 *
 * POST { links: { email, phone, whatsapp, instagram, linkedin, behance, youtube } }
 *
 * Every field is checked before anything is stored; if one is wrong the
 * response names it and nothing changes.
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/render.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonFail('POST only.', 405);
}

requireCsrf();

$in = jsonBody();
$sent = is_array($in['links'] ?? null) ? $in['links'] : null;
if ($sent === null) {
    jsonFail('Send the links to save.');
}

$clean = [];
$errors = [];
foreach (linkFields() as $key => $meta) {
    [$value, $error] = normaliseLink($key, (string) ($sent[$key] ?? ''));
    $clean[$key] = $value;
    if ($error !== null) {
        $errors[$key] = $error;
    }
}

if ($clean['email'] === '') {
    $errors['email'] = 'The site needs an email address for its contact buttons.';
}

if ($errors) {
    jsonOut(['ok' => false, 'error' => 'Some of these need another look.', 'fields' => $errors], 422);
}

try {
    settingSet(LINKS_SETTING, json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $result = publishSite();
    jsonOut(['ok' => true, 'links' => $clean, 'published' => $result]);
} catch (Throwable $e) {
    error_log('links save failed: ' . $e->getMessage());
    jsonFail('Could not save the links: ' . $e->getMessage(), 500);
}
