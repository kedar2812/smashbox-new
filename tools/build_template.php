<?php
/**
 * One-time build step.
 *
 * Reads the hand-authored index.html, tags every editable node with a stable
 * data-cms id, and writes:
 *   template.html      the source the renderer works from
 *   cms-manifest.json  the field registry the dashboard reads
 *
 * Ids are assigned in document order (t1..tN for text, i1..iN for images) and
 * are baked into template.html, so they stay stable as long as this script is
 * not re-run against a re-ordered document.
 *
 * Usage: php tools/build_template.php
 */

$root = dirname(__DIR__);
$srcFile = $root . '/index.html';

if (!is_file($srcFile)) {
    fwrite(STDERR, "index.html not found\n");
    exit(1);
}

$html = file_get_contents($srcFile);

libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->preserveWhiteSpace = true;
$doc->formatOutput = false;
$doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
libxml_clear_errors();

$xp = new DOMXPath($doc);

/* ---------------------------------------------------------------
   Which text nodes count as "major"
   --------------------------------------------------------------- */
$TEXT_TAGS = ['h1', 'h2', 'h3', 'h4', 'p', 'blockquote'];

// inline markup we are willing to keep inside an editable block
$INLINE_OK = ['br', 'em', 'strong', 'b', 'i', 'span', 'a', 'sup', 'sub', 'u'];

// never descend into these
$SKIP_ANCESTORS = ['script', 'style', 'noscript', 'template', 'svg'];

function hasSkippedAncestor(DOMNode $n, array $skip): bool
{
    for ($p = $n->parentNode; $p instanceof DOMElement; $p = $p->parentNode) {
        if (in_array(strtolower($p->tagName), $skip, true)) {
            return true;
        }
    }
    return false;
}

/** Nearest meaningful container, used to group fields in the dashboard. */
function sectionOf(DOMNode $n): array
{
    for ($p = $n->parentNode; $p instanceof DOMElement; $p = $p->parentNode) {
        $tag = strtolower($p->tagName);
        if ($tag === 'section' && $p->hasAttribute('id')) {
            return [$p->getAttribute('id'), null];
        }
        if (in_array($tag, ['header', 'footer', 'nav'], true)) {
            return [$tag, null];
        }
        if ($tag === 'section' || $tag === 'main') {
            $cls = $p->getAttribute('class');
            if ($cls !== '') {
                return [explode(' ', trim($cls))[0], null];
            }
        }
    }
    return ['page', null];
}

/** innerHTML of an element. */
function innerHtml(DOMElement $el): string
{
    $out = '';
    foreach ($el->childNodes as $child) {
        $out .= $el->ownerDocument->saveHTML($child);
    }
    return $out;
}

/** Only inline formatting inside? Then it is safe to edit as a block. */
function isEditableBlock(DOMElement $el, array $inlineOk): bool
{
    foreach ($el->getElementsByTagName('*') as $desc) {
        if (!in_array(strtolower($desc->tagName), $inlineOk, true)) {
            return false;
        }
    }
    return true;
}

/** Short human label from the content itself. */
function labelFrom(string $text, int $max = 52): string
{
    $t = trim(preg_replace('/\s+/u', ' ', $text));
    if ($t === '') {
        return '(empty)';
    }
    if (mb_strlen($t) <= $max) {
        return $t;
    }
    return mb_substr($t, 0, $max - 1) . '…';
}

$fields = [];
$tSeq = 0;
$iSeq = 0;

/* ---------------------------------------------------------------
   Text fields
   --------------------------------------------------------------- */
foreach ($TEXT_TAGS as $tag) {
    // re-query per tag so document order is preserved within each tag group,
    // then we sort the whole set by document position afterwards
    foreach ($xp->query('//' . $tag) as $el) {
        /** @var DOMElement $el */
        if (hasSkippedAncestor($el, $SKIP_ANCESTORS)) {
            continue;
        }
        if ($el->getAttribute('aria-hidden') === 'true') {
            continue;
        }
        $text = trim($el->textContent);
        if ($text === '') {
            continue;
        }
        if (!isEditableBlock($el, $INLINE_OK)) {
            continue;
        }
        $el->setAttribute('data-cms-pending', '1');
    }
}

// assign ids in true document order
foreach ($xp->query('//*[@data-cms-pending]') as $el) {
    /** @var DOMElement $el */
    $tSeq++;
    $id = 't' . $tSeq;
    $el->removeAttribute('data-cms-pending');
    $el->setAttribute('data-cms-text', $id);

    [$section] = sectionOf($el);
    $value = innerHtml($el);

    $fields[] = [
        'id'      => $id,
        'type'    => 'text',
        'tag'     => strtolower($el->tagName),
        'section' => $section,
        'label'   => labelFrom($el->textContent),
        'default' => $value,
        'rich'    => (bool) preg_match('/<[a-z]/i', $value),
    ];
}

/* ---------------------------------------------------------------
   Image fields — every <img>, plus the data-img swap targets the
   work grid uses on hover.
   --------------------------------------------------------------- */
foreach ($xp->query('//img') as $el) {
    /** @var DOMElement $el */
    if (hasSkippedAncestor($el, $SKIP_ANCESTORS)) {
        continue;
    }
    $iSeq++;
    $id = 'i' . $iSeq;
    $el->setAttribute('data-cms-img', $id);

    [$section] = sectionOf($el);
    $alt = $el->getAttribute('alt');

    $fields[] = [
        'id'      => $id,
        'type'    => 'image',
        'attr'    => 'src',
        'section' => $section,
        'label'   => $alt !== '' ? labelFrom($alt) : 'Untitled image',
        'default' => $el->getAttribute('src'),
        'alt'     => $alt,
        'width'   => $el->getAttribute('width'),
        'height'  => $el->getAttribute('height'),
    ];
}

foreach ($xp->query('//*[@data-img]') as $el) {
    /** @var DOMElement $el */
    $iSeq++;
    $id = 'i' . $iSeq;
    $el->setAttribute('data-cms-img', $id);

    [$section] = sectionOf($el);
    $rowLabel = '';
    $titleEl = $xp->query('.//*[@class="work-title" or contains(@class,"title")]', $el);
    if ($titleEl->length) {
        $rowLabel = trim($titleEl->item(0)->textContent);
    }
    if ($rowLabel === '') {
        $rowLabel = trim($el->textContent);
    }

    $fields[] = [
        'id'      => $id,
        'type'    => 'image',
        'attr'    => 'data-img',
        'section' => $section,
        'label'   => $rowLabel !== '' ? labelFrom($rowLabel) . ' — hover frame' : 'Hover frame',
        'default' => $el->getAttribute('data-img'),
        'alt'     => '',
        'width'   => '',
        'height'  => '',
    ];
}

/* ---------------------------------------------------------------
   Write output
   --------------------------------------------------------------- */
$template = $doc->saveHTML();

file_put_contents($root . '/template.html', $template);

$sections = [];
foreach ($fields as $f) {
    $sections[$f['section']] = true;
}

$manifest = [
    'generated' => gmdate('c'),
    'source'    => 'index.html',
    'sections'  => array_keys($sections),
    'counts'    => [
        'text'  => $tSeq,
        'image' => $iSeq,
    ],
    'fields'    => $fields,
];

file_put_contents(
    $root . '/cms-manifest.json',
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

printf(
    "template.html written (%d bytes)\ncms-manifest.json written — %d text fields, %d image fields, %d sections\n",
    strlen($template),
    $tSeq,
    $iSeq,
    count($sections)
);
