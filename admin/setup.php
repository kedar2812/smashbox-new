<?php

declare(strict_types=1);

/**
 * One-shot installer.
 *
 * Creates the tables, seeds the single admin account and the reply templates,
 * then deletes itself. Only reachable with the token below, and it refuses to
 * run at all once an account exists.
 *
 *   https://admin.smashboxproductions.com/setup.php?token=…
 */

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';

const SETUP_TOKEN = 'CHANGEME_generate_a_random_token';

// The password itself was never written to disk or sent over the wire —
// only this bcrypt hash of it.
const SEED_EMAIL = 'smashboxindia@gmail.com';
const SEED_NAME  = 'Smashbox Studio';
const SEED_HASH  = 'CHANGEME_password_hash_from_password_hash()';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

if (!hash_equals(SETUP_TOKEN, (string) ($_GET['token'] ?? ''))) {
    http_response_code(404);
    exit("Not found\n");
}

$log = [];

try {
    $pdo = db();

    /* ---- schema ---- */
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('schema.sql is missing.');
    }

    // strip comment lines first — otherwise a leading comment block swallows
    // the statement that follows it when we split on the semicolon
    $stripped = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';

    $made = 0;
    foreach (array_filter(array_map('trim', explode(';', $stripped))) as $stmt) {
        if ($stmt === '') {
            continue;
        }
        $pdo->exec($stmt);
        $made++;
    }
    $log[] = $made . ' schema statements executed.';

    /* ---- admin account ---- */
    $existing = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    if ($existing > 0) {
        $log[] = 'An account already exists — left untouched.';
    } else {
        $pdo->prepare(
            'INSERT INTO users (email, name, password_hash, must_change) VALUES (?, ?, ?, 1)'
        )->execute([SEED_EMAIL, SEED_NAME, SEED_HASH]);
        $log[] = 'Admin account created for ' . SEED_EMAIL . '.';
    }

    /* ---- reply templates ---- */
    $haveTemplates = (int) $pdo->query('SELECT COUNT(*) FROM templates')->fetchColumn();

    if ($haveTemplates === 0) {
        $templates = [
            [
                'First reply',
                'Thanks for getting in touch — Smashbox Productions',
                "Hi {name},\n\n"
                . "Thanks for reaching out to Smashbox Productions. Good to hear from you.\n\n"
                . "So we can point you at the right part of the studio, could you tell us a little about "
                . "what you have in mind — what you are making, roughly when you need it, and where it will "
                . "be seen?\n\n"
                . "We will come back to you with how we would approach it.\n\n"
                . "Best,\nSmashbox Productions\nPune · smashboxproductions.com",
            ],
            [
                'Ask for the brief',
                'A few details so we can quote properly',
                "Hi {name},\n\n"
                . "Happy to put a number against this. To quote it properly we usually need:\n\n"
                . "  · What you are making — stills, film, campaign, website, or a mix\n"
                . "  · Where it runs and for how long\n"
                . "  · Rough timeline and any fixed dates\n"
                . "  · A budget range, even a wide one\n\n"
                . "Send whatever you have and we will work from there.\n\n"
                . "Best,\nSmashbox Productions\nPune · smashboxproductions.com",
            ],
            [
                'Set up a call',
                'Shall we talk this week?',
                "Hi {name},\n\n"
                . "This sounds like it would be quicker to talk through than to type. "
                . "Are you free for twenty minutes this week?\n\n"
                . "Send a couple of times that suit you and I will send an invite.\n\n"
                . "Best,\nSmashbox Productions\nPune · smashboxproductions.com",
            ],
            [
                'Newsletter welcome',
                "You're on the list",
                "Hi {name},\n\n"
                . "Thanks for subscribing. You will get one note a month — what we shot, what we learned, "
                . "and the occasional thing worth stealing. Nothing else, and you can leave any time.\n\n"
                . "Best,\nSmashbox Productions\nPune · smashboxproductions.com",
            ],
            [
                'Not a fit right now',
                'About your enquiry',
                "Hi {name},\n\n"
                . "Thanks for thinking of us, and for the detail you sent.\n\n"
                . "This one is not a fit for the studio at the moment — either the timing or the shape of "
                . "the work. I did not want to leave you waiting on an answer.\n\n"
                . "Do keep us in mind for the next one.\n\n"
                . "Best,\nSmashbox Productions\nPune · smashboxproductions.com",
            ],
        ];

        $st = $pdo->prepare('INSERT INTO templates (name, subject, body, position) VALUES (?, ?, ?, ?)');
        foreach ($templates as $i => $t) {
            $st->execute([$t[0], $t[1], $t[2], $i]);
        }
        $log[] = count($templates) . ' reply templates seeded.';
    } else {
        $log[] = 'Reply templates already present — left untouched.';
    }

    /* ---- writability checks ---- */
    $log[] = is_writable(SITE_DIR)
        ? 'Site folder is writable (publishing will work).'
        : 'WARNING: ' . SITE_DIR . ' is not writable — publishing will fail.';

    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0755, true);
    }
    $log[] = is_writable(UPLOAD_DIR)
        ? 'Uploads folder is writable.'
        : 'WARNING: ' . UPLOAD_DIR . ' is not writable — image uploads will fail.';

    $log[] = is_readable(TEMPLATE_FILE) ? 'template.html found.' : 'WARNING: template.html missing.';
    $log[] = is_readable(MANIFEST_FILE) ? 'cms-manifest.json found.' : 'WARNING: cms-manifest.json missing.';

} catch (Throwable $e) {
    http_response_code(500);
    echo "Setup failed:\n  " . $e->getMessage() . "\n\n" . implode("\n", $log) . "\n";
    exit;
}

echo "Smashbox Studio Desk — setup complete\n";
echo str_repeat('=', 44) . "\n\n";
foreach ($log as $line) {
    echo '  · ' . $line . "\n";
}

$gone = @unlink(__FILE__);
echo "\n" . ($gone
    ? "This installer has deleted itself.\n"
    : "Could not self-delete — remove admin/setup.php manually.\n");

echo "\nSign in at https://admin.smashboxproductions.com/\n";
