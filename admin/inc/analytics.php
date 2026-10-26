<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Website analytics.
 *
 * The public site sends a small beacon to /api/track.php for every page view,
 * every click on a link or button, and once more when the visitor leaves (how
 * long they stayed, how far they scrolled). Nothing is stored that identifies
 * a person: no cookies, no IP addresses. A visitor is a hash of their address
 * and browser with a salt that changes every day, so the same person can be
 * counted once per day and never followed from one day to the next.
 *
 * "Where from" is worked out without sending anyone's address to a third
 * party: the country comes from the browser's own time zone (or the CDN's
 * country header when there is one), and the source from the referring site
 * or the campaign tags on the link.
 *
 * Times are stored in UTC and reported in the studio's time, India Standard
 * Time, which has no daylight saving — a fixed offset is exact.
 */

const ANALYTICS_SCHEMA = '1';
const ANALYTICS_OFFSET_MINUTES = 330;      // IST, UTC+05:30
const ANALYTICS_RETENTION_DAYS = 400;

function analyticsEnsureSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (settingGet('analytics_schema') === ANALYTICS_SCHEMA) {
        return;
    }

    db()->exec(
        "CREATE TABLE IF NOT EXISTS analytics_events (
          id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          created_at DATETIME NOT NULL,
          type       ENUM('pageview','click','leave') NOT NULL,
          visitor    CHAR(16) NOT NULL,
          session    CHAR(16) NOT NULL DEFAULT '',
          path       VARCHAR(190) NOT NULL DEFAULT '/',
          source     VARCHAR(60) NOT NULL DEFAULT 'Direct',
          medium     VARCHAR(20) NOT NULL DEFAULT 'direct',
          referrer   VARCHAR(190) NOT NULL DEFAULT '',
          campaign   VARCHAR(120) NOT NULL DEFAULT '',
          country    CHAR(2) NOT NULL DEFAULT '',
          device     VARCHAR(10) NOT NULL DEFAULT 'desktop',
          browser    VARCHAR(30) NOT NULL DEFAULT '',
          os         VARCHAR(30) NOT NULL DEFAULT '',
          label      VARCHAR(120) NOT NULL DEFAULT '',
          target     VARCHAR(255) NOT NULL DEFAULT '',
          kind       VARCHAR(20) NOT NULL DEFAULT '',
          section    VARCHAR(40) NOT NULL DEFAULT '',
          seconds    INT UNSIGNED NOT NULL DEFAULT 0,
          scroll     TINYINT UNSIGNED NOT NULL DEFAULT 0,
          PRIMARY KEY (id),
          KEY idx_ae_created (created_at, type),
          KEY idx_ae_visitor (visitor, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    settingSet('analytics_schema', ANALYTICS_SCHEMA);
}

/* -------------------------------------------------------------------------
   Classifying a visit
   ------------------------------------------------------------------------- */

/** A salt that changes once a day, so visitor hashes cannot be joined up across days. */
function analyticsDailySalt(): string
{
    $today = gmdate('Y-m-d');
    $raw = settingGet('analytics_salt');
    $parts = $raw !== null ? explode('|', $raw, 2) : [];

    if (count($parts) === 2 && $parts[0] === $today) {
        return $parts[1];
    }

    $salt = bin2hex(random_bytes(16));
    settingSet('analytics_salt', $today . '|' . $salt);
    return $salt;
}

function analyticsVisitorId(string $ip, string $ua): string
{
    return substr(hash('sha256', analyticsDailySalt() . '|' . $ip . '|' . $ua), 0, 16);
}

function analyticsIsBot(string $ua): bool
{
    if ($ua === '') {
        return true;
    }
    return (bool) preg_match(
        '/bot|crawl|spider|slurp|archiver|preview|headless|lighthouse|pagespeed|pingdom|uptime|monitor|curl|wget|python|scrapy|httpclient|java\/|go-http|facebookexternalhit|embedly|quora link/i',
        $ua
    );
}

/** @return array{device:string, browser:string, os:string} */
function analyticsParseAgent(string $ua, int $screenWidth): array
{
    $os = match (true) {
        (bool) preg_match('/iPhone|iPod/i', $ua)            => 'iOS',
        (bool) preg_match('/iPad/i', $ua)                   => 'iPadOS',
        (bool) preg_match('/Android/i', $ua)                => 'Android',
        (bool) preg_match('/Windows/i', $ua)                => 'Windows',
        (bool) preg_match('/Mac OS X|Macintosh/i', $ua)     => 'macOS',
        (bool) preg_match('/CrOS/i', $ua)                   => 'ChromeOS',
        (bool) preg_match('/Linux/i', $ua)                  => 'Linux',
        default                                             => 'Other',
    };

    $browser = match (true) {
        (bool) preg_match('/Instagram/i', $ua)              => 'Instagram app',
        (bool) preg_match('/FBAN|FBAV|FB_IAB/i', $ua)       => 'Facebook app',
        (bool) preg_match('/LinkedInApp/i', $ua)            => 'LinkedIn app',
        (bool) preg_match('/Edg\//i', $ua)                  => 'Edge',
        (bool) preg_match('/OPR\/|Opera/i', $ua)            => 'Opera',
        (bool) preg_match('/SamsungBrowser/i', $ua)         => 'Samsung Internet',
        (bool) preg_match('/Firefox|FxiOS/i', $ua)          => 'Firefox',
        (bool) preg_match('/Chrome|CriOS/i', $ua)           => 'Chrome',
        (bool) preg_match('/Safari/i', $ua)                 => 'Safari',
        default                                             => 'Other',
    };

    $isTablet = (bool) preg_match('/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $ua);
    $isMobile = !$isTablet && (bool) preg_match('/Mobi|iPhone|iPod|Android.*Mobile|Windows Phone/i', $ua);

    // iPadOS asks for the desktop site and says "Macintosh"; the screen gives it away
    if (!$isTablet && !$isMobile && $os === 'macOS' && $screenWidth > 0 && $screenWidth <= 1366
        && preg_match('/Safari/i', $ua) && !preg_match('/Chrome/i', $ua)) {
        // a real Mac at this width is common too, so only trust touch-sized screens
        if ($screenWidth <= 1032) {
            $isTablet = true;
            $os = 'iPadOS';
        }
    }

    $device = $isTablet ? 'tablet' : ($isMobile ? 'mobile' : 'desktop');

    return ['device' => $device, 'browser' => $browser, 'os' => $os];
}

/** Country from the browser's time zone. Empty when it cannot be told. */
function analyticsCountryFromTimezone(string $tz): string
{
    static $aliases = [
        'Asia/Calcutta' => 'IN', 'Asia/Saigon' => 'VN', 'Asia/Katmandu' => 'NP',
        'Asia/Rangoon' => 'MM', 'Asia/Dacca' => 'BD', 'Asia/Thimbu' => 'BT',
        'Asia/Ulan_Bator' => 'MN', 'Asia/Macao' => 'MO', 'Asia/Chongqing' => 'CN',
        'Asia/Harbin' => 'CN', 'Asia/Tel_Aviv' => 'IL', 'Asia/Istanbul' => 'TR',
        'Europe/Kiev' => 'UA', 'Europe/Belfast' => 'GB', 'America/Buenos_Aires' => 'AR',
        'America/Indianapolis' => 'US', 'America/Louisville' => 'US', 'America/Montreal' => 'CA',
        'America/Godthab' => 'GL', 'Australia/ACT' => 'AU', 'Australia/NSW' => 'AU',
        'Australia/Canberra' => 'AU', 'Pacific/Auckland' => 'NZ', 'US/Eastern' => 'US',
        'US/Central' => 'US', 'US/Mountain' => 'US', 'US/Pacific' => 'US',
        'Canada/Eastern' => 'CA', 'Atlantic/Faeroe' => 'FO', 'Africa/Asmera' => 'ER',
        'GB' => 'GB', 'Singapore' => 'SG', 'Japan' => 'JP', 'Hongkong' => 'HK',
    ];

    if ($tz === '' || strlen($tz) > 64) {
        return '';
    }
    if (isset($aliases[$tz])) {
        return $aliases[$tz];
    }

    try {
        $code = (new DateTimeZone($tz))->getLocation()['country_code'] ?? '';
    } catch (Throwable $e) {
        return '';
    }

    return preg_match('/^[A-Z]{2}$/', (string) $code) ? (string) $code : '';
}

function analyticsCountryName(string $code): string
{
    static $names = null;
    if ($names === null) {
        $names = require __DIR__ . '/countries.php';
    }
    return $names[$code] ?? ($code !== '' ? $code : 'Unknown');
}

/** Regional-indicator flag for a two-letter code. */
function analyticsFlag(string $code): string
{
    if (!preg_match('/^[A-Z]{2}$/', $code)) {
        return '';
    }
    return mb_chr(0x1F1E6 + ord($code[0]) - 65) . mb_chr(0x1F1E6 + ord($code[1]) - 65);
}

/**
 * Name the place a visitor came from.
 *
 * Campaign tags win over the referrer — a link in an Instagram bio tagged
 * utm_source=instagram is Instagram whatever the browser reports.
 *
 * @return array{source:string, medium:string, referrer:string}
 */
function analyticsClassifySource(string $referrer, string $utmSource, string $utmMedium, string $ownHost): array
{
    static $known = [
        // host fragment => [name, medium]
        'google.'        => ['Google', 'search'],
        'bing.'          => ['Bing', 'search'],
        'duckduckgo.'    => ['DuckDuckGo', 'search'],
        'yahoo.'         => ['Yahoo', 'search'],
        'ecosia.'        => ['Ecosia', 'search'],
        'yandex.'        => ['Yandex', 'search'],
        'baidu.'         => ['Baidu', 'search'],
        'instagram.'     => ['Instagram', 'social'],
        'facebook.'      => ['Facebook', 'social'],
        'fb.me'          => ['Facebook', 'social'],
        'fb.com'         => ['Facebook', 'social'],
        'linkedin.'      => ['LinkedIn', 'social'],
        'lnkd.in'        => ['LinkedIn', 'social'],
        't.co'           => ['X (Twitter)', 'social'],
        'twitter.'       => ['X (Twitter)', 'social'],
        'x.com'          => ['X (Twitter)', 'social'],
        'youtube.'       => ['YouTube', 'social'],
        'youtu.be'       => ['YouTube', 'social'],
        'whatsapp.'      => ['WhatsApp', 'social'],
        'wa.me'          => ['WhatsApp', 'social'],
        'pinterest.'     => ['Pinterest', 'social'],
        'reddit.'        => ['Reddit', 'social'],
        'threads.'       => ['Threads', 'social'],
        'behance.'       => ['Behance', 'social'],
        'justdial.'      => ['Justdial', 'referral'],
        'chatgpt.'       => ['ChatGPT', 'ai'],
        'openai.'        => ['ChatGPT', 'ai'],
        'perplexity.'    => ['Perplexity', 'ai'],
        'gemini.google'  => ['Gemini', 'ai'],
        'copilot.'       => ['Copilot', 'ai'],
        'mail.google'    => ['Gmail', 'email'],
        'outlook.'       => ['Outlook', 'email'],
    ];

    static $apps = [
        'com.google.android.gm'               => ['Gmail', 'email'],
        'com.google.android.googlequicksearchbox' => ['Google', 'search'],
        'com.instagram.android'               => ['Instagram', 'social'],
        'com.facebook.katana'                 => ['Facebook', 'social'],
        'com.linkedin.android'                => ['LinkedIn', 'social'],
        'com.whatsapp'                        => ['WhatsApp', 'social'],
        'com.twitter.android'                 => ['X (Twitter)', 'social'],
        'com.google.android.youtube'          => ['YouTube', 'social'],
    ];

    $utmSource = strtolower(trim($utmSource));
    if ($utmSource !== '') {
        foreach ($known as $frag => [$name, $medium]) {
            if (str_contains($utmSource . '.', rtrim($frag, '.') . '.') || strtolower($name) === $utmSource) {
                return ['source' => $name, 'medium' => 'campaign', 'referrer' => mb_substr($referrer, 0, 190)];
            }
        }
        $pretty = ucwords(str_replace(['_', '-', '+'], ' ', mb_substr($utmSource, 0, 40)));
        return ['source' => $pretty, 'medium' => 'campaign', 'referrer' => mb_substr($referrer, 0, 190)];
    }

    $referrer = trim($referrer);
    if ($referrer === '') {
        return ['source' => 'Direct', 'medium' => 'direct', 'referrer' => ''];
    }

    if (str_starts_with($referrer, 'android-app://')) {
        $pkg = strtolower(explode('/', substr($referrer, 14))[0]);
        if (isset($apps[$pkg])) {
            return ['source' => $apps[$pkg][0], 'medium' => $apps[$pkg][1], 'referrer' => $pkg];
        }
        return ['source' => 'Android app', 'medium' => 'referral', 'referrer' => mb_substr($pkg, 0, 190)];
    }

    $host = strtolower((string) parse_url($referrer, PHP_URL_HOST));
    $host = preg_replace('/^(www\.|m\.|l\.|lm\.|mobile\.)/', '', $host) ?? $host;

    if ($host === '') {
        return ['source' => 'Direct', 'medium' => 'direct', 'referrer' => ''];
    }

    // the site sending people to itself is not a source
    $own = preg_replace('/^www\./', '', strtolower($ownHost)) ?? $ownHost;
    if ($own !== '' && ($host === $own || str_ends_with($host, '.' . $own))) {
        return ['source' => 'Direct', 'medium' => 'direct', 'referrer' => ''];
    }

    foreach ($known as $frag => [$name, $medium]) {
        if (str_contains($host . '/', $frag) || str_starts_with($host, rtrim($frag, '.') . '.') || $host === rtrim($frag, '.')) {
            return ['source' => $name, 'medium' => $medium, 'referrer' => $host];
        }
    }

    return ['source' => mb_substr($host, 0, 60), 'medium' => 'referral', 'referrer' => $host];
}

/* -------------------------------------------------------------------------
   Reporting
   ------------------------------------------------------------------------- */

/**
 * The window a range covers, in UTC, plus the window before it for comparison.
 *
 * @return array{key:string, label:string, start:string, end:string, prevStart:string, prevEnd:string, buckets:string, days:int}
 */
function analyticsRange(string $key): array
{
    $offset = ANALYTICS_OFFSET_MINUTES * 60;
    $nowLocal = time() + $offset;
    $todayLocal = intdiv($nowLocal, 86400) * 86400;          // local midnight, as a UTC-epoch-like number

    $days = match ($key) {
        'today' => 1,
        '30d'   => 30,
        '90d'   => 90,
        default => 7,
    };
    if (!in_array($key, ['today', '7d', '30d', '90d'], true)) {
        $key = '7d';
    }

    $startLocal = $todayLocal - ($days - 1) * 86400;
    $endLocal = $nowLocal;
    $len = $days * 86400;

    $toUtc = static fn(int $local): string => gmdate('Y-m-d H:i:s', $local - $offset);

    return [
        'key'       => $key,
        'label'     => match ($key) {
            'today' => 'Today',
            '30d'   => 'Last 30 days',
            '90d'   => 'Last 90 days',
            default => 'Last 7 days',
        },
        'start'     => $toUtc($startLocal),
        'end'       => $toUtc($endLocal),
        'prevStart' => $toUtc($startLocal - $len),
        'prevEnd'   => $toUtc($endLocal - $len),
        'buckets'   => $key === 'today' ? 'hour' : 'day',
        'days'      => $days,
        'startLocal'=> $startLocal,
        'nowLocal'  => $nowLocal,
    ];
}

/** Totals for one window. */
function analyticsTotals(string $start, string $end): array
{
    $st = db()->prepare(
        "SELECT
           COUNT(DISTINCT CASE WHEN type = 'pageview' THEN visitor END) AS visitors,
           SUM(type = 'pageview') AS pageviews,
           SUM(type = 'click') AS clicks,
           AVG(CASE WHEN type = 'leave' AND seconds BETWEEN 1 AND 3600 THEN seconds END) AS seconds
         FROM analytics_events
         WHERE created_at >= ? AND created_at < ?"
    );
    $st->execute([$start, $end]);
    $r = $st->fetch() ?: [];

    return [
        'visitors'  => (int) ($r['visitors'] ?? 0),
        'pageviews' => (int) ($r['pageviews'] ?? 0),
        'clicks'    => (int) ($r['clicks'] ?? 0),
        'seconds'   => (int) round((float) ($r['seconds'] ?? 0)),
    ];
}

/** Enquiries in the same window, from the leads table (which keeps the server's own clock). */
function analyticsLeadCount(int $secondsAgoStart, int $secondsAgoEnd): int
{
    $st = db()->prepare(
        'SELECT COUNT(*) FROM leads
         WHERE created_at >= NOW() - INTERVAL ? SECOND AND created_at < NOW() - INTERVAL ? SECOND'
    );
    $st->execute([$secondsAgoStart, $secondsAgoEnd]);
    return (int) $st->fetchColumn();
}

/** Group a window by one column. */
function analyticsBreakdown(string $column, string $start, string $end, int $limit = 8, string $type = 'pageview'): array
{
    $allowed = ['source', 'country', 'device', 'browser', 'os', 'campaign', 'medium'];
    if (!in_array($column, $allowed, true)) {
        return [];
    }

    $extra = $column === 'campaign' ? " AND campaign <> ''" : '';

    $st = db()->prepare(
        "SELECT $column AS k, COUNT(DISTINCT visitor) AS visitors, COUNT(*) AS views
         FROM analytics_events
         WHERE type = ? AND created_at >= ? AND created_at < ? $extra
         GROUP BY $column
         ORDER BY visitors DESC, views DESC
         LIMIT " . (int) $limit
    );
    $st->execute([$type, $start, $end]);

    return array_map(static fn(array $r): array => [
        'key'      => (string) $r['k'],
        'visitors' => (int) $r['visitors'],
        'views'    => (int) $r['views'],
    ], $st->fetchAll());
}

function analyticsTopClicks(string $start, string $end, int $limit = 10): array
{
    $st = db()->prepare(
        "SELECT label, kind, MAX(target) AS target, COUNT(*) AS clicks, COUNT(DISTINCT visitor) AS people
         FROM analytics_events
         WHERE type = 'click' AND created_at >= ? AND created_at < ? AND label <> ''
         GROUP BY label, kind
         ORDER BY clicks DESC
         LIMIT " . (int) $limit
    );
    $st->execute([$start, $end]);

    return array_map(static fn(array $r): array => [
        'label'  => (string) $r['label'],
        'kind'   => (string) $r['kind'],
        'target' => (string) $r['target'],
        'clicks' => (int) $r['clicks'],
        'people' => (int) $r['people'],
    ], $st->fetchAll());
}

/** Clicks grouped by what kind of thing was clicked: calls, emails, WhatsApp… */
function analyticsClickKinds(string $start, string $end): array
{
    $st = db()->prepare(
        "SELECT kind, COUNT(*) AS clicks
         FROM analytics_events
         WHERE type = 'click' AND created_at >= ? AND created_at < ?
         GROUP BY kind ORDER BY clicks DESC"
    );
    $st->execute([$start, $end]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[(string) $r['kind']] = (int) $r['clicks'];
    }
    return $out;
}

/** How far down the page people get: share of visits reaching each quarter. */
function analyticsScrollDepth(string $start, string $end): array
{
    $st = db()->prepare(
        "SELECT
           COUNT(*) AS n,
           SUM(scroll >= 25) AS q1,
           SUM(scroll >= 50) AS q2,
           SUM(scroll >= 75) AS q3,
           SUM(scroll >= 90) AS q4
         FROM analytics_events
         WHERE type = 'leave' AND created_at >= ? AND created_at < ?"
    );
    $st->execute([$start, $end]);
    $r = $st->fetch() ?: [];
    $n = (int) ($r['n'] ?? 0);

    $pct = static fn($v): int => $n > 0 ? (int) round(((int) $v / $n) * 100) : 0;

    return [
        'visits' => $n,
        'steps'  => [
            ['label' => 'A quarter of the way', 'pct' => $pct($r['q1'] ?? 0)],
            ['label' => 'Halfway',              'pct' => $pct($r['q2'] ?? 0)],
            ['label' => 'Three quarters',       'pct' => $pct($r['q3'] ?? 0)],
            ['label' => 'All the way down',     'pct' => $pct($r['q4'] ?? 0)],
        ],
    ];
}

/** Visitors and page views per hour (today) or per day. */
function analyticsSeries(array $range): array
{
    $off = ANALYTICS_OFFSET_MINUTES;
    $isHour = $range['buckets'] === 'hour';

    $bucketSql = $isHour
        ? "DATE_FORMAT(created_at + INTERVAL $off MINUTE, '%Y-%m-%d %H:00')"
        : "DATE(created_at + INTERVAL $off MINUTE)";

    $st = db()->prepare(
        "SELECT $bucketSql AS b,
                COUNT(DISTINCT visitor) AS visitors,
                COUNT(*) AS views
         FROM analytics_events
         WHERE type = 'pageview' AND created_at >= ? AND created_at < ?
         GROUP BY b"
    );
    $st->execute([$range['start'], $range['end']]);

    $byKey = [];
    foreach ($st->fetchAll() as $r) {
        $byKey[(string) $r['b']] = ['visitors' => (int) $r['visitors'], 'views' => (int) $r['views']];
    }

    $points = [];
    if ($isHour) {
        $hours = (int) gmdate('G', $range['nowLocal']);
        for ($h = 0; $h <= $hours; $h++) {
            $k = gmdate('Y-m-d', $range['startLocal']) . sprintf(' %02d:00', $h);
            $points[] = [
                'key'      => $k,
                'label'    => date('ga', mktime($h, 0, 0)),
                'visitors' => $byKey[$k]['visitors'] ?? 0,
                'views'    => $byKey[$k]['views'] ?? 0,
            ];
        }
    } else {
        for ($i = 0; $i < $range['days']; $i++) {
            $t = $range['startLocal'] + $i * 86400;
            $k = gmdate('Y-m-d', $t);
            $points[] = [
                'key'      => $k,
                'label'    => gmdate('j M', $t),
                'visitors' => $byKey[$k]['visitors'] ?? 0,
                'views'    => $byKey[$k]['views'] ?? 0,
            ];
        }
    }

    return $points;
}

/** People on the site in the last five minutes. */
function analyticsLiveNow(): int
{
    return (int) db()->query(
        "SELECT COUNT(DISTINCT visitor) FROM analytics_events
         WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 5 MINUTE"
    )->fetchColumn();
}

/** Everything the analytics page shows, for one range. */
function analyticsReport(string $key): array
{
    analyticsEnsureSchema();

    $r = analyticsRange($key);
    $now = analyticsTotals($r['start'], $r['end']);
    $prev = analyticsTotals($r['prevStart'], $r['prevEnd']);

    // leads keep the server's clock, so they are counted by age instead
    $nowEpoch = time();
    $startAgo = $nowEpoch - (strtotime($r['start'] . ' UTC') ?: $nowEpoch);
    $len = $r['days'] * 86400;
    $now['leads'] = analyticsLeadCount($startAgo, 0);
    $prev['leads'] = analyticsLeadCount($startAgo + $len, $len);

    $countries = array_map(static function (array $row): array {
        $row['name'] = analyticsCountryName($row['key']);
        $row['flag'] = analyticsFlag($row['key']);
        return $row;
    }, analyticsBreakdown('country', $r['start'], $r['end'], 8));

    return [
        'range'     => ['key' => $r['key'], 'label' => $r['label'], 'buckets' => $r['buckets']],
        'totals'    => $now,
        'previous'  => $prev,
        'series'    => analyticsSeries($r),
        'sources'   => analyticsBreakdown('source', $r['start'], $r['end'], 8),
        'mediums'   => analyticsBreakdown('medium', $r['start'], $r['end'], 8),
        'countries' => $countries,
        'devices'   => analyticsBreakdown('device', $r['start'], $r['end'], 3),
        'browsers'  => analyticsBreakdown('browser', $r['start'], $r['end'], 6),
        'campaigns' => analyticsBreakdown('campaign', $r['start'], $r['end'], 6),
        'clicks'    => analyticsTopClicks($r['start'], $r['end'], 10),
        'kinds'     => analyticsClickKinds($r['start'], $r['end']),
        'scroll'    => analyticsScrollDepth($r['start'], $r['end']),
        'live'      => analyticsLiveNow(),
        'hasData'   => (int) db()->query('SELECT EXISTS(SELECT 1 FROM analytics_events)')->fetchColumn() === 1,
    ];
}

/** Occasionally drop events past the retention window. */
function analyticsPrune(): void
{
    if (random_int(1, 500) !== 1) {
        return;
    }
    db()->prepare('DELETE FROM analytics_events WHERE created_at < UTC_TIMESTAMP() - INTERVAL ? DAY LIMIT 5000')
        ->execute([ANALYTICS_RETENTION_DAYS]);
}
