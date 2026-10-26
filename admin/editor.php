<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = requireLogin();

layoutTop('editor', $user, 'Edit visually', ['wide' => true]);
?>

<div class="ve" id="ve">
  <div class="ve-stage">
    <div class="ve-bar">
      <span class="ve-hint"><?= icon('pointer') ?><b>Click any words or picture</b><span>&nbsp;to change them</span></span>
      <span class="spacer"></span>
      <div class="seg" id="veDevice" role="group" aria-label="Preview size">
        <button type="button" data-device="desktop" aria-pressed="true" aria-label="Computer"><?= icon('desktop') ?></button>
        <button type="button" data-device="tablet" aria-pressed="false" aria-label="Tablet"><?= icon('tablet') ?></button>
        <button type="button" data-device="mobile" aria-pressed="false" aria-label="Phone"><?= icon('mobile') ?></button>
      </div>
      <button class="icon-btn" id="veReload" type="button" aria-label="Reload the preview" title="Reload the preview"><?= icon('reload') ?></button>
    </div>

    <div class="ve-canvas">
      <div class="ve-device" id="veDeviceFrame" data-device="desktop">
        <iframe id="veFrame" src="preview.php" title="Your website, ready to edit"></iframe>
        <div class="ve-loading" id="veLoading">
          <div style="display:grid;justify-items:center">
            <span>Opening your website…</span>
            <div class="progress is-indeterminate"><i></i></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <aside class="ve-panel" id="vePanel" aria-label="Editor">
    <div class="ve-panel-inner" id="vePanelInner"></div>
  </aside>
</div>

<script>window.SBX_FIELDS = <?= json_encode(
    array_reduce(
        array_merge(fieldsByType('text'), fieldsByType('image')),
        static function (array $map, array $f): array {
            $map[$f['id']] = [fieldTitle($f), $f['type']];
            return $map;
        },
        []
    ),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
) ?>;</script>

<?php layoutBottom();
