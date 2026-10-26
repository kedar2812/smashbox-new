<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/auth.php';

startSession();

if (currentUser()) {
    header('Location: home.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    loginThrottle();

    if (!csrfCheck($_POST['csrf'] ?? null)) {
        $error = 'That form expired. Try again.';
    } else {
        $email = (string) ($_POST['email'] ?? '');
        $pass  = (string) ($_POST['password'] ?? '');

        if (attemptLogin($email, $pass)) {
            clearThrottle();
            header('Location: home.php');
            exit;
        }
        $error = 'That email and password do not match.';
    }
}

$token = csrfToken();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light dark">
<title>Sign in · Smashbox Studio Desk</title>
<link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="assets/favicon-180.png">
<script>
(function () {
  try {
    var t = localStorage.getItem('sbx-desk-theme');
    if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
  } catch (e) {}
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Inter+Tight:wght@400;500;600&display=swap">
<link rel="stylesheet" href="assets/admin.css?v=<?= filemtime(__DIR__ . '/assets/admin.css') ?>">
</head>
<body>
<div class="signin">
  <div class="signin-card">
    <div class="signin-brand">
      <span class="sb-mark"><img src="assets/smashbox-logo.png" alt=""></span>
      <span class="sb-name">Smashbox Productions<small>Studio Desk</small></span>
    </div>

    <h1>Welcome back</h1>
    <p class="lede">Sign in to update the website, see who’s visiting and reply to enquiries.</p>

    <form method="post" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">

      <?php if ($error !== ''): ?>
        <div class="notice notice-bad" role="alert" style="margin-bottom:16px">
          <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.4v.1"/></svg>
          <span><?= htmlspecialchars($error, ENT_QUOTES) ?></span>
        </div>
      <?php endif; ?>

      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus
               autocomplete="username" value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES) ?>">
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="pw">
          <input type="password" id="password" name="password" required autocomplete="current-password">
          <button type="button" id="pwToggle" aria-label="Show password" aria-pressed="false">
            <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <button class="btn btn-primary btn-lg" type="submit">Sign in</button>
    </form>

    <p class="signin-foot">Smashbox Productions · Pune</p>
  </div>
</div>
<script>
(function () {
  var b = document.getElementById('pwToggle'), i = document.getElementById('password');
  b.addEventListener('click', function () {
    var show = i.type === 'password';
    i.type = show ? 'text' : 'password';
    b.setAttribute('aria-pressed', String(show));
    b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    i.focus();
  });
})();
</script>
</body>
</html>
