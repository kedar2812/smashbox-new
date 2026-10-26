<?php
/**
 * Build img/og.jpg, the picture shown when the site is shared on WhatsApp,
 * LinkedIn, Instagram DMs and the like.
 *
 * The client's lockup sits on the brand near-black under a soft blue key
 * light — the same softbox glow the hero uses — so a shared link reads as
 * Smashbox before anyone opens it. The title and description travel with the
 * card as text, so none is set in the image.
 *
 * Usage: php tools/make_og.php
 */

$root = dirname(__DIR__);
$src  = imagecreatefrompng($root . '/img/smashbox-logo.png');

if (!$src) {
    fwrite(STDERR, "img/smashbox-logo.png could not be read\n");
    exit(1);
}

const W = 1200;
const H = 630;

$im = imagecreatetruecolor(W, H);
imagealphablending($im, true);

// ground: --bg, the site's near-black
imagefilledrectangle($im, 0, 0, W, H, imagecolorallocate($im, 0x07, 0x07, 0x0A));

// the key light and a cooler fill, computed per pixel so the falloff is
// smooth: a warm-blue softbox up and right, a pale bounce low and left
$lights = [
    // centre x, centre y, radius x, radius y, r, g, b, strength
    [W * 0.64, H * 0.24, 640, 420, 0x2E, 0x7F, 0xD4, 0.34],
    [W * 0.12, H * 1.02, 560, 360, 0x78, 0xC8, 0xF0, 0.12],
];
for ($y = 0; $y < H; $y++) {
    for ($x = 0; $x < W; $x++) {
        $r = 0x07; $g = 0x07; $b = 0x0A;
        foreach ($lights as [$lx, $ly, $rx, $ry, $cr, $cg, $cb, $k]) {
            $d = (($x - $lx) / $rx) ** 2 + (($y - $ly) / $ry) ** 2;
            $f = $k * exp(-2.6 * $d);
            $r += ($cr - $r) * $f; $g += ($cg - $g) * $f; $b += ($cb - $b) * $f;
        }
        imagesetpixel($im, $x, $y, ((int) $r << 16) | ((int) $g << 8) | (int) $b);
    }
}

// the lockup, centred, about half the card's height
$sw = imagesx($src);
$sh = imagesy($src);
$th = (int) round(H * 0.46);
$tw = (int) round($sw * $th / $sh);
imagecopyresampled($im, $src, (int) ((W - $tw) / 2), (int) ((H - $th) / 2), 0, 0, $tw, $th, $sw, $sh);

// a hairline frame, like the corners of a viewfinder
$line = imagecolorallocatealpha($im, 255, 255, 255, 100);
$m = 36;
$l = 34;
foreach ([[$m, $m, 1, 1], [W - $m, $m, -1, 1], [$m, H - $m, 1, -1], [W - $m, H - $m, -1, -1]] as [$x, $y, $dx, $dy]) {
    imagesetthickness($im, 2);
    imageline($im, $x, $y, $x + $l * $dx, $y, $line);
    imageline($im, $x, $y, $x, $y + $l * $dy, $line);
}

imagejpeg($im, $root . '/img/og.jpg', 88);
echo "wrote img/og.jpg\n";
