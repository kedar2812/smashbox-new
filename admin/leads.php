<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = requireLogin();

$rows = db()->query(
    'SELECT id, email, name, phone, message, source, status, seen_at, replied_at, note, created_at
     FROM leads ORDER BY created_at DESC LIMIT 500'
)->fetchAll();

$leads = array_map(static fn(array $l): array => [
    'id'       => (int) $l['id'],
    'email'    => $l['email'],
    'name'     => $l['name'],
    'phone'    => $l['phone'],
    'message'  => (string) ($l['message'] ?? ''),
    'source'   => leadSourceLabel($l['source']),
    'status'   => $l['status'],
    'unseen'   => $l['seen_at'] === null,
    'note'     => (string) ($l['note'] ?? ''),
    'initials' => initials($l['name'], $l['email']),
    'ago'      => timeAgo($l['created_at']),
    'when'     => date('j M Y, g:i a', strtotime($l['created_at']) ?: time()),
], $rows);

$templates = db()->query('SELECT id, name, subject, body FROM templates ORDER BY position, id')->fetchAll();

$counts = ['all' => count($leads), 'new' => 0, 'replied' => 0, 'archived' => 0];
foreach ($leads as $l) {
    $counts[$l['status']] = ($counts[$l['status']] ?? 0) + 1;
}

layoutTop('leads', $user, 'Leads', [
    'subtitle' => 'Everyone who has left their details on the site. Open one to write back — the reply is drafted for you and opens in your mail app, ready to send.',
]);
?>

<div class="inbox" id="inbox">
  <div class="inbox-side">
    <div class="inbox-filter">
      <div class="seg" id="leadFilter" role="tablist" aria-label="Show">
        <button type="button" data-filter="active" aria-pressed="true">Inbox</button>
        <button type="button" data-filter="new" aria-pressed="false">New<?= $counts['new'] ? ' · ' . $counts['new'] : '' ?></button>
        <button type="button" data-filter="replied" aria-pressed="false">Replied</button>
        <button type="button" data-filter="archived" aria-pressed="false">Archived</button>
      </div>
    </div>
    <div class="inbox-list" id="leadList"></div>
  </div>

  <div class="inbox-main" id="leadMain">
    <div class="empty" style="height:100%;align-content:center">
      <?= icon('leads') ?>
      <h3><?= $leads ? 'Pick an enquiry to read it' : 'No enquiries yet' ?></h3>
      <p><?= $leads ? 'Its details, a drafted reply and your private notes open here.' : 'When someone leaves their details on the site, they land here and the bell lights up.' ?></p>
    </div>
  </div>
</div>

<script>
window.SBX_LEADS = <?= json_encode($leads, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.SBX_TEMPLATES = <?= json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.SBX_OPEN_LEAD = <?= isset($_GET['open']) ? (int) $_GET['open'] : 0 ?>;
</script>

<?php layoutBottom();
