<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/render.php';

$user = requireLogin();

$fields = editableFields('text');
$overrides = contentOverrides();
$groups = groupBySection($fields);

$editedCount = 0;
foreach ($fields as $f) {
    if (isset($overrides[$f['id']])) {
        $editedCount++;
    }
}

layoutTop('text', $user, 'Words', [
    'subtitle' => 'Every headline, paragraph, button and name on the site, top to bottom. Tap one to change it — you’ll see exactly what changes before anything goes live.',
    'actions'  => '<a class="btn btn-tinted btn-sm" href="editor.php">' . icon('cursor') . 'Edit on the page instead</a>',
]);
?>

<div class="tools">
  <label class="search">
    <?= icon('search') ?>
    <input type="search" id="textSearch" placeholder="Find words on the site" autocomplete="off" aria-label="Find words on the site">
  </label>
  <span class="faint" style="font-size:13px" id="textCount"><?= count($fields) ?> blocks<?= $editedCount ? ' · ' . $editedCount . ' changed' : '' ?></span>
</div>

<nav class="jump" aria-label="Sections" style="margin:-8px 0 22px">
  <?php foreach ($groups as $section => $items): ?>
    <a href="#<?= e($section) ?>"><?= e(sectionTitle($section)) ?></a>
  <?php endforeach; ?>
</nav>

<?php foreach ($groups as $section => $items): ?>
  <section class="tgroup" id="<?= e($section) ?>" data-group>
    <div class="tgroup-head">
      <h2><?= e(sectionTitle($section)) ?></h2>
      <span><?= count($items) ?></span>
    </div>

    <div class="tlist">
      <?php foreach ($items as $f):
          $val = effectiveValue($f, $overrides);
          $isEdited = isset($overrides[$f['id']]);
          $big = in_array($f['tag'], ['h1', 'h2'], true);
      ?>
        <button class="trow<?= $big ? ' is-big' : '' ?>" type="button" data-field="<?= e($f['id']) ?>">
          <span class="trow-kind"><?= e(fieldTitle($f)) ?></span>
          <span class="trow-text" data-value><?= displayInline($val) ?></span>
          <span class="trow-status"><?php if ($isEdited): ?><span class="pill pill-edited">Changed</span><?php endif; ?></span>
          <?= icon('chevron') ?>
        </button>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>

<div class="empty no-results" id="noResults">
  <?= icon('search') ?>
  <h3>Nothing on the site says that</h3>
  <p>Try a shorter word, or look through the sections above.</p>
</div>

<?php layoutBottom();
