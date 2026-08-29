<?php
/**
 * Build the favicon set from the client's light lockup.
 *
 * img/smashbox-logo.png is white artwork on transparency, so on its own it
 * disappears against a light tab strip. Here it is set on the brand near-black
 * in the same rounded square the site's first favicon drew by hand, which
 * reads on both light and dark chrome.
 *
 * Usage: php tools/make_favicon.php
 */

$root = dirname(__DIR__);
$src  = imagecreatefrompng($root . '/img/smashbox-logo.png');

if (!$src) {
    fwrite(STDERR, "img/smashbox-logo.png could not be read\n");
    exit(1);
}

imagealphablending($src, false);
imagesavealpha($src, true);

$sw = imagesx($src);
$sh = imagesy($src);

const GROUND = [0x07, 0x07, 0x0A]; // --ink, the site's near-black
const PAD    = 0.085;              // breathing room inside the tile
const RADIUS = 0.22;               // matches rx=7 on the old 32px square

/** One square tile at the given edge length. */
function tile($src, int $sw, int $sh, int $size)
{
    $im = imagecreatetruecolor($size, $size);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));

    $ground = imagecolorallocate($im, GROUND[0], GROUND[1], GROUND[2]);
    $r = (int) round($size * RADIUS);

    // rounded square ground
    imagealphablending($im, true);
    imagefilledrectangle($im, $r, 0, $size - 1 - $r, $size - 1, $ground);
    imagefilledrectangle($im, 0, $r, $size - 1, $size - 1 - $r, $ground);
    $d = $r * 2;
    imagefilledarc($im, $r, $r, $d, $d, 180, 270, $ground, IMG_ARC_PIE);
    imagefilledarc($im, $size - 1 - $r, $r, $d, $d, 270, 360, $ground, IMG_ARC_PIE);
    imagefilledarc($im, $r, $size - 1 - $r, $d, $d, 90, 180, $ground, IMG_ARC_PIE);
    imagefilledarc($im, $size - 1 - $r, $size - 1 - $r, $d, $d, 0, 90, $ground, IMG_ARC_PIE);

    // the lockup, contained and centred
    $box = $size * (1 - PAD * 2);
    $scale = min($box / $sw, $box / $sh);
    $dw = (int) round($sw * $scale);
    $dh = (int) round($sh * $scale);

    imagecopyresampled(
        $im, $src,
        (int) round(($size - $dw) / 2), (int) round(($size - $dh) / 2),
        0, 0,
        $dw, $dh, $sw, $sh
    );

    return $im;
}

$targets = [
    'img/favicon-32.png'      => 32,
    'img/favicon-180.png'     => 180,
    'img/favicon-512.png'     => 512,
];

foreach ($targets as $path => $size) {
    $im = tile($src, $sw, $sh, $size);
    imagepng($im, $root . '/' . $path, 9);
    imagedestroy($im);
    printf("%s written (%d×%d)\n", $path, $size, $size);
}

// the dashboard lives on its own subdomain, so it needs its own copy
copy($root . '/img/favicon-32.png', $root . '/admin/assets/favicon-32.png');
copy($root . '/img/favicon-180.png', $root . '/admin/assets/favicon-180.png');
copy($root . '/img/smashbox-logo.png', $root . '/admin/assets/smashbox-logo.png');
echo "admin/assets/ copies written\n";
