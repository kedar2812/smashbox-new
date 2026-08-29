<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Splitting a field's markup from its words.
 *
 * The dashboard must never show a tag to the person using it. Every editable
 * block is therefore taken apart into two things:
 *
 *   skeleton  the markup, with {{0}} {{1}} … where the words used to be
 *   slots     the words, as runs of plain text carrying at most bold/highlight
 *
 * The browser only ever sees and returns slots. The skeleton stays on the
 * server and is rebuilt from the stored value at save time, so the markup on
 * the page cannot be changed from the browser at all — not by a paste out of
 * Word, not by a hand-written tag, not by anything.
 *
 * Three kinds of node, decided by isEmphasis():
 *
 *   emphasis    <em> <strong> <br> and friends, bare of attributes and
 *               holding actual words — these are the studio's to set, and
 *               become runs inside a slot
 *   decorative  empty elements like <span class="eyebrow-dot"></span>, and
 *               separators like <i>·</i> that hold no letters — these go into
 *               the skeleton untouched and are never shown
 *   structural  anything else: a classed span, a link. Its contents become a
 *               slot of their own, so "Capture <span>Photography & film</span>"
 *               is edited as two plain boxes rather than one line of HTML
 */

const SKELETON_TAGS = ['br', 'em', 'strong', 'b', 'i', 'span', 'a', 'sup', 'sub', 'u'];
const EMPHASIS_TAGS = ['em', 'strong', 'b', 'i', 'u', 'br'];

/** Is this element the studio's to toggle, rather than part of the design? */
function isEmphasis(DOMElement $el): bool
{
    $tag = strtolower($el->tagName);

    if (!in_array($tag, EMPHASIS_TAGS, true)) {
        return false;
    }
    // a class or an href means the design put it there
    if ($el->attributes !== null && $el->attributes->length > 0) {
        return false;
    }
    if ($tag === 'br') {
        return true;
    }

    $text = trim($el->textContent);
    if ($text === '') {
        return false;                           // decorative, e.g. the eyebrow dot
    }
    // a bullet or a dash between links is a separator, not an emphasised word
    return (bool) preg_match('/[\p{L}\p{N}]/u', $text);
}

/** Which mark a run carries, if any. */
function emphasisKind(string $tag): ?string
{
    return match (strtolower($tag)) {
        'strong', 'b' => 'strong',
        'em', 'i', 'u' => 'em',
        default => null,
    };
}

/**
 * Take a field apart.
 *
 * @return array{skeleton:string, slots:array<int,array<int,array>>}
 */
function decomposeField(string $html): array
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
        . '<div id="sbx-root">' . $html . '</div>',
        LIBXML_NOERROR | LIBXML_NOWARNING
    );
    libxml_clear_errors();

    $root = $doc->getElementById('sbx-root');
    if (!$root) {
        return ['skeleton' => '{{0}}', 'slots' => [[['t' => plainText($html)]]]];
    }

    $slots = [];
    $labels = [];
    $skeleton = walkForSkeleton($root, $slots, $labels, null);

    if (!$slots) {
        // nothing editable in there — keep it whole so it cannot be lost
        return ['skeleton' => '{{0}}', 'slots' => [[['t' => plainText($html)]]], 'labels' => ['Text']];
    }

    return [
        'skeleton' => $skeleton,
        'slots'    => $slots,
        'labels'   => numberDuplicates($labels),
    ];
}

/**
 * A name for each box, so a field in several parts does not read as
 * "Part 1 of 3". Repeats get numbered: three lines become Line 1, 2, 3.
 *
 * @param array<int,?string> $labels
 * @return array<int,string>
 */
function numberDuplicates(array $labels): array
{
    $out = array_map(static fn(?string $l): string => $l ?? 'Main text', $labels);

    $counts = array_count_values($out);
    $seen = [];

    foreach ($out as $i => $label) {
        if (($counts[$label] ?? 0) < 2) {
            continue;
        }
        $seen[$label] = ($seen[$label] ?? 0) + 1;
        $out[$i] = $label . ' ' . $seen[$label];
    }

    return $out;
}

