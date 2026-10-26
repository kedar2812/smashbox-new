<?php

declare(strict_types=1);

/**
 * Analytics beacon. Called by js/track.js with navigator.sendBeacon.
 *
 * POST (JSON, sent as text/plain so no preflight is needed):
 *   { t: "pageview", p, r, us, um, uc, tz, sw, sid }
 *   { t: "click",    p, l, h, k, s, tz, sw, sid }
 *   { t: "leave",    p, d, sc, tz, sw, sid }
 *
 * Always answers 204 — a visitor's browser has no use for the outcome, and
 * an error page would only be noise in their console.
 */

require_once __DIR__ . '/../admin/inc/config.php';
require_once __DIR__ . '/../admin/inc/db.php';
require_once __DIR__ . '/../admin/inc/analytics.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function done(): never
{
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    done();
}

$raw = file_get_contents('php://input', false, null, 0, 4096) ?: '';
$in = json_decode($raw, true);
if (!is_array($in)) {
    done();
}

$ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 400);
if (analyticsIsBot($ua)) {
    done();
}

$type = (string) ($in['t'] ?? '');
if (!in_array($type, ['pageview', 'click', 'leave'], true)) {
    done();
}

$str = static fn(string $k, int $max): string =>
    mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($in[$k] ?? ''))) ?? ''), 0, $max);

try {
    analyticsEnsureSchema();

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $visitor = analyticsVisitorId($ip, $ua);

    $pdo = db();

    // a runaway script or a refresh loop should not be able to flood the table
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM analytics_events
         WHERE visitor = ? AND created_at >= UTC_TIMESTAMP() - INTERVAL 10 MINUTE'
    );
    $st->execute([$visitor]);
    if ((int) $st->fetchColumn() >= 240) {
        done();
    }

    $screen = max(0, min(10000, (int) ($in['sw'] ?? 0)));
    $agent = analyticsParseAgent($ua, $screen);

    $country = strtoupper((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));
    if (!preg_match('/^[A-Z]{2}$/', $country) || $country === 'XX' || $country === 'T1') {
        $country = analyticsCountryFromTimezone($str('tz', 64));
    }

    $path = $str('p', 190);
    if ($path === '' || $path[0] !== '/') {
        $path = '/';
    }

    $session = preg_replace('/[^a-f0-9]/', '', strtolower($str('sid', 16))) ?? '';

    $row = [
        'type'     => $type,
        'visitor'  => $visitor,
        'session'  => $session,
        'path'     => $path,
        'source'   => 'Direct',
        'medium'   => 'direct',
        'referrer' => '',
        'campaign' => '',
        'country'  => $country,
        'device'   => $agent['device'],
        'browser'  => $agent['browser'],
        'os'       => $agent['os'],
        'label'    => '',
        'target'   => '',
        'kind'     => '',
        'section'  => '',
        'seconds'  => 0,
        'scroll'   => 0,
    ];

    if ($type === 'pageview') {
        $src = analyticsClassifySource(
            $str('r', 500),
            $str('us', 60),
            $str('um', 60),
            (string) parse_url(SITE_URL, PHP_URL_HOST)
        );
        $row['source'] = $src['source'];
        $row['medium'] = $src['medium'];
        $row['referrer'] = $src['referrer'];
        $row['campaign'] = $str('uc', 120);
    }

    if ($type === 'click') {
        $row['label'] = $str('l', 120);
        $row['target'] = $str('h', 255);
        $kind = $str('k', 20);
        $row['kind'] = in_array($kind, ['cta', 'phone', 'email', 'whatsapp', 'social', 'nav', 'form', 'link', 'filter', 'faq', 'button'], true) ? $kind : 'link';
        $row['section'] = preg_replace('/[^a-z0-9_-]/', '', strtolower($str('s', 40))) ?? '';
        if ($row['label'] === '') {
            done();
        }
    }

    if ($type === 'leave') {
        $row['seconds'] = max(0, min(86400, (int) ($in['d'] ?? 0)));
        $row['scroll'] = max(0, min(100, (int) ($in['sc'] ?? 0)));
    }

    $cols = array_keys($row);
    $sql = 'INSERT INTO analytics_events (created_at, ' . implode(', ', $cols) . ')
            VALUES (UTC_TIMESTAMP(), ' . implode(', ', array_fill(0, count($cols), '?')) . ')';
    $pdo->prepare($sql)->execute(array_values($row));

    analyticsPrune();
} catch (Throwable $e) {
    error_log('analytics beacon failed: ' . $e->getMessage());
}

done();
