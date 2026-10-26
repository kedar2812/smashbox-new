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
    $hasClientList = clientNamesAreCustom();
    $clients = $hasClientList ? clientNames() : [];

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = true;
    $doc->formatOutput = false;
    $doc->loadHTML($template, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();

    $xp = new DOMXPath($doc);

    /* ---- client marquee ---- */
    if ($hasClientList) {
        writeClientMarquee($xp, $clients);
    }

    /* ---- email, phone, WhatsApp, social profiles ---- */
    writeLinks($xp, siteLinks());

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
 * Rewrite the client strip from the dashboard's list.
 *
 * The track is scrolled with a plain `translateX(-50%)`, so its contents have
 * to be written twice for the loop to come round without a seam. Spaces inside
 * a name are non-breaking, so "Saffron & Co" never wraps mid-name.
 *
 * An empty list takes the whole strip off the page rather than leaving a
 * bordered band with nothing in it. Every publish starts from template.html
 * again, so putting a name back brings the section straight back.
 */
function writeClientMarquee(DOMXPath $xp, array $names): void
{
    $track = $xp->query('//*[@data-cms-list="clients"]')->item(0);
    if (!$track instanceof DOMElement) {
        return;
    }

    if (!$names) {
        for ($p = $track->parentNode; $p instanceof DOMElement; $p = $p->parentNode) {
            if (strtolower($p->tagName) === 'section') {
                $p->parentNode->removeChild($p);
                return;
            }
        }
        $track->parentNode->removeChild($track);
        return;
    }

    while ($track->firstChild) {
        $track->removeChild($track->firstChild);
    }

    $doc = $track->ownerDocument;

    for ($pass = 0; $pass < 2; $pass++) {
        foreach ($names as $name) {
            $span = $doc->createElement('span');
            $span->appendChild($doc->createTextNode(str_replace(' ', "\u{00A0}", $name)));
            $track->appendChild($span);

            $sep = $doc->createElement('i');
            $sep->appendChild($doc->createTextNode("\u{00B7}"));
            $track->appendChild($sep);
        }
    }
}

/**
 * Point every data-cms-link element at the stored address.
 *
 *   data-cms-link="phone"          href becomes tel:…
 *   data-cms-link-text="phone"     text becomes the number as written,
 *                                  after any data-cms-link-prefix
 *
 * A link whose value is empty is taken off the page — a social icon with no
 * profile behind it, or the WhatsApp button when there is no number.
 * The structured data block for search engines is rewritten to match.
 */
function writeLinks(DOMXPath $xp, array $links): void
{
    $href = static function (string $key, string $value): string {
        if ($value === '') {
            return '';
        }
        return match ($key) {
            'email'    => 'mailto:' . $value,
            'phone'    => phoneHref($value, 'tel'),
            'whatsapp' => phoneHref($value, 'whatsapp'),
            default    => $value,
        };
    };

    foreach (iterator_to_array($xp->query('//*[@data-cms-link]')) as $el) {
        /** @var DOMElement $el */
        $key = $el->getAttribute('data-cms-link');
        if (!array_key_exists($key, $links)) {
            continue;
        }
        $url = $href($key, trim((string) $links[$key]));
        if ($url === '') {
            $el->parentNode?->removeChild($el);
            continue;
        }
        $el->setAttribute('href', $url);
    }

    foreach ($xp->query('//*[@data-cms-link-text]') as $el) {
        /** @var DOMElement $el */
        $key = $el->getAttribute('data-cms-link-text');
        $value = trim((string) ($links[$key] ?? ''));
        if ($value === '') {
            continue;
        }
        while ($el->firstChild) {
            $el->removeChild($el->firstChild);
        }
        $text = $el->getAttribute('data-cms-link-prefix') . $value;
        // a phone number should never break across two lines
        if ($key !== 'email') {
            $text = str_replace(' ', "\u{00A0}", $text);
        }
        $el->appendChild($el->ownerDocument->createTextNode($text));
    }

    $ld = $xp->query('//script[@data-cms-ld]')->item(0);
    if ($ld instanceof DOMElement) {
        $data = json_decode($ld->textContent, true);
        if (is_array($data)) {
            if ($links['email'] !== '') {
                $data['email'] = $links['email'];
            }
            if ($links['phone'] !== '') {
                $data['telephone'] = '+' . (preg_replace('/\D+/', '', $links['phone']) ?? '');
            }
            $data['sameAs'] = array_values(array_filter(
                [$links['instagram'], $links['linkedin'], $links['behance'], $links['youtube']],
                static fn($v): bool => is_string($v) && $v !== ''
            ));
            if (!$data['sameAs']) {
                unset($data['sameAs']);
            }
            while ($ld->firstChild) {
                $ld->removeChild($ld->firstChild);
            }
            $ld->appendChild($ld->ownerDocument->createTextNode(
                json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)
            ));
        }
    }
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

/**
 * Republish when template.html is newer than the last publish.
 *
 * A deploy uploads a new template but deliberately not index.html (that file
 * carries the studio's edits). This brings the two back in line, and does
 * nothing at all when they already are, so it is safe to call from anywhere.
 */
function republishIfTemplateChanged(): bool
{
    clearstatcache(true, TEMPLATE_FILE);
    $tpl = @filemtime(TEMPLATE_FILE);
    $last = settingGet('last_published_at');
    $lastTs = $last !== null ? strtotime($last) : false;

    if ($tpl === false || ($lastTs !== false && $tpl <= $lastTs && is_file(PUBLISHED_FILE))) {
        return false;
    }

    publishSite();
    return true;
}
