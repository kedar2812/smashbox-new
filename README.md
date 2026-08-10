# Smashbox Productions

Marketing site for Smashbox Productions — a Pune-based integrated creative
agency founded in 2008 by Taher Husain, running three divisions: Capture
(photography and film), Create (design and digital) and Connect (influencer
marketing and PR).

Static site. No build step, no dependencies, no framework.

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
index.html        the whole page
css/styles.css    all styling, both themes
js/main.js        all behaviour, vanilla, no dependencies
img/              the logo, web-sized
logo.png          the master logo supplied by the client
about.txt         the client's first brief (superseded by the brand document)
ASSETS.md         every placeholder on the site and how to replace it
design inspo/     reference screenshots the design was briefed against
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
  stock portraits — replace these first
- The client marquee is eight invented brand names
- "400+ projects" is an estimate. Everything else on the numbers — 18+ years,
  2008, three divisions, 45 services, 17 industries — comes from the client's
  brand document
- Contact details were taken from public listings and should be confirmed
- Both forms validate and confirm, but have no backend
- Still missing from the client's wish list, because they all need real
  material: the cinematic showreel behind the hero, video-on-hover in the work
  grid, a behind-the-scenes section, awards and a journal
