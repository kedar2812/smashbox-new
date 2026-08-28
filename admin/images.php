<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/render.php';

$user = requireLogin();

$fields = fieldsByType('image');
$overrides = contentOverrides();

// group by section, preserving document order
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

layoutTop('images', $user, 'Images');
?>

<div class="page-head">
  <div class="ph-text">
    <span class="eyebrow">Contact sheet</span>
    <h1>Images</h1>
    <p>Every picture on the site, in the order it appears. Click a frame to replace it.
       Changes go live the moment you save.</p>
  </div>
  <div class="ph-actions">
    <span class="mono" style="color:var(--faint)">
      <?= count($fields) ?> frames<?= $editedCount ? ' · ' . $editedCount . ' replaced' : '' ?>
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

    <div class="sheet">
      <?php foreach ($items as $f):
          $val = effectiveValue($f, $overrides);
          $alt = effectiveAlt($f, $overrides);
          $isEdited = isset($overrides[$f['id']]);
          $isHover = ($f['attr'] ?? 'src') === 'data-img';
      ?>
        <button class="frame<?= $isEdited ? ' is-changed' : '' ?>"
                type="button"
                data-frame="<?= e($f['id']) ?>"
                data-value="<?= e($val) ?>"
                data-alt="<?= e($alt) ?>"
                data-label="<?= e($f['label']) ?>"
                data-section="<?= e(sectionLabel($section)) ?>"
                data-kind="<?= $isHover ? 'hover' : 'main' ?>">
          <span class="frame-well">
            <?php if ($isEdited): ?>
              <span class="frame-badge"><span class="pill pill-changed">Replaced</span></span>
            <?php endif; ?>
            <img src="<?= e(publicSrc($val)) ?>" alt="" loading="lazy" decoding="async">
            <span class="frame-swap"><?= icon('upload') ?> Replace</span>
          </span>
          <span class="frame-cap">
            <span class="fc-label"><?= e($f['label']) ?></span>
            <span class="fc-meta">
              <span><?= e($f['id']) ?></span>
              <span class="sep">·</span>
              <span><?= $isHover ? 'hover frame' : 'image' ?></span>
            </span>
          </span>
        </button>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>

<!-- replace drawer -->
<aside class="drawer" id="imgDrawer" role="dialog" aria-modal="true" aria-labelledby="imgDrawerTitle">
  <div class="drawer-head">
    <div>
      <h2 id="imgDrawerTitle">Replace image</h2>
      <div class="sub" id="imgDrawerSub"></div>
    </div>
    <button class="drawer-close" type="button" data-close aria-label="Close"><?= icon('close') ?></button>
  </div>

  <div class="drawer-body">
    <div class="preview">
      <img id="imgPreview" src="" alt="">
    </div>

    <label class="drop" id="imgDrop">
      <?= icon('upload') ?>
      <b>Drop a picture here, or click to choose</b>
      <span>JPG · PNG · WEBP · AVIF — up to 10 MB</span>
      <input type="file" id="imgFile" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" hidden>
    </label>

    <div class="field" style="margin-top:18px;">
      <label for="imgAlt">Description for screen readers &amp; search</label>
      <input type="text" id="imgAlt" maxlength="480" placeholder="What is in this picture?">
      <div class="help">Plain language, no need to start with “image of”.</div>
    </div>

    <div class="field">
      <label for="imgUrl">Or paste an image address</label>
      <input type="url" id="imgUrl" placeholder="https://…">
    </div>
  </div>

  <div class="drawer-foot">
    <button class="btn btn-danger btn-sm" type="button" id="imgReset" hidden>
      <?= icon('undo') ?> Restore original
    </button>
    <span class="spacer"></span>
    <button class="btn" type="button" data-close>Cancel</button>
    <button class="btn btn-primary" type="button" id="imgSave">Save &amp; publish</button>
  </div>
</aside>

<?php layoutBottom();
