<?php

declare(strict_types=1);

/**
 * The live site, rendered for the visual editor.
 *
 * Served from the dashboard's own origin so the editor can reach into the
 * page, with a <base> pointing at the public site so every stylesheet, script
 * and picture loads from where it really lives. The opening loader, the
 * custom cursor and the scroll reveals are switched off, so everything is
 * visible and clickable at once, and the visit counter is told not to count.
 */

require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/render.php';

requireLogin();

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

try {
    $html = renderSite();
} catch (Throwable $e) {
    http_response_code(500);
    exit('The preview could not be built: ' . e($e->getMessage()));
}

$inject = '<base href="' . e(SITE_URL) . '">'
    . '<script>window.SBX_NO_TRACK=1;document.documentElement.classList.add("no-loader","sbx-preview");'
    . 'try{sessionStorage.setItem("sbx-seen","1")}catch(e){}</script>'
    . '<style id="sbx-preview-css">'
    . '.loader,.cursor,.keylight,.scroll-cue{display:none!important}'
    . 'html,body,body *{cursor:default!important}'
    . '.reveal{opacity:1!important;transform:none!important;filter:none!important;transition:none!important}'
    . '.js .card .card-media{opacity:1!important;transform:none!important}'
    . '.hero-title .line>span{transform:none!important;transition:none!important}'
    . '.tile,.chip{opacity:1!important;transform:none!important}'
    // things the page hides until a hover or a tap, shown so they can be edited
    . '.acc-a{height:auto!important}'
    . '.card-body p{max-height:none!important;opacity:1!important}'
    . '</style>';

// right after <head>, ahead of every stylesheet and script
$html = preg_replace('/<head(\s[^>]*)?>/i', '$0' . $inject, $html, 1) ?? $html;

header('Content-Type: text/html; charset=utf-8');
echo $html;
