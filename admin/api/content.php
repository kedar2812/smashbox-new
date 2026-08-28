<?php

declare(strict_types=1);

/**
 * Save or clear a single content field, then republish the site.
 *
 * POST { id, type, value, alt? }         set an override
 * POST { id, reset: true }               drop the override, restoring the original
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/render.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonFail('POST only.', 405);
}

requireCsrf();

$in = jsonBody();
$id = (string) ($in['id'] ?? '');

$field = fieldById($id);
if (!$field) {
    jsonFail('That field is not part of the site.', 404);
}

try {
    if (!empty($in['reset'])) {
        db()->prepare('DELETE FROM content WHERE field_id = ?')->execute([$id]);

        $result = publishSite();

        jsonOut([
            'ok'        => true,
            'reset'     => true,
            'value'     => $field['default'],
            'alt'       => $field['alt'] ?? '',
            'published' => $result,
        ]);
    }

    $value = (string) ($in['value'] ?? '');
    $alt = array_key_exists('alt', $in) ? (string) $in['alt'] : null;

    if ($field['type'] === 'text') {
        $value = sanitizeInline($value);
        if (plainText($value) === '' && trim($value) === '') {
            jsonFail('That block cannot be left empty. Use “Restore original” instead.');
        }
    } else {
        $value = trim($value);
        if ($value === '') {
            jsonFail('Choose a picture or paste an image address.');
        }
        // only allow a site-relative path or an http(s) URL
        if (!preg_match('#^(https?://|uploads/|img/|/)#i', $value)) {
            jsonFail('That image address is not one the site can use.');
        }
        if ($alt !== null) {
            $alt = trim(strip_tags($alt));
            if (mb_strlen($alt) > 480) {
                $alt = mb_substr($alt, 0, 480);
            }
        }
    }

    $st = db()->prepare(
        'INSERT INTO content (field_id, type, value, alt)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value), alt = VALUES(alt)'
    );
    $st->execute([$id, $field['type'], $value, $alt]);

    $result = publishSite();

    jsonOut([
        'ok'        => true,
        'value'     => $value,
        'alt'       => $alt,
        'published' => $result,
    ]);
} catch (Throwable $e) {
    error_log('content save failed: ' . $e->getMessage());
    jsonFail('Could not save that change: ' . $e->getMessage(), 500);
}
