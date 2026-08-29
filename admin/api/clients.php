<?php

declare(strict_types=1);

/**
 * Save the client marquee, then republish the site.
 *
 * POST { names: ["Taj Hotels", …] }   replace the list
 * POST { reset: true }                drop it, restoring the names in the template
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/render.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonFail('POST only.', 405);
}

requireCsrf();

$in = jsonBody();

try {
    if (!empty($in['reset'])) {
        settingDelete(CLIENTS_SETTING);
        $names = defaultClientNames();

        $result = publishSite();

        jsonOut([
            'ok'        => true,
            'reset'     => true,
            'names'     => $names,
            'published' => $result,
        ]);
    }

    if (!array_key_exists('names', $in) || !is_array($in['names'])) {
        jsonFail('Send the list of client names.');
    }

    if (count($in['names']) > CLIENTS_MAX) {
        jsonFail('The strip holds at most ' . CLIENTS_MAX . ' names.');
    }

    $names = saveClientNames($in['names']);

    $result = publishSite();

    jsonOut([
        'ok'        => true,
        'names'     => $names,
        'published' => $result,
    ]);
} catch (Throwable $e) {
    error_log('client marquee save failed: ' . $e->getMessage());
    jsonFail('Could not save the client list: ' . $e->getMessage(), 500);
}