/**
 * What to call a box, taken from the element its words sit in.
 *
 * Class names are the design's own vocabulary, so they read better than any
 * numbering would: span.line becomes "Line", span.vision-k becomes "Vision".
 */
function contextLabel(?DOMElement $el): ?string
{
    if ($el === null) {
        return null;                            // sits at the top level
    }

    if (strtolower($el->tagName) === 'a') {
        return 'Link';
    }

    // an unclassed wrapper says nothing; the name is on whatever encloses it,
    // as with the hero's <span class="line"><span>…</span></span>
    for ($n = $el; $n instanceof DOMElement; $n = $n->parentNode) {
        if ($n->getAttribute('id') === 'sbx-root') {
            break;
        }
        $class = trim($n->getAttribute('class'));
        if ($class === '') {
            continue;
        }
        $first = explode(' ', $class)[0];
        // trailing initials like the -k in vision-k are shorthand, not a word
        $first = preg_replace('/-[a-z]$/', '', $first) ?? $first;
        $words = trim(str_replace('-', ' ', $first));
        if ($words !== '') {
            return ucfirst($words);
        }
    }

    return strtolower($el->tagName) === 'span' ? 'Smaller line' : null;
}

/** Recurse, emitting skeleton markup and collecting slots as we go. */
function walkForSkeleton(DOMElement $el, array &$slots, array &$labels, ?DOMElement $context): string
{
    $out = '';
    $buffer = [];   // text and emphasis nodes waiting to become a slot

    foreach ($el->childNodes as $child) {
        if ($child instanceof DOMText) {
            $buffer[] = $child;
            continue;
        }
        if (!$child instanceof DOMElement) {
            continue;                           // comments and the like are dropped
        }
        if (isEmphasis($child)) {
            $buffer[] = $child;
            continue;
        }

        // structural or decorative — it belongs to the skeleton
        $out .= flushBuffer($buffer, $slots, $labels, $context);
        $out .= openTag($child);

        if (isDecorative($child)) {
            // the eyebrow dot, the · between the nav links: chrome, not copy.
            // Kept whole and never offered for editing.
            $out .= escapeText(collapse($child->textContent));
        } else {
            $out .= walkForSkeleton($child, $slots, $labels, $child);
        }

        if (!isVoidTag($child->tagName)) {
            $out .= '</' . strtolower($child->tagName) . '>';
        }
    }

    return $out . flushBuffer($buffer, $slots, $labels, $context);
}

/** Turn whatever has piled up into a numbered placeholder. */
function flushBuffer(array &$buffer, array &$slots, array &$labels, ?DOMElement $context): string
{
    if (!$buffer) {
        return '';
    }

    [$runs, $lead, $trail] = runsFromNodes($buffer);
    $buffer = [];

    if (!$runs) {
        return $lead !== '' || $trail !== '' ? ' ' : '';
    }

    $out = $lead . '{{' . count($slots) . '}}' . $trail;
    $slots[] = $runs;
    $labels[] = contextLabel($context);

    return $out;
}

function isVoidTag(string $tag): bool
{
    return strtolower($tag) === 'br';
}

/**
 * Chrome rather than copy: an element holding no letters or digits.
 *
 * Covers the empty <span class="eyebrow-dot"></span> and the <i>·</i> that
 * sits between the nav links. There is nothing in either worth editing, and
 * offering them as boxes would only invite someone to empty one.
 */
function isDecorative(DOMElement $el): bool
{
    return !preg_match('/[\p{L}\p{N}]/u', $el->textContent);
}

/** Re-emit a structural tag, keeping only attributes the site actually uses. */
function openTag(DOMElement $el): string
{
    $tag = strtolower($el->tagName);
    if (!in_array($tag, SKELETON_TAGS, true)) {
        return '';                              // unwrap anything unexpected
    }

    $out = '<' . $tag;

    $class = $el->getAttribute('class');
    if ($class !== '') {
        $out .= ' class="' . e($class) . '"';
    }

    if ($tag === 'a') {
        $href = trim($el->getAttribute('href'));
        if ($href !== '' && preg_match('#^(\#|https?://|mailto:|tel:|/)#i', $href)) {
            $out .= ' href="' . e($href) . '"';
        }
        foreach (['target', 'rel'] as $a) {
            $v = $el->getAttribute($a);
            if ($v !== '') {
                $out .= ' ' . $a . '="' . e($v) . '"';
            }
        }
    }

    return $out . '>';
}

