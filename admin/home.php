<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/analytics.php';

$user = requireLogin();

$report = null;
try {
    $report = analyticsReport('7d');
} catch (Throwable $e) {
    error_log('overview analytics failed: ' . $e->getMessage());
}

$recent = db()->query(
    'SELECT id, email, name, message, source, status, seen_at, created_at
     FROM leads WHERE status <> "archived" ORDER BY created_at DESC LIMIT 5'
)->fetchAll();

$links = siteLinks();
$overrides = contentOverrides();
$published = settingGet('last_published_at');

/* ---- things worth doing, worked out from what is on the site ---- */
$todo = [];
if ($links['instagram'] === '' && $links['linkedin'] === '') {
    $todo[] = ['Add your social profiles', 'Instagram, LinkedIn, Behance and YouTube icons stay hidden until they have a link.', 'links.php'];
}
$teamEdited = false;
foreach (['text', 'image'] as $type) {
    foreach (editableFields($type) as $f) {
        if ($f['section'] === 'studio' && isset($overrides[$f['id']])) {
            $teamEdited = true;
        }
    }
}
if (!$teamEdited) {
    $todo[] = ['Put your real team on the site', 'The Studio section still shows sample names and stock portraits.', 'editor.php#studio'];
}
$quotesEdited = false;
foreach (editableFields('text') as $f) {
    if ($f['section'] === 'quotes' && $f['tag'] === 'blockquote' && isset($overrides[$f['id']])) {
        $quotesEdited = true;
    }
}
if (!$quotesEdited) {
    $todo[] = ['Swap in real client quotes', 'The five testimonials are written as examples. Real words from real clients convert far better.', 'text.php#quotes'];
}
$workEdited = false;
foreach (editableFields('image') as $f) {
    if ($f['section'] === 'work' && isset($overrides[$f['id']])) {
        $workEdited = true;
    }
}
if (!$workEdited) {
    $todo[] = ['Show your own work', 'The Selected work grid uses stock photographs. Replace them with your projects.', 'images.php#work'];
}

$hour = (int) gmdate('G', time() + ANALYTICS_OFFSET_MINUTES * 60);
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$name = firstName($user);

function deltaHtml(int $now, int $prev, string $period = 'last week'): string
{
    if ($prev === 0) {
        return $now > 0 ? '<span class="kpi-delta up">New <span>vs ' . e($period) . '</span></span>' : '<span class="kpi-delta"><span>No change</span></span>';
    }
    $pct = (int) round((($now - $prev) / $prev) * 100);
    if ($pct === 0) {
        return '<span class="kpi-delta">0% <span>vs ' . e($period) . '</span></span>';
    }
    $cls = $pct > 0 ? 'up' : 'down';
    $arrow = $pct > 0 ? '↑' : '↓';
    return '<span class="kpi-delta ' . $cls . '">' . $arrow . ' ' . abs($pct) . '% <span>vs ' . e($period) . '</span></span>';
}

layoutTop('home', $user, 'Overview', ['head' => false]);
?>

<div class="hello">
  <h1><?= e($greet . ($name !== '' ? ', ' . $name : '')) ?>.</h1>
  <p>
    <?php if ($report && $report['live'] > 0): ?>
      <?= (int) $report['live'] ?> <?= $report['live'] === 1 ? 'person is' : 'people are' ?> on the site right now.
    <?php else: ?>
      Here’s how the site is doing this week.
    <?php endif; ?>
    <?php if ($published): ?>Last published <?= e(timeAgo(date('Y-m-d H:i:s', strtotime($published)))) ?>.<?php endif; ?>
  </p>
</div>

