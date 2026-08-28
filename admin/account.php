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
            $notice = 'Password changed.';
        }
    }
}

layoutTop('account', $user, 'Account');
?>

<div class="page-head">
  <div class="ph-text">
    <span class="eyebrow">Account</span>
    <h1>Your sign-in</h1>
    <p>One account runs this dashboard. Change the password here.</p>
  </div>
</div>

<?php if ($user['must_change']): ?>
  <div class="alert" style="background:var(--warn-wash);color:var(--warn);max-width:520px;margin-bottom:20px;">
    <?= icon('alert') ?>
    <span>This account is still on the password it was set up with. Change it to something only you know.</span>
  </div>
<?php endif; ?>

<?php if ($notice !== ''): ?>
  <div class="alert" style="background:var(--ok-wash);color:var(--ok);max-width:520px;margin-bottom:20px;">
    <?= icon('check') ?><span><?= e($notice) ?></span>
  </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
  <div class="alert" style="max-width:520px;margin-bottom:20px;">
    <?= icon('alert') ?><span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<form method="post" style="max-width:520px;padding:24px;border:1px solid var(--line);border-radius:var(--r-lg);background:var(--card);box-shadow:var(--shadow-sm);">
  <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">

  <div class="field">
    <label for="current">Current password</label>
    <input type="password" id="current" name="current" required autocomplete="current-password">
  </div>

  <div class="field">
    <label for="next">New password</label>
    <input type="password" id="next" name="next" required minlength="10" autocomplete="new-password">
    <div class="help">At least 10 characters. A short phrase you will remember beats a scramble you will not.</div>
  </div>

  <div class="field">
    <label for="confirm">New password again</label>
    <input type="password" id="confirm" name="confirm" required minlength="10" autocomplete="new-password">
  </div>

  <button class="btn btn-primary" type="submit">Change password</button>
</form>

<?php layoutBottom();
