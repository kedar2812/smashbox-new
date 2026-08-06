# Stock imagery — swap list

Every photo on the site is a **placeholder** from Pexels, loaded by URL. None of it
is Smashbox work. Replace each one with a real shot before the site goes live.

URL shape currently used:

```
https://images.pexels.com/photos/<ID>/pexels-photo-<ID>.jpeg?auto=compress&cs=tinysrgb&w=<WIDTH>
```

To swap: drop your file into an `img/` folder and replace the whole `src` with
e.g. `img/work-kavya-01.jpg`. Keep the `alt` text accurate — it is read aloud by
screen readers and used by search engines. Keep `loading="lazy"` on everything
except the first hero tile.

---

## Hero — the light table (`#lighttable`)

Eight tiles. The cursor-driven exposure effect looks best on images with a clear
subject and real tonal range; avoid anything already very dark or very flat.

| Tile | Caption | Pexels ID | Wants |
|------|---------|-----------|-------|
| `tile-a` (large) | Fashion | 31719545 | Hero frame. A lit subject on seamless. |
| `tile-b` | Jewellery | 2732096 | Macro on a dark surface |
| `tile-c` | Industrial | 36090199 | Strong graphic form, midtones |
| `tile-d` | Food | 22994309 | Overhead plate |
| `tile-e` (wide) | Interiors | 38310837 | Wide architectural interior |
| `tile-f` | Product | 13898256 | Single product, bold colour |
| `tile-g` | Film | 3062533 | Camera / on set |
| `tile-h` | Architecture | 17594273 | Facade texture |

## Process — the sticky frame (`.pm-img`)

Four images, one per stage. Keep them dark and behind-the-scenes; a bright,
clean stock shot breaks the section.

| Stage | Pexels ID |
|-------|-----------|
| 01 Discovery | 37425739 |
| 02 Direction | 6322363 |
| 03 Production | 19923619 |
| 04 Post &amp; delivery | 15713296 |

## Work grid (`#workGrid`)

Nine cards. `data-cat` drives the filter buttons — keep it when swapping.

| Project | Category (`data-cat`) | Pexels ID |
|---------|----------------------|-----------|
| Elf Power Grip | `product` | 30836149 |
| Kavya Jewels | `jewellery` | 19820885 |
| Saffron &amp; Co | `food` | 1327393 |
| Norwell Interiors | `interiors` | 13722872 |
| Atelier Nine | `fashion` | 31870834 |
| Deccan Forge | `industrial` | 15866139 |
| The Marbury | `film` | 3062541 |
| Studio Verd | `product` | 6867431 |
| Halcyon Hotels | `interiors` | 14590688 |

Card sizes come from the `wide` and `tall` classes. `tall` cards crop to roughly
1:2.4 — give them portrait-orientation photos or the subject will be cut.

## Services — hover previews

Each `<li class="svc">` carries a `data-img` attribute. These are the small
images that follow the cursor. They reuse IDs from the tables above, plus
33231201 for Event coverage.

## Studio — team portraits (`.member`)

Placeholder people. **Replace these first** — they are presented as named team
members, so shipping with stock portraits would be misleading.

| Name in markup | Pexels ID |
|----------------|-----------|
| Taher Husain (lead) | 36645466 |
| Aditi Rane | 29852852 |
| Rohan Kale | 37605831 |
| Imran Shaikh | 10657877 |
| Neha Kulkarni | 11655430 |

## Testimonials — avatars (`.q-card figcaption img`)

Also placeholder people attached to invented quotes and invented company names.
Swap the photos, the quotes and the attributions together, or delete the section
until you have real ones.

---

## Other placeholder content

Not imagery, but equally fake — fix before launch:

- **Client marquee** (`.clients`) — Atelier Nine, Saffron &amp; Co, Norwell
  Interiors, Kavya Jewels, Deccan Forge, The Marbury, Studio Verd, Halcyon
  Hotels are all invented. Replace with real client wordmarks or logos.
- **Stats** — 18+ years and 2008 come from `about.txt`. **400+ projects** and
  **12 industries** are estimates; confirm or change them in `index.html`
  (`data-count` attributes) and in the hero facts list.
- **Contact details** — now real, but **please confirm them before launch**.
  Sourced from public listings, not from you:

  | Field | Value | Where it came from |
  |-------|-------|--------------------|
  | Email | `smashboxindia@gmail.com` | Sulekha listing for Amour Affairs |
  | Phone | `+91 99210 00052` | amouraffairs.in contact page |
  | Address | Rowshni Bungalow, 5 Finance Road, Opp. Ministry of Defence, Agarkar Nagar, Pune, Maharashtra 411001 | Sulekha + amouraffairs.in |

  Caveat: the phone and address are published under **Amour Affairs**, the
  wedding brand run by the same founder (Taher Husainbhai Kolsawala) from the
  same premises. Only the email carries the Smashbox name. If Smashbox uses a
  separate studio line or a different address for commercial work, swap it in
  `index.html` — the values appear in the footer contact column and in the big
  CTA's `mailto:` / `tel:` links.
- **Forms** — the hero and newsletter forms validate the address and show a
  confirmation, but there is no backend. Point them at a real endpoint.
