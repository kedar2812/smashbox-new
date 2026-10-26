<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/render.php';

$user = requireLogin();

$fields = editableFields('image');
$overrides = contentOverrides();

// hover pictures for the services list are many and rarely changed, so they
// sit in their own folded group at the end
$main = array_values(array_filter($fields, static fn($f) => ($f['attr'] ?? 'src') !== 'data-img'));
$hover = array_values(array_filter($fields, static fn($f) => ($f['attr'] ?? 'src') === 'data-img'));
$groups = groupBySection($main);

$editedCount = 0;
foreach ($fields as $f) {
    if (isset($overrides[$f['id']])) {
        $editedCount++;
    }
}

function imageCard(array $f, array $overrides): string
{
    $val = effectiveValue($f, $overrides);
    $isEdited = isset($overrides[$f['id']]);
    $isLogo = str_contains(fieldTitle($f), 'Logo');
    ob_start(); ?>
    <button class="icard" type="button" data-field="<?= e($f['id']) ?>">
      <span class="icard-media<?= $isLogo ? ' is-logo' : '' ?>">
        <?php if ($isEdited): ?><span class="pill pill-edited">Replaced</span><?php endif; ?>
        <img src="<?= e(publicSrc($val)) ?>" alt="" loading="lazy" decoding="async">
      </span>
      <span class="icard-cap">
        <b><?= e(fieldTitle($f)) ?></b>
        <span><?= e(sectionTitle($f['section'])) ?></span>
      </span>
    </button>
    <?php
    return (string) ob_get_clean();
}

layoutTop('images', $user, 'Pictures', [
    'subtitle' => 'Every photograph on the site, in the order it appears. Tap one to replace it — drop in a new picture, or reuse one you uploaded before.',
    'actions'  => '<span class="faint" style="font-size:13px">' . count($fields) . ' pictures' . ($editedCount ? ' · ' . $editedCount . ' replaced' : '') . '</span>',
]);
?>

<nav class="jump" aria-label="Sections" style="margin:-6px 0 24px">
  <?php foreach ($groups as $section => $items): ?>
    <a href="#<?= e($section) ?>"><?= e(sectionTitle($section)) ?></a>
  <?php endforeach; ?>
  <?php if ($hover): ?><a href="#hover">Service hover pictures</a><?php endif; ?>
</nav>

<?php foreach ($groups as $section => $items): ?>
  <section class="igroup" id="<?= e($section) ?>">
    <div class="tgroup-head">
      <h2><?= e(sectionTitle($section)) ?></h2>
      <span><?= count($items) ?></span>
    </div>
    <div class="igrid">
      <?php foreach ($items as $f) { echo imageCard($f, $overrides); } ?>
    </div>
  </section>
<?php endforeach; ?>

<?php if ($hover): ?>
  <details class="igroup" id="hover">
    <summary>
      <div class="summary-row">
        <span class="grow">
          <b>Service hover pictures</b><br>
          <span><?= count($hover) ?> small pictures that follow the pointer over the services list</span>
        </span>
        <?= icon('chevron', 'chev') ?>
      </div>
    </summary>
    <div class="igrid">
      <?php foreach ($hover as $f) { echo imageCard($f, $overrides); } ?>
    </div>
  </details>
<?php endif; ?>

<?php layoutBottom();
