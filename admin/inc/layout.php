<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/** Inline icon set. Kept here so markup stays readable. */
function icon(string $name, string $cls = ''): string
{
    $paths = [
        'images' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.6"/><path d="m21 15-4.5-4.5L7 20"/>',
        'text'   => '<path d="M4 6h16M4 12h11M4 18h7"/>',
        'leads'  => '<path d="M4 5h16v14H4z"/><path d="m4 7 8 5 8-5"/>',
        'bell'   => '<path d="M18 8a6 6 0 1 0-12 0c0 6-3 7-3 7h18s-3-1-3-7"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'close'  => '<path d="M18 6 6 18M6 6l12 12"/>',
        'check'  => '<path d="M20 6 9 17l-5-5"/>',
        'alert'  => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5M12 3v13"/>',
        'out'    => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6M10 14 21 3"/>',
        'signout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'inbox'  => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'eye'    => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
        'undo'   => '<path d="M3 7v6h6"/><path d="M3.5 13a9 9 0 1 0 2.1-5.6L3 10"/>',
    ];

    $d = $paths[$name] ?? '';
    return '<svg class="' . e($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
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

function layoutTop(string $page, array $user, string $crumb = ''): void
{
    $unseen = unseenLeads();
    $unseenN = unseenCount();
    $newN = newLeadCount();
    $published = settingGet('last_published_at');

    $nav = [
        ['key' => 'images', 'href' => 'images.php', 'icon' => 'images', 'label' => 'Images',  'count' => 0],
        ['key' => 'text',   'href' => 'text.php',   'icon' => 'text',   'label' => 'Text',    'count' => 0],
        ['key' => 'leads',  'href' => 'leads.php',  'icon' => 'leads',  'label' => 'Leads',   'count' => $newN],
    ];
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(ucfirst($page)) ?> · Smashbox Studio Desk</title>
<link rel="icon" href="../img/smashbox-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Inter+Tight:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="assets/admin.css?v=<?= filemtime(ADMIN_DIR . '/assets/admin.css') ?>">
</head>
<body data-page="<?= e($page) ?>">
<script>window.SBX = <?= json_encode([
    'csrf' => csrfToken(),
    'siteUrl' => SITE_URL,
    'studioEmail' => STUDIO_EMAIL,
    'studioName' => STUDIO_NAME,
], JSON_UNESCAPED_SLASHES) ?>;</script>

<div class="shell">
  <aside class="rail">
    <a class="rail-brand" href="images.php">
      <span class="brand-chip"><img src="../img/smashbox-logo.png" alt=""></span>
      <span class="bn">Smashbox<span class="bs">Studio desk</span></span>
    </a>

    <nav class="rail-nav" aria-label="Sections">
      <?php foreach ($nav as $n): ?>
        <a class="nav-item<?= $page === $n['key'] ? ' is-on' : '' ?>"
           href="<?= e($n['href']) ?>"
           <?= $page === $n['key'] ? 'aria-current="page"' : '' ?>>
          <?= icon($n['icon']) ?>
          <span><?= e($n['label']) ?></span>
          <span class="count<?= $n['count'] === 0 ? ' is-zero' : '' ?>" data-count="<?= e($n['key']) ?>"><?= $n['count'] ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="rail-foot">
      <div class="rail-user">
        <span class="avatar"><?= e(initials($user['name'], $user['email'])) ?></span>
        <span class="who">
          <b><?= e($user['name'] !== '' ? $user['name'] : 'Studio') ?></b>
          <span><?= e($user['email']) ?></span>
        </span>
      </div>
      <a class="nav-item<?= $page === 'account' ? ' is-on' : '' ?>" href="account.php"><?= icon('alert') ?><span>Account</span></a>
      <a class="nav-item" href="logout.php"><?= icon('signout') ?><span>Sign out</span></a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <span class="crumb"><?= e($crumb !== '' ? $crumb : $page) ?></span>

      <div class="topbar-right">
        <?php if ($published): ?>
          <span class="live-dot" title="Last published <?= e($published) ?>">
            <i></i> Live
          </span>
        <?php endif; ?>

        <a class="btn btn-sm btn-quiet" href="<?= e(SITE_URL) ?>" target="_blank" rel="noopener">
          <?= icon('eye') ?> View site
        </a>

        <div style="position:relative;">
          <button class="bell" id="bell" type="button"
                  aria-expanded="false" aria-controls="notif" aria-label="Notifications">
            <?= icon('bell') ?>
            <span class="dot<?= $unseenN === 0 ? ' is-zero' : '' ?>" id="bellDot"><?= $unseenN ?></span>
          </button>

          <div class="notif" id="notif" role="dialog" aria-label="Notifications">
            <div class="notif-head">
              <h3>Notifications</h3>
              <button class="btn btn-sm btn-quiet" id="markAllRead" type="button">Mark all read</button>
            </div>
            <div class="notif-list" id="notifList">
              <?php if (!$unseen): ?>
                <div class="notif-empty">
                  No new leads.<br>New enquiries from the site land here.
                </div>
              <?php else: ?>
                <?php foreach ($unseen as $l): ?>
                  <a class="notif-item" href="leads.php?open=<?= (int) $l['id'] ?>">
                    <span class="mark"></span>
                    <span class="body">
                      <b>New lead — <?= e($l['name'] !== '' ? $l['name'] : $l['email']) ?></b>
                      <p><?= e(truncate($l['message'] ?? '', 64) ?: $l['email']) ?></p>
                      <time><?= e(timeAgo($l['created_at'])) ?> · <?= e($l['source']) ?></time>
                    </span>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main class="page">
<?php
}

function layoutBottom(): void
{
    ?>
    </main>
  </div>
</div>

<div class="scrim" id="scrim"></div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script src="assets/admin.js?v=<?= filemtime(ADMIN_DIR . '/assets/admin.js') ?>"></script>
</body>
</html>
<?php
}
