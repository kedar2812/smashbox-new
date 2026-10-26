<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/fields.php';
require_once __DIR__ . '/render.php';

/** Inline icon set — one stroke weight, drawn on a 24 grid. */
function icon(string $name, string $cls = ''): string
{
    $paths = [
        'home'    => '<path d="M3.5 10.5 12 3.8l8.5 6.7"/><path d="M5.5 9v10.2a.8.8 0 0 0 .8.8H10v-5.5h4V20h3.7a.8.8 0 0 0 .8-.8V9"/>',
        'chart'   => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'cursor'  => '<path d="m5 3 14 7.5-6 1.6L10.5 18 5 3Z"/><path d="m13 12 5.5 5.5"/>',
        'images'  => '<rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="1.8"/><path d="m21 16-4.8-4.8L7 20"/>',
        'text'    => '<path d="M5 6.5V5h14v1.5M12 5v14M9 19h6"/>',
        'clients' => '<path d="M4 21V8l8-5 8 5v13"/><path d="M9 21v-6h6v6"/><path d="M2.5 21h19"/>',
        'link'    => '<path d="M10 13.5a4 4 0 0 0 6 .4l2.8-2.8a4 4 0 0 0-5.7-5.7l-1.4 1.4"/><path d="M14 10.5a4 4 0 0 0-6-.4l-2.8 2.8a4 4 0 0 0 5.7 5.7l1.4-1.4"/>',
        'leads'   => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7.5 8 5.5 8-5.5"/>',
        'bell'    => '<path d="M18 9.5a6 6 0 1 0-12 0c0 5.5-2.5 7-2.5 7h17s-2.5-1.5-2.5-7"/><path d="M13.7 20a2 2 0 0 1-3.4 0"/>',
        'close'   => '<path d="M18 6 6 18M6 6l12 12"/>',
        'check'   => '<path d="M20 6 9 17l-5-5"/>',
        'alert'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.4v.1"/>',
        'upload'  => '<path d="M12 15V3.5M7 8l5-5 5 5"/><path d="M20 15v3.5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V15"/>',
        'out'     => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v4.5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 18.5v-11A1.5 1.5 0 0 1 5.5 6H10"/>',
        'signout' => '<path d="M15 4h3.5A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5H15"/><path d="M10 16.5 5.5 12 10 7.5M5.5 12H15"/>',
        'eye'     => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
        'undo'    => '<path d="M4 9h11a5 5 0 0 1 0 10h-4"/><path d="M8 5 4 9l4 4"/>',
        'user'    => '<circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>',
        'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'search'  => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.4-4.4"/>',
        'sun'     => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M4.6 4.6 6 6M18 18l1.4 1.4M2.5 12h2M19.5 12h2M4.6 19.4 6 18M18 6l1.4-1.4"/>',
        'moon'    => '<path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11Z"/>',
        'auto'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 3.5v17" /><path d="M12 3.5a8.5 8.5 0 0 1 0 17Z" fill="currentColor" stroke="none"/>',
        'phone'   => '<path d="M5 4h3.5l1.5 4.5-2 1.5a11 11 0 0 0 6 6l1.5-2 4.5 1.5V19a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
        'desktop' => '<rect x="2.5" y="4" width="19" height="12.5" rx="2"/><path d="M8.5 20.5h7M12 16.5v4"/>',
        'tablet'  => '<rect x="5" y="2.5" width="14" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
        'mobile'  => '<rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
        'reload'  => '<path d="M20 11.5a8 8 0 1 0-2.4 5.7"/><path d="M20 5v6.5h-6.5"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'trash'   => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4.5h6V7"/>',
        'grip'    => '<circle cx="9" cy="6" r="1.2"/><circle cx="15" cy="6" r="1.2"/><circle cx="9" cy="12" r="1.2"/><circle cx="15" cy="12" r="1.2"/><circle cx="9" cy="18" r="1.2"/><circle cx="15" cy="18" r="1.2"/>',
        'live'    => '<circle cx="12" cy="12" r="3"/><path d="M7.8 7.8a6 6 0 0 0 0 8.4M16.2 16.2a6 6 0 0 0 0-8.4M5 5a10 10 0 0 0 0 14M19 19a10 10 0 0 0 0-14"/>',
        'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'chevron' => '<path d="m9 6 6 6-6 6"/>',
        'globe'   => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'pointer' => '<path d="M9 11V4.5a1.5 1.5 0 0 1 3 0V10m0-1a1.5 1.5 0 0 1 3 0v1.5m0-.5a1.5 1.5 0 0 1 3 0V15a6 6 0 0 1-6 6h-1a6 6 0 0 1-4.6-2.2L4 15.5a1.6 1.6 0 0 1 2.4-2L9 15"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'device'  => '<rect x="2.5" y="5" width="13" height="10" rx="1.5"/><rect x="15.5" y="8" width="6" height="11" rx="1.5"/><path d="M6 19h6"/>',
    ];

    $d = $paths[$name] ?? '';
    return '<svg class="ico ' . e($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . $d . '</svg>';
}

/** Leads that have never been opened. */
function unseenLeads(int $limit = 12): array
{
    $st = db()->prepare(
        'SELECT id, email, name, source, message, created_at
         FROM leads
         WHERE seen_at IS NULL AND status <> "archived"
         ORDER BY created_at DESC
         LIMIT ' . (int) $limit
    );
    $st->execute();
    return $st->fetchAll();
}

function unseenCount(): int
{
    return (int) db()->query(
        'SELECT COUNT(*) FROM leads WHERE seen_at IS NULL AND status <> "archived"'
    )->fetchColumn();
}

function newLeadCount(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM leads WHERE status = "new"')->fetchColumn();
}

function initials(string $name, string $email): string
{
    $base = trim($name) !== '' ? trim($name) : $email;
    $parts = preg_split('/[\s@._-]+/', $base) ?: [];
    $parts = array_values(array_filter($parts));
    if (!$parts) {
        return '?';
    }
    $out = mb_strtoupper(mb_substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $out .= mb_strtoupper(mb_substr($parts[1], 0, 1));
    }
    return $out;
}

/** Where a lead came from, in words. */
function leadSourceLabel(string $source): string
{
    return match ($source) {
        'hero'       => 'Top of page',
        'contact'    => 'Brief form',
        'newsletter' => 'Newsletter',
        default      => ucfirst($source),
    };
}

function firstName(array $user): string
{
    $n = trim((string) $user['name']);
    if ($n === '' || $n === 'Smashbox Studio') {
        return '';
    }
    return explode(' ', $n)[0];
}

/**
 * Open a dashboard page.
 *
 * @param array{subtitle?:string, wide?:bool, actions?:string, head?:bool} $opts
 */
function layoutTop(string $page, array $user, string $title, array $opts = []): void
{
    // a deploy may have brought a new template since the last publish
    try {
        republishIfTemplateChanged();
    } catch (Throwable $e) {
        error_log('auto-republish failed: ' . $e->getMessage());
    }

    $unseen = unseenLeads();
    $unseenN = unseenCount();
    $newN = newLeadCount();
    $wide = !empty($opts['wide']);

    $groups = [
        '' => [
            ['key' => 'home',      'href' => 'home.php',      'icon' => 'home',    'label' => 'Overview'],
            ['key' => 'analytics', 'href' => 'analytics.php', 'icon' => 'chart',   'label' => 'Analytics'],
        ],
        'Website' => [
            ['key' => 'editor',    'href' => 'editor.php',    'icon' => 'cursor',  'label' => 'Edit visually'],
            ['key' => 'text',      'href' => 'text.php',      'icon' => 'text',    'label' => 'Words'],
            ['key' => 'images',    'href' => 'images.php',    'icon' => 'images',  'label' => 'Pictures'],
            ['key' => 'clients',   'href' => 'clients.php',   'icon' => 'clients', 'label' => 'Client strip'],
            ['key' => 'links',     'href' => 'links.php',     'icon' => 'link',    'label' => 'Contact & links'],
        ],
        'Enquiries' => [
            ['key' => 'leads',     'href' => 'leads.php',     'icon' => 'leads',   'label' => 'Leads', 'count' => $newN],
        ],
    ];
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light dark">
<title><?= e($title) ?> · Smashbox Studio Desk</title>
<link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="assets/favicon-180.png">
<script>
(function () {
  try {
    var t = localStorage.getItem('sbx-desk-theme');
    if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
  } catch (e) {}
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Inter+Tight:ital,wght@0,400;0,500;0,600;1,400&family=Instrument+Serif:ital@1&display=swap">
<link rel="stylesheet" href="assets/admin.css?v=<?= filemtime(ADMIN_DIR . '/assets/admin.css') ?>">
</head>
<body data-page="<?= e($page) ?>" class="<?= $wide ? 'is-wide' : '' ?>">
<script>window.SBX = <?= json_encode([
    'csrf'        => csrfToken(),
    'siteUrl'     => SITE_URL,
    'studioEmail' => STUDIO_EMAIL,
    'studioName'  => STUDIO_NAME,
    'maxUpload'   => MAX_UPLOAD_BYTES,
], JSON_UNESCAPED_SLASHES) ?>;</script>

<div class="app">
  <aside class="sidebar" id="sidebar" aria-label="Dashboard">
    <a class="sb-brand" href="home.php">
      <span class="sb-mark"><img src="assets/smashbox-logo.png" alt=""></span>
      <span class="sb-name">Smashbox<small>Studio Desk</small></span>
    </a>

    <nav class="sb-nav">
      <?php foreach ($groups as $heading => $items): ?>
        <div class="sb-group">
          <?php if ($heading !== ''): ?><p class="sb-heading"><?= e($heading) ?></p><?php endif; ?>
          <?php foreach ($items as $n): $on = $page === $n['key']; ?>
            <a class="sb-item<?= $on ? ' is-on' : '' ?>" href="<?= e($n['href']) ?>"<?= $on ? ' aria-current="page"' : '' ?>>
              <?= icon($n['icon']) ?>
              <span><?= e($n['label']) ?></span>
              <?php if (isset($n['count'])): ?>
                <span class="sb-count<?= $n['count'] === 0 ? ' is-zero' : '' ?>" data-count="<?= e($n['key']) ?>"><?= (int) $n['count'] ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>

    <div class="sb-foot">
      <div class="appearance" role="radiogroup" aria-label="Appearance">
        <button type="button" role="radio" data-theme-set="light" aria-label="Light"><?= icon('sun') ?></button>
        <button type="button" role="radio" data-theme-set="auto" aria-label="Match this device"><?= icon('auto') ?></button>
        <button type="button" role="radio" data-theme-set="dark" aria-label="Dark"><?= icon('moon') ?></button>
      </div>
      <a class="sb-user<?= $page === 'account' ? ' is-on' : '' ?>" href="account.php">
        <span class="avatar"><?= e(initials($user['name'], $user['email'])) ?></span>
        <span class="sb-who"><b><?= e($user['name'] !== '' ? $user['name'] : 'Studio') ?></b><small>Account &amp; password</small></span>
      </a>
      <a class="sb-item sb-signout" href="logout.php"><?= icon('signout') ?><span>Sign out</span></a>
    </div>
  </aside>
  <div class="sb-scrim" id="sbScrim"></div>

  <div class="main">
    <header class="toolbar" id="toolbar">
      <button class="icon-btn tb-menu" id="menuBtn" type="button" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false"><?= icon('menu') ?></button>
      <span class="tb-title"><?= e($title) ?></span>

      <div class="tb-end">
        <a class="btn btn-plain btn-sm tb-site" href="<?= e(SITE_URL) ?>" target="_blank" rel="noopener"><?= icon('out') ?><span>View site</span></a>

        <div class="bell-wrap">
          <button class="icon-btn bell" id="bell" type="button" aria-expanded="false" aria-controls="notif" aria-label="Notifications">
            <?= icon('bell') ?>
            <span class="bell-dot<?= $unseenN === 0 ? ' is-zero' : '' ?>" id="bellDot"><?= $unseenN ?></span>
          </button>

          <div class="popover notif" id="notif" role="dialog" aria-label="Notifications">
            <div class="notif-head">
              <h3>New enquiries</h3>
              <button class="btn btn-plain btn-xs" id="markAllRead" type="button">Mark all read</button>
            </div>
            <div class="notif-list" id="notifList">
              <?php if (!$unseen): ?>
                <div class="notif-empty"><?= icon('check') ?><span>You’re all caught up.<br>New enquiries from the site appear here.</span></div>
              <?php else: ?>
                <?php foreach ($unseen as $l): ?>
                  <a class="notif-item" href="leads.php?open=<?= (int) $l['id'] ?>">
                    <span class="avatar avatar-sm"><?= e(initials($l['name'], $l['email'])) ?></span>
                    <span class="notif-body">
                      <b><?= e($l['name'] !== '' ? $l['name'] : $l['email']) ?></b>
                      <span><?= e(truncate($l['message'] ?? '', 64) ?: 'Left their email address') ?></span>
                      <time><?= e(timeAgo($l['created_at'])) ?> · <?= e(leadSourceLabel($l['source'])) ?></time>
                    </span>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <a class="notif-all" href="leads.php">Open all leads</a>
          </div>
        </div>
      </div>
    </header>

    <main class="content<?= $wide ? ' content-wide' : '' ?>" id="content">
      <?php if (!$wide && ($opts['head'] ?? true)): ?>
        <div class="page-head">
          <div class="ph-text">
            <h1><?= e($title) ?></h1>
            <?php if (!empty($opts['subtitle'])): ?><p><?= e($opts['subtitle']) ?></p><?php endif; ?>
          </div>
          <?php if (!empty($opts['actions'])): ?><div class="ph-actions"><?= $opts['actions'] ?></div><?php endif; ?>
        </div>
      <?php endif; ?>
<?php
}

function layoutBottom(): void
{
    ?>
    </main>
  </div>
</div>

<div class="toast-stack" id="toasts" aria-live="polite"></div>

<script src="assets/admin.js?v=<?= filemtime(ADMIN_DIR . '/assets/admin.js') ?>"></script>
</body>
</html>
<?php
}
