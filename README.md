# Smashbox Productions

Marketing site for Smashbox Productions — a Pune-based integrated creative
agency founded in 2008 by Taher Husain, running three divisions: Capture
(photography and film), Create (design and digital) and Connect (influencer
marketing and PR).

Static site with a small PHP dashboard behind it. No build step, no
dependencies, no framework.

## Running it

Any static server will do:

```bash
python -m http.server 8412 --bind 127.0.0.1
```

Then open <http://127.0.0.1:8412>.

Opening `index.html` directly over `file://` mostly works, but the theme
transition and a few other bits behave better over HTTP.

## Structure

```
index.html        the hand-authored page (the live one is generated, see below)
template.html     the page with every editable node tagged data-cms-*
cms-manifest.json the registry of those fields, read by the dashboard
privacy.html      what the site collects
css/styles.css    all styling, both themes
js/main.js        all behaviour, vanilla, no dependencies
js/track.js       the cookieless visit counter behind Analytics
api/              public endpoints: lead.php (forms), track.php (visits),
                  rebuild.php (regenerates index.html after a deploy)
admin/            the Studio Desk dashboard (its own subdomain)
img/              the logo, favicons and og.jpg, the share card
tools/            deploy.sh, field tagging, favicon and share-card builders,
                  and dev/ for running everything locally
logo.png          the master logo supplied by the client
ASSETS.md         every placeholder on the site and how to replace it
```

## What the page says

The running order is built to answer "who are these people?" in the first two
screens, because the breadth of the business is the pitch:

    hero  ->  three divisions  ->  clients  ->  where ideas collide + stats
          ->  process  ->  work  ->  services  ->  industries
          ->  commitment  ->  studio + why + vision  ->  clients' notes
          ->  contact  ->  FAQ

All of the copy comes from the client's brand document: the Capture / Create /
Connect split, the "collision of assorted ideas" story behind the name, the
five-stage process, all forty-five services, the seventeen industries, the
twelve reasons-to-choose, the No Compromise commitment and the vision
statement.

## The idea

Smashbox is a softbox, so light is the material of the site. A soft key light
follows the cursor across a contact sheet of work in the hero, and the photos
physically **expose** — brightening and gaining colour — as it passes over them.
The cursor doubles as a viewfinder that locks focus on hover. Everything else is
kept deliberately quiet so that one idea carries the page.

- **Type** — Bricolage Grotesque (display), Instrument Serif italic (the
  emphasised word in a headline), Inter Tight (body), JetBrains Mono (data and
  labels)
- **Colour** — `#1559A6` in light, `#2E7FD4` in dark, against near-black or
  cool paper. The gradient on emphasised words is the cool half of the Kelvin
  colour-temperature scale, daylight through to shade.
- **Logo** — the client's lockup, white artwork on transparency, inverted to
  ink in light mode by CSS. See ASSETS.md before replacing it.

## Themes

Dark is the default, including with JavaScript disabled. The choice persists in
`localStorage` under `sbx-theme`, and is applied before first paint by an inline
script in `<head>` so the page never flashes.

The toggle animation is [skiper-ui](https://skiper-ui.com)'s `skiper26`
(`createAnimation`, circle variant, blur on, starting top right), ported from
React to vanilla and driven by the View Transitions API. Browsers without
`document.startViewTransition` switch instantly instead.

## Accessibility

Keyboard focus is visible throughout, the accordion and filters are real
buttons with `aria-expanded` / `aria-selected`, and `prefers-reduced-motion`
disables the custom cursor, the key light, the reveals and the theme
transition.

## Before this goes live

The site is fully built but the *content* is placeholder. `ASSETS.md` lists
every item. The short version:

- All photography is stock (Pexels), loaded by URL. The design and campaign
  work in the grid is represented by photographs of other things — that is the
  weakest spot on the page and the first thing to fix
- The five named team members and the five testimonials are invented, with
  stock portraits — replace these first (all editable from the dashboard)
- The client marquee — the studio has already put its real clients on it from
  the dashboard's Client strip page; the work grid and testimonials still name
  the invented brands
- "400+ projects" is an estimate. Everything else on the numbers — 18+ years,
  2008, three divisions, 46 services, 17 industries — comes from the client's
  brand document
- Contact details were taken from public listings and should be confirmed
- Social profile links are empty until set in Contact & links, so their icons
  are hidden; the WhatsApp button assumes the phone number is on WhatsApp
- Still missing from the client's wish list, because they all need real
  material: the cinematic showreel behind the hero, video-on-hover in the work
  grid, a behind-the-scenes section, awards and a journal

## The dashboard — Studio Desk

`admin.smashboxproductions.com`, document root `admin/`. One account. It
writes the public `index.html` from `template.html` plus every saved edit, so
nothing the studio changes ever touches code.

- **Overview** — this week's visitors, clicks, enquiries and time on site, the
  latest enquiries, where visitors come from, and a short list of sample
  content still on the site
- **Analytics** — visitors over time, sources, countries, what people click
  (calls, WhatsApp, email, buttons), devices, scroll depth, browsers and
  campaigns, for today, 7, 30 or 90 days. Counted by `js/track.js` +
  `api/track.php` without cookies or stored IP addresses (see the comment at the
  top of `admin/inc/analytics.php`); the table creates itself on first use.
  Visiting the site once with `?sbx-ignore=1` stops counting that browser
- **Edit visually** — the live page in a frame; click any words or picture to
  change them, see the change on the page as you type, then save. Desktop,
  tablet and phone widths
- **Words** and **Pictures** — the same fields as lists, searchable, grouped by
  section, with plain names ("Team — Taher Husain", "Button label in the top
  bar") worked out in `admin/inc/fields.php`
- **Client strip**, **Contact & links** (email, phone, WhatsApp, social
  profiles — written into every button that uses them), **Leads**, **Account**

Text never reaches the browser as markup: `admin/inc/richtext.php` splits each
field into the design's skeleton and the studio's words, so an edit can change
what the page says but never its structure. Uploads show a progress bar, and
photos are resized in the browser first so nothing straight off a camera is
ever too big. Anything unsaved — in a sheet, the visual editor, the client
list, the links, a lead's note — asks before it is lost: save, discard, or
keep editing.

### Making more of the page editable

Tag the element in `template.html` and register it in the manifest — or add an
XPath to `tools/add_fields.php` and run it. Ids are never reused or moved.

## Running it locally

See `tools/dev/README.md`: a scratch MariaDB, `php -S` for the site and the
dashboard, and a seed with demo leads and three months of demo analytics.

## Deploying

```bash
bash tools/deploy.sh
```

Uploads over FTPS (credentials in `tools/deploy.env`, gitignored), then calls
`api/rebuild.php`, which regenerates `index.html` from the new template with
every saved edit intact. `index.html` itself is never uploaded.
