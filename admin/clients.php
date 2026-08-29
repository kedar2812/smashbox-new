<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/render.php';

$user = requireLogin();

$names = clientNames();
$isCustom = clientNamesAreCustom();

layoutTop('clients', $user, 'Clients');
?>

<div class="page-head">
  <div class="ph-text">
    <span class="eyebrow">Brands we've lit</span>
    <h1>Clients</h1>
    <p>The names that scroll across the strip below the three divisions.
       Add one, drop one, or drag them into the order you want them read.</p>
  </div>
  <div class="ph-actions">
    <span class="mono" style="color:var(--faint)" id="clientCount"><?= count($names) ?> names</span>
    <button class="btn btn-sm btn-quiet" id="clientReset" type="button"<?= $isCustom ? '' : ' hidden' ?>><?= icon('undo') ?> Restore original</button>
    <button class="btn btn-sm btn-primary" id="clientSave" type="button">Save &amp; publish</button>
  </div>
</div>

<section class="group">
  <div class="group-head">
    <h2>On the strip</h2>
    <span class="rule"></span>
    <span class="n" data-client-n><?= count($names) ?></span>
  </div>

  <form class="client-add" id="clientAdd" autocomplete="off">
    <input type="text" id="clientNew" maxlength="60" placeholder="Add a client — e.g. Taj Hotels">
    <button class="btn" type="submit">Add</button>
  </form>

  <ol class="client-list" id="clientList">
    <?php foreach ($names as $i => $n): ?>
      <li class="client-row" draggable="true">
        <span class="client-grip" aria-hidden="true"><span></span><span></span><span></span></span>
        <span class="client-no mono"><?= $i + 1 ?></span>
        <input type="text" class="client-name" maxlength="60" value="<?= e($n) ?>"
               aria-label="Client name">
        <span class="client-move">
          <button class="btn btn-sm btn-quiet" type="button" data-up aria-label="Move up">&uarr;</button>
          <button class="btn btn-sm btn-quiet" type="button" data-down aria-label="Move down">&darr;</button>
        </span>
        <button class="btn btn-sm btn-danger" type="button" data-remove>Remove</button>
      </li>
    <?php endforeach; ?>
  </ol>

  <div class="empty" id="clientEmpty"<?= $names ? ' hidden' : '' ?>>
    <?= icon('clients') ?>
    <h3>No clients on the strip</h3>
    <p>Add the first name above. The strip hides itself on the site while this list is empty.</p>
  </div>

  <p class="client-note">
    The strip scrolls on a loop, so it reads best with six names or more.
    Up to <?= CLIENTS_MAX ?> can go on it.
  </p>
</section>

<?php layoutBottom();
