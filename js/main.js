/* =========================================================
   SMASHBOX PRODUCTIONS — interactions
   No dependencies. Everything degrades without JS.
   ========================================================= */
(function () {
  'use strict';

  const $  = (s, c) => (c || document).querySelector(s);
  const $$ = (s, c) => Array.from((c || document).querySelectorAll(s));
  const lerp = (a, b, t) => a + (b - a) * t;
  const clamp = (v, a, b) => Math.min(b, Math.max(a, v));

  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const fine    = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  /* ---------------------------------------------------------
     0. THEME — light by default, dark on request.
        Transition ported from skiper-ui / skiper26
        (createAnimation), locked to: variant "circle",
        start "top-right", blur on.
     --------------------------------------------------------- */
  (function theme() {
    const KEY = 'sbx-theme';
    const root = document.documentElement;
    const btn = $('#themeToggle');
    const STYLE_ID = 'theme-transition-styles';

    const current = () => root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';

    // skiper26: generateSVG() — circle-blur, top-right is cx 40 / cy 0
    function createAnimation() {
      const svg = "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'>" +
                  "<defs><filter id='blur'><feGaussianBlur stdDeviation='2'/></filter></defs>" +
                  "<circle cx='40' cy='0' r='18' fill='white' filter='url(%23blur)'/></svg>";
      return `
        ::view-transition-group(root){
          animation-timing-function: var(--expo-out);
          animation-duration: .9s;
        }
        ::view-transition-new(root){
          mask: url("${svg}") top right / 0 no-repeat;
          mask-origin: content-box;
          /* 'forwards' matters: without it the mask snaps back to
             size 0 for one frame before the snapshot is torn down */
          animation: sbx-scale .9s var(--expo-out) forwards;
          transform-origin: top right;
        }
        ::view-transition-old(root){
          animation: none;
          z-index: -1;
        }
        @keyframes sbx-scale { from { mask-size: 0; } to { mask-size: 350vmax; } }
      `;
    }

    function updateStyles(css) {
      let el = document.getElementById(STYLE_ID);
      if (!el) {
        el = document.createElement('style');
        el.id = STYLE_ID;
        document.head.appendChild(el);
      }
      el.textContent = css;
    }

    function apply(next) {
      root.setAttribute('data-theme', next);
      try { localStorage.setItem(KEY, next); } catch (e) {}
      const meta = document.querySelector('meta[name="theme-color"]');
      if (meta) meta.setAttribute('content', next === 'dark' ? '#07070A' : '#EFF1F4');
      if (btn) {
        const toDark = next === 'light';
        btn.setAttribute('aria-pressed', String(next === 'dark'));
        btn.setAttribute('aria-label', toDark ? 'Switch to dark theme' : 'Switch to light theme');
      }
    }

    apply(current());

    if (!btn) return;

    let busy = false;

    btn.addEventListener('click', () => {
      // a second click mid-flight aborts the running transition, which
      // shows up as a hard cut. Ignore it instead.
      if (busy) return;

      const next = current() === 'dark' ? 'light' : 'dark';

      if (reduced || !document.startViewTransition) { apply(next); return; }

      updateStyles(createAnimation());

      // freeze every colour/background transition on the page for the
      // duration — otherwise they keep running under the snapshot and
      // jump the moment it is removed
      root.classList.add('theme-switching');
      busy = true;

      const done = () => { root.classList.remove('theme-switching'); busy = false; };

      const vt = document.startViewTransition(() => apply(next));
      // a skipped transition (background tab, resize mid-flight) rejects
      // both promises; the theme still applies, so treat it as success
      vt.ready.catch(() => {});
      vt.finished.then(done, done);
    });
  })();

  /* ---------------------------------------------------------
     1. PRELOADER — exposure meter, then hand off to the hero
     --------------------------------------------------------- */
  function preload() {
    const loader = $('#loader');
    const num    = $('#loaderNum');
    const bar    = $('#loaderBar');
    if (!loader) return start();

    // the meter plays on the first page of a visit only
    if (reduced || document.documentElement.classList.contains('no-loader')) {
      loader.classList.add('is-done');
      return start();
    }

    let p = 0;
    const tick = setInterval(() => {
      // ease toward 100, slower near the end — about a second in all
      p += Math.max(1.6, (100 - p) * 0.13);
      if (p >= 100) { p = 100; clearInterval(tick); finish(); }
      num.textContent = String(Math.floor(p)).padStart(2, '0');
      bar.style.width = p + '%';
    }, 34);

    function finish() {
      setTimeout(() => {
        loader.classList.add('is-done');
        start();
      }, 180);
    }
  }

  function start() {
    document.documentElement.classList.add('is-ready');
    document.body.classList.add('is-ready');
  }

  // The hero only needs its own pictures, not every lazy image further down,
  // so the meter starts once the document is parsed rather than at load.
  if (document.readyState !== 'loading') preload();
  else document.addEventListener('DOMContentLoaded', preload);
  // safety net: never leave the page behind a loader
  setTimeout(() => {
    const l = $('#loader');
    if (l && !l.classList.contains('is-done')) { l.classList.add('is-done'); start(); }
  }, 4500);

  /* ---------------------------------------------------------
     2. CURSOR + KEY LIGHT — the signature
        One pointer drives: the viewfinder cursor, the visible
        key light, and the per-tile exposure on the light table.
     --------------------------------------------------------- */
  const pointer = { x: innerWidth * 0.62, y: innerHeight * 0.5, has: false };
  let lightX = pointer.x, lightY = pointer.y;

  if (fine && !reduced) {
    const cursor = $('#cursor');
    const keyl   = $('#keylight');
    const label  = $('#cursorLabel');
    let cx = pointer.x, cy = pointer.y;

    addEventListener('pointermove', (e) => {
      pointer.x = e.clientX; pointer.y = e.clientY;
      if (!pointer.has) { pointer.has = true; keyl.classList.add('is-on'); }
    }, { passive: true });

    // hover states: photos get a focus lock, links get a soft disc
    const focusSel = '.tile, .card, .member, .process-frame';
    const linkSel  = 'a, button, input, .svc, .pill, .filter';

    addEventListener('pointerover', (e) => {
      const t = e.target;
      if (!(t instanceof Element)) return;
      const focusEl = t.closest(focusSel);
      const linkEl  = t.closest(linkSel);
      cursor.classList.toggle('is-focus', !!focusEl);
      cursor.classList.toggle('is-link', !focusEl && !!linkEl);
      if (focusEl) {
        // short, viewfinder-style label only — never the whole caption
        const tag = focusEl.dataset.cursor
          || (focusEl.querySelector('.card-cat, figcaption') || {}).firstChild;
        const txt = typeof tag === 'string' ? tag : (tag && tag.textContent) || 'focus';
        label.textContent = txt.trim().slice(0, 16);
      }
    }, { passive: true });

    // the loop parks itself once the light has caught up with the pointer
    let running = false;
    const kick = () => { if (!running) { running = true; requestAnimationFrame(raf); } };

    function raf() {
      cx = lerp(cx, pointer.x, 0.22);
      cy = lerp(cy, pointer.y, 0.22);
      lightX = lerp(lightX, pointer.x, 0.11);
      lightY = lerp(lightY, pointer.y, 0.11);
      cursor.style.transform = `translate3d(${cx}px,${cy}px,0)`;
      keyl.style.transform   = `translate3d(${lightX}px,${lightY}px,0)`;

      const settled = Math.abs(cx - pointer.x) < 0.4 && Math.abs(cy - pointer.y) < 0.4 &&
                      Math.abs(lightX - pointer.x) < 0.4 && Math.abs(lightY - pointer.y) < 0.4;
      if (settled) running = false;
      else requestAnimationFrame(raf);
    }

    addEventListener('pointermove', kick, { passive: true });
    addEventListener('scroll', kick, { passive: true });
    kick();
  }

  /* ---------------------------------------------------------
     3. THE LIGHT TABLE — tiles expose as the key light sweeps
        On touch, the light drifts on its own so the idea still
        reads.
     --------------------------------------------------------- */
  (function lightTable() {
    const table = $('#lighttable');
    if (!table || reduced) return;
    const tiles = $$('[data-tile]', table);
    if (!tiles.length) return;

    let boxes = [];
    let visible = true;
    const measure = () => { boxes = tiles.map(t => t.getBoundingClientRect()); };

    addEventListener('resize', measure, { passive: true });
    addEventListener('scroll', measure, { passive: true });
    setTimeout(measure, 60);
    measure();

    const radius = 370;
    const t0 = performance.now();
    let running = false, lastX = NaN, lastY = NaN, idle = 0;

    const kick = () => { if (!running && visible) { running = true; requestAnimationFrame(raf); } };
    addEventListener('pointermove', kick, { passive: true });
    addEventListener('scroll', kick, { passive: true });

    function raf(now) {
      if (!visible || !boxes.length) { running = false; return; }

      let px = lightX, py = lightY;

      if (!fine || !pointer.has) {
        // ambient sweep: a slow lissajous so the light never sits still
        const r = table.getBoundingClientRect();
        const t = (now - t0) / 1000;
        px = r.left + r.width  * (0.5 + 0.42 * Math.sin(t * 0.42));
        py = r.top  + r.height * (0.5 + 0.36 * Math.sin(t * 0.29 + 1.1));
      }

      for (let i = 0; i < tiles.length; i++) {
        const b = boxes[i];
        if (!b) continue;
        const dx = px - (b.left + b.width / 2);
        const dy = py - (b.top + b.height / 2);
        const d  = Math.sqrt(dx * dx + dy * dy);
        const lit = clamp(1 - d / radius, 0, 1);
        // ease the falloff so the centre reads hot and the edge stays moody
        tiles[i].style.setProperty('--lit', (0.22 + lit * lit * 0.9).toFixed(3));
      }

      // park the loop once the light stops moving (the ambient sweep never parks)
      const still = Math.abs(px - lastX) < 0.3 && Math.abs(py - lastY) < 0.3;
      lastX = px; lastY = py;
      idle = still ? idle + 1 : 0;
      if (idle > 20 && fine && pointer.has) { running = false; return; }
      requestAnimationFrame(raf);
    }

    // the ambient sweep needs to run whenever the table is on screen
    const io = new IntersectionObserver(([e]) => {
      visible = e.isIntersecting;
      if (visible) kick();
    }, { threshold: 0 });
    io.observe(table);
    kick();
  })();

  /* ---------------------------------------------------------
     4. FLOATING EXIF CHIPS — light parallax against the table
     --------------------------------------------------------- */
  (function floatChips() {
    if (reduced || !fine) return;
    const chips = $$('[data-float]');
    const table = $('#lighttable');
    if (!chips.length || !table) return;

    let rx = 0, ry = 0, tx = 0, ty = 0, running = false;

    const kick = () => { if (!running) { running = true; requestAnimationFrame(raf); } };

    addEventListener('pointermove', (e) => {
      const r = table.getBoundingClientRect();
      if (r.bottom < 0 || r.top > innerHeight) return;   // table is off screen
      tx = ((e.clientX - (r.left + r.width / 2)) / r.width) * 2;
      ty = ((e.clientY - (r.top + r.height / 2)) / r.height) * 2;
      kick();
    }, { passive: true });

    function raf() {
      rx = lerp(rx, tx, 0.06);
      ry = lerp(ry, ty, 0.06);
      chips.forEach((c, i) => {
        const k = 12 + i * 7;
        c.style.translate = `${(-rx * k).toFixed(2)}px ${(-ry * k).toFixed(2)}px`;
      });
      if (Math.abs(rx - tx) < 0.002 && Math.abs(ry - ty) < 0.002) running = false;
      else requestAnimationFrame(raf);
    }
  })();

  /* ---------------------------------------------------------
     5. SCROLL REVEALS
     --------------------------------------------------------- */
  (function reveals() {
    const items = $$('.reveal');
    if (!items.length) return;
    if (reduced || !('IntersectionObserver' in window)) {
      items.forEach(i => i.classList.add('is-in'));
      return;
    }
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
      });
    }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });
    items.forEach(i => io.observe(i));
  })();

  /* ---------------------------------------------------------
     6. NAV — condense on scroll, mobile menu, active link
     --------------------------------------------------------- */
  (function nav() {
    const nav = $('#nav');
    const burger = $('#burger');
    const menu = $('#mobileMenu');

    let last = 0;
    addEventListener('scroll', () => {
      const y = scrollY;
      nav.classList.toggle('is-stuck', y > 24);
      last = y;
    }, { passive: true });

    if (burger && menu) {
      const setOpen = (open) => {
        burger.setAttribute('aria-expanded', String(open));
        menu.hidden = !open;
        document.body.classList.toggle('is-locked', open);
      };
      burger.addEventListener('click', () => setOpen(menu.hidden));
      menu.addEventListener('click', (e) => { if (e.target.closest('a')) setOpen(false); });
      addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
      addEventListener('resize', () => { if (innerWidth > 760) setOpen(false); });
    }

    // active section highlight
    const links = $$('.nav-links a');
    const secs = links.map(a => $(a.getAttribute('href'))).filter(Boolean);
    if (secs.length && 'IntersectionObserver' in window) {
      let activeId = '';
      const io = new IntersectionObserver((entries) => {
        entries.forEach(e => {
          // leaving the lit section for one with no nav link (the FAQ, the
          // contact block) clears the highlight rather than leaving it stuck
          if (!e.isIntersecting) {
            if (e.target.id === activeId) {
              activeId = '';
              links.forEach(a => { a.style.color = ''; a.removeAttribute('aria-current'); });
            }
            return;
          }
          activeId = e.target.id;
          links.forEach(a => { a.style.color = ''; a.removeAttribute('aria-current'); });
          const match = links.find(a => a.getAttribute('href') === '#' + e.target.id);
          if (match) { match.style.color = 'var(--text)'; match.setAttribute('aria-current', 'true'); }
        });
      }, { rootMargin: '-45% 0px -50% 0px' });
      secs.forEach(s => io.observe(s));
    }
  })();

  /* ---------------------------------------------------------
     7. COUNT-UP STATS
     --------------------------------------------------------- */
  (function counters() {
    // The figure is read from the text itself ("400+" counts to 400 and keeps
    // the "+"), so a number changed in the dashboard animates to the new value.
    // Anything that does not start with a number is left alone.
    const nums = $$('[data-count]').filter(n => {
      const m = n.textContent.trim().match(/^(\d+)(.*)$/);
      if (!m) return false;
      n.dataset.count = m[1];
      n.dataset.suffix = m[2];
      return true;
    });
    if (!nums.length) return;
    // the markup carries the real figures; only zero them once it is
    // certain they will be counted back up
    if (reduced || !('IntersectionObserver' in window)) return;
    nums.forEach(n => { n.textContent = '0'; });
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (!e.isIntersecting) return;
        io.unobserve(e.target);
        const el = e.target;
        const to = parseFloat(el.dataset.count);
        const suf = el.dataset.suffix || '';
        const dur = 1200;
        const t0 = performance.now();
        (function step(now) {
          const p = clamp((now - t0) / dur, 0, 1);
          const eased = 1 - Math.pow(1 - p, 3);
          el.textContent = Math.round(to * eased) + (p === 1 ? suf : '');
          if (p < 1) requestAnimationFrame(step);
        })(t0);
      });
    }, { threshold: 0.5 });
    nums.forEach(n => io.observe(n));
  })();

  /* ---------------------------------------------------------
     8. PROCESS — steps drive the sticky frame
     --------------------------------------------------------- */
  (function process() {
    const steps = $$('.step');
    const imgs  = $$('.pm-img');
    const hud   = $('#hudMeta');
    if (!steps.length || !imgs.length) return;

    // taken from the step titles, so a renamed step renames its frame too
    const labels = steps.map((s, i) => {
      const h = s.querySelector('h3');
      return String(i + 1).padStart(2, '0') + ' — ' + (h ? h.textContent.trim().toUpperCase() : '');
    });

    const show = (i) => {
      steps.forEach((s, k) => s.classList.toggle('is-active', k === i));
      imgs.forEach((m, k) => m.classList.toggle('is-on', k === i));
      if (hud) hud.textContent = labels[i] || labels[0];
    };

    steps.forEach((s, i) => {
      s.addEventListener('pointerenter', () => show(i));
      s.addEventListener('focusin', () => show(i));
    });

    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((entries) => {
        entries.forEach(e => {
          if (e.isIntersecting) show(steps.indexOf(e.target));
        });
      }, { rootMargin: '-40% 0px -45% 0px' });
      steps.forEach(s => io.observe(s));
    }
  })();

  /* ---------------------------------------------------------
     9. WORK FILTERS
     --------------------------------------------------------- */
  (function filters() {
    const btns = $$('.filter');
    const cards = $$('#workGrid .card');
    if (!btns.length) return;

    btns.forEach(b => b.addEventListener('click', () => {
      const f = b.dataset.filter;
      btns.forEach(x => {
        const on = x === b;
        x.classList.toggle('is-on', on);
        x.setAttribute('aria-selected', String(on));
      });

      cards.forEach(c => {
        const match = f === 'all' || c.dataset.cat === f;
        if (match) {
          c.classList.remove('is-hidden');
          requestAnimationFrame(() => c.classList.remove('is-out'));
        } else {
          c.classList.add('is-out');
          setTimeout(() => { if (c.classList.contains('is-out')) c.classList.add('is-hidden'); }, reduced ? 0 : 340);
        }
      });
    }));
  })();

  /* ---------------------------------------------------------
     10. SERVICES — image peek follows the pointer
     --------------------------------------------------------- */
  (function svcPeek() {
    if (!fine || reduced) return;
    const peek = $('#svcPeek');
    const img  = $('#svcPeekImg');
    const rows = $$('.svc');
    const cur  = $('#cursor');
    if (!peek || !rows.length) return;

    // warm the cache so the first hover isn't blank
    rows.forEach(r => { const i = new Image(); i.src = r.dataset.img; });

    let px = 0, py = 0, tx = 0, ty = 0, on = false;

    rows.forEach(r => {
      r.addEventListener('pointerenter', (e) => {
        if (img.getAttribute('src') !== r.dataset.img) img.src = r.dataset.img;
        // snap to the pointer so the preview never flies in from the corner
        if (!on) { px = tx = e.clientX; py = ty = e.clientY; }
        peek.style.transform = `translate3d(${px}px,${py}px,0) scale(1) rotate(-2deg)`;
        peek.classList.add('is-on');
        if (cur) cur.classList.add('is-over-peek');   // the disc would smudge the preview
        on = true;
      });
      r.addEventListener('pointerleave', () => {
        peek.classList.remove('is-on');
        if (cur) cur.classList.remove('is-over-peek');
        on = false;
      });
    });

    let running = false;
    const kick = () => { if (!running && on) { running = true; requestAnimationFrame(raf); } };

    addEventListener('pointermove', (e) => { tx = e.clientX; ty = e.clientY; kick(); }, { passive: true });

    function raf() {
      if (!on) { running = false; return; }
      px = lerp(px, tx, 0.13);
      py = lerp(py, ty, 0.13);
      peek.style.transform = `translate3d(${px}px,${py}px,0) scale(1) rotate(-2deg)`;
      if (Math.abs(px - tx) < 0.4 && Math.abs(py - ty) < 0.4) running = false;
      else requestAnimationFrame(raf);
    }

    peek.style.transformOrigin = 'center';
  })();

  /* ---------------------------------------------------------
     11. TESTIMONIAL RAIL — arrows, drag, wheel guard
     --------------------------------------------------------- */
  (function rail() {
    const rail = $('#qRail');
    const prev = $('#qPrev');
    const next = $('#qNext');
    if (!rail) return;

    // Judged from where the first and last cards sit rather than from
    // scrollLeft: with snap padding in play the browser's scroll origin is
    // not where the first card rests.
    const cards = $$('.q-card', rail);
    const sync = () => {
      if (!cards.length) return;
      const r = rail.getBoundingClientRect();
      const cs = getComputedStyle(rail);
      const padL = parseFloat(cs.scrollPaddingLeft) || parseFloat(cs.paddingLeft) || 0;
      const padR = parseFloat(cs.scrollPaddingRight) || parseFloat(cs.paddingRight) || 0;
      if (prev) prev.disabled = cards[0].getBoundingClientRect().left >= r.left + padL - 4;
      if (next) next.disabled = cards[cards.length - 1].getBoundingClientRect().right <= r.right - padR + 4;
    };

    // Go to the next or previous card exactly — a fixed step fights the snap
    // points and can leave the rail where it was.
    const go = (dir) => {
      const r = rail.getBoundingClientRect();
      const pad = parseFloat(getComputedStyle(rail).scrollPaddingLeft) || 0;
      const edge = r.left + pad;
      const lefts = cards.map(c => c.getBoundingClientRect().left - edge);
      const target = dir > 0
        ? lefts.find(d => d > 4)
        : lefts.filter(d => d < -4).pop();
      if (target === undefined) return;
      // Chrome re-snaps a snap container mid-way through a smooth programmatic
      // scroll and cancels it, so snapping sits out while the arrow's scroll
      // runs; the target is a card edge anyway
      rail.style.scrollSnapType = 'none';
      rail.scrollBy({ left: target, behavior: reduced ? 'auto' : 'smooth' });
      clearTimeout(snapBack);
      snapBack = setTimeout(() => { rail.style.scrollSnapType = ''; }, reduced ? 0 : 900);
    };
    let snapBack;
    prev && prev.addEventListener('click', () => go(-1));
    next && next.addEventListener('click', () => go(1));
    rail.addEventListener('scroll', sync, { passive: true });
    addEventListener('resize', sync);
    sync();

    // Chrome turns a vertical wheel into horizontal scroll on an x-only
    // overflow container, which traps the page. Vertical intent goes to the page.
    rail.addEventListener('wheel', (e) => {
      if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
        e.preventDefault();
        scrollBy({ top: e.deltaY, behavior: 'auto' });
      }
    }, { passive: false });

    // pointer drag
    let down = false, sx = 0, sl = 0, moved = 0;
    rail.addEventListener('pointerdown', (e) => {
      if (e.pointerType === 'touch') return; // native touch scroll is better
      down = true; moved = 0;
      sx = e.clientX; sl = rail.scrollLeft;
      rail.classList.add('is-drag');
      rail.setPointerCapture(e.pointerId);
    });
    rail.addEventListener('pointermove', (e) => {
      if (!down) return;
      const dx = e.clientX - sx;
      moved = Math.abs(dx);
      rail.scrollLeft = sl - dx;
    });
    const up = () => { if (!down) return; down = false; rail.classList.remove('is-drag'); };
    rail.addEventListener('pointerup', up);
    rail.addEventListener('pointercancel', up);
    rail.addEventListener('click', (e) => { if (moved > 8) e.preventDefault(); }, true);
  })();

  /* ---------------------------------------------------------
     12. FAQ ACCORDION — height-animated, one open at a time
     --------------------------------------------------------- */
  (function faq() {
    const accs = $$('.acc');
    accs.forEach(acc => {
      const q = $('.acc-q', acc);
      const a = $('.acc-a', acc);
      if (!q || !a) return;

      q.addEventListener('click', () => {
        const open = acc.classList.contains('is-open');

        accs.forEach(other => {
          if (other === acc) return;
          other.classList.remove('is-open');
          const oa = $('.acc-a', other), oq = $('.acc-q', other);
          if (oa) oa.style.height = '0px';
          if (oq) oq.setAttribute('aria-expanded', 'false');
        });

        acc.classList.toggle('is-open', !open);
        q.setAttribute('aria-expanded', String(!open));
        a.style.height = open ? '0px' : a.scrollHeight + 'px';
      });
    });

    addEventListener('resize', () => {
      accs.forEach(acc => {
        if (!acc.classList.contains('is-open')) return;
        const a = $('.acc-a', acc);
        if (a) a.style.height = a.scrollHeight + 'px';
      });
    });
  })();

  /* ---------------------------------------------------------
     13. MAGNETIC BUTTONS
     --------------------------------------------------------- */
  (function magnets() {
    if (!fine || reduced) return;
    $$('.magnet').forEach(el => {
      let raf = null, tx = 0, ty = 0, cx = 0, cy = 0;

      const loop = () => {
        cx = lerp(cx, tx, 0.18); cy = lerp(cy, ty, 0.18);
        el.style.translate = `${cx.toFixed(2)}px ${cy.toFixed(2)}px`;
        if (Math.abs(cx - tx) > 0.1 || Math.abs(cy - ty) > 0.1) raf = requestAnimationFrame(loop);
        else raf = null;
      };
      const kick = () => { if (!raf) raf = requestAnimationFrame(loop); };

      el.addEventListener('pointermove', (e) => {
        const r = el.getBoundingClientRect();
        tx = (e.clientX - (r.left + r.width / 2)) * 0.28;
        ty = (e.clientY - (r.top + r.height / 2)) * 0.42;
        kick();
      });
      el.addEventListener('pointerleave', () => { tx = 0; ty = 0; kick(); });
    });
  })();

  /* ---------------------------------------------------------
     14. FORMS — no backend yet, so confirm honestly
     --------------------------------------------------------- */
  (function forms() {
    const toast = $('#toast');
    let timer = null;
    const say = (msg) => {
      if (!toast) return;
      toast.textContent = msg;
      toast.classList.add('is-on');
      clearTimeout(timer);
      timer = setTimeout(() => toast.classList.remove('is-on'), 4200);
    };

    const valid = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);

    const wire = (formId, inputId, source, ok) => {
      const form = $(formId), input = $(inputId);
      if (!form || !input) return;

      const button = form.querySelector('button[type=submit]');

      form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const v = input.value.trim();
        if (!valid(v)) { say('That email address needs another look.'); input.focus(); return; }

        const hp = form.querySelector('[name=company]');
        if (button) button.disabled = true;

        try {
          const res = await fetch('api/lead.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              email: v,
              source,
              company: hp ? hp.value : ''
            })
          });
          const data = await res.json();

          if (!res.ok || !data.ok) {
            say(data.error || 'That did not go through. Email us at smashboxindia@gmail.com.');
            return;
          }

          say(ok);
          input.value = '';
        } catch (err) {
          say('That did not go through. Email us at smashboxindia@gmail.com.');
        } finally {
          if (button) button.disabled = false;
        }
      });
    };

    wire('#heroForm', '#heroEmail', 'hero', 'Got it. The studio will reply within one working day.');
    wire('#subForm',  '#subEmail',  'newsletter', "You're on the list. First note lands at the start of next month.");
  })();

  /* ---------------------------------------------------------
     15. BRIEF FORM — the contact block's short enquiry
     --------------------------------------------------------- */
  (function brief() {
    const form = $('#briefForm');
    if (!form) return;

    const body = $('.brief-body', form);
    const done = $('.brief-done', form);
    const send = $('.brief-send', form);
    const toast = $('#toast');
    const say = (msg) => {
      if (!toast) return;
      toast.textContent = msg;
      toast.classList.add('is-on');
      setTimeout(() => toast.classList.remove('is-on'), 4200);
    };

    const field = (name) => form.elements.namedItem(name);
    const mark = (input, bad) => input.closest('.bf').classList.toggle('is-bad', bad);

    // a field flagged as wrong clears itself the moment it is being fixed
    form.addEventListener('input', (e) => {
      const bf = e.target.closest && e.target.closest('.bf');
      if (bf) bf.classList.remove('is-bad');
    });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const name = field('name'), email = field('email');
      const okName = name.value.trim().length > 1;
      const okEmail = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.value.trim());
      mark(name, !okName);
      mark(email, !okEmail);
      if (!okName) { name.focus(); say('Add your name so the studio knows who to reply to.'); return; }
      if (!okEmail) { email.focus(); say('That email address needs another look.'); return; }

      const needs = $$('input[name=need]:checked', form).map(i => i.value);
      const text = field('message').value.trim();
      const message = (needs.length ? 'Looking for: ' + needs.join(', ') + (text ? '\n\n' : '') : '') + text;

      send.disabled = true;
      send.querySelector('span').textContent = 'Sending…';

      try {
        const res = await fetch('api/lead.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            name: name.value.trim(),
            email: email.value.trim(),
            phone: field('phone').value.trim(),
            message,
            source: 'contact',
            company: field('company').value
          })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) throw new Error(data.error || '');

        body.hidden = true;
        done.hidden = false;
        form.reset();
      } catch (err) {
        say(err.message || 'That did not go through. Email us at smashboxindia@gmail.com.');
      } finally {
        send.disabled = false;
        send.querySelector('span').textContent = 'Send brief';
      }
    });
  })();

  /* ---------------------------------------------------------
     16. KEY LIGHT ON CARDS + DEVELOPING PRINTS
     --------------------------------------------------------- */
  (function cardLight() {
    if (!fine || reduced) return;
    $$('.div-card, .q-card, .svc-card').forEach(card => {
      card.addEventListener('pointermove', (e) => {
        const r = card.getBoundingClientRect();
        card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
        card.style.setProperty('--my', (e.clientY - r.top) + 'px');
      }, { passive: true });
    });
  })();

  (function develop() {
    const cards = $$('#workGrid .card');
    if (!cards.length) return;
    if (reduced || !('IntersectionObserver' in window)) {
      cards.forEach(c => c.classList.add('is-in'));
      return;
    }
    const io = new IntersectionObserver((entries) => {
      // cards that arrive together develop one after another, left to right
      entries.filter(e => e.isIntersecting)
        .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top || a.boundingClientRect.left - b.boundingClientRect.left)
        .forEach((e, i) => {
          e.target.querySelector('.card-media').style.transitionDelay = (i * 0.09) + 's';
          e.target.classList.add('is-in');
          io.unobserve(e.target);
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
    cards.forEach(c => io.observe(c));
  })();

  /* ---------------------------------------------------------
     17. ODDS AND ENDS
     --------------------------------------------------------- */
  const yr = $('#yr');
  if (yr) yr.textContent = new Date().getFullYear();

  // smooth anchor scroll with a nav offset
  $$('a[href^="#"]').forEach(a => {
    a.addEventListener('click', (e) => {
      const id = a.getAttribute('href');
      if (!id || id === '#') return;
      const t = $(id);
      if (!t) return;
      e.preventDefault();
      const top = t.getBoundingClientRect().top + scrollY - 92;
      scrollTo({ top, behavior: reduced ? 'auto' : 'smooth' });
    });
  });
})();
