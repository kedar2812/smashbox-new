<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/layout.php';

$user = requireLogin();

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfCheck($_POST['csrf'] ?? null)) {
        $error = 'That form expired. Try again.';
    } else {
        $current = (string) ($_POST['current'] ?? '');
        $next    = (string) ($_POST['next'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');

        $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([$user['id']]);
        $hash = (string) $st->fetchColumn();

        if (!password_verify($current, $hash)) {
            $error = 'That is not your current password.';
        } elseif (strlen($next) < 10) {
            $error = 'Use at least 10 characters.';
        } elseif ($next !== $confirm) {
            $error = 'The two new passwords do not match.';
        } else {
            db()->prepare('UPDATE users SET password_hash = ?, must_change = 0 WHERE id = ?')
                ->execute([password_hash($next, PASSWORD_BCRYPT, ['cost' => 12]), $user['id']]);
            $notice = 'Password changed. Use the new one next time you sign in.';
            $user['must_change'] = 0;
        }
    }
}

layoutTop('account', $user, 'Account', [
    'subtitle' => 'One account runs this dashboard. Change its password here.',
]);
?>

<div class="narrow">
  <?php if ($user['must_change']): ?>
    <div class="notice notice-warn" style="margin-bottom:16px"><?= icon('alert') ?><span>This account is still on the password it was set up with. Change it to something only you know.</span></div>
  <?php endif; ?>
  <?php if ($notice !== ''): ?>
    <div class="notice notice-ok" style="margin-bottom:16px"><?= icon('check') ?><span><?= e($notice) ?></span></div>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <div class="notice notice-bad" style="margin-bottom:16px"><?= icon('alert') ?><span><?= e($error) ?></span></div>
  <?php endif; ?>

  <section class="card" style="margin-bottom:16px">
    <div class="list">
      <div class="list-row">
        <span class="avatar avatar-lg"><?= e(initials($user['name'], $user['email'])) ?></span>
        <span class="grow"><b><?= e($user['name'] !== '' ? $user['name'] : 'Studio') ?></b><small><?= e($user['email']) ?></small></span>
      </div>
    </div>
  </section>

  <form method="post" class="card" id="pwForm">
    <h2 class="card-title" style="margin-bottom:16px">Change password</h2>
    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

    <div class="field">
      <label for="current">Current password</label>
      <input type="password" id="current" name="current" required autocomplete="current-password">
    </div>

    <div class="field">
      <label for="next">New password</label>
      <input type="password" id="next" name="next" required minlength="10" autocomplete="new-password">
      <div class="help">At least 10 characters. A short phrase you’ll remember beats a scramble you won’t.</div>
    </div>

    <div class="field">
      <label for="confirm">New password again</label>
      <input type="password" id="confirm" name="confirm" required minlength="10" autocomplete="new-password">
    </div>

    <button class="btn btn-primary" type="submit">Change password</button>
  </form>
</div>

<?php layoutBottom();
