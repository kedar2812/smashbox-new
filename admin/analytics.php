<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/analytics.php';

$user = requireLogin();

$range = (string) ($_GET['range'] ?? '7d');
try {
    $report = analyticsReport($range);
} catch (Throwable $e) {
    error_log('analytics page failed: ' . $e->getMessage());
    $report = null;
}

layoutTop('analytics', $user, 'Analytics', [
    'subtitle' => 'Who’s visiting the site, where they come from and what they click. Counted privately — no cookies, nothing that identifies a person.',
]);
?>

<div class="an-bar">
  <div class="seg" id="anRange" role="group" aria-label="Period">
    <?php foreach (['today' => 'Today', '7d' => '7 days', '30d' => '30 days', '90d' => '90 days'] as $k => $label): ?>
      <button type="button" data-range="<?= $k ?>" aria-pressed="<?= ($report['range']['key'] ?? '7d') === $k ? 'true' : 'false' ?>"><?= e($label) ?></button>
    <?php endforeach; ?>
  </div>
  <span class="live<?= ($report['live'] ?? 0) ? '' : ' is-zero' ?>" id="anLive"><i></i><span><?= (int) ($report['live'] ?? 0) ?> on the site now</span></span>
  <a class="an-note" href="<?= e(SITE_URL) ?>?sbx-ignore=1" target="_blank" rel="noopener" id="anIgnore">Don’t count my own visits on this device</a>
</div>

<?php if (!$report): ?>
  <div class="card empty"><?= icon('alert') ?><h3>The numbers could not be loaded</h3><p>Reload the page in a moment.</p></div>
<?php else: ?>

<div class="kpis" id="anKpis"></div>

<section class="card chart-card" style="margin-bottom:16px">
  <div class="card-head">
    <div><h2 class="card-title">Visitors</h2><p class="card-sub" id="anChartSub"></p></div>
  </div>
  <div class="chart" id="anChart"></div>
</section>

<div class="grid g-2" style="margin-bottom:16px">
  <section class="card">
    <div class="card-head"><div><h2 class="card-title">Where they come from</h2><p class="card-sub">The site or app that sent each visitor</p></div></div>
    <div id="anSources"></div>
  </section>
  <section class="card">
    <div class="card-head"><div><h2 class="card-title">Countries</h2><p class="card-sub">From each visitor’s time zone</p></div></div>
    <div id="anCountries"></div>
  </section>
</div>

<section class="card" style="margin-bottom:16px">
  <div class="card-head"><div><h2 class="card-title">What they click</h2><p class="card-sub">Buttons and links pressed on the site</p></div></div>
  <div class="kinds" id="anKinds"></div>
  <div id="anClicks"></div>
</section>

<div class="grid g-2" style="margin-bottom:16px">
  <section class="card">
    <div class="card-head"><div><h2 class="card-title">Devices</h2><p class="card-sub">What people view the site on</p></div></div>
    <div id="anDevices"></div>
  </section>
  <section class="card">
    <div class="card-head"><div><h2 class="card-title">How far down they read</h2><p class="card-sub">Share of visits that scrolled at least this far</p></div></div>
    <div id="anDepth"></div>
  </section>
</div>

<div class="grid g-2">
  <section class="card">
    <div class="card-head"><div><h2 class="card-title">Browsers</h2></div></div>
    <div id="anBrowsers"></div>
  </section>
  <section class="card">
    <div class="card-head"><div><h2 class="card-title">Campaigns</h2><p class="card-sub">Links tagged with utm_campaign</p></div></div>
    <div id="anCampaigns"></div>
  </section>
</div>

<p class="faint" style="font-size:12.5px;margin:18px 4px 0;max-width:80ch">
  A visitor is counted once a day: someone who comes back tomorrow counts again, and nobody can be followed from one day to the next.
  Visits from the dashboard’s own preview are never counted.
</p>

<script>window.SBX_REPORT = <?= json_encode($report, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php endif; ?>

<?php layoutBottom();
