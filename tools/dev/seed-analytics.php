<?php
/**
 * Ninety days of made-up visits, for local development only.
 * Included by seed.php once the analytics table exists.
 */

declare(strict_types=1);

mt_srand(2008);

$pick = static function (array $weights): string {
    $total = array_sum($weights);
    $r = mt_rand(1, $total);
    foreach ($weights as $k => $w) {
        $r -= $w;
        if ($r <= 0) {
            return (string) $k;
        }
    }
    return (string) array_key_first($weights);
};

$sources = [
    'Google|search|google.com' => 40, 'Direct|direct|' => 26, 'Instagram|social|instagram.com' => 18,
    'LinkedIn|social|linkedin.com' => 6, 'WhatsApp|social|wa.me' => 4, 'Facebook|social|facebook.com' => 3,
    'Bing|search|bing.com' => 1, 'Justdial|referral|justdial.com' => 2, 'ChatGPT|ai|chatgpt.com' => 1,
];
$countries = ['IN' => 82, 'AE' => 5, 'US' => 4, 'GB' => 3, 'SG' => 2, 'AU' => 1, 'CA' => 1, 'DE' => 1];
$devices = ['mobile|Android|Chrome' => 38, 'mobile|iOS|Safari' => 16, 'mobile|iOS|Instagram app' => 6,
            'desktop|Windows|Chrome' => 22, 'desktop|macOS|Safari' => 7, 'desktop|Windows|Edge' => 5,
            'tablet|iPadOS|Safari' => 3, 'desktop|macOS|Chrome' => 3];
$clicks = [
    'Start a project|cta|#contact|header' => 30, "Let's talk|form||top" => 12, 'Call +91 99210 00052|phone|tel:+919921000052|contact' => 14,
    'Email the studio|email|mailto:smashboxindia@gmail.com|contact' => 10, 'WhatsApp us|whatsapp|https://wa.me/919921000052|contact' => 16,
    'Work|nav|#work|header' => 18, 'Services|nav|#services|header' => 12, 'Instagram|social|https://instagram.com/|footer' => 6,
    'See the full portfolio|cta|#contact|work' => 7, 'Product|filter||work' => 8, 'What does a project cost?|faq||faq' => 9,
    'Send enquiry|form||contact' => 5,
];

$pdo = db();
$ins = $pdo->prepare(
    'INSERT INTO analytics_events (created_at, type, visitor, session, path, source, medium, referrer, campaign,
       country, device, browser, os, label, target, kind, section, seconds, scroll)
     VALUES (?, ?, ?, ?, "/", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$pdo->beginTransaction();
$n = 0;
for ($day = 89; $day >= 0; $day--) {
    $dayStart = strtotime('today', time()) - $day * 86400;
    $dow = (int) date('N', $dayStart);
    $base = $dow >= 6 ? 14 : 24;
    $visits = (int) round($base * (0.7 + mt_rand(0, 60) / 100) * (1 + (89 - $day) / 160));
    if ($day === 0) {
        $visits = (int) round($visits * min(1, (time() - $dayStart) / 86400) + 2);
    }

    for ($v = 0; $v < $visits; $v++) {
        $ts = $dayStart + mt_rand(7 * 3600, 23 * 3600);
        if ($ts > time()) {
            $ts = time() - mt_rand(30, 3000);
        }
        $visitor = substr(md5($day . '-' . $v), 0, 16);
        $session = substr(md5('s' . $day . '-' . $v), 0, 16);
        [$src, $med, $ref] = explode('|', $pick($sources));
        $country = $pick($countries);
        [$device, $os, $browser] = explode('|', $pick($devices));
        $campaign = ($src === 'Instagram' && mt_rand(1, 4) === 1) ? 'diwali-reel' : '';
        if ($campaign) {
            $med = 'campaign';
        }

        $at = static fn(int $t): string => gmdate('Y-m-d H:i:s', $t);
        $ins->execute([$at($ts), 'pageview', $visitor, $session, $src, $med, $ref, $campaign, $country, $device, $browser, $os, '', '', '', '', 0, 0]);
        $n++;

        $clickCount = mt_rand(0, 100) < 55 ? mt_rand(1, 3) : 0;
        for ($c = 0; $c < $clickCount; $c++) {
            [$label, $kind, $target, $section] = explode('|', $pick($clicks));
            $ins->execute([$at($ts + 20 + $c * 30), 'click', $visitor, $session, $src, $med, $ref, $campaign, $country, $device, $browser, $os, $label, $target, $kind, $section, 0, 0]);
            $n++;
        }

        $secs = mt_rand(0, 100) < 30 ? mt_rand(4, 25) : mt_rand(30, 340);
        $scroll = min(100, (int) round($secs / 3.4 + mt_rand(0, 30)));
        $ins->execute([$at($ts + $secs), 'leave', $visitor, $session, $src, $med, $ref, $campaign, $country, $device, $browser, $os, '', '', '', '', $secs, $scroll]);
        $n++;
    }
}
$pdo->commit();

echo "Analytics: $n events\n";
