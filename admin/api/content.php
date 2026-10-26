<?php

declare(strict_types=1);

/**
 * Save or clear a single content field, then republish the site.
 *
 * POST { id, slots: [[{t,em?,strong?}|{br:true}, …], …] }   set a text override
 * POST { id, value, alt? }                                  set an image override
 * POST { id, reset: true }                                  drop it, restoring the original
 * POST { id, slots, preview: true }                        compose without saving, for a live preview
 *
 * Text arrives as words, never as markup. The skeleton — the tags, classes and
 * links that make up the design — is taken from the value already stored and
 * refilled here, so no request can change the shape of the page, only what it
 * says. See inc/richtext.php.
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/render.php';
require_once __DIR__ . '/../inc/richtext.php';

require_once __DIR__ . '/../inc/fields.php';

requireLogin();

/** What the field says right now — the override if there is one, else the original. */
function currentValue(array $field): string
{
    $overrides = contentOverrides();
    return $overrides[$field['id']]['value'] ?? (string) $field['default'];
}

/* ---- GET ?id= : everything an editor panel needs to open a field ---- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $field = fieldById((string) ($_GET['id'] ?? ''));
    if (!$field) {
        jsonFail('That part of the page is not editable.', 404);
    }

    $overrides = contentOverrides();
    $edited = isset($overrides[$field['id']]);
    $out = [
        'ok'      => true,
        'id'      => $field['id'],
        'type'    => $field['type'],
        'title'   => fieldTitle($field),
        'kind'    => fieldKind($field),
        'hint'    => fieldHint($field),
        'section' => sectionTitle($field['section']),
        'edited'  => $edited,
    ];

    if ($field['type'] === 'text') {
        $parts = decomposeField(currentValue($field));
        $out += [
            'tag'      => $field['tag'],
            'slots'    => $parts['slots'],
            'labels'   => $parts['labels'] ?? [],
            'original' => truncate(plainText((string) $field['default']), 400),
        ];
    } else {
        $value = effectiveValue($field, $overrides);
        $out += [
            'attr'     => $field['attr'] ?? 'src',
            'value'    => $value,
            'preview'  => publicSrc($value),
            'alt'      => effectiveAlt($field, $overrides),
            'original' => publicSrc((string) $field['default']),
        ];
    }

    jsonOut($out);
}

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
        $parts = decomposeField((string) $field['default']);

        jsonOut([
            'ok'        => true,
            'reset'     => true,
            'value'     => $field['default'],
            'display'   => $field['type'] === 'text' ? displayInline((string) $field['default']) : null,
            'preview'   => $field['type'] === 'image' ? publicSrc((string) $field['default']) : null,
            'slots'     => $parts['slots'],
            'labels'    => $parts['labels'] ?? [],
            'alt'       => $field['alt'] ?? '',
            'published' => $result,
        ]);
    }

    $alt = array_key_exists('alt', $in) ? (string) $in['alt'] : null;

    if ($field['type'] === 'text') {
        if (!isset($in['slots']) || !is_array($in['slots'])) {
            jsonFail('Send the text as slots.');
        }

        $skeleton = decomposeField(currentValue($field))['skeleton'];
        $value = composeField($skeleton, $in['slots']);

        // the visual editor shows the change on the page before it is saved
        if (!empty($in['preview'])) {
            jsonOut(['ok' => true, 'preview' => true, 'value' => sanitizeInline($value)]);
        }

        if (plainText($value) === '') {
            jsonFail('That block cannot be left empty. Use “Restore original” instead.');
        }
        if (mb_strlen($value) > 20000) {
            jsonFail('That is longer than this block can hold.');
        }
    } else {
        $value = trim((string) ($in['value'] ?? ''));
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

    // hand back what the field now looks like, so the page does not need a reload
    $parts = $field['type'] === 'text' ? decomposeField($value) : null;

    jsonOut([
        'ok'        => true,
        'value'     => $value,
        'display'   => $field['type'] === 'text' ? displayInline($value) : null,
        'preview'   => $field['type'] === 'image' ? publicSrc($value) : null,
        'slots'     => $parts['slots'] ?? null,
        'labels'    => $parts['labels'] ?? null,
        'alt'       => $alt,
        'published' => $result,
    ]);
} catch (Throwable $e) {
    error_log('content save failed: ' . $e->getMessage());
    jsonFail('Could not save that change: ' . $e->getMessage(), 500);
}
