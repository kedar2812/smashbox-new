<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/render.php';

$user = requireLogin();

$fields = fieldsByType('text');
$overrides = contentOverrides();

$groups = [];
foreach ($fields as $f) {
    $groups[$f['section']][] = $f;
}

$editedCount = 0;
foreach ($fields as $f) {
    if (isset($overrides[$f['id']])) {
        $editedCount++;
    }
}

layoutTop('text', $user, 'Text');
?>

<div class="page-head">
  <div class="ph-text">
    <span class="eyebrow">Copy deck</span>
    <h1>Text</h1>
    <p>Every headline and paragraph on the site, in the order it appears.
       Click any line to rewrite it. Changes go live as soon as you save.</p>
  </div>
  <div class="ph-actions">
    <span class="mono" style="color:var(--faint)">
      <?= count($fields) ?> blocks<?= $editedCount ? ' · ' . $editedCount . ' edited' : '' ?>
    </span>
  </div>
</div>

<?php foreach ($groups as $section => $items): ?>
  <section class="group">
    <div class="group-head">
      <h2><?= e(sectionLabel($section)) ?></h2>
      <span class="rule"></span>
      <span class="n"><?= count($items) ?></span>
    </div>

    <div class="text-list">
      <?php foreach ($items as $f):
          $val = effectiveValue($f, $overrides);
          $isEdited = isset($overrides[$f['id']]);
          $isHeading = in_array($f['tag'], ['h1', 'h2', 'h3', 'h4'], true);
      ?>
        <div class="trow<?= $isHeading ? ' is-h' : '' ?><?= $isEdited ? ' is-changed' : '' ?>"
             data-row="<?= e($f['id']) ?>"
             data-original="<?= e($f['default']) ?>">
          <div class="trow-tag">
            <?= e($f['tag']) ?>
            <?php if ($isEdited): ?><br><span style="color:var(--warn)">edited</span><?php endif; ?>
          </div>

          <div class="trow-body">
            <div class="trow-value" data-value><?= sanitizeInline($val) ?></div>
          </div>

          <div class="trow-actions">
            <button class="btn btn-sm" type="button" data-edit>Edit</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>

<?php layoutBottom();