<?php if ($report): $t = $report['totals']; $p = $report['previous']; ?>
<div class="kpis">
  <a class="kpi" href="analytics.php">
    <span class="kpi-label"><?= icon('user') ?>Visitors this week</span>
    <span class="kpi-value"><?= number_format($t['visitors']) ?></span>
    <?= deltaHtml($t['visitors'], $p['visitors']) ?>
    <svg class="kpi-spark" data-spark="<?= e(json_encode(array_column($report['series'], 'visitors'))) ?>" viewBox="0 0 200 34" preserveAspectRatio="none" aria-hidden="true"></svg>
  </a>
  <a class="kpi" href="analytics.php">
    <span class="kpi-label"><?= icon('pointer') ?>Clicks</span>
    <span class="kpi-value"><?= number_format($t['clicks']) ?></span>
    <?= deltaHtml($t['clicks'], $p['clicks']) ?>
  </a>
  <a class="kpi" href="leads.php">
    <span class="kpi-label"><?= icon('leads') ?>Enquiries</span>
    <span class="kpi-value"><?= number_format($t['leads']) ?></span>
    <?= deltaHtml($t['leads'], $p['leads']) ?>
  </a>
  <a class="kpi" href="analytics.php">
    <span class="kpi-label"><?= icon('clock') ?>Time on site</span>
    <span class="kpi-value"><?= $t['seconds'] >= 60 ? intdiv($t['seconds'], 60) . '<small>m</small> ' . ($t['seconds'] % 60) . '<small>s</small>' : $t['seconds'] . '<small>s</small>' ?></span>
    <span class="kpi-delta"><span>average visit</span></span>
  </a>
</div>
<?php endif; ?>

<div class="quick" style="margin-bottom:16px">
  <a class="quick-card" href="editor.php">
    <span class="quick-ico"><?= icon('cursor') ?></span>
    <span><b>Edit the website</b><span>Click anything on the page to change it</span></span>
    <?= icon('chevron') ?>
  </a>
  <a class="quick-card" href="images.php">
    <span class="quick-ico"><?= icon('images') ?></span>
    <span><b>Replace a picture</b><span>Every photo on the site, in one place</span></span>
    <?= icon('chevron') ?>
  </a>
  <a class="quick-card" href="leads.php">
    <span class="quick-ico"><?= icon('leads') ?></span>
    <span><b>Reply to enquiries</b><span><?= newLeadCount() ?> waiting for a reply</span></span>
    <?= icon('chevron') ?>
  </a>
</div>

<div class="grid g-2">
  <section class="card">
    <div class="card-head">
      <div><h2 class="card-title">Latest enquiries</h2><p class="card-sub">From the forms on the site</p></div>
      <a class="card-link" href="leads.php">All leads<?= icon('chevron') ?></a>
    </div>
    <?php if (!$recent): ?>
      <div class="empty" style="padding:30px 10px"><?= icon('leads') ?><h3>No enquiries yet</h3><p>When someone leaves their details on the site, they appear here.</p></div>
    <?php else: ?>
      <div class="list">
        <?php foreach ($recent as $l): ?>
          <a class="list-row" href="leads.php?open=<?= (int) $l['id'] ?>">
            <span class="avatar avatar-sm"><?= e(initials($l['name'], $l['email'])) ?></span>
            <span class="grow">
              <b><?= e($l['name'] !== '' ? $l['name'] : $l['email']) ?></b>
              <small><?= e(truncate($l['message'] ?? '', 70) ?: leadSourceLabel($l['source'])) ?></small>
            </span>
            <span class="faint" style="font-size:12.5px;white-space:nowrap"><?= e(timeAgo($l['created_at'])) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head">
      <div><h2 class="card-title">Where visitors come from</h2><p class="card-sub">This week</p></div>
      <a class="card-link" href="analytics.php">Analytics<?= icon('chevron') ?></a>
    </div>
    <?php if ($report && $report['sources']):
        $max = max(array_column($report['sources'], 'visitors')) ?: 1; ?>
      <div class="barlist">
        <?php foreach (array_slice($report['sources'], 0, 6) as $s): ?>
          <div class="bl-row">
            <span class="bl-fill" style="width:<?= round($s['visitors'] / $max * 100, 1) ?>%"></span>
            <span class="bl-name"><?= e($s['key']) ?></span>
            <span class="bl-val"><?= number_format($s['visitors']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty" style="padding:30px 10px"><?= icon('chart') ?><h3>Counting starts now</h3><p>Visits appear here within a minute of someone opening the site.</p></div>
    <?php endif; ?>
  </section>
</div>

<?php if ($todo): ?>
<section class="card" style="margin-top:16px">
  <div class="card-head"><div><h2 class="card-title">Make the site yours</h2><p class="card-sub">A few things still showing sample content</p></div></div>
  <div>
    <?php foreach ($todo as [$title, $body, $href]): ?>
      <a class="todo-row" href="<?= e($href) ?>">
        <span class="todo-dot"><?= icon('alert') ?></span>
        <span style="flex:1"><b><?= e($title) ?></b><span><?= e($body) ?></span></span>
        <?= icon('chevron') ?>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php layoutBottom();