/**
 * Turn a run of text and emphasis nodes into slot runs.
 *
 * Leading and trailing whitespace is handed back separately so it can stay in
 * the skeleton — otherwise trimming "Capture " for display would weld it to
 * the subtitle that follows it.
 *
 * @return array{0:array<int,array>, 1:string, 2:string}
 */
function runsFromNodes(array $nodes): array
{
    $runs = [];

    foreach ($nodes as $n) {
        if ($n instanceof DOMText) {
            $runs[] = ['t' => collapse($n->nodeValue ?? '')];
            continue;
        }
        /** @var DOMElement $n */
        if (strtolower($n->tagName) === 'br') {
            $runs[] = ['br' => true];
            continue;
        }
        $mark = emphasisKind($n->tagName);
        $run = ['t' => collapse($n->textContent)];
        if ($mark !== null) {
            $run[$mark] = true;
        }
        $runs[] = $run;
    }

    // pull the outer whitespace out into the skeleton
    $lead = '';
    $trail = '';

    if ($runs && isset($runs[0]['t'])) {
        if (preg_match('/^\s/', $runs[0]['t'])) {
            $lead = ' ';
        }
        $runs[0]['t'] = ltrim($runs[0]['t']);
    }

    $last = count($runs) - 1;
    if ($last >= 0 && isset($runs[$last]['t'])) {
        if (preg_match('/\s$/', $runs[$last]['t'])) {
            $trail = ' ';
        }
        $runs[$last]['t'] = rtrim($runs[$last]['t']);
    }

    // drop runs that ended up empty, but keep line breaks
    $runs = array_values(array_filter(
        $runs,
        static fn(array $r): bool => isset($r['br']) || ($r['t'] ?? '') !== ''
    ));

    return [$runs, $lead, $trail];
}

/**
 * Escape a run of words.
 *
 * Only the three characters that would be read as markup. e() is for
 * attributes and turns every apostrophe into &#039;, which is correct but
 * litters copy that is full of them.
 */
function escapeText(string $s): string
{
    return htmlspecialchars($s, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Newlines and runs of spaces in the source are not content. */
function collapse(string $s): string
{
    return preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $s)) ?? '';
}

/**
 * Put a field back together from slots the browser sent.
 *
 * The skeleton is always the one derived from the stored value, never anything
 * the browser supplied, so this cannot introduce markup.
 */
function composeField(string $skeleton, array $slots): string
{
    // One pass over the skeleton, so words that happen to contain {{0}} are
    // left alone rather than being read as a placeholder and swallowed. A
    // placeholder with no slot to fill it simply empties.
    $html = preg_replace_callback(
        '/\{\{(\d+)\}\}/',
        static function (array $m) use ($slots): string {
            $i = (int) $m[1];
            return isset($slots[$i]) && is_array($slots[$i]) ? renderRuns($slots[$i]) : '';
        },
        $skeleton
    ) ?? $skeleton;

    return trim($html);
}

/** One slot's runs as HTML. */
function renderRuns(array $runs): string
{
    $out = '';

    foreach ($runs as $run) {
        if (!is_array($run)) {
            continue;
        }
        if (!empty($run['br'])) {
            $out .= '<br>';
            continue;
        }

        $text = collapse((string) ($run['t'] ?? ''));
        if ($text === '') {
            continue;
        }

        $text = escapeText($text);

        // <em> is the site's highlight: serif italic under a colour ramp
        if (!empty($run['em'])) {
            $text = '<em>' . $text . '</em>';
        }
        if (!empty($run['strong'])) {
            $text = '<strong>' . $text . '</strong>';
        }

        $out .= $text;
    }

    return $out;
}

/** Everything a slot holds, as one plain string — used for labels and checks. */
function slotPlain(array $runs): string
{
    $out = '';

    foreach ($runs as $run) {
        if (!empty($run['br'])) {
            $out .= ' ';
        } else {
            $out .= (string) ($run['t'] ?? '');
        }
    }

    return trim(preg_replace('/\s+/u', ' ', $out) ?? '');
}
