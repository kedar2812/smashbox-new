<?php
/**
 * Make more of the page editable without disturbing the ids already in use.
 *
 * build_template.php tagged headings and paragraphs only, which left the
 * studio unable to change the team's names, who said each testimonial, the
 * FAQ questions, the figures, the service names or the button labels without
 * touching code. This tags those too, continuing the numbering from the
 * highest id already in template.html, and adds them to cms-manifest.json.
 *
 * Safe to run again: anything already tagged is left alone, so ids never
 * move once assigned.
 *
 * Usage: php tools/add_fields.php
 */

$root = dirname(__DIR__);
$tplFile = $root . '/template.html';
$manFile = $root . '/cms-manifest.json';

$html = file_get_contents($tplFile);
$manifest = json_decode(file_get_contents($manFile), true);

libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->preserveWhiteSpace = true;
$doc->formatOutput = false;
$doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
libxml_clear_errors();
$xp = new DOMXPath($doc);

// what to tag, as XPath, in the order they are read on the page
$targets = [
    // the hero's three figures
    '//ul[contains(@class,"hero-facts")]/li/b',
    '//ul[contains(@class,"hero-facts")]/li/span',
    // the hero and newsletter submit buttons
    '//form[@id="heroForm"]//button[@type="submit"]/span',
    // the top bar's call to action
    '//div[contains(@class,"nav-end")]/a[contains(@class,"btn")]/span',
    '//div[@id="mobileMenu"]/a[contains(@class,"btn")]/span',
    // the tags under each division
    '//ul[contains(@class,"div-tags")]/li',
    // the four stats
    '//div[contains(@class,"stat")]/b',
    '//div[contains(@class,"stat")]/span',
    // work: the category above each title, and the portfolio button
    '//span[contains(@class,"card-cat")]',
    '//div[contains(@class,"work-more")]/a/span',
    // services: the group headings and every service name
    '//li[contains(@class,"svc-group")]',
    '//span[contains(@class,"svc-name")]',
    '//div[contains(@class,"svc-card")]/a[contains(@class,"link-arrow")]',
    // commitment banner button
    '//div[contains(@class,"cta-mid")]/a[contains(@class,"btn")]/span',
    // team names and roles
    '//figure[contains(@class,"member")]/figcaption/b',
    '//figure[contains(@class,"member")]/figcaption/span',
    // who said each testimonial
    '//figure[contains(@class,"q-card")]/figcaption/span',
    // contact buttons
    '//a[@data-cms-link="email" and contains(@class,"btn")]/span',
    '//button[contains(@class,"brief-send")]/span',
    // FAQ questions and the subscribe button
    '//button[contains(@class,"acc-q")]',
    '//form[@id="subForm"]//button[@type="submit"]/span',
    // the studio's address in the footer
    '//span[contains(@class,"foot-addr")]',
];

function sectionOf(DOMNode $n): string
{
    for ($p = $n->parentNode; $p instanceof DOMElement; $p = $p->parentNode) {
        $tag = strtolower($p->tagName);
        if ($tag === 'section' && $p->hasAttribute('id')) {
            return $p->getAttribute('id');
        }
        if (in_array($tag, ['header', 'footer', 'nav'], true)) {
            return $tag;
        }
        if ($p->getAttribute('id') === 'mobileMenu') {
            return 'header';
        }
        if ($tag === 'section' || $tag === 'main') {
            $cls = trim($p->getAttribute('class'));
            if ($cls !== '') {
                return explode(' ', $cls)[0];
            }
        }
    }
    return 'page';
}

function innerHtml(DOMElement $el): string
{
    $out = '';
    foreach ($el->childNodes as $child) {
        $out .= $el->ownerDocument->saveHTML($child);
    }
    return $out;
}

function labelFrom(string $text, int $max = 52): string
{
    $t = trim(preg_replace('/\s+/u', ' ', $text));
    return mb_strlen($t) <= $max ? $t : mb_substr($t, 0, $max - 1) . '…';
}

// continue from the highest t-number already in use
$next = 0;
foreach ($xp->query('//*[@data-cms-text]') as $el) {
    $next = max($next, (int) substr($el->getAttribute('data-cms-text'), 1));
}
foreach ($manifest['fields'] as $f) {
    if ($f['type'] === 'text') {
        $next = max($next, (int) substr($f['id'], 1));
    }
}

// gather every match, then tag in document order so the ids read top to bottom
$found = [];
foreach ($targets as $q) {
    foreach ($xp->query($q) as $el) {
        /** @var DOMElement $el */
        if ($el->hasAttribute('data-cms-text') || $el->hasAttribute('data-cms-link-text')) {
            continue;
        }
        // never inside an element that is already editable as a whole
        for ($p = $el->parentNode; $p instanceof DOMElement; $p = $p->parentNode) {
            if ($p->hasAttribute('data-cms-text')) {
                continue 2;
            }
        }
        if (trim($el->textContent) === '') {
            continue;
        }
        $found[spl_object_id($el)] = $el;
    }
}

$all = [];
foreach ($xp->query('//*') as $i => $el) {
    $all[spl_object_id($el)] = $i;
}
uasort($found, static fn($a, $b) => $all[spl_object_id($a)] <=> $all[spl_object_id($b)]);

$added = [];
foreach ($found as $el) {
    $id = 't' . (++$next);
    $el->setAttribute('data-cms-text', $id);
    $added[] = [
        'id'      => $id,
        'type'    => 'text',
        'tag'     => strtolower($el->tagName),
        'section' => sectionOf($el),
        'label'   => labelFrom($el->textContent),
        'default' => innerHtml($el),
        'rich'    => (bool) $el->getElementsByTagName('*')->length,
    ];
}

if (!$added) {
    echo "Nothing new to tag.\n";
    exit(0);
}

file_put_contents($tplFile, $doc->saveHTML());

// the dashboard lists text in the order the page reads, so re-sort every text
// field by where it sits in the document; images keep their order
$pos = [];
foreach ($xp->query('//*[@data-cms-text]') as $i => $el) {
    $pos[$el->getAttribute('data-cms-text')] = $i;
}
$texts = array_merge(
    array_values(array_filter($manifest['fields'], static fn($f) => $f['type'] === 'text')),
    $added
);
usort($texts, static fn($a, $b) => ($pos[$a['id']] ?? PHP_INT_MAX) <=> ($pos[$b['id']] ?? PHP_INT_MAX));
$images = array_values(array_filter($manifest['fields'], static fn($f) => $f['type'] !== 'text'));
$fields = array_merge($texts, $images);
$manifest['fields'] = $fields;
$manifest['counts']['text'] = count(array_filter($fields, static fn($f) => $f['type'] === 'text'));
if (!in_array('header', $manifest['sections'], true)) {
    $manifest['sections'][] = 'header';
}

file_put_contents($manFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo 'Tagged ' . count($added) . " fields:\n";
foreach ($added as $f) {
    echo '  ' . $f['id'] . '  ' . str_pad($f['section'], 11) . ' ' . $f['tag'] . '  ' . $f['label'] . "\n";
}
