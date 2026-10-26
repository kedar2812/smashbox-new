<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/render.php';

$user = requireLogin();

$names = clientNames();
$isCustom = clientNamesAreCustom();

layoutTop('clients', $user, 'Client strip', [
    'subtitle' => 'The names that scroll across the site under the three divisions. Add, rename, remove, or drag them into the order you want — then save.',
    'actions'  => '<button class="btn btn-plain btn-sm" id="clientReset" type="button"' . ($isCustom ? '' : ' hidden') . '>' . icon('undo') . 'Restore the original list</button>',
]);
?>

<div class="clients-wrap">
  <div>
    <form class="client-add" id="clientAdd" autocomplete="off">
      <input class="input" type="text" id="clientNew" maxlength="<?= CLIENT_NAME_MAX ?>" placeholder="Add a client — for example, Taj Hotels" aria-label="New client name">
      <button class="btn btn-primary" type="submit"><?= icon('plus') ?>Add</button>
    </form>

    <ol class="clist" id="clientList">
      <?php foreach ($names as $i => $n): ?>
        <li class="crow" draggable="true">
          <span class="crow-grip" aria-hidden="true"><?= icon('grip') ?></span>
          <span class="crow-no"><?= $i + 1 ?></span>
          <input type="text" maxlength="<?= CLIENT_NAME_MAX ?>" value="<?= e($n) ?>" aria-label="Client name">
          <button class="icon-btn" type="button" data-up aria-label="Move up">↑</button>
          <button class="icon-btn" type="button" data-down aria-label="Move down">↓</button>
          <button class="icon-btn del" type="button" data-remove aria-label="Remove"><?= icon('trash') ?></button>
        </li>
      <?php endforeach; ?>
    </ol>

    <div class="card empty" id="clientEmpty"<?= $names ? ' hidden' : '' ?>>
      <?= icon('clients') ?>
      <h3>The strip is empty</h3>
      <p>Add a name above. While the list is empty, the strip is hidden on the site.</p>
    </div>

    <p class="faint" style="font-size:13px;margin:12px 4px 0">
      Reads best with six names or more. Up to <?= CLIENTS_MAX ?> fit.
    </p>
  </div>

  <aside class="card" style="position:sticky;top:calc(var(--tb-h) + 16px)">
    <div class="card-head"><div><h2 class="card-title">How it will look</h2><p class="card-sub" id="clientCount"><?= count($names) ?> names</p></div></div>
    <div class="strip-preview"><div class="strip-track" id="stripTrack"></div></div>
  </aside>
</div>

<div class="savebar" id="clientBar" role="region" aria-label="Unsaved changes">
  <span class="savebar-dot"></span>
  <span>Unsaved changes to the client strip</span>
  <button class="btn btn-plain btn-sm" type="button" id="clientDiscard">Discard</button>
  <button class="btn btn-primary btn-sm" type="button" id="clientSave">Save &amp; publish</button>
</div>

<?php layoutBottom();
