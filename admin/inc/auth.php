<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function startSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? '') === '443'
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // idle timeout
    if (isset($_SESSION['last_seen']) && time() - (int) $_SESSION['last_seen'] > SESSION_IDLE_SECONDS) {
        logout();
    }
    $_SESSION['last_seen'] = time();
}

function currentUser(): ?array
{
    startSession();
    if (empty($_SESSION['uid'])) {
        return null;
    }

    static $user = null;
    if ($user === null) {
        $st = db()->prepare('SELECT id, email, name, must_change FROM users WHERE id = ?');
        $st->execute([$_SESSION['uid']]);
        $user = $st->fetch() ?: null;
    }
    return $user ?: null;
}

function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            jsonFail('Not signed in.', 401);
        }
        header('Location: index.php');
        exit;
    }
    return $user;
}

function attemptLogin(string $email, string $password): bool
{
    startSession();

    $st = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $st->execute([strtolower(trim($email))]);
    $row = $st->fetch();

    // constant-ish work whether or not the account exists
    $hash = $row['password_hash'] ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';

    if (!password_verify($password, $hash) || !$row) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $row['id'];
    $_SESSION['last_seen'] = time();

    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$row['id']]);

    return true;
}

function logout(): never
{
    startSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

/** Simple per-IP throttle so the login form cannot be ground down. */
function loginThrottle(): void
{
    startSession();
    $now = time();
    $attempts = $_SESSION['login_attempts'] ?? [];
    $attempts = array_values(array_filter($attempts, static fn($t) => $now - $t < 900));

    if (count($attempts) >= 8) {
        http_response_code(429);
        exit('Too many sign-in attempts. Wait 15 minutes and try again.');
    }

    $attempts[] = $now;
    $_SESSION['login_attempts'] = $attempts;
}

function clearThrottle(): void
{
    unset($_SESSION['login_attempts']);
}
