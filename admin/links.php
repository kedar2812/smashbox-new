<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = requireLogin();

$links = siteLinks();
$fields = linkFields();

$icons = [
    'email' => 'leads', 'phone' => 'phone', 'whatsapp' => 'phone',
    'instagram' => 'globe', 'linkedin' => 'globe', 'behance' => 'globe', 'youtube' => 'globe',
];

layoutTop('links', $user, 'Contact & links', [
    'subtitle' => 'Your email, phone, WhatsApp and social profiles. They’re used by every button and icon on the site that points to them, so you only change them here.',
]);
?>

<form class="card form-card" id="linksForm" novalidate>
  <p class="form-section-title" style="margin-top:0">How people reach you</p>
  <?php foreach ($fields as $key => $meta):
      if ($key === 'instagram'): ?>
        <p class="form-section-title">Social profiles</p>
        <p class="faint" style="margin:0 0 6px;font-size:13px">Leave one empty and its icon is hidden on the site.</p>
      <?php endif; ?>
    <div class="link-row">
      <div class="lr-label">
        <b><?= icon($icons[$key] ?? 'link') ?><?= e($meta['label']) ?></b>
        <?php if ($meta['help'] !== ''): ?><span><?= e($meta['help']) ?></span><?php endif; ?>
      </div>
      <div class="field" data-key="<?= e($key) ?>">
        <input type="<?= $meta['kind'] === 'email' ? 'email' : ($meta['kind'] === 'phone' ? 'tel' : 'url') ?>"
               name="<?= e($key) ?>" value="<?= e($links[$key]) ?>"
               placeholder="<?= e($meta['placeholder']) ?>" aria-label="<?= e($meta['label']) ?>"
               autocomplete="off" spellcheck="false">
        <span class="err" hidden></span>
        <a class="link-test" href="#" target="_blank" rel="noopener" data-test hidden><?= icon('out') ?>Try it</a>
      </div>
    </div>
  <?php endforeach; ?>
</form>

<div class="savebar" id="linksBar" role="region" aria-label="Unsaved changes">
  <span class="savebar-dot"></span>
  <span>Unsaved changes</span>
  <button class="btn btn-plain btn-sm" type="button" id="linksDiscard">Discard</button>
  <button class="btn btn-primary btn-sm" type="button" id="linksSave">Save &amp; publish</button>
</div>

<?php layoutBottom();
