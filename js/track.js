/* =========================================================
   SMASHBOX — visit counter
   Feeds the Analytics page in the dashboard. No cookies and
   nothing that identifies a person: a page view, the links and
   buttons people press, and how long they stayed.

   Visit the site once with ?sbx-ignore=1 to stop counting your
   own visits on that browser (?sbx-ignore=0 to start again).
   ========================================================= */
(function () {
  'use strict';

  var ENDPOINT = 'api/track.php';

  try {
    var q = new URLSearchParams(location.search);
    if (q.get('sbx-ignore') === '1') localStorage.setItem('sbx-ignore', '1');
    if (q.get('sbx-ignore') === '0') localStorage.removeItem('sbx-ignore');
    if (localStorage.getItem('sbx-ignore') === '1') {
      if (q.has('sbx-ignore')) announce('This browser is no longer counted in the studio’s analytics.');
      return;
    }
    if (q.get('sbx-ignore') === '0') announce('This browser is counted in the analytics again.');
  } catch (e) { /* private mode: count as normal */ }

  if (window.SBX_NO_TRACK || navigator.webdriver) return;

  function announce(msg) {
    addEventListener('load', function () {
      var t = document.getElementById('toast');
      if (!t) return;
      setTimeout(function () {
        t.textContent = msg;
        t.classList.add('is-on');
        setTimeout(function () { t.classList.remove('is-on'); }, 5000);
      }, 1800);
    });
  }

  /* one id per tab, so a visit's events can be told apart */
  var sid;
  try {
    sid = sessionStorage.getItem('sbx-sid');
    if (!sid) {
      sid = Array.from(crypto.getRandomValues(new Uint8Array(8)), function (b) {
        return ('0' + b.toString(16)).slice(-2);
      }).join('');
      sessionStorage.setItem('sbx-sid', sid);
    }
  } catch (e) { sid = ''; }

  var tz = '';
  try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}

  function send(data) {
    data.p = location.pathname;
    data.tz = tz;
    data.sw = screen.width || 0;
    data.sid = sid;
    var body = JSON.stringify(data);
    try {
      if (navigator.sendBeacon && navigator.sendBeacon(ENDPOINT, body)) return;
    } catch (e) {}
    try {
      fetch(ENDPOINT, { method: 'POST', body: body, keepalive: true, headers: { 'Content-Type': 'text/plain' } });
    } catch (e) {}
  }

  /* ---- the visit ---- */
  var params = new URLSearchParams(location.search);
  send({
    t: 'pageview',
    r: document.referrer || '',
    us: params.get('utm_source') || params.get('ref') || '',
    um: params.get('utm_medium') || '',
    uc: params.get('utm_campaign') || ''
  });

  /* ---- what gets pressed ---- */
  var SOCIAL = /instagram\.|facebook\.|linkedin\.|behance\.|youtube\.|youtu\.be|twitter\.|x\.com|pinterest\./i;

  function kindOf(el, href) {
    if (/^tel:/i.test(href)) return 'phone';
    if (/^mailto:/i.test(href)) return 'email';
    if (/wa\.me|whatsapp\./i.test(href)) return 'whatsapp';
    if (SOCIAL.test(href)) return 'social';
    if (el.closest('.nav-links, .mobile-menu, .foot-base, .foot-col')) return 'nav';
    if (el.matches('.filter')) return 'filter';
    if (el.matches('.acc-q')) return 'faq';
    if (el.matches('button[type=submit]')) return 'form';
    if (el.matches('.btn')) return 'cta';
    return el.tagName === 'BUTTON' ? 'button' : 'link';
  }

  function labelOf(el) {
    var l = el.getAttribute('data-track') || el.getAttribute('aria-label') || el.textContent || el.title || '';
    return l.replace(/\s+/g, ' ').trim().slice(0, 110);
  }

  function sectionOf(el) {
    var s = el.closest('section[id], header, footer, .mobile-menu');
    if (!s) return '';
    if (s.id) return s.id;
    if (s.matches('.mobile-menu')) return 'menu';
    return s.tagName.toLowerCase() === 'header' ? 'header' : 'footer';
  }

  document.addEventListener('click', function (e) {
    var el = e.target instanceof Element ? e.target.closest('a, button, [data-track]') : null;
    if (!el || el.matches('.theme-toggle, .nav-burger, .q-btn')) return;
    var href = el.getAttribute('href') || '';
    var label = labelOf(el);
    if (!label) return;
    send({ t: 'click', l: label, h: href.slice(0, 250), k: kindOf(el, href), s: sectionOf(el) });
  }, true);

  /* ---- how long, how far ---- */
  var shown = Date.now();
  var active = 0;
  var maxScroll = 0;
  var left = false;

  function depth() {
    var h = document.documentElement.scrollHeight - innerHeight;
    var pct = h > 0 ? Math.round((scrollY / h) * 100) : 100;
    if (pct > maxScroll) maxScroll = Math.min(100, pct);
  }
  addEventListener('scroll', depth, { passive: true });

  function leave() {
    if (left) return;
    left = true;
    active += Date.now() - shown;
    depth();
    send({ t: 'leave', d: Math.round(active / 1000), sc: maxScroll });
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') leave();
  });
  addEventListener('pagehide', leave);
})();
