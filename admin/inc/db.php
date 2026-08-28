<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('DB connect failed: ' . $e->getMessage());
        exit('The dashboard cannot reach its database right now.');
    }

    return $pdo;
}

/** Every content override currently set, keyed by field id. */
function contentOverrides(): array
{
    $rows = db()->query('SELECT field_id, value, alt FROM content')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['field_id']] = ['value' => $r['value'], 'alt' => $r['alt']];
    }
    return $out;
}

function settingGet(string $key, ?string $default = null): ?string
{
    $st = db()->prepare('SELECT value FROM settings WHERE name = ?');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : (string) $v;
}

function settingSet(string $key, string $value): void
{
    $st = db()->prepare(
        'INSERT INTO settings (name, value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );
    $st->execute([$key, $value]);
}
