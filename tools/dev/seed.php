<?php
/**
 * Seed the local dev database: one admin account, the reply templates,
 * a handful of leads and a month of made-up analytics so every screen
 * has something on it.
 *
 *   php tools/dev/seed.php
 *
 * Local sign-in: dev@smashbox.test / local-dev-pass
 */

declare(strict_types=1);

require __DIR__ . '/../../admin/inc/db.php';
require __DIR__ . '/../../admin/inc/helpers.php';

$pdo = db();

$sql = preg_replace('/^\s*--.*$/m', '', file_get_contents(__DIR__ . '/../../admin/schema.sql')) ?? '';
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    $pdo->exec($stmt);
}

$pdo->exec('DELETE FROM users');
$pdo->prepare('INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)')
    ->execute(['dev@smashbox.test', 'Taher Husain', password_hash('local-dev-pass', PASSWORD_BCRYPT)]);

if (!(int) $pdo->query('SELECT COUNT(*) FROM templates')->fetchColumn()) {
    $st = $pdo->prepare('INSERT INTO templates (name, subject, body, position) VALUES (?, ?, ?, ?)');
    $st->execute(['First reply', 'Thanks for getting in touch — Smashbox Productions', "Hi {name},\n\nThanks for reaching out.\n\nBest,\nSmashbox Productions", 0]);
    $st->execute(['Set up a call', 'Shall we talk this week?', "Hi {name},\n\nAre you free for twenty minutes this week?\n\nBest,\nSmashbox Productions", 1]);
}

$pdo->exec('DELETE FROM leads');
$st = $pdo->prepare('INSERT INTO leads (email, name, phone, message, source, status, seen_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$leads = [
    ['priya@kavyajewels.in', 'Priya Deshmukh', '+91 98220 11111', 'We are launching a bridal line in March and need a full catalogue shoot plus social cutdowns.', 'hero', 'new', null, '-2 hours'],
    ['arjun@deccanforge.com', 'Arjun Patil', '', 'Looking for an industrial film for our investor deck.', 'hero', 'new', null, '-1 day'],
    ['hello@saffronco.in', '', '', '', 'newsletter', 'replied', '-3 days', '-4 days'],
    ['farah@halcyon.in', 'Farah Contractor', '+91 90000 22222', 'Creator residency for the new rooftop opening.', 'hero', 'archived', '-9 days', '-10 days'],
];
foreach ($leads as $l) {
    $st->execute([$l[0], $l[1], $l[2], $l[3], $l[4], $l[5], $l[6] ? date('Y-m-d H:i:s', strtotime($l[6])) : null, date('Y-m-d H:i:s', strtotime($l[7]))]);
}

if (is_file(__DIR__ . '/../../admin/inc/analytics.php')) {
    require __DIR__ . '/../../admin/inc/analytics.php';
    analyticsEnsureSchema();
    $pdo->exec('DELETE FROM analytics_events');
    require __DIR__ . '/seed-analytics.php';
}

echo "Seeded. Sign in as dev@smashbox.test / local-dev-pass\n";
