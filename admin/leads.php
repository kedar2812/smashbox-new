<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = requireLogin();

$filter = $_GET['status'] ?? 'all';
$allowed = ['all', 'new', 'replied', 'archived'];
if (!in_array($filter, $allowed, true)) {
    $filter = 'all';
}

$sql = 'SELECT id, email, name, phone, message, source, status, seen_at, replied_at, note, created_at
        FROM leads';
$params = [];
if ($filter !== 'all') {
    $sql .= ' WHERE status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY created_at DESC LIMIT 400';

$st = db()->prepare($sql);
$st->execute($params);
$leads = $st->fetchAll();

$counts = [];
foreach (db()->query('SELECT status, COUNT(*) c FROM leads GROUP BY status')->fetchAll() as $r) {
    $counts[$r['status']] = (int) $r['c'];
}
$counts['all'] = array_sum($counts);

$templates = db()->query('SELECT id, name, subject, body FROM templates ORDER BY position, id')->fetchAll();

$openId = isset($_GET['open']) ? (int) $_GET['open'] : 0;

layoutTop('leads', $user, 'Leads');
?>

<div class="page-head">
  <div class="ph-text">
    <span class="eyebrow">Enquiries</span>
    <h1>Leads</h1>
    <p>Everyone who has left an email address on the site. Open one to write back —
       the dashboard drafts the message and hands it to your mail app.</p>
  </div>
</div>

<div class="filters">
  <?php foreach ([
      'all'      => 'All',
      'new'      => 'New',
      'replied'  => 'Replied',
      'archived' => 'Archived',
  ] as $key => $label): ?>
    <a href="?status=<?= e($key) ?>" class="<?= $filter === $key ? 'is-on' : '' ?>">
      <?= e($label) ?>
      <?php if (!empty($counts[$key])): ?>
        <span class="mono" style="opacity:.6">&nbsp;<?= (int) $counts[$key] ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$leads): ?>
  <div class="empty">
    <?= icon('inbox') ?>
    <h3>No leads<?= $filter !== 'all' ? ' in ' . e($filter) : ' yet' ?></h3>
    <p>When somebody submits their email on the site, it appears here and the bell
       in the top bar lights up.</p>
  </div>
<?php else: ?>
  <div class="leads">
    <?php foreach ($leads as $l): ?>
      <button class="lead<?= $l['seen_at'] === null ? ' is-unseen' : '' ?>"
              type="button"
              data-lead="<?= (int) $l['id'] ?>"
              data-json="<?= e(json_encode($l, JSON_UNESCAPED_UNICODE)) ?>">
        <span class="mark"></span>
        <span class="who">
          <b><?= e($l['name'] !== '' ? $l['name'] : $l['email']) ?></b>
          <span><?= e($l['name'] !== '' ? $l['email'] : truncate($l['message'] ?? '', 70)) ?></span>
        </span>
        <span class="src"><?= e($l['source']) ?></span>
        <span class="when"><?= e(timeAgo($l['created_at'])) ?></span>
        <span class="pill pill-<?= e($l['status']) ?>"><?= e($l['status']) ?></span>
      </button>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- lead drawer -->
<aside class="drawer" id="leadDrawer" role="dialog" aria-modal="true" aria-labelledby="leadDrawerTitle">
  <div class="drawer-head">
    <div>
      <h2 id="leadDrawerTitle">Lead</h2>
      <div class="sub" id="leadDrawerSub"></div>
    </div>
    <button class="drawer-close" type="button" data-close aria-label="Close"><?= icon('close') ?></button>
  </div>

  <div class="drawer-body">
    <dl class="lead-facts">
      <dt>Email</dt><dd id="lfEmail"></dd>
      <dt>Name</dt><dd id="lfName"></dd>
      <dt>Phone</dt><dd id="lfPhone"></dd>
      <dt>From</dt><dd id="lfSource"></dd>
      <dt>Received</dt><dd id="lfWhen"></dd>
    </dl>

    <div class="field" id="lfMessageWrap" hidden>
      <label>What they wrote</label>
      <div id="lfMessage" style="padding:12px 13px;background:var(--wash);border-radius:var(--r-sm);font-size:14px;white-space:pre-wrap;"></div>
    </div>

    <div class="group-head" style="margin:26px 0 12px;">
      <h2 style="font-size:14px;">Write back</h2>
      <span class="rule"></span>
    </div>

    <div class="tmpl-picker" id="tmplPicker">
      <?php foreach ($templates as $i => $t): ?>
        <button class="tmpl<?= $i === 0 ? ' is-on' : '' ?>" type="button"
                data-tmpl="<?= (int) $t['id'] ?>"
                data-subject="<?= e($t['subject']) ?>"
                data-body="<?= e($t['body']) ?>"><?= e($t['name']) ?></button>
      <?php endforeach; ?>
    </div>

    <div class="field">
      <label for="mailSubject">Subject</label>
      <input type="text" id="mailSubject">
    </div>

    <div class="field">
      <label for="mailBody">Message</label>
      <textarea id="mailBody"></textarea>
      <div class="help">Edit freely — nothing is sent from here. The next step opens your
        mail app with this message already typed out, and you press send.</div>
    </div>

    <div class="field">
      <label>Open in</label>
      <div class="platforms" id="platforms">
        <button class="platform" type="button" data-platform="gmail">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>
          <span><b>Gmail</b><span>Web</span></span>
        </button>
        <button class="platform" type="button" data-platform="outlook">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 9h20"/><circle cx="7" cy="14" r="2"/></svg>
          <span><b>Outlook</b><span>Web</span></span>
        </button>
        <button class="platform" type="button" data-platform="yahoo">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m3 5 6 8v6"/><path d="m21 5-9 12"/></svg>
          <span><b>Yahoo</b><span>Web</span></span>
        </button>
        <button class="platform" type="button" data-platform="mailto">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="m4 7 8 5 8-5"/></svg>
          <span><b>Mail app</b><span>Default</span></span>
        </button>
      </div>
    </div>

    <div class="field">
      <label for="leadNote">Private note</label>
      <textarea id="leadNote" style="min-height:76px;" placeholder="Only you see this."></textarea>
    </div>
  </div>

  <div class="drawer-foot">
    <button class="btn btn-sm" type="button" id="leadArchive">Archive</button>
    <button class="btn btn-sm" type="button" id="leadReplied"><?= icon('check') ?> Mark replied</button>
    <span class="spacer"></span>
    <button class="btn btn-primary btn-sm" type="button" id="leadSaveNote">Save note</button>
  </div>
</aside>

<script>window.SBX_OPEN_LEAD = <?= $openId ?>;</script>

<?php layoutBottom();
