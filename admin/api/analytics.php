<?php

declare(strict_types=1);

/**
 * Analytics for one range.  GET ?range=today|7d|30d|90d
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/analytics.php';

requireLogin();

try {
    jsonOut(['ok' => true, 'report' => analyticsReport((string) ($_GET['range'] ?? '7d'))]);
} catch (Throwable $e) {
    error_log('analytics report failed: ' . $e->getMessage());
    jsonFail('Could not load the numbers right now.', 500);
}
