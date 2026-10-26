<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Plain names for the things on the page.
 *
 * The manifest knows a field's tag and section; the person using the
 * dashboard knows "the founder's photo" and "the button at the top". This
 * reads template.html once per request to see where each field actually
 * sits — inside a team member's caption, on a service row, in a button — and
 * names it from that.
 */

/** @return array<string, array{kind:string, title:string, hidden:bool, hint:string}> */
function fieldContext(): array
{
    static $ctx = null;
    if ($ctx !== null) {
        return $ctx;
    }
    $ctx = [];

    $html = @file_get_contents(TEMPLATE_FILE);
    if ($html === false) {
        return $ctx;
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    $xp = new DOMXPath($doc);

    $has = static fn(?DOMNode $el, string $cls): bool =>
        $el instanceof DOMElement && in_array($cls, preg_split('/\s+/', $el->getAttribute('class')) ?: [], true);

    $closest = static function (DOMNode $el, string $cls) use ($has): ?DOMElement {
        for ($p = $el; $p instanceof DOMElement; $p = $p->parentNode) {
            if ($has($p, $cls)) {
                return $p;
            }
        }
        return null;
    };

    $text = static fn(?DOMNode $n): string => $n ? trim(preg_replace('/\s+/u', ' ', $n->textContent) ?? '') : '';

    /* ---- words ---- */
    foreach ($xp->query('//*[@data-cms-text]') as $el) {
        /** @var DOMElement $el */
        $id = $el->getAttribute('data-cms-text');
        $tag = strtolower($el->tagName);
        $parent = $el->parentNode;
        $len = mb_strlen($text($el));

        $kind = match (true) {
            $tag === 'h1'                                       => 'Main headline',
            $tag === 'h2'                                       => 'Headline',
            $tag === 'h4'                                       => 'Column heading',
            $tag === 'blockquote'                               => 'Testimonial',
            $has($el, 'acc-q')                                  => 'Question',
            $closest($el, 'acc-a') !== null                     => 'Answer',
            $has($el, 'eyebrow') || $has($el, 'cta-mid-k') || $has($el, 'svc-card-k') => 'Small label',
            $has($el, 'svc-title')                              => 'Column heading',
            $has($el, 'svc-group')                              => 'Group heading',
            $has($el, 'svc-name')                               => 'Service',
            $has($el, 'card-cat')                               => 'Category',
            $closest($el, 'div-tags') !== null                  => 'Tag',
            $has($el, 'div-line')                               => 'Subtitle',
            $has($el, 'link-arrow')                             => 'Link',
            $has($el, 'foot-addr')                              => 'Address',
            $has($el, 'field-note')                             => 'Small print',
            $has($el, 'hero-divline')                           => 'Division links',
            $has($el, 'brief-title')                            => 'Form title',
            $tag === 'b' && ($closest($el, 'stat') || $closest($el, 'hero-facts')) => 'Figure',
            $tag === 'span' && ($closest($el, 'stat') || $closest($el, 'hero-facts')) => 'What the figure counts',
            $tag === 'b' && $closest($el, 'member')             => 'Name',
            $tag === 'span' && $closest($el, 'member')          => 'Role',
            $closest($el, 'q-card') !== null && $tag === 'span' => 'Name and role',
            $tag === 'span' && ($parent instanceof DOMElement) && (strtolower($parent->tagName) === 'button' || $has($parent, 'btn')) => 'Button label',
            $tag === 'h3' && $closest($el, 'card')              => 'Project title',
            $tag === 'h3' && $closest($el, 'step')              => 'Step title',
            $tag === 'h3'                                       => 'Title',
            $closest($el, 'card') !== null                      => 'Project description',
            $closest($el, 'step') !== null                      => 'Step description',
            $has($el, 'vision')                                 => 'Vision statement',
            $len <= 60                                          => 'Short line',
            default                                             => 'Paragraph',
        };

        $title = $kind;
        // the same label in two places needs telling apart
        if ($kind === 'Button label' && $closest($el, 'mobile-menu')) {
            $title = 'Button label in the phone menu';
        } elseif ($kind === 'Button label' && $closest($el, 'nav-end')) {
            $title = 'Button label in the top bar';
        }

        $ctx[$id] = ['kind' => $kind, 'title' => $title, 'hidden' => false, 'hint' => ''];
    }

    /* ---- pictures ---- */
    foreach ($xp->query('//*[@data-cms-img]') as $el) {
        /** @var DOMElement $el */
        $id = $el->getAttribute('data-cms-img');
        $attr = $el->hasAttribute('data-img') && strtolower($el->tagName) !== 'img' ? 'data-img' : 'src';
        $alt = trim($el->getAttribute('alt'));
        $hidden = false;
        $hint = '';

        if ($has($el, 'brand-logo')) {
            $title = $closest($el, 'brand--foot') ? 'Logo in the footer' : 'Logo in the top bar';
            $hint = 'Use a white logo on a transparent background (PNG). The site darkens it automatically in light mode.';
        } elseif ($attr === 'data-img') {
            $title = 'Hover picture — ' . $text($el->getElementsByTagName('span')->item(1) ?? $el);
            $hint = 'Appears beside the pointer when someone hovers this service.';
        } elseif ($el->getAttribute('id') === 'svcPeekImg') {
            $title = 'Service preview';
            $hidden = true;                      // filled in by the page itself
        } elseif ($t = $closest($el, 'tile')) {
            $title = 'Opening screen — ' . $text($t->getElementsByTagName('figcaption')->item(0));
        } elseif ($has($el, 'pm-img')) {
            $steps = ['Discover', 'Strategize', 'Create', 'Deliver', 'Connect'];
            $title = 'Process — ' . ($steps[(int) $el->getAttribute('data-step')] ?? 'step');
        } elseif ($c = $closest($el, 'card')) {
            $title = 'Work — ' . $text($c->getElementsByTagName('h3')->item(0));
        } elseif ($m = $closest($el, 'member')) {
            $title = 'Team — ' . $text($m->getElementsByTagName('b')->item(0));
        } elseif ($q = $closest($el, 'q-card')) {
            $title = 'Client photo — ' . $text($q->getElementsByTagName('b')->item(0));
        } else {
            $title = $alt !== '' ? $alt : 'Picture';
        }

        $ctx[$id] = ['kind' => $attr === 'data-img' ? 'Hover picture' : 'Picture', 'title' => $title, 'hidden' => $hidden, 'hint' => $hint];
    }

    return $ctx;
}

/** Human name for a field, e.g. "Team — Taher Husain" or "Button label". */
function fieldTitle(array $f): string
{
    return fieldContext()[$f['id']]['title'] ?? ($f['type'] === 'image' ? 'Picture' : 'Text');
}

function fieldKind(array $f): string
{
    return fieldContext()[$f['id']]['kind'] ?? '';
}

function fieldHint(array $f): string
{
    return fieldContext()[$f['id']]['hint'] ?? '';
}

/** Fields the studio can usefully edit — excludes the ones the page fills itself. */
function editableFields(string $type): array
{
    return array_values(array_filter(
        fieldsByType($type),
        static fn(array $f): bool => !(fieldContext()[$f['id']]['hidden'] ?? false)
    ));
}

/** Section key -> the name the studio would use for that part of the page. */
function sectionTitle(string $key): string
{
    static $names = [
        'header'     => 'Top bar',
        'hero'       => 'Opening screen',
        'divisions'  => 'Three divisions',
        'clients'    => 'Client strip',
        'commit'     => 'Numbers and the name',
        'process'    => 'Process',
        'work'       => 'Selected work',
        'services'   => 'Services',
        'industries' => 'Industries',
        'section'    => 'Commitment banner',
        'cta-mid-wrap' => 'Commitment banner',
        'studio'     => 'Studio and team',
        'quotes'     => 'Client notes',
        'contact'    => 'Contact',
        'faq'        => 'Questions',
        'footer'     => 'Footer',
        'page'       => 'Other',
    ];
    return $names[$key] ?? ucfirst($key);
}

/** Group fields by section, keeping the order they appear in. */
function groupBySection(array $fields): array
{
    $groups = [];
    foreach ($fields as $f) {
        $groups[$f['section']][] = $f;
    }
    return $groups;
}

/**
 * A field's words for a list row: highlight and bold kept, everything else
 * (links, classed spans, line breaks) flattened so it can sit inside a button.
 */
function displayInline(string $html): string
{
    $html = preg_replace('#<br\s*/?>#i', ' ', $html) ?? $html;
    $html = strip_tags($html, '<em><strong><b><i>');
    // strip_tags keeps attributes on the tags it allows
    $html = preg_replace('#<(em|strong|b|i)\s[^>]*>#i', '<$1>', $html) ?? $html;
    return trim(preg_replace('/\s+/u', ' ', $html) ?? $html);
}
