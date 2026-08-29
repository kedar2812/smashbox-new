<?php

declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function jsonOut(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonFail(string $message, int $status = 400): never
{
    jsonOut(['ok' => false, 'error' => $message], $status);
}

function jsonBody(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/* -------------------------------------------------------------------------
   CSRF
   ------------------------------------------------------------------------- */

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfCheck(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

function requireCsrf(): void
{
    $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!csrfCheck(is_string($token) ? $token : null)) {
        jsonFail('Your session expired. Reload the page and try again.', 419);
    }
}

/* -------------------------------------------------------------------------
   Editable text sanitising

   The editors accept a small set of inline tags so a headline can keep its
   emphasis, and nothing else. Everything past that is stripped rather than
   escaped, so a paste from Word does not leave markup on the page.
   ------------------------------------------------------------------------- */

const ALLOWED_INLINE_TAGS = '<br><em><strong><b><i><span><a><sup><sub><u>';

function sanitizeInline(string $html): string
{
    $clean = strip_tags($html, ALLOWED_INLINE_TAGS);

    // drop event handlers and javascript: URLs from anything that survived
    $clean = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';
    $clean = preg_replace('/(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2/i', '$1="#"', $clean) ?? '';

    return trim($clean);
}

/** Strip every tag — used for labels and previews. */
function plainText(string $html): string
{
    return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function truncate(string $s, int $max = 90): string
{
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    return mb_strlen($s) <= $max ? $s : mb_substr($s, 0, $max - 1) . '…';
}

/* -------------------------------------------------------------------------
   Field registry
   ------------------------------------------------------------------------- */

function manifest(): array
{
    static $m = null;
    if ($m === null) {
        $raw = file_get_contents(MANIFEST_FILE);
        $m = json_decode($raw ?: '[]', true) ?: ['fields' => []];
    }
    return $m;
}

function fieldsByType(string $type): array
{
    return array_values(array_filter(
        manifest()['fields'] ?? [],
        static fn(array $f): bool => $f['type'] === $type
    ));
}

function fieldById(string $id): ?array
{
    foreach (manifest()['fields'] ?? [] as $f) {
        if ($f['id'] === $id) {
            return $f;
        }
    }
    return null;
}

/** Human name for a section key. */
function sectionLabel(string $key): string
{
    static $names = [
        'header'     => 'Navigation',
        'hero'       => 'Hero',
        'divisions'  => 'Divisions',
        'clients'    => 'Clients',
        'commit'     => 'Commitment',
        'process'    => 'Process',
        'work'       => 'Work',
        'services'   => 'Services',
        'industries' => 'Industries',
        'studio'     => 'Studio',
        'quotes'     => 'Quotes',
        'contact'    => 'Contact',
        'faq'        => 'FAQ',
        'footer'     => 'Footer',
        'section'    => 'Other',
        'page'       => 'Other',
    ];
    return $names[$key] ?? ucfirst($key);
}

function timeAgo(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }
    $diff = time() - $ts;

    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return $m . ($m === 1 ? ' minute ago' : ' minutes ago');
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return $h . ($h === 1 ? ' hour ago' : ' hours ago');
    }
    if ($diff < 604800) {
        $d = (int) floor($diff / 86400);
        return $d . ($d === 1 ? ' day ago' : ' days ago');
    }
    return date('j M Y', $ts);
}

function formatBytes(int $b): string
{
    if ($b >= 1048576) {
        return round($b / 1048576, 1) . ' MB';
    }
    if ($b >= 1024) {
        return round($b / 1024) . ' KB';
    }
    return $b . ' B';
}

/**
 * Turn a stored image value into something the dashboard can preview.
 * Site-relative paths need a ../ because the admin lives one level down.
 */
function publicSrc(string $value): string
{
    $v = trim($value);
    if ($v === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $v) || str_starts_with($v, 'data:')) {
        return $v;
    }
    return '../' . ltrim($v, '/');
}

/* -------------------------------------------------------------------------
   Client marquee

   The strip of client names under the divisions is a list, not a block of
   copy, so it does not fit the field registry. It is stored whole, as a JSON
   array in settings, and written back into the marquee on every publish.
   ------------------------------------------------------------------------- */

const CLIENTS_SETTING = 'client_marquee';
const CLIENTS_MAX = 60;
const CLIENT_NAME_MAX = 60;

/** Tidy one name: single spaces, no markup, capped length. */
function normaliseClientName(string $name): string
{
    $n = str_replace("\u{00A0}", ' ', strip_tags($name));
    $n = trim(preg_replace('/\s+/u', ' ', $n) ?? '');
    if (mb_strlen($n) > CLIENT_NAME_MAX) {
        $n = trim(mb_substr($n, 0, CLIENT_NAME_MAX));
    }
    return $n;
}

/** The names baked into template.html, used until the dashboard sets its own. */
function defaultClientNames(): array
{
    static $names = null;
    if ($names !== null) {
        return $names;
    }

    $names = [];
    $html = @file_get_contents(TEMPLATE_FILE);
    if ($html === false) {
        return $names;
    }

    if (!preg_match('#<div[^>]*data-cms-list="clients"[^>]*>(.*?)</div>#si', $html, $m)) {
        return $names;
    }

    preg_match_all('#<span[^>]*>(.*?)</span>#si', $m[1], $found);
    foreach ($found[1] as $raw) {
        $n = normaliseClientName(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        // the track carries each name twice so the loop has no seam
        if ($n !== '' && !in_array($n, $names, true)) {
            $names[] = $n;
        }
    }

    return $names;
}

/** True once the dashboard has saved its own list. */
function clientNamesAreCustom(): bool
{
    return settingGet(CLIENTS_SETTING) !== null;
}

/** The list currently on the site. */
function clientNames(): array
{
    $raw = settingGet(CLIENTS_SETTING);
    if ($raw === null) {
        return defaultClientNames();
    }

    $list = json_decode($raw, true);
    if (!is_array($list)) {
        return defaultClientNames();
    }

    $out = [];
    foreach ($list as $n) {
        $n = normaliseClientName((string) $n);
        if ($n !== '') {
            $out[] = $n;
        }
    }
    return $out;
}

/** Store a list. Order is kept as given; blanks and duplicates are dropped. */
function saveClientNames(array $names): array
{
    $clean = [];
    foreach ($names as $n) {
        $n = normaliseClientName((string) $n);
        if ($n === '') {
            continue;
        }
        foreach ($clean as $have) {
            if (mb_strtolower($have) === mb_strtolower($n)) {
                continue 2;
            }
        }
        $clean[] = $n;
        if (count($clean) >= CLIENTS_MAX) {
            break;
        }
    }

    settingSet(CLIENTS_SETTING, json_encode(array_values($clean), JSON_UNESCAPED_UNICODE));
    return $clean;
}
