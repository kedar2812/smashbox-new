<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/auth.php';

startSession();

if (currentUser()) {
    header('Location: images.php');
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
            header('Location: images.php');
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
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Sign in · Smashbox Studio Desk</title>
<link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="assets/favicon-180.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Inter+Tight:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="assets/admin.css?v=<?= filemtime(__DIR__ . '/assets/admin.css') ?>">
</head>
<body>
<div class="signin">
  <div class="signin-card">
    <div class="signin-brand">
      <span class="brand-chip"><img src="assets/smashbox-logo.png" alt="Smashbox Productions"></span>
      <span class="signin-brand-text">
        <b>Smashbox Productions</b>
        <span class="sub">Studio desk</span>
      </span>
    </div>

    <h1>Sign in</h1>
    <p class="lede">Pictures, words and enquiries for smashboxproductions.com.</p>

    <form method="post" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">

      <?php if ($error !== ''): ?>
        <div class="alert" role="alert">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
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
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>

      <button class="btn btn-primary" type="submit">Sign in</button>
    </form>

    <p class="signin-foot">Smashbox Productions · Pune</p>
  </div>
</div>
</body>
</html>
