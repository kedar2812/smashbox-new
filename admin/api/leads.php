<?php

declare(strict_types=1);

/**
 * Lead actions from the dashboard.
 *
 * POST { action: "seen",      id }
 * POST { action: "seen_all" }
 * POST { action: "status",    id, status }
 * POST { action: "note",      id, note }
 * GET  ?action=unseen                     poll for the bell
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/layout.php';

requireLogin();

/* ---- polling ---- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['action'] ?? '') !== 'unseen') {
        jsonFail('Unknown action.');
    }

    $items = [];
    foreach (unseenLeads() as $l) {
        $items[] = [
            'id'      => (int) $l['id'],
            'title'   => 'New lead — ' . ($l['name'] !== '' ? $l['name'] : $l['email']),
            'preview' => truncate($l['message'] ?? '', 64) ?: $l['email'],
            'when'    => timeAgo($l['created_at']),
            'source'  => $l['source'],
        ];
    }

    jsonOut([
        'ok'     => true,
        'unseen' => unseenCount(),
        'new'    => newLeadCount(),
        'items'  => $items,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonFail('POST only.', 405);
}

requireCsrf();

$in = jsonBody();
$action = (string) ($in['action'] ?? '');
$id = (int) ($in['id'] ?? 0);

try {
    switch ($action) {
        case 'seen':
            if ($id <= 0) {
                jsonFail('Which lead?');
            }
            db()->prepare('UPDATE leads SET seen_at = NOW() WHERE id = ? AND seen_at IS NULL')
                ->execute([$id]);
            break;

        case 'seen_all':
            db()->query('UPDATE leads SET seen_at = NOW() WHERE seen_at IS NULL');
            break;

        case 'status':
            $status = (string) ($in['status'] ?? '');
            if (!in_array($status, ['new', 'replied', 'archived'], true)) {
                jsonFail('That is not a status.');
            }
            if ($id <= 0) {
                jsonFail('Which lead?');
            }
            $sql = 'UPDATE leads SET status = ?, seen_at = COALESCE(seen_at, NOW())';
            $params = [$status];
            if ($status === 'replied') {
                $sql .= ', replied_at = NOW()';
            }
            $sql .= ' WHERE id = ?';
            $params[] = $id;
            db()->prepare($sql)->execute($params);
            break;

        case 'note':
            if ($id <= 0) {
                jsonFail('Which lead?');
            }
            $note = trim(strip_tags((string) ($in['note'] ?? '')));
            db()->prepare('UPDATE leads SET note = ? WHERE id = ?')->execute([$note, $id]);
            break;

        default:
            jsonFail('Unknown action.');
    }

    jsonOut([
        'ok'     => true,
        'unseen' => unseenCount(),
        'new'    => newLeadCount(),
    ]);
} catch (Throwable $e) {
    error_log('lead action failed: ' . $e->getMessage());
    jsonFail('Could not update that lead.', 500);
}
