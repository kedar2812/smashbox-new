<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Apply every stored override to template.html and return the finished page.
 *
 * template.html is already DOM-normalised by tools/build_template.php, so
 * re-serialising it here is idempotent — the output does not drift on
 * repeated publishes.
 */
function renderSite(): string
{
    $template = file_get_contents(TEMPLATE_FILE);
    if ($template === false) {
        throw new RuntimeException('template.html is missing.');
    }

    $overrides = contentOverrides();
    if (!$overrides) {
        return $template;
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = true;
    $doc->formatOutput = false;
    $doc->loadHTML($template, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    $xp = new DOMXPath($doc);

    /* ---- text ---- */
    foreach ($xp->query('//*[@data-cms-text]') as $el) {
        /** @var DOMElement $el */
        $id = $el->getAttribute('data-cms-text');
        if (!isset($overrides[$id])) {
            continue;
        }

        $html = sanitizeInline($overrides[$id]['value']);
        replaceInner($el, $html);
    }

    /* ---- images ---- */
    foreach ($xp->query('//*[@data-cms-img]') as $el) {
        /** @var DOMElement $el */
        $id = $el->getAttribute('data-cms-img');
        if (!isset($overrides[$id])) {
            continue;
        }

        $field = fieldById($id);
        $attr = $field['attr'] ?? 'src';
        $value = trim($overrides[$id]['value']);

        if ($value !== '') {
            $el->setAttribute($attr, $value);

            // a swapped-in file rarely matches the baked-in dimensions
            if ($attr === 'src' && $el->tagName === 'img') {
                $el->removeAttribute('width');
                $el->removeAttribute('height');
            }
        }

        $alt = $overrides[$id]['alt'];
        if ($alt !== null && $el->tagName === 'img') {
            $el->setAttribute('alt', $alt);
        }
    }

    return $doc->saveHTML();
}

/**
 * Replace an element's children with a fragment of inline HTML.
 * Falls back to a plain text node if the fragment will not parse.
 */
function replaceInner(DOMElement $el, string $html): void
{
    while ($el->firstChild) {
        $el->removeChild($el->firstChild);
    }

    if ($html === '') {
        return;
    }

    if (!preg_match('/<[a-z]/i', $html)) {
        $el->appendChild($el->ownerDocument->createTextNode(
            html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8')
        ));
        return;
    }

    $frag = $el->ownerDocument->createDocumentFragment();
    // appendXML is strict; wrap and re-parse through HTML rules instead
    $tmp = new DOMDocument();
    libxml_use_internal_errors(true);
    $tmp->loadHTML(
        '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><div id="sbx-frag">' . $html . '</div>',
        LIBXML_NOERROR | LIBXML_NOWARNING
    );
    libxml_clear_errors();

    $holder = $tmp->getElementById('sbx-frag');
    if (!$holder) {
        $el->appendChild($el->ownerDocument->createTextNode(plainText($html)));
        return;
    }

    foreach (iterator_to_array($holder->childNodes) as $child) {
        $frag->appendChild($el->ownerDocument->importNode($child, true));
    }
    $el->appendChild($frag);
}

/**
 * Render and write index.html.
 *
 * Writes to a temp file in the same directory and renames, so a visitor
 * never sees a partially written page.
 */
function publishSite(): array
{
    $html = renderSite();

    $tmp = PUBLISHED_FILE . '.tmp-' . bin2hex(random_bytes(4));

    if (file_put_contents($tmp, $html, LOCK_EX) === false) {
        throw new RuntimeException('Could not write the published page. Check folder permissions.');
    }

    @chmod($tmp, 0644);

    if (!@rename($tmp, PUBLISHED_FILE)) {
        @unlink($tmp);
        throw new RuntimeException('Could not replace index.html. Check folder permissions.');
    }

    settingSet('last_published_at', gmdate('c'));

    return ['bytes' => strlen($html), 'at' => gmdate('c')];
}

/** Value currently in force for a field — override if set, else the original. */
function effectiveValue(array $field, array $overrides): string
{
    return $overrides[$field['id']]['value'] ?? (string) $field['default'];
}

function effectiveAlt(array $field, array $overrides): string
{
    $o = $overrides[$field['id']]['alt'] ?? null;
    return $o !== null ? $o : (string) ($field['alt'] ?? '');
}
