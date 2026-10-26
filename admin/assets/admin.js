/* =========================================================
   SMASHBOX — STUDIO DESK
   One file, no framework, no build step: whoever picks this up
   next can open it and read it top to bottom.

     1. Basics — selectors, icons, toasts, requests
     2. Dialogs and the unsaved-changes guard
     3. Shell — sidebar, appearance, toolbar, notifications
     4. Segmented controls and the sheet
     5. Uploading, with a progress bar
     6. The text editor
     7. The picture picker
     8. The field editor (text or picture, in a sheet or a panel)
     9. Pages: Words, Pictures, Edit visually, Client strip,
        Contact & links, Leads, Analytics, Overview, Account
   ========================================================= */
(function () {
  'use strict';

  const CFG = window.SBX || {};
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const page = document.body.dataset.page || '';

  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

  /* =======================================================
     1. BASICS
     ======================================================= */
  const ICONS = {
    check: '<path d="M20 6 9 17l-5-5"/>',
    alert: '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.4v.1"/>',
    close: '<path d="M18 6 6 18M6 6l12 12"/>',
    upload: '<path d="M12 15V3.5M7 8l5-5 5 5"/><path d="M20 15v3.5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V15"/>',
    undo: '<path d="M4 9h11a5 5 0 0 1 0 10h-4"/><path d="M8 5 4 9l4 4"/>',
    images: '<rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="1.8"/><path d="m21 16-4.8-4.8L7 20"/>',
    link: '<path d="M10 13.5a4 4 0 0 0 6 .4l2.8-2.8a4 4 0 0 0-5.7-5.7l-1.4 1.4"/><path d="M14 10.5a4 4 0 0 0-6-.4l-2.8 2.8a4 4 0 0 0 5.7 5.7l1.4-1.4"/>',
    grip: '<circle cx="9" cy="6" r="1.2"/><circle cx="15" cy="6" r="1.2"/><circle cx="9" cy="12" r="1.2"/><circle cx="15" cy="12" r="1.2"/><circle cx="9" cy="18" r="1.2"/><circle cx="15" cy="18" r="1.2"/>',
    trash: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4.5h6V7"/>',
    leads: '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7.5 8 5.5 8-5.5"/>',
    phone: '<path d="M5 4h3.5l1.5 4.5-2 1.5a11 11 0 0 0 6 6l1.5-2 4.5 1.5V19a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
    out: '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v4.5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 18.5v-11A1.5 1.5 0 0 1 5.5 6H10"/>',
    arrowLeft: '<path d="M19 12H5M11 6l-6 6 6 6"/>',
    archive: '<rect x="3" y="4" width="18" height="5" rx="1.5"/><path d="M5 9v9.5A1.5 1.5 0 0 0 6.5 20h11a1.5 1.5 0 0 0 1.5-1.5V9M10 13h4"/>',
    inbox: '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5h13l3.5 7v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6Z"/>',
    note: '<path d="M5 4h10l4 4v12H5Z"/><path d="M14 4v5h5M8.5 13h7M8.5 16.5h5"/>',
    chat: '<path d="M12 3.5a8.5 8.5 0 0 0-7.3 12.8L3.5 20.5l4.3-1.2A8.5 8.5 0 1 0 12 3.5Z"/>'
  };
  const ico = (n, cls) => '<svg class="ico ' + (cls || '') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICONS[n] || '') + '</svg>';

  /* ---- toasts: what just happened, in a word or two ---- */
  const toastStack = $('#toasts');
  function toast(msg, kind) {
    if (!toastStack) return;
    const t = document.createElement('div');
    t.className = 'toast' + (kind ? ' is-' + kind : '');
    t.setAttribute('role', kind === 'bad' ? 'alert' : 'status');
    t.innerHTML = ico(kind === 'bad' ? 'alert' : 'check') + '<span>' + esc(msg) + '</span>';
    toastStack.appendChild(t);
    while (toastStack.children.length > 3) toastStack.firstChild.remove();
    setTimeout(() => {
      t.classList.add('is-leaving');
      setTimeout(() => t.remove(), 320);
    }, kind === 'bad' ? 5200 : 3200);
  }

  /* ---- requests ---- */
  async function readJson(res) {
    let data = null;
    try { data = await res.json(); } catch (e) { /* fall through */ }
    if (!data) {
      const err = new Error(res.status === 401 ? 'You were signed out. Sign in again to carry on.' : 'The server sent something unreadable. Try again.');
      err.status = res.status;
      throw err;
    }
    if (!res.ok || !data.ok) {
      const err = new Error(data.error || 'That did not work. Try again.');
      err.fields = data.fields || null;
      err.status = res.status;
      throw err;
    }
    return data;
  }

  async function post(url, body) {
    let res;
    try {
      res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CFG.csrf },
        body: JSON.stringify(body)
      });
    } catch (e) {
      throw new Error('No connection. Check the internet and try again — nothing was lost.');
    }
    return readJson(res);
  }

  async function getJson(url) {
    let res;
    try {
      res = await fetch(url, { headers: { 'X-CSRF-Token': CFG.csrf }, cache: 'no-store' });
    } catch (e) {
      throw new Error('No connection. Check the internet and try again.');
    }
    return readJson(res);
  }

  /* button busy state, so a double tap never sends twice */
  function busy(btn, on, label) {
    if (!btn) return;
    if (on) {
      btn.dataset.label = btn.innerHTML;
      btn.classList.add('is-busy');
      btn.disabled = true;
      btn.innerHTML = '<span class="spin"></span>' + esc(label || 'Saving…');
    } else {
      btn.classList.remove('is-busy');
      btn.disabled = false;
      if (btn.dataset.label != null) btn.innerHTML = btn.dataset.label;
    }
  }

  /* =======================================================
     2. DIALOGS + UNSAVED-CHANGES GUARD
     ======================================================= */

  /**
   * A small centred question. Resolves with the value of the chosen action;
   * Escape or a click outside resolves with the action marked `cancel`.
   */
  function dialog(opts) {
    return new Promise((resolve) => {
      const prevFocus = document.activeElement;
      const layer = document.createElement('div');
      layer.className = 'dialog-layer';
      const actions = opts.actions || [{ label: 'OK', value: true, kind: 'primary' }];
      const cancel = actions.find((a) => a.cancel) || actions[actions.length - 1];

      layer.innerHTML =
        '<div class="scrim"></div>' +
        '<div class="dialog" role="alertdialog" aria-modal="true" aria-labelledby="dlgT" aria-describedby="dlgM">' +
          (opts.icon !== false ? '<div class="dialog-icon">' + ico(opts.icon || 'alert') + '</div>' : '') +
          '<h2 id="dlgT">' + esc(opts.title) + '</h2>' +
          '<p id="dlgM">' + esc(opts.message || '') + '</p>' +
          '<div class="dialog-actions' + (actions.length === 2 ? ' is-row' : '') + '"></div>' +
        '</div>';

      const row = $('.dialog-actions', layer);
      actions.forEach((a) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn ' + (a.kind === 'primary' ? 'btn-primary' : a.kind === 'danger' ? 'btn-danger' : '');
        b.textContent = a.label;
        b.addEventListener('click', () => done(a.value));
        row.appendChild(b);
      });

      function done(v) {
        document.removeEventListener('keydown', onKey, true);
        layer.classList.add('is-leaving');
        $('.dialog', layer).classList.add('is-leaving');
        setTimeout(() => { layer.remove(); if (prevFocus && prevFocus.focus) prevFocus.focus(); }, 200);
        resolve(v);
      }
      function onKey(e) {
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); done(cancel.value); }
        if (e.key === 'Tab') {
          const f = $$('button', layer);
          const i = f.indexOf(document.activeElement);
          e.preventDefault();
          f[(i + (e.shiftKey ? -1 : 1) + f.length) % f.length].focus();
        }
      }
      $('.scrim', layer).addEventListener('click', () => done(cancel.value));
      document.addEventListener('keydown', onKey, true);
      document.body.appendChild(layer);
      const primary = $('.btn-primary', layer) || $('button', layer);
      setTimeout(() => primary.focus(), 30);
    });
  }

  /** The one question asked whenever unsaved work is about to be lost. */
  function askUnsaved(what, isPrivate) {
    return dialog({
      title: 'Save your changes?',
      message: 'You’ve changed ' + (what || 'this') + ' but haven’t saved it yet. ' + (isPrivate
        ? 'Save it to keep it, or discard the change.'
        : 'Save it to put it on the site, or discard it to leave the site as it is.'),
      actions: [
        { label: isPrivate ? 'Save' : 'Save & publish', value: 'save', kind: 'primary' },
        { label: 'Discard changes', value: 'discard', kind: 'danger' },
        { label: 'Keep editing', value: 'keep', cancel: true }
      ]
    });
  }

  /*
   * Anything holding unsaved work registers here. Leaving the page — a link,
   * the back button, closing the tab — checks every one of them first.
   */
  const guards = new Set();
  function guard(g) { guards.add(g); return () => guards.delete(g); }
  const dirtyGuards = () => Array.from(guards).filter((g) => { try { return g.isDirty(); } catch (e) { return false; } });

  /** Resolve every dirty guard by asking. True when it is safe to move on. */
  async function settleGuards() {
    const dirty = dirtyGuards();
    if (!dirty.length) return true;
    const choice = await askUnsaved(dirty[0].what, dirty[0].isPrivate);
    if (choice === 'keep') return false;
    for (const g of dirty) {
      if (choice === 'save') {
        try { await g.save(); } catch (e) { if (e.message !== 'Not saved.') toast(e.message, 'bad'); return false; }
      } else if (g.discard) {
        g.discard();
      }
    }
    return true;
  }

  // once the question has been answered for a navigation, the browser's own
  // "leave site?" prompt must not ask it a second time
  let leaving = false;
  addEventListener('beforeunload', (e) => {
    if (!leaving && dirtyGuards().length) { e.preventDefault(); e.returnValue = ''; }
  });

  document.addEventListener('click', async (e) => {
    const a = e.target instanceof Element ? e.target.closest('a[href]') : null;
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if (a.target === '_blank' || a.hasAttribute('download')) return;
    const href = a.getAttribute('href');
    if (!href || href.charAt(0) === '#' || /^(mailto|tel|javascript):/i.test(href)) return;
    if (!dirtyGuards().length) return;
    e.preventDefault();
    if (await settleGuards()) { leaving = true; location.href = a.href; }
  });

  /* =======================================================
     3. SHELL
     ======================================================= */

  /* ---- sidebar on small screens ---- */
  const sidebar = $('#sidebar');
  const sbScrim = $('#sbScrim');
  const menuBtn = $('#menuBtn');
  function setSidebar(open) {
    if (!sidebar) return;
    sidebar.classList.toggle('is-open', open);
    if (sbScrim) sbScrim.classList.toggle('is-open', open);
    if (menuBtn) menuBtn.setAttribute('aria-expanded', String(open));
  }
  if (menuBtn) menuBtn.addEventListener('click', () => setSidebar(!sidebar.classList.contains('is-open')));
  if (sbScrim) sbScrim.addEventListener('click', () => setSidebar(false));

  /* ---- appearance: light, dark, or follow the device ---- */
  const themeBtns = $$('[data-theme-set]');
  function applyTheme(t) {
    if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
    else document.documentElement.removeAttribute('data-theme');
    try { if (t === 'auto') localStorage.removeItem('sbx-desk-theme'); else localStorage.setItem('sbx-desk-theme', t); } catch (e) {}
    themeBtns.forEach((b) => b.setAttribute('aria-checked', String(b.dataset.themeSet === t)));
  }
  let savedTheme = 'auto';
  try { savedTheme = localStorage.getItem('sbx-desk-theme') || 'auto'; } catch (e) {}
  themeBtns.forEach((b) => {
    b.setAttribute('aria-checked', String(b.dataset.themeSet === savedTheme));
    b.addEventListener('click', () => applyTheme(b.dataset.themeSet));
  });

  /* ---- toolbar picks up a hairline once the page scrolls under it ---- */
  const toolbar = $('#toolbar');
  if (toolbar) {
    const onScroll = () => toolbar.classList.toggle('is-scrolled', scrollY > 6);
    addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---- notifications ---- */
  const bell = $('#bell');
  const notif = $('#notif');
  const bellDot = $('#bellDot');
  const notifList = $('#notifList');
  let lastUnseen = bellDot ? parseInt(bellDot.textContent, 10) || 0 : 0;

  function closeNotif() {
    if (!notif) return;
    notif.classList.remove('is-open');
    bell.setAttribute('aria-expanded', 'false');
  }
  if (bell && notif) {
    bell.addEventListener('click', (e) => {
      e.stopPropagation();
      const open = notif.classList.toggle('is-open');
      bell.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('click', (e) => { if (!notif.contains(e.target) && !bell.contains(e.target)) closeNotif(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeNotif(); });
  }

  const emptyNotif = '<div class="notif-empty">' + ico('check') + '<span>You’re all caught up.<br>New enquiries from the site appear here.</span></div>';

  function paintCounts(d) {
    if (bellDot) {
      bellDot.textContent = d.unseen;
      bellDot.classList.toggle('is-zero', d.unseen === 0);
    }
    const c = $('[data-count="leads"]');
    if (c) { c.textContent = d.new; c.classList.toggle('is-zero', d.new === 0); }
  }

  const markAll = $('#markAllRead');
  if (markAll) {
    markAll.addEventListener('click', async () => {
      try {
        const d = await post('api/leads.php', { action: 'seen_all' });
        paintCounts(d);
        if (notifList) notifList.innerHTML = emptyNotif;
        document.dispatchEvent(new CustomEvent('sbx:all-seen'));
        toast('All caught up', 'ok');
      } catch (err) { toast(err.message, 'bad'); }
    });
  }

  // check every 45 seconds, so an enquiry that lands while the dashboard is open still rings
  async function pollLeads() {
    if (document.hidden) return;
    try {
      const d = await getJson('api/leads.php?action=unseen');
      if (d.unseen > lastUnseen && bell) {
        bell.classList.add('is-ringing');
        setTimeout(() => bell.classList.remove('is-ringing'), 900);
        toast(d.unseen - lastUnseen === 1 ? 'A new enquiry just came in' : (d.unseen - lastUnseen) + ' new enquiries just came in', 'ok');
      }
      lastUnseen = d.unseen;
      paintCounts(d);
      if (notifList) {
        notifList.innerHTML = d.items.length ? d.items.map((i) =>
          '<a class="notif-item" href="leads.php?open=' + i.id + '">' +
            '<span class="avatar avatar-sm">' + esc(i.initials || '•') + '</span>' +
            '<span class="notif-body"><b>' + esc(i.name) + '</b><span>' + esc(i.preview) + '</span>' +
            '<time>' + esc(i.when) + ' · ' + esc(i.source) + '</time></span></a>').join('') : emptyNotif;
      }
    } catch (e) { /* being offline for a minute is not worth a message */ }
  }
  if (bell) setInterval(pollLeads, 45000);

  /* =======================================================
     4. SEGMENTED CONTROLS + THE SHEET
     ======================================================= */

  /** A segmented control whose highlight slides to the chosen segment. */
  function initSeg(seg, onChange) {
    const btns = $$('button', seg);
    let thumb = $('.seg-thumb', seg);
    if (!thumb) {
      thumb = document.createElement('span');
      thumb.className = 'seg-thumb';
      seg.prepend(thumb);
    }
    const attr = btns[0] && btns[0].hasAttribute('aria-selected') ? 'aria-selected' : 'aria-pressed';
    function place(animate) {
      const on = btns.find((b) => b.getAttribute(attr) === 'true') || btns[0];
      if (!on) return;
      if (!animate) thumb.style.transition = 'none';
      thumb.style.width = on.offsetWidth + 'px';
      thumb.style.transform = 'translateX(' + (on.offsetLeft) + 'px)';
      if (!animate) { thumb.offsetWidth; thumb.style.transition = ''; }
    }
    btns.forEach((b) => b.addEventListener('click', () => {
      if (b.getAttribute(attr) === 'true') return;
      const prev = btns.find((x) => x.getAttribute(attr) === 'true');
      Promise.resolve(onChange ? onChange(b, prev) : true).then((ok) => {
        if (ok === false) return;
        btns.forEach((x) => x.setAttribute(attr, String(x === b)));
        place(true);
      });
    }));
    place(false);
    addEventListener('resize', () => place(false));
    // built before it is on screen (inside a sheet, say): measure once it is
    if ('ResizeObserver' in window) new ResizeObserver(() => place(false)).observe(seg);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => place(false));
    return { place, select(value, key) {
      const b = btns.find((x) => x.dataset[key] === value);
      if (!b) return;
      btns.forEach((x) => x.setAttribute(attr, String(x === b)));
      place(true);
    } };
  }

  /**
   * One sheet, slid in from the right (from the bottom on a phone), for
   * whatever is being edited. Closing asks first if there is unsaved work.
   */
  const Sheet = (function () {
    let el, scrim, host, onRequestClose = null, prevFocus = null, open = false;

    function build() {
      if (el) return;
      scrim = document.createElement('div');
      scrim.className = 'scrim';
      el = document.createElement('aside');
      el.className = 'sheet';
      el.setAttribute('role', 'dialog');
      el.setAttribute('aria-modal', 'true');
      el.setAttribute('aria-labelledby', 'sheetTitle');
      document.body.append(scrim, el);
      scrim.addEventListener('click', () => requestClose());
      document.addEventListener('keydown', (e) => {
        if (!open) return;
        if (e.key === 'Escape' && !document.querySelector('.dialog-layer')) { e.preventDefault(); requestClose(); }
        if (e.key === 'Tab') trap(e);
      });
    }

    function trap(e) {
      const f = $$('a[href], button:not([disabled]), input, textarea, select, [contenteditable="true"]', el)
        .filter((x) => x.offsetParent !== null);
      if (!f.length) return;
      const first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }

    function show(parts, opts) {
      build();
      el.innerHTML = '';
      el.append(parts.head, parts.body, parts.foot);
      onRequestClose = (opts && opts.onRequestClose) || null;
      if (!open) prevFocus = document.activeElement;
      open = true;
      requestAnimationFrame(() => {
        el.classList.add('is-open');
        scrim.classList.add('is-open');
      });
      document.documentElement.style.overflow = 'hidden';
    }

    async function requestClose() {
      if (!open) return true;
      if (onRequestClose) {
        const ok = await onRequestClose();
        if (!ok) return false;
      }
      close();
      return true;
    }

    function close() {
      if (!open) return;
      open = false;
      el.classList.remove('is-open');
      scrim.classList.remove('is-open');
      document.documentElement.style.overflow = '';
      if (prevFocus && prevFocus.focus) prevFocus.focus({ preventScroll: true });
      setTimeout(() => { if (!open) el.innerHTML = ''; }, 500);
    }

    return { show, close, requestClose, isOpen: () => open, el: () => el };
  })();

  /* =======================================================
     5. UPLOADING
     ======================================================= */
  const MAX_BYTES = CFG.maxUpload || 10 * 1024 * 1024;
  const MAX_EDGE = 3000;

  function prettyBytes(b) {
    if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
    if (b >= 1024) return Math.round(b / 1024) + ' KB';
    return b + ' B';
  }

  /**
   * Shrink a photo before it travels, so a picture straight off a phone or
   * camera never meets a "file too big" error and the site stays quick.
   * Only JPG, PNG and WEBP are touched; anything already small passes through.
   */
  async function prepareImage(file) {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return { blob: file, resized: false };
    let bmp;
    try {
      bmp = await createImageBitmap(file);
    } catch (e) {
      return { blob: file, resized: false };
    }
    const big = Math.max(bmp.width, bmp.height);
    if (big <= MAX_EDGE && file.size <= MAX_BYTES * 0.9) { bmp.close && bmp.close(); return { blob: file, resized: false }; }

    const scale = Math.min(1, MAX_EDGE / big);
    const w = Math.round(bmp.width * scale), h = Math.round(bmp.height * scale);
    const canvas = document.createElement('canvas');
    canvas.width = w; canvas.height = h;
    canvas.getContext('2d').drawImage(bmp, 0, 0, w, h);
    bmp.close && bmp.close();

    // PNG keeps its transparency as WEBP; photographs go out as JPEG
    const type = file.type === 'image/jpeg' ? 'image/jpeg' : 'image/webp';
    const blob = await new Promise((r) => canvas.toBlob(r, type, 0.88));
    if (!blob) return { blob: file, resized: false };
    const name = file.name.replace(/\.[a-z0-9]+$/i, '') + (type === 'image/jpeg' ? '.jpg' : '.webp');
    return { blob: new File([blob], name, { type }), resized: true };
  }

  /**
   * Upload one file with progress. Returns { promise, abort }.
   * onProgress(fraction 0–1, sentBytes, totalBytes); onStage('uploading'|'processing').
   */
  function uploadFile(file, onProgress, onStage) {
    const xhr = new XMLHttpRequest();
    const promise = new Promise((resolve, reject) => {
      const fd = new FormData();
      fd.append('file', file, file.name);
      xhr.open('POST', 'api/upload.php');
      xhr.setRequestHeader('X-CSRF-Token', CFG.csrf);
      xhr.upload.onprogress = (e) => {
        if (e.lengthComputable && onProgress) onProgress(e.loaded / e.total, e.loaded, e.total);
      };
      xhr.upload.onload = () => { if (onStage) onStage('processing'); };
      xhr.onload = () => {
        let data = null;
        try { data = JSON.parse(xhr.responseText); } catch (e) {}
        if (xhr.status === 413) return reject(new Error('That picture is too large for the server. Try a smaller one.'));
        if (!data) return reject(new Error('The upload finished but the server did not answer properly. Try again.'));
        if (xhr.status >= 400 || !data.ok) return reject(new Error(data.error || 'The upload failed. Try again.'));
        resolve(data);
      };
      xhr.onerror = () => reject(new Error('The connection dropped during the upload. Try again.'));
      xhr.onabort = () => { const e = new Error('Upload stopped.'); e.aborted = true; reject(e); };
      if (onStage) onStage('uploading');
      xhr.send(fd);
    });
    return { promise, abort: () => xhr.abort() };
  }

  /* =======================================================
     6. THE TEXT EDITOR

     Nobody using this should ever meet a tag. Each block arrives already
     taken apart by the server (inc/richtext.php): the words, as runs carrying
     at most "highlight" or "bold". They are shown in a box you type in, and
     sent back as words. A block in several parts — a title and its subtitle,
     the three lines of the hero — gets one box per part.
     ======================================================= */
  function paintRuns(box, runs) {
    box.innerHTML = '';
    (runs || []).forEach((run) => {
      if (run.br) { box.appendChild(document.createElement('br')); return; }
      let node = document.createTextNode(run.t || '');
      if (run.em) { const em = document.createElement('em'); em.appendChild(node); node = em; }
      if (run.strong) { const st = document.createElement('strong'); st.appendChild(node); node = st; }
      box.appendChild(node);
    });
  }

  // Marks come from the ancestors, so it does not matter whether the browser
  // gave back <b> or <strong>, <i> or <em>, or wrapped things twice over.
  function readRuns(box) {
    const runs = [];
    (function walk(node, marks) {
      for (let n = node.firstChild; n; n = n.nextSibling) {
        if (n.nodeType === 3) {
          String(n.nodeValue).split(/\r?\n/).forEach((piece, i) => {
            if (i) runs.push({ br: true });
            if (piece) runs.push(Object.assign({ t: piece }, marks.em ? { em: true } : {}, marks.strong ? { strong: true } : {}));
          });
          continue;
        }
        if (n.nodeType !== 1) continue;
        const tag = n.tagName.toLowerCase();
        if (tag === 'br') { runs.push({ br: true }); continue; }
        const next = { em: marks.em, strong: marks.strong };
        if (tag === 'b' || tag === 'strong') next.strong = true;
        if (tag === 'i' || tag === 'em' || tag === 'u') next.em = true;
        const style = n.getAttribute('style') || '';
        if (/font-weight\s*:\s*(bold|[6-9]00)/i.test(style)) next.strong = true;
        if (/font-style\s*:\s*italic/i.test(style)) next.em = true;
        // a <div> a browser adds on Enter is a new line
        if ((tag === 'div' || tag === 'p') && runs.length && !runs[runs.length - 1].br) runs.push({ br: true });
        walk(n, next);
      }
    })(box, {});

    // merge neighbours with the same marks, drop empties and trailing breaks
    const out = [];
    runs.forEach((r) => {
      if (r.br) { out.push(r); return; }
      const last = out[out.length - 1];
      if (last && !last.br && !!last.em === !!r.em && !!last.strong === !!r.strong) last.t += r.t;
      else out.push(r);
    });
    while (out.length && out[out.length - 1].br) out.pop();
    return out;
  }

  const runsText = (runs) => (runs || []).map((r) => (r.br ? ' ' : r.t || '')).join('').replace(/\s+/g, ' ').trim();

  /**
   * @param opts { slots, labels, heading, original, onInput, onSubmit }
   */
  function TextEditor(opts) {
    const el = document.createElement('div');
    el.className = 'te';
    const slots = opts.slots && opts.slots.length ? opts.slots : [[{ t: '' }]];
    const labels = opts.labels || [];

    el.innerHTML =
      '<div class="te-toolbar" role="toolbar" aria-label="Formatting">' +
        '<button class="te-btn" type="button" data-mark="em" title="Highlight — the site’s serif italic in the accent colour, for the standout word in a headline"><span class="hl">Highlight</span></button>' +
        '<button class="te-btn" type="button" data-mark="strong" title="Bold (Ctrl+B)"><b>Bold</b></button>' +
        '<span class="te-sep"></span>' +
        '<button class="te-btn" type="button" data-mark="none" title="Remove highlight and bold">Plain</button>' +
        '<span class="te-tip">Select words, then pick a style</span>' +
      '</div>';

    const boxes = [];
    slots.forEach((runs, i) => {
      const slot = document.createElement('div');
      slot.className = 'te-slot';
      const name = labels[i] || 'Part ' + (i + 1);
      const id = 'te-' + Math.random().toString(36).slice(2, 8);
      if (slots.length > 1) slot.innerHTML = '<label class="te-label" for="' + id + '">' + esc(name) + '</label>';
      const box = document.createElement('div');
      box.className = 'te-box' + (opts.heading ? ' is-heading' : '');
      box.id = id;
      box.contentEditable = 'true';
      box.spellcheck = true;
      box.setAttribute('role', 'textbox');
      box.setAttribute('aria-multiline', 'true');
      box.setAttribute('aria-label', slots.length > 1 ? name : 'Text');
      paintRuns(box, runs);
      slot.appendChild(box);
      el.appendChild(slot);
      boxes.push(box);
    });

    const origLen = opts.original ? opts.original.length : 0;
    const meta = document.createElement('div');
    meta.className = 'te-meta';
    meta.innerHTML = '<span>Enter saves · Shift+Enter starts a new line</span><span class="num" data-count></span>';
    el.appendChild(meta);

    if (opts.original) {
      const o = document.createElement('div');
      o.className = 'te-original';
      o.innerHTML = '<b>Originally</b>' + esc(opts.original);
      el.appendChild(o);
    }

    // the baseline is read back out of the boxes themselves, so a block that
    // has only been looked at never counts as changed
    const before = JSON.stringify(boxes.map(readRuns));

    function count() {
      const n = boxes.map((b) => runsText(readRuns(b))).join(' ').length;
      $('[data-count]', meta).textContent = n + ' characters' + (origLen && Math.abs(n - origLen) > 2 ? ' · was ' + origLen : '');
    }

    try { document.execCommand('styleWithCSS', false, false); } catch (e) {}

    boxes.forEach((box) => {
      // nothing pasted in ever brings formatting along
      box.addEventListener('paste', (e) => {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, text.replace(/\s*\n\s*/g, ' '));
      });
      box.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); if (opts.onSubmit) opts.onSubmit(); return; }
        if (e.key === 'Enter' && e.shiftKey) { e.preventDefault(); document.execCommand('insertLineBreak'); return; }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') { e.preventDefault(); applyMark('strong'); }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'i') { e.preventDefault(); applyMark('em'); }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); if (opts.onSubmit) opts.onSubmit(); }
      });
      box.addEventListener('input', () => { count(); if (opts.onInput) opts.onInput(); });
    });

    // mousedown, not click: the selection is still alive at that point
    $$('.te-btn', el).forEach((b) => b.addEventListener('mousedown', (e) => { e.preventDefault(); applyMark(b.dataset.mark); }));

    function inBoxes(node) {
      for (; node; node = node.parentNode) if (boxes.indexOf(node) !== -1) return true;
      return false;
    }

    function applyMark(kind) {
      const sel = getSelection();
      if (!sel || sel.isCollapsed || !inBoxes(sel.anchorNode)) {
        toast('Select the words you want to style first');
        return;
      }
      if (kind === 'em') document.execCommand('italic');
      else if (kind === 'strong') document.execCommand('bold');
      else document.execCommand('removeFormat');
      count();
      if (opts.onInput) opts.onInput();
      syncToolbar();
    }

    function syncToolbar() {
      const sel = getSelection();
      const node = sel && sel.anchorNode;
      if (!node || !inBoxes(node)) return;
      let em = false, strong = false;
      for (let n = node.nodeType === 3 ? node.parentNode : node; n && boxes.indexOf(n) === -1; n = n.parentNode) {
        const t = n.tagName && n.tagName.toLowerCase();
        if (t === 'em' || t === 'i') em = true;
        if (t === 'strong' || t === 'b') strong = true;
      }
      $('[data-mark="em"]', el).classList.toggle('is-on', em);
      $('[data-mark="strong"]', el).classList.toggle('is-on', strong);
    }
    const onSel = () => syncToolbar();
    document.addEventListener('selectionchange', onSel);

    count();

    return {
      el,
      getSlots: () => boxes.map(readRuns),
      isDirty: () => JSON.stringify(boxes.map(readRuns)) !== before,
      isEmpty: () => boxes.every((b) => runsText(readRuns(b)) === ''),
      focus() {
        const b = boxes[0];
        b.focus();
        const r = document.createRange();
        r.selectNodeContents(b);
        r.collapse(false);
        const s = getSelection();
        s.removeAllRanges();
        s.addRange(r);
      },
      destroy() { document.removeEventListener('selectionchange', onSel); }
    };
  }

  /* =======================================================
     7. THE PICTURE PICKER
     ======================================================= */
  let libraryCache = null;

  /**
   * @param opts { value, preview, alt, attr, isLogo, hint, onChange }
   *   onChange(previewUrl) fires whenever the chosen picture changes.
   */
  function ImagePicker(opts) {
    const el = document.createElement('div');
    el.className = 'ip';
    const start = { value: opts.value || '', alt: opts.alt || '' };
    let value = start.value;
    let current = null;      // the upload in flight, if any
    const isHover = opts.attr === 'data-img';

    el.innerHTML =
      '<div class="ip-preview' + (opts.isLogo ? ' is-dark' : '') + '"><img alt=""><span class="ip-meta" hidden></span></div>' +
      (opts.hint ? '<div class="notice">' + ico('alert') + '<span>' + esc(opts.hint) + '</span></div>' : '') +
      '<div class="seg ip-tabs" role="tablist">' +
        '<button type="button" role="tab" aria-selected="true" data-tab="upload">' + ico('upload') + 'Upload</button>' +
        '<button type="button" role="tab" aria-selected="false" data-tab="library">' + ico('images') + 'Library</button>' +
        '<button type="button" role="tab" aria-selected="false" data-tab="link">' + ico('link') + 'Link</button>' +
      '</div>' +
      '<div data-panel="upload">' +
        '<label class="ip-drop" tabindex="0">' + ico('upload') +
          '<b>Drop a picture here, or click to choose</b>' +
          '<span>JPG, PNG, WEBP or AVIF · large photos are resized for you</span>' +
          '<input type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" hidden>' +
        '</label>' +
        '<div class="ip-up" hidden>' +
          '<div class="ip-up-row"><img class="ip-up-thumb" alt=""><span class="ip-up-txt"><b></b><span></span></span><span class="ip-up-pct">0%</span></div>' +
          '<div class="progress"><i></i></div>' +
          '<div style="display:flex;justify-content:flex-end"><button class="btn btn-plain btn-xs" type="button" data-cancel>Stop upload</button></div>' +
        '</div>' +
      '</div>' +
      '<div data-panel="library" hidden><div class="ip-lib"></div></div>' +
      '<div data-panel="link" hidden>' +
        '<div class="field" style="margin:0"><label>Picture address</label><input type="url" placeholder="https://…" spellcheck="false">' +
        '<div class="help">Paste the address of a picture already online — right-click a picture and choose “Copy image address”.</div></div>' +
      '</div>' +
      (isHover ? '' :
        '<div class="field" style="margin:0"><label>Describe the picture</label>' +
        '<input type="text" maxlength="480" data-alt placeholder="For example: bride in a red lehenga on a rooftop terrace">' +
        '<div class="help">Read aloud to people who can’t see it, and used by Google Images. Plain words; no need to start with “picture of”.</div></div>');

    const img = $('.ip-preview img', el);
    const metaEl = $('.ip-meta', el);
    const fileInput = $('input[type=file]', el);
    const drop = $('.ip-drop', el);
    const up = $('.ip-up', el);
    const altInput = $('[data-alt]', el);
    const urlInput = $('[data-panel="link"] input', el);
    if (altInput) altInput.value = start.alt;

    function show(src, meta) {
      img.style.opacity = '.35';
      const pre = new Image();
      pre.onload = () => { img.src = src; img.style.opacity = '1'; };
      pre.onerror = () => { img.src = src; img.style.opacity = '1'; };
      pre.src = src;
      metaEl.hidden = !meta;
      metaEl.textContent = meta || '';
    }
    show(opts.preview || '');

    function choose(newValue, previewUrl, meta) {
      value = newValue;
      show(previewUrl, meta);
      if (opts.onChange) opts.onChange(previewUrl);
    }

    /* tabs */
    initSeg($('.ip-tabs', el), (b) => {
      $$('[data-panel]', el).forEach((p) => { p.hidden = p.dataset.panel !== b.dataset.tab; });
      if (b.dataset.tab === 'library') loadLibrary();
      return true;
    });

    /* upload */
    drop.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); } });
    ['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.remove('is-over'); }));
    drop.addEventListener('drop', (e) => {
      const f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
      if (f) startUpload(f);
    });
    fileInput.addEventListener('change', () => {
      if (fileInput.files && fileInput.files[0]) startUpload(fileInput.files[0]);
      fileInput.value = '';
    });
    $('[data-cancel]', el).addEventListener('click', () => { if (current) current.abort(); });

    async function startUpload(file) {
      if (!/^image\//.test(file.type)) { toast('That isn’t a picture. Choose a JPG, PNG, WEBP or AVIF.', 'bad'); return; }
      if (current) return;

      const bar = $('.progress', up);
      const fill = $('.progress i', up);
      const pct = $('.ip-up-pct', up);
      const line = $('.ip-up-txt span', up);
      $('.ip-up-txt b', up).textContent = file.name;
      const thumb = $('.ip-up-thumb', up);
      const localUrl = URL.createObjectURL(file);
      thumb.src = localUrl;
      drop.hidden = true;
      up.hidden = false;
      fill.style.width = '0%';
      pct.textContent = '';
      bar.classList.add('is-indeterminate');
      line.textContent = 'Getting it ready…';
      current = { abort() {} };

      try {
        const prepared = await prepareImage(file);
        const f = prepared.blob;
        if (f.size > MAX_BYTES) throw new Error('That picture is ' + prettyBytes(f.size) + ' — the limit is ' + prettyBytes(MAX_BYTES) + '. Try a smaller one.');

        bar.classList.remove('is-indeterminate');
        line.textContent = (prepared.resized ? 'Resized to ' : '') + prettyBytes(f.size) + ' · uploading';
        const job = uploadFile(f, (frac, sent, total) => {
          const p = Math.round(frac * 100);
          fill.style.width = p + '%';
          pct.textContent = p + '%';
          line.textContent = prettyBytes(sent) + ' of ' + prettyBytes(total);
        }, (stage) => {
          if (stage === 'processing') {
            fill.style.width = '100%';
            pct.textContent = '100%';
            line.textContent = 'Checking the picture…';
          }
        });
        current = job;
        const d = await job.promise;
        line.textContent = 'Uploaded · ' + d.width + ' × ' + d.height + ' · ' + d.size;
        libraryCache = null;
        choose(d.path, d.url, d.width + ' × ' + d.height);
        if (altInput && !altInput.value.trim()) altInput.focus();
        await sleep(900);
      } catch (err) {
        if (!err.aborted) toast(err.message, 'bad');
        else toast('Upload stopped');
      } finally {
        URL.revokeObjectURL(localUrl);
        current = null;
        up.hidden = true;
        drop.hidden = false;
      }
    }

    /* library */
    async function loadLibrary() {
      const grid = $('.ip-lib', el);
      if (!libraryCache) {
        grid.innerHTML = '<div class="ip-hint">Loading your pictures…</div>';
        try { libraryCache = (await getJson('api/media.php')).items; }
        catch (e) { grid.innerHTML = '<div class="ip-hint">' + esc(e.message) + '</div>'; return; }
      }
      if (!libraryCache.length) {
        grid.innerHTML = '<div class="ip-hint">Pictures you upload are kept here, so you can use them again anywhere on the site. Nothing uploaded yet.</div>';
        return;
      }
      grid.innerHTML = libraryCache.map((m, i) =>
        '<button type="button" class="ip-thumb' + (m.path === value ? ' is-on' : '') + '" data-i="' + i + '" title="' + esc(m.name + ' · ' + m.width + '×' + m.height + ' · ' + m.when) + '">' +
          '<img src="' + esc(m.url) + '" alt="" loading="lazy"></button>').join('');
      $$('.ip-thumb', grid).forEach((b) => b.addEventListener('click', () => {
        const m = libraryCache[+b.dataset.i];
        $$('.ip-thumb', grid).forEach((x) => x.classList.toggle('is-on', x === b));
        choose(m.path, m.url, m.width + ' × ' + m.height);
      }));
    }

    /* link */
    let urlTimer;
    urlInput.addEventListener('input', () => {
      clearTimeout(urlTimer);
      urlTimer = setTimeout(() => {
        const v = urlInput.value.trim();
        if (!/^https?:\/\/\S+$/i.test(v)) return;
        const test = new Image();
        test.onload = () => choose(v, v, test.naturalWidth + ' × ' + test.naturalHeight);
        test.onerror = () => toast('That address doesn’t lead to a picture', 'bad');
        test.src = v;
      }, 450);
    });

    return {
      el,
      getValue: () => value,
      getAlt: () => (altInput ? altInput.value.trim() : null),
      isDirty: () => value !== start.value || (altInput ? altInput.value.trim() !== start.alt.trim() : false),
      isBusy: () => !!current,
      abort: () => { if (current) current.abort(); },
      onAltInput: (fn) => { if (altInput) altInput.addEventListener('input', fn); }
    };
  }

  /* =======================================================
     8. THE FIELD EDITOR
     Builds the head, body and footer for one field, so the same editor
     can sit in the sheet (Words, Pictures) or the side panel (Edit visually).
     ======================================================= */

  /**
   * @param data   the GET api/content.php?id= response
   * @param hooks  { onPreview(html|src), onSaved(response), onReset(response), close() }
   */
  function FieldEditor(data, hooks) {
    hooks = hooks || {};
    const isText = data.type === 'text';
    const head = document.createElement('div');
    head.className = 'sheet-head';
    head.innerHTML =
      '<div class="t"><p class="k">' + esc(data.section) + '</p>' +
      '<h2 id="sheetTitle">' + esc(data.title) + '</h2>' +
      (isText ? '' : '<p class="sub">' + (data.attr === 'data-img' ? 'Shown on hover' : 'Picture') + '</p>') + '</div>' +
      '<button class="icon-btn" type="button" data-close aria-label="Close">' + ico('close') + '</button>';

    const body = document.createElement('div');
    body.className = 'sheet-body';

    const foot = document.createElement('div');
    foot.className = 'sheet-foot';
    foot.innerHTML =
      (data.edited ? '<button class="btn btn-plain btn-sm" type="button" data-restore>' + ico('undo') + 'Restore original</button>' : '') +
      '<span class="sheet-state" data-state>No changes yet</span>' +
      '<span class="spacer"></span>' +
      '<button class="btn btn-sm" type="button" data-cancel>Cancel</button>' +
      '<button class="btn btn-primary btn-sm" type="button" data-save disabled>Save &amp; publish</button>';

    const saveBtn = $('[data-save]', foot);
    const state = $('[data-state]', foot);
    let editor, picker, previewTimer, saving = false;

    function refresh() {
      const dirty = isDirty();
      saveBtn.disabled = !dirty || (picker && picker.isBusy());
      state.textContent = picker && picker.isBusy() ? 'Uploading…' : dirty ? 'Unsaved changes' : 'No changes yet';
      state.classList.toggle('is-dirty', dirty);
    }

    if (isText) {
      editor = TextEditor({
        slots: data.slots,
        labels: data.labels,
        heading: data.tag === 'h1' || data.tag === 'h2',
        original: data.edited ? data.original : '',
        onInput() {
          refresh();
          if (!hooks.onPreview) return;
          clearTimeout(previewTimer);
          previewTimer = setTimeout(async () => {
            if (editor.isEmpty()) return;
            try {
              const d = await post('api/content.php', { id: data.id, slots: editor.getSlots(), preview: true });
              hooks.onPreview(d.value);
            } catch (e) { /* the preview is a nicety; saving reports real errors */ }
          }, 260);
        },
        onSubmit: () => save()
      });
      body.appendChild(editor.el);
    } else {
      picker = ImagePicker({
        value: data.value,
        preview: data.preview,
        alt: data.alt,
        attr: data.attr,
        isLogo: /logo/i.test(data.title),
        hint: data.hint,
        onChange(src) { refresh(); if (hooks.onPreview) hooks.onPreview(src); }
      });
      picker.onAltInput(refresh);
      body.appendChild(picker.el);
      // the busy state changes outside onChange while an upload runs
      var busyTimer = setInterval(refresh, 300);
    }

    function isDirty() { return isText ? editor.isDirty() : picker.isDirty(); }

    async function save() {
      if (saving) return;
      if (!isDirty()) { if (hooks.close) hooks.close(true); return; }
      if (picker && picker.isBusy()) { toast('Wait for the upload to finish first'); return; }
      if (isText && editor.isEmpty()) { toast('This can’t be left empty. Use “Restore original” to bring back the first version.', 'bad'); return; }

      saving = true;
      busy(saveBtn, true, 'Publishing…');
      try {
        const payload = isText
          ? { id: data.id, slots: editor.getSlots() }
          : { id: data.id, value: picker.getValue(), alt: picker.getAlt() };
        const d = await post('api/content.php', payload);
        toast('Saved — it’s live on the site', 'ok');
        if (hooks.onSaved) hooks.onSaved(d);
        markClean();
        if (hooks.close) hooks.close(true);
      } catch (err) {
        toast(err.message, 'bad');
        throw err;
      } finally {
        saving = false;
        busy(saveBtn, false);
        refresh();
      }
    }

    // after a save the editor is clean, so closing never asks again
    let clean = false;
    function markClean() { clean = true; }

    async function restore() {
      const ok = await dialog({
        title: 'Bring back the original?',
        message: 'This puts back what the site said when it was built, and publishes it straight away.',
        actions: [
          { label: 'Restore original', value: true, kind: 'danger' },
          { label: 'Cancel', value: false, cancel: true }
        ]
      });
      if (!ok) return;
      const btn = $('[data-restore]', foot);
      busy(btn, true, 'Restoring…');
      try {
        const d = await post('api/content.php', { id: data.id, reset: true });
        toast('Original restored', 'ok');
        markClean();
        if (hooks.onReset) hooks.onReset(d);
        if (hooks.close) hooks.close(true);
      } catch (err) {
        toast(err.message, 'bad');
        busy(btn, false);
      }
    }

    $('[data-close]', head).addEventListener('click', () => hooks.close && hooks.close(false));
    $('[data-cancel]', foot).addEventListener('click', () => hooks.close && hooks.close(false));
    saveBtn.addEventListener('click', () => save().catch(() => {}));
    const rb = $('[data-restore]', foot);
    if (rb) rb.addEventListener('click', restore);

    return {
      head, body, foot,
      isDirty: () => !clean && isDirty(),
      isBusy: () => !!(picker && picker.isBusy()),
      save,
      focus() { if (editor) setTimeout(() => editor.focus(), 380); },
      destroy() {
        clearTimeout(previewTimer);
        if (busyTimer) clearInterval(busyTimer);
        if (editor) editor.destroy();
        if (picker) picker.abort();
      },
      what: isText ? 'this text' : 'this picture'
    };
  }

  /**
   * Ask before a field editor goes away. True when it may close.
   * An upload in flight is its own question.
   */
  async function confirmLeaveField(fe, discard) {
    if (!fe) return true;
    if (fe.isBusy()) {
      const stop = await dialog({
        title: 'Stop the upload?',
        message: 'A picture is still uploading. Stop it and close, or keep waiting.',
        actions: [
          { label: 'Keep waiting', value: false, kind: 'primary', cancel: true },
          { label: 'Stop and close', value: true, kind: 'danger' }
        ]
      });
      if (!stop) return false;
      if (discard) discard();
      return true;
    }
    if (!fe.isDirty()) return true;
    const choice = await askUnsaved(fe.what);
    if (choice === 'keep') return false;
    if (choice === 'save') {
      try { await fe.save(); return true; } catch (e) { return false; }
    }
    if (discard) discard();
    return true;
  }

  /** Open a field in the sheet. Used by the Words and Pictures pages. */
  async function openFieldSheet(id, hooks) {
    let data;
    try { data = await getJson('api/content.php?id=' + encodeURIComponent(id)); }
    catch (err) { toast(err.message, 'bad'); return; }

    let fe = null;
    let release = null;
    const finish = () => {
      if (release) release();
      if (fe) fe.destroy();
      fe = null;
    };

    fe = FieldEditor(data, Object.assign({}, hooks, {
      close: async (saved) => {
        if (saved) { Sheet.close(); finish(); return; }
        await Sheet.requestClose();
      }
    }));

    release = guard({ isDirty: () => !!fe && fe.isDirty(), save: () => fe.save(), discard: () => {}, what: fe.what });

    Sheet.show(fe, {
      onRequestClose: async () => {
        const ok = await confirmLeaveField(fe, null);
        if (ok) finish();
        return ok;
      }
    });
    fe.focus();
  }

  /* =======================================================
     9. PAGES
     ======================================================= */

  /* ---------------- WORDS ---------------- */
  if (page === 'text') {
    $$('.trow').forEach((row) => row.addEventListener('click', () => openFieldSheet(row.dataset.field, {
      onSaved: (d) => paintRow(row, d, true),
      onReset: (d) => paintRow(row, d, false)
    })));

    function paintRow(row, d, edited) {
      if (d.display != null) $('[data-value]', row).innerHTML = d.display;
      $('.trow-status', row).innerHTML = edited ? '<span class="pill pill-edited">Changed</span>' : '';
      row.animate && !reduced && row.animate([{ background: 'var(--accent-soft)' }, { background: 'transparent' }], { duration: 1400, easing: 'ease-out' });
    }

    const search = $('#textSearch');
    const none = $('#noResults');
    if (search) {
      search.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        let shown = 0;
        $$('[data-group]').forEach((g) => {
          let any = 0;
          $$('.trow', g).forEach((r) => {
            const hit = !q || r.textContent.toLowerCase().indexOf(q) !== -1;
            r.hidden = !hit;
            if (hit) any++;
          });
          g.hidden = !any;
          shown += any;
        });
        none.classList.toggle('is-on', !shown);
      });
    }
  }

  /* ---------------- PICTURES ---------------- */
  if (page === 'images') {
    $$('.icard').forEach((card) => card.addEventListener('click', () => openFieldSheet(card.dataset.field, {
      onSaved: (d) => paintCard(card, d, true),
      onReset: (d) => paintCard(card, d, false)
    })));

    function paintCard(card, d, edited) {
      const media = $('.icard-media', card);
      if (d.preview) $('img', media).src = d.preview;
      const pill = $('.pill', media);
      if (edited && !pill) media.insertAdjacentHTML('afterbegin', '<span class="pill pill-edited">Replaced</span>');
      if (!edited && pill) pill.remove();
    }

    // a link from the Overview's to-do list straight to a group
    if (location.hash === '#hover') { const d = $('#hover'); if (d) d.open = true; }
  }

  /* ---------------- EDIT VISUALLY ---------------- */
  if (page === 'editor') (function visualEditor() {
    const ve = $('#ve');
    const frame = $('#veFrame');
    const device = $('#veDeviceFrame');
    const loading = $('#veLoading');
    const panel = $('#vePanelInner');
    const names = window.SBX_FIELDS || {};

    let doc = null;          // the page inside the frame
    let hoverBox, selBox;    // highlight overlays inside the frame
    let selected = null;     // { el, id, type, original }
    let fe = null;           // the open field editor
    let hovered = null;

    guard({
      isDirty: () => !!fe && fe.isDirty(),
      save: () => fe.save(),
      discard: () => discardPreview(),
      get what() { return fe ? fe.what : 'part of the page'; }
    });

    /* ---- device sizes ---- */
    initSeg($('#veDevice'), (b) => {
      device.dataset.device = b.dataset.device;
      setTimeout(updateBoxes, 600);
      return true;
    });

    $('#veReload').addEventListener('click', async () => {
      if (!(await closePanel())) return;
      const y = doc ? doc.defaultView.scrollY : 0;
      loading.classList.remove('is-done');
      frame.dataset.restore = String(y);
      frame.contentWindow.location.reload();
    });

    frame.addEventListener('load', () => {
      try { doc = frame.contentDocument; } catch (e) { doc = null; }
      if (!doc || !doc.body) return;
      setupFrame();
      loading.classList.add('is-done');
      const y = parseInt(frame.dataset.restore || '0', 10);
      if (y) { doc.defaultView.scrollTo(0, y); frame.dataset.restore = ''; }
      else if (location.hash.length > 1) {
        const target = doc.getElementById(location.hash.slice(1));
        if (target) target.scrollIntoView({ block: 'start' });
      }
    });

    function setupFrame() {
      const style = doc.createElement('style');
      style.textContent =
        '#sbx-hover,#sbx-sel{position:fixed;z-index:2147483000;pointer-events:none;border-radius:8px;transition:all .14s cubic-bezier(.22,1,.36,1)}' +
        '#sbx-hover{border:2px solid rgba(76,149,238,.95);background:rgba(76,149,238,.07);opacity:0}' +
        '#sbx-hover.on{opacity:1}' +
        '#sbx-sel{border:2px solid #4C95EE;box-shadow:0 0 0 4px rgba(76,149,238,.25);opacity:0}' +
        '#sbx-sel.on{opacity:1}' +
        '#sbx-hover b{position:absolute;left:-2px;bottom:calc(100% + 6px);white-space:nowrap;' +
          'font:600 12px/1 "Inter Tight",system-ui,sans-serif;letter-spacing:0;text-transform:none;color:#fff;' +
          'background:#2E7FD4;padding:6px 9px;border-radius:7px;box-shadow:0 6px 16px -6px rgba(0,0,0,.5)}' +
        '#sbx-hover.below b{bottom:auto;top:calc(100% + 6px)}' +
        '[data-cms-text],[data-cms-img]{cursor:pointer!important}';
      doc.head.appendChild(style);

      hoverBox = doc.createElement('div'); hoverBox.id = 'sbx-hover'; hoverBox.innerHTML = '<b></b>';
      selBox = doc.createElement('div'); selBox.id = 'sbx-sel';
      doc.body.append(hoverBox, selBox);

      doc.addEventListener('mousemove', (e) => {
        const t = pick(e.clientX, e.clientY);
        if (t === hovered) return;
        hovered = t;
        paintHover();
      }, { passive: true });
      doc.addEventListener('mouseleave', () => { hovered = null; paintHover(); });

      // nothing on the page navigates or submits while it is being edited
      doc.addEventListener('click', (e) => {
        const t = pick(e.clientX, e.clientY);
        const link = e.target.closest && e.target.closest('a[href], button');
        if (t || link) { e.preventDefault(); e.stopPropagation(); }
        if (t) select(t);
      }, true);
      doc.addEventListener('submit', (e) => e.preventDefault(), true);

      doc.defaultView.addEventListener('scroll', updateBoxes, { passive: true });
      doc.defaultView.addEventListener('resize', updateBoxes);
    }

    /** The editable thing under a point: words win over the picture behind them. */
    function pick(x, y) {
      if (!doc) return null;
      const stack = doc.elementsFromPoint(x, y);
      for (const el of stack) {
        if (el.id === 'sbx-hover' || el.id === 'sbx-sel') continue;
        const t = el.closest('[data-cms-text]');
        if (t) return t;
        if (el.matches('img[data-cms-img]')) return el;
      }
      return null;
    }

    function fieldId(el) { return el.getAttribute('data-cms-text') || el.getAttribute('data-cms-img'); }

    function place(box, el) {
      const r = el.getBoundingClientRect();
      const pad = 4;
      box.style.left = (r.left - pad) + 'px';
      box.style.top = (r.top - pad) + 'px';
      box.style.width = (r.width + pad * 2) + 'px';
      box.style.height = (r.height + pad * 2) + 'px';
      return r;
    }

    function paintHover() {
      if (!hoverBox) return;
      if (!hovered || (selected && hovered === selected.el)) { hoverBox.classList.remove('on'); return; }
      const r = place(hoverBox, hovered);
      const info = names[fieldId(hovered)] || [];
      const isImg = hovered.tagName === 'IMG';
      $('b', hoverBox).textContent = (info[0] || (isImg ? 'Picture' : 'Text')) + (isImg ? ' · click to replace' : ' · click to edit');
      hoverBox.classList.toggle('below', r.top < 40);
      hoverBox.classList.add('on');
    }

    function updateBoxes() {
      if (selected && selBox) place(selBox, selected.el);
      if (hovered) paintHover();
    }

    async function select(el) {
      if (selected && selected.el === el) return;
      if (!(await closePanel())) return;

      const id = fieldId(el);
      selected = { el, id, original: el.tagName === 'IMG' ? el.getAttribute('src') : el.innerHTML };
      place(selBox, el);
      selBox.classList.add('on');
      paintHover();

      panel.innerHTML = '<div class="ve-empty"><div><div class="progress is-indeterminate" style="width:140px;margin:0 auto 12px"><i></i></div>Opening…</div></div>';
      ve.classList.add('is-editing');
      setTimeout(updateBoxes, 520);

      let data;
      try { data = await getJson('api/content.php?id=' + encodeURIComponent(id)); }
      catch (err) { toast(err.message, 'bad'); closeNow(); return; }
      if (!selected || selected.id !== id) return;

      fe = FieldEditor(data, {
        onPreview(v) {
          if (!selected) return;
          if (selected.el.tagName === 'IMG') { selected.el.src = v; selected.el.removeAttribute('srcset'); }
          else selected.el.innerHTML = v;
          requestAnimationFrame(updateBoxes);
        },
        onSaved(d) {
          if (!selected) return;
          if (selected.el.tagName === 'IMG') { if (d.preview) selected.el.src = d.preview; }
          else selected.el.innerHTML = d.value;
          selected.original = selected.el.tagName === 'IMG' ? selected.el.getAttribute('src') : selected.el.innerHTML;
        },
        onReset(d) {
          if (!selected) return;
          if (selected.el.tagName === 'IMG') { if (d.preview) selected.el.src = d.preview; }
          else selected.el.innerHTML = d.value;
          selected.original = selected.el.tagName === 'IMG' ? selected.el.getAttribute('src') : selected.el.innerHTML;
        },
        close: async (saved) => { if (saved) closeNow(); else await closePanel(); }
      });

      panel.innerHTML = '';
      panel.append(fe.head, fe.body, fe.foot);
      fe.focus();

      // bring it into view if the panel opening pushed it out of sight; on a
      // phone the panel rises over the lower part, so park it near the top
      setTimeout(() => {
        const r = el.getBoundingClientRect();
        const h = doc.defaultView.innerHeight;
        if (matchMedia('(max-width: 960px)').matches) {
          const visible = frame.getBoundingClientRect().height - $('#vePanel').getBoundingClientRect().height;
          if (r.top < 90 || r.top > visible * 0.6) doc.defaultView.scrollBy({ top: r.top - 90, behavior: reduced ? 'auto' : 'smooth' });
        } else if (r.bottom < 60 || r.top > h - 60) {
          el.scrollIntoView({ block: 'center', behavior: reduced ? 'auto' : 'smooth' });
        }
      }, 560);
    }

    function discardPreview() {
      if (!selected) return;
      if (selected.el.tagName === 'IMG') selected.el.setAttribute('src', selected.original);
      else selected.el.innerHTML = selected.original;
    }

    /** Close the panel, asking first if there is unsaved work. */
    async function closePanel() {
      if (!fe) { if (selected) closeNow(); return true; }
      const ok = await confirmLeaveField(fe, discardPreview);
      if (ok) closeNow();
      return ok;
    }

    function closeNow() {
      if (fe) fe.destroy();
      fe = null;
      selected = null;
      if (selBox) selBox.classList.remove('on');
      ve.classList.remove('is-editing');
      setTimeout(updateBoxes, 520);
    }

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && fe && !document.querySelector('.dialog-layer')) closePanel();
    });
  })();

  /* ---------------- CLIENT STRIP ---------------- */
  if (page === 'clients') (function clients() {
    const list = $('#clientList');
    const empty = $('#clientEmpty');
    const count = $('#clientCount');
    const addForm = $('#clientAdd');
    const input = $('#clientNew');
    const bar = $('#clientBar');
    const saveBtn = $('#clientSave');
    const resetBtn = $('#clientReset');
    const track = $('#stripTrack');
    let dragRow = null;

    const rows = () => $$('.crow', list);
    const names = () => rows().map((r) => $('input', r).value.trim().replace(/\s+/g, ' ')).filter(Boolean);
    let saved = JSON.stringify(names());
    const isDirty = () => JSON.stringify(names()) !== saved;

    function row(name) {
      const li = document.createElement('li');
      li.className = 'crow';
      li.draggable = true;
      li.innerHTML =
        '<span class="crow-grip" aria-hidden="true">' + ico('grip') + '</span>' +
        '<span class="crow-no"></span>' +
        '<input type="text" maxlength="60" aria-label="Client name">' +
        '<button class="icon-btn" type="button" data-up aria-label="Move up">↑</button>' +
        '<button class="icon-btn" type="button" data-down aria-label="Move down">↓</button>' +
        '<button class="icon-btn del" type="button" data-remove aria-label="Remove">' + ico('trash') + '</button>';
      $('input', li).value = name;
      wire(li);
      return li;
    }

    function update() {
      const all = rows();
      all.forEach((r, i) => { $('.crow-no', r).textContent = i + 1; });
      empty.hidden = all.length > 0;
      const n = names();
      count.textContent = n.length + (n.length === 1 ? ' name' : ' names');
      const strip = n.map((x) => '<span>' + esc(x) + '</span><i>·</i>').join('');
      track.innerHTML = strip + strip;
      bar.classList.toggle('is-on', isDirty());
    }

    function wire(li) {
      $('[data-remove]', li).addEventListener('click', () => {
        li.style.transition = 'opacity .2s, transform .25s';
        li.style.opacity = '0';
        li.style.transform = 'translateX(12px)';
        setTimeout(() => { li.remove(); update(); }, reduced ? 0 : 200);
      });
      $('[data-up]', li).addEventListener('click', () => { const p = li.previousElementSibling; if (p) { list.insertBefore(li, p); update(); } });
      $('[data-down]', li).addEventListener('click', () => { const n = li.nextElementSibling; if (n) { list.insertBefore(n, li); update(); } });
      $('input', li).addEventListener('input', update);
      li.addEventListener('dragstart', (e) => {
        if (document.activeElement && document.activeElement.tagName === 'INPUT' && li.contains(document.activeElement)) { e.preventDefault(); return; }
        dragRow = li; li.classList.add('is-dragging');
        if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move';
      });
      li.addEventListener('dragend', () => { dragRow = null; li.classList.remove('is-dragging'); rows().forEach((r) => r.classList.remove('is-over')); update(); });
      li.addEventListener('dragover', (e) => { if (dragRow && dragRow !== li) { e.preventDefault(); li.classList.add('is-over'); } });
      li.addEventListener('dragleave', () => li.classList.remove('is-over'));
      li.addEventListener('drop', (e) => {
        if (!dragRow || dragRow === li) return;
        e.preventDefault();
        li.classList.remove('is-over');
        const after = li.compareDocumentPosition(dragRow) & Node.DOCUMENT_POSITION_PRECEDING;
        list.insertBefore(dragRow, after ? li.nextSibling : li);
        update();
      });
    }
    rows().forEach(wire);

    addForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const v = input.value.trim().replace(/\s+/g, ' ');
      if (!v) return;
      if (names().some((n) => n.toLowerCase() === v.toLowerCase())) { toast('“' + v + '” is already on the strip', 'bad'); return; }
      if (rows().length >= 60) { toast('The strip holds up to 60 names', 'bad'); return; }
      const li = row(v);
      list.appendChild(li);
      input.value = '';
      input.focus();
      update();
      li.animate && !reduced && li.animate([{ opacity: 0, transform: 'translateY(-6px)' }, { opacity: 1, transform: 'none' }], { duration: 260, easing: 'cubic-bezier(.22,1,.36,1)' });
    });

    async function save() {
      const list2 = names();
      if (!list2.length) {
        const ok = await dialog({
          title: 'Hide the client strip?',
          message: 'With no names in the list, the strip comes off the site until you add one back.',
          actions: [{ label: 'Save empty list', value: true, kind: 'danger' }, { label: 'Cancel', value: false, cancel: true }]
        });
        if (!ok) throw new Error('Not saved.');
      }
      busy(saveBtn, true, 'Publishing…');
      try {
        const d = await post('api/clients.php', { names: list2 });
        paint(d.names);
        if (resetBtn) resetBtn.hidden = false;
        toast('Saved — the strip is live', 'ok');
      } finally {
        busy(saveBtn, false);
      }
    }

    function paint(n) {
      list.innerHTML = '';
      (n || []).forEach((x) => list.appendChild(row(x)));
      saved = JSON.stringify(names());
      update();
    }

    saveBtn.addEventListener('click', () => save().catch((e) => { if (e.message !== 'Not saved.') toast(e.message, 'bad'); }));
    $('#clientDiscard').addEventListener('click', () => { paint(JSON.parse(saved)); toast('Changes discarded'); });

    if (resetBtn) resetBtn.addEventListener('click', async () => {
      const ok = await dialog({
        title: 'Restore the original list?',
        message: 'This replaces the strip with the names the site launched with, and publishes it straight away.',
        actions: [{ label: 'Restore', value: true, kind: 'danger' }, { label: 'Cancel', value: false, cancel: true }]
      });
      if (!ok) return;
      try {
        const d = await post('api/clients.php', { reset: true });
        paint(d.names);
        resetBtn.hidden = true;
        toast('Original list restored', 'ok');
      } catch (e) { toast(e.message, 'bad'); }
    });

    guard({ isDirty, save, discard: () => paint(JSON.parse(saved)), what: 'the client strip' });
    update();
  })();

  /* ---------------- CONTACT & LINKS ---------------- */
  if (page === 'links') (function links() {
    const form = $('#linksForm');
    const bar = $('#linksBar');
    const saveBtn = $('#linksSave');
    const inputs = $$('input[name]', form);
    const read = () => inputs.reduce((o, i) => { o[i.name] = i.value.trim(); return o; }, {});
    let saved = JSON.stringify(read());
    const isDirty = () => JSON.stringify(read()) !== saved;

    function testHref(name, v) {
      if (!v) return '';
      const digits = v.replace(/\D+/g, '');
      if (name === 'email') return 'mailto:' + v;
      if (name === 'phone') return 'tel:' + (v.trim().charAt(0) === '+' ? '+' : '') + digits;
      if (name === 'whatsapp') return 'https://wa.me/' + (digits.length === 10 ? '91' + digits : digits);
      return /^https?:\/\//i.test(v) ? v : 'https://' + v;
    }

    function update() {
      inputs.forEach((i) => {
        const f = i.closest('.field');
        const a = $('[data-test]', f);
        const href = testHref(i.name, i.value.trim());
        a.hidden = !href;
        if (href) a.href = href;
      });
      bar.classList.toggle('is-on', isDirty());
    }

    inputs.forEach((i) => i.addEventListener('input', () => {
      const f = i.closest('.field');
      f.classList.remove('is-bad');
      $('.err', f).hidden = true;
      update();
    }));
    form.addEventListener('submit', (e) => { e.preventDefault(); save().catch(() => {}); });

    async function save() {
      inputs.forEach((i) => { const f = i.closest('.field'); f.classList.remove('is-bad'); $('.err', f).hidden = true; });
      busy(saveBtn, true, 'Publishing…');
      try {
        const d = await post('api/links.php', { links: read() });
        inputs.forEach((i) => { if (d.links[i.name] != null) i.value = d.links[i.name]; });
        saved = JSON.stringify(read());
        update();
        toast('Saved — every button on the site is updated', 'ok');
      } catch (err) {
        if (err.fields) {
          Object.keys(err.fields).forEach((k) => {
            const f = $('.field[data-key="' + k + '"]', form);
            if (!f) return;
            f.classList.add('is-bad');
            const m = $('.err', f);
            m.textContent = err.fields[k];
            m.hidden = false;
          });
          const first = $('.field.is-bad input', form);
          if (first) first.focus();
        }
        toast(err.message, 'bad');
        throw err;
      } finally {
        busy(saveBtn, false);
      }
    }

    saveBtn.addEventListener('click', () => save().catch(() => {}));
    $('#linksDiscard').addEventListener('click', () => {
      const s = JSON.parse(saved);
      inputs.forEach((i) => { i.value = s[i.name] || ''; i.closest('.field').classList.remove('is-bad'); $('.err', i.closest('.field')).hidden = true; });
      update();
      toast('Changes discarded');
    });

    guard({ isDirty, save, discard: () => {}, what: 'your contact details' });
    update();
  })();

  /* ---------------- LEADS ---------------- */
  if (page === 'leads') (function leads() {
    const all = window.SBX_LEADS || [];
    const templates = window.SBX_TEMPLATES || [];
    const inbox = $('#inbox');
    const listEl = $('#leadList');
    const main = $('#leadMain');
    let filter = 'active';
    let current = null;
    let noteSaved = '';

    const visible = () => all.filter((l) => filter === 'active' ? l.status !== 'archived' : l.status === filter);

    function renderList() {
      const items = visible();
      if (!items.length) {
        listEl.innerHTML = '<div class="empty" style="padding:40px 16px">' + ico('inbox') + '<h3>Nothing here</h3><p>' +
          (filter === 'archived' ? 'Archived enquiries are kept here.' : filter === 'replied' ? 'Enquiries you’ve marked as replied show here.' : 'New enquiries from the site land here.') + '</p></div>';
        return;
      }
      listEl.innerHTML = items.map((l) =>
        '<button type="button" class="lead-row' + (l.unseen ? ' is-unseen' : '') + (current && current.id === l.id ? ' is-on' : '') + '" data-id="' + l.id + '">' +
          '<span class="dot"></span>' +
          '<b>' + esc(l.name || l.email) + '</b>' +
          '<time>' + esc(l.ago) + '</time>' +
          '<p>' + esc(l.message || (l.name ? l.email : 'Left their email address')) + '</p>' +
          '<span class="meta"><span class="pill pill-' + esc(l.status) + '">' + esc(l.status[0].toUpperCase() + l.status.slice(1)) + '</span><span class="faint" style="font-size:12px">' + esc(l.source) + '</span></span>' +
        '</button>').join('');
      $$('.lead-row', listEl).forEach((b) => b.addEventListener('click', () => open(+b.dataset.id)));
    }

    function fill(s, l) {
      return String(s || '')
        .replace(/\{name\}/g, l.name ? l.name.split(' ')[0] : 'there')
        .replace(/\{email\}/g, l.email)
        .replace(/\{studio\}/g, CFG.studioName || 'Smashbox Productions');
    }

    async function open(id) {
      if (current && current.id === id) return;
      if (!(await settleNote())) return;
      const l = all.find((x) => x.id === id);
      if (!l) return;
      current = l;
      noteSaved = l.note || '';
      inbox.classList.add('is-reading');

      main.innerHTML =
        '<div class="lead-view">' +
          '<button class="btn btn-plain btn-sm inbox-back" type="button" data-back>' + ico('arrowLeft') + 'All leads</button>' +
          '<div class="lead-top"><span class="avatar avatar-lg">' + esc(l.initials) + '</span>' +
            '<div><h2>' + esc(l.name || l.email) + '</h2><p>' + esc(l.source) + ' · ' + esc(l.when) + '</p></div></div>' +
          '<div class="lead-actions">' +
            '<button class="btn btn-tinted btn-sm" type="button" data-status="replied">' + ico('check') + 'Mark as replied</button>' +
            '<button class="btn btn-sm" type="button" data-status="' + (l.status === 'archived' ? 'new' : 'archived') + '">' + ico('archive') + (l.status === 'archived' ? 'Move to inbox' : 'Archive') + '</button>' +
          '</div>' +
          '<dl class="facts">' +
            '<div><dt>Email</dt><dd><a href="mailto:' + esc(l.email) + '">' + esc(l.email) + '</a></dd></div>' +
            '<div><dt>Phone</dt><dd>' + (l.phone ? '<a href="tel:' + esc(l.phone.replace(/[^\d+]/g, '')) + '">' + esc(l.phone) + '</a>' : '<span class="faint">Not given</span>') + '</dd></div>' +
            '<div><dt>Status</dt><dd><span class="pill pill-' + esc(l.status) + '">' + esc(l.status[0].toUpperCase() + l.status.slice(1)) + '</span></dd></div>' +
          '</dl>' +
          (l.message ? '<div class="message">' + esc(l.message) + '</div>' : '<div class="notice" style="margin-bottom:26px">' + ico('alert') + '<span>They left their email address without a message. A short first reply usually gets the conversation going.</span></div>') +
          '<h3 class="section-title">Write back</h3>' +
          '<div class="tmpls">' + templates.map((t, i) => '<button type="button" class="tmpl' + (i === 0 ? ' is-on' : '') + '" data-t="' + i + '">' + esc(t.name) + '</button>').join('') + '</div>' +
          '<div class="field"><label for="mailSubject">Subject</label><input id="mailSubject" type="text"></div>' +
          '<div class="field"><label for="mailBody">Message</label><textarea id="mailBody" rows="9"></textarea>' +
            '<div class="help">Change anything you like. Nothing is sent from here — pick your mail app below and the message opens there, ready to send.</div></div>' +
          '<div class="platforms">' +
            '<button class="platform" type="button" data-p="gmail">' + ico('leads') + 'Gmail<small>in the browser</small></button>' +
            '<button class="platform" type="button" data-p="outlook">' + ico('leads') + 'Outlook<small>in the browser</small></button>' +
            '<button class="platform" type="button" data-p="whatsapp"' + (l.phone ? '' : ' hidden') + '>' + ico('chat') + 'WhatsApp<small>their phone</small></button>' +
            '<button class="platform" type="button" data-p="mailto">' + ico('out') + 'Mail app<small>on this device</small></button>' +
          '</div>' +
          '<h3 class="section-title">Private note</h3>' +
          '<div class="field" style="margin-bottom:8px"><textarea id="leadNote" rows="3" placeholder="Only you see this — budget, timing, who referred them…"></textarea></div>' +
          '<button class="btn btn-sm" type="button" id="noteSave" disabled>Save note</button>' +
        '</div>';

      const subject = $('#mailSubject', main);
      const bodyEl = $('#mailBody', main);
      const note = $('#leadNote', main);
      const noteBtn = $('#noteSave', main);
      note.value = l.note || '';

      function applyTemplate(i) {
        const t = templates[i];
        $$('.tmpl', main).forEach((b) => b.classList.toggle('is-on', +b.dataset.t === i));
        if (!t) return;
        subject.value = fill(t.subject, l);
        bodyEl.value = fill(t.body, l);
      }
      applyTemplate(0);
      $$('.tmpl', main).forEach((b) => b.addEventListener('click', () => applyTemplate(+b.dataset.t)));

      $$('.platform', main).forEach((b) => b.addEventListener('click', () => {
        const to = encodeURIComponent(l.email), su = encodeURIComponent(subject.value), bo = encodeURIComponent(bodyEl.value);
        let url;
        switch (b.dataset.p) {
          case 'gmail': url = 'https://mail.google.com/mail/?view=cm&fs=1&to=' + to + '&su=' + su + '&body=' + bo; break;
          case 'outlook': url = 'https://outlook.live.com/mail/0/deeplink/compose?to=' + to + '&subject=' + su + '&body=' + bo; break;
          case 'whatsapp': {
            let d = (l.phone || '').replace(/\D+/g, '');
            if (d.length === 10) d = '91' + d;
            url = 'https://wa.me/' + d + '?text=' + bo;
            break;
          }
          default: url = 'mailto:' + to + '?subject=' + su + '&body=' + bo;
        }
        if (b.dataset.p === 'mailto') location.href = url;
        else window.open(url, '_blank', 'noopener');
        toast('Draft opened — press send there when it reads right', 'ok');
      }));

      note.addEventListener('input', () => { noteBtn.disabled = note.value === noteSaved; });
      noteBtn.addEventListener('click', () => saveNote().catch(() => {}));

      $$('[data-status]', main).forEach((b) => b.addEventListener('click', () => setStatus(b.dataset.status)));
      const back = $('[data-back]', main);
      if (back) back.addEventListener('click', async () => {
        if (!(await settleNote())) return;
        inbox.classList.remove('is-reading');
        current = null;
        renderList();
      });

      renderList();

      if (l.unseen) {
        try {
          const d = await post('api/leads.php', { action: 'seen', id: l.id });
          l.unseen = false;
          paintCounts(d);
          lastUnseen = d.unseen;
          const n = $('.notif-item[href$="open=' + l.id + '"]');
          if (n) n.remove();
          renderList();
        } catch (e) { /* reading it still worked */ }
      }
    }

    async function saveNote() {
      if (!current) return;
      const note = $('#leadNote', main);
      const btn = $('#noteSave', main);
      busy(btn, true, 'Saving…');
      try {
        await post('api/leads.php', { action: 'note', id: current.id, note: note.value });
        current.note = note.value;
        noteSaved = note.value;
        toast('Note saved', 'ok');
      } catch (e) { toast(e.message, 'bad'); throw e; }
      finally { busy(btn, false); btn.disabled = note.value === noteSaved; }
    }

    const noteDirty = () => { const n = $('#leadNote', main); return !!(current && n && n.value !== noteSaved); };

    async function settleNote() {
      if (!noteDirty()) return true;
      const c = await askUnsaved('the private note', true);
      if (c === 'keep') return false;
      if (c === 'save') { try { await saveNote(); } catch (e) { return false; } }
      return true;
    }

    guard({ isDirty: noteDirty, save: saveNote, discard: () => {}, what: 'the private note', isPrivate: true });

    async function setStatus(status) {
      if (!current) return;
      try {
        const d = await post('api/leads.php', { action: 'status', id: current.id, status });
        current.status = status;
        current.unseen = false;
        paintCounts(d);
        toast(status === 'replied' ? 'Marked as replied' : status === 'archived' ? 'Archived' : 'Moved back to the inbox', 'ok');
        const id = current.id;
        current = null;
        await open(id);
      } catch (e) { toast(e.message, 'bad'); }
    }

    initSeg($('#leadFilter'), async (b) => {
      filter = b.dataset.filter;
      renderList();
      return true;
    });

    document.addEventListener('sbx:all-seen', () => { all.forEach((l) => { l.unseen = false; }); renderList(); });

    renderList();
    if (window.SBX_OPEN_LEAD) open(window.SBX_OPEN_LEAD);
  })();

  /* ---------------- ANALYTICS ---------------- */
  const fmt = (n) => (n >= 10000 ? (n / 1000).toFixed(n >= 100000 ? 0 : 1).replace(/\.0$/, '') + 'K' : Number(n).toLocaleString('en-IN'));
  const fmtTime = (s) => (s >= 60 ? Math.floor(s / 60) + '<small>m</small> ' + (s % 60) + '<small>s</small>' : s + '<small>s</small>');

  function delta(now, prev, label) {
    if (!prev) return now ? '<span class="kpi-delta up">New <span>vs ' + label + '</span></span>' : '<span class="kpi-delta"><span>No visits ' + label + '</span></span>';
    const p = Math.round(((now - prev) / prev) * 100);
    if (!p) return '<span class="kpi-delta">0% <span>vs ' + label + '</span></span>';
    return '<span class="kpi-delta ' + (p > 0 ? 'up' : 'down') + '">' + (p > 0 ? '↑ ' : '↓ ') + Math.abs(p) + '% <span>vs ' + label + '</span></span>';
  }

  // four whole-number gridlines: people come in ones, never 12.5s
  function niceMax(v) {
    const raw = Math.max(1, v / 4);
    const mag = Math.pow(10, Math.floor(Math.log10(raw)));
    const steps = [1, 2, 2.5, 3, 4, 5, 7.5, 10].map((k) => k * mag).filter((k) => Number.isInteger(k));
    const step = steps.find((k) => k >= raw) || 10 * mag;
    return step * 4;
  }

  /** Visitors over time: one line, an area wash, a crosshair and a tooltip. */
  function lineChart(host, points, opts) {
    host.innerHTML = '';
    const total = points.reduce((s, p) => s + p.visitors, 0);
    if (!total) {
      host.innerHTML = '<div class="chart-empty">No visits in this period yet.<br>They appear here within a minute of someone opening the site.</div>';
      return;
    }
    const W = host.clientWidth || 600, H = host.clientHeight || 260;
    const pad = { l: 34, r: 12, t: 12, b: 26 };
    const iw = W - pad.l - pad.r, ih = H - pad.t - pad.b;
    const max = niceMax(Math.max.apply(null, points.map((p) => p.visitors)));
    const n = points.length;
    const x = (i) => pad.l + (n === 1 ? iw / 2 : (i / (n - 1)) * iw);
    const y = (v) => pad.t + ih - (v / max) * ih;

    let svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="Visitors per ' + (opts.hourly ? 'hour' : 'day') + '">';
    for (let k = 0; k <= 4; k++) {
      const v = (max / 4) * k, yy = y(v);
      svg += '<line class="grid-line" x1="' + pad.l + '" x2="' + (W - pad.r) + '" y1="' + yy + '" y2="' + yy + '"/>';
      svg += '<text class="axis-txt" x="' + (pad.l - 8) + '" y="' + (yy + 4) + '" text-anchor="end">' + fmt(Math.round(v * 10) / 10) + '</text>';
    }
    const every = Math.max(1, Math.ceil(n / Math.max(2, Math.floor(iw / 70))));
    points.forEach((p, i) => {
      if (i % every && i !== n - 1) return;
      if (i !== n - 1 && n - 1 - i < every * 0.6) return;
      svg += '<text class="axis-txt" x="' + x(i) + '" y="' + (H - 6) + '" text-anchor="' + (i === 0 ? 'start' : i === n - 1 ? 'end' : 'middle') + '">' + esc(p.label) + '</text>';
    });
    const line = points.map((p, i) => (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(p.visitors).toFixed(1)).join(' ');
    const area = line + ' L' + x(n - 1).toFixed(1) + ' ' + y(0) + ' L' + x(0).toFixed(1) + ' ' + y(0) + ' Z';
    svg += '<path class="area" d="' + area + '"/>';
    svg += '<path class="line" d="' + line + '" pathLength="1"/>';
    svg += '<line class="xhair" y1="' + pad.t + '" y2="' + (pad.t + ih) + '" style="display:none"/>';
    svg += '<circle class="dot" r="4.5" style="display:none"/>';
    svg += '<circle class="dot dot-end" r="4.5" cx="' + x(n - 1) + '" cy="' + y(points[n - 1].visitors) + '"/>';
    svg += '<rect x="' + pad.l + '" y="0" width="' + iw + '" height="' + H + '" fill="transparent" data-hit/>';
    svg += '</svg>';
    host.innerHTML = svg + '<div class="chart-tip"></div>';

    const s = $('svg', host), tip = $('.chart-tip', host), xh = $('.xhair', s), dot = $('.dot:not(.dot-end)', s), hit = $('[data-hit]', s);
    const path = $('.line', s);
    if (!reduced && path.animate) path.animate([{ strokeDasharray: '1 1', strokeDashoffset: 1 }, { strokeDasharray: '1 1', strokeDashoffset: 0 }], { duration: 900, easing: 'cubic-bezier(.22,1,.36,1)' });

    function at(clientX) {
      const r = s.getBoundingClientRect();
      const px = (clientX - r.left) * (W / r.width);
      const i = Math.max(0, Math.min(n - 1, Math.round(((px - pad.l) / iw) * (n - 1))));
      const p = points[i];
      xh.setAttribute('x1', x(i)); xh.setAttribute('x2', x(i)); xh.style.display = '';
      dot.setAttribute('cx', x(i)); dot.setAttribute('cy', y(p.visitors)); dot.style.display = '';
      tip.innerHTML = '<b>' + esc(opts.hourly ? p.label : p.key ? new Date(p.key + 'T12:00:00').toLocaleDateString('en-IN', { weekday: 'short', day: 'numeric', month: 'short' }) : p.label) + '</b>' +
        '<div class="r"><span>Visitors</span><strong>' + fmt(p.visitors) + '</strong></div>' +
        '<div class="r"><span>Page views</span><strong>' + fmt(p.views) + '</strong></div>';
      const tx = (x(i) / W) * r.width, ty = (y(p.visitors) / H) * r.height;
      const tw = tip.offsetWidth || 140;
      let left = tx + 14;
      if (left + tw > r.width) left = tx - tw - 14;
      tip.style.transform = 'translate(' + left + 'px,' + Math.max(0, ty - 30) + 'px)';
      tip.classList.add('is-on');
    }
    hit.addEventListener('pointermove', (e) => at(e.clientX));
    hit.addEventListener('pointerdown', (e) => at(e.clientX));
    hit.addEventListener('pointerleave', () => { tip.classList.remove('is-on'); xh.style.display = 'none'; dot.style.display = 'none'; });
  }

  const pct = (v, total) => { const p = (v / total) * 100; return v > 0 && p < 1 ? '<1%' : Math.round(p) + '%'; };

  function barList(host, rows, opts) {
    opts = opts || {};
    if (!rows.length) { host.innerHTML = '<div class="bl-empty">' + esc(opts.empty || 'Nothing yet for this period.') + '</div>'; return; }
    const max = Math.max.apply(null, rows.map((r) => r.value)) || 1;
    const total = opts.total || rows.reduce((s, r) => s + r.value, 0) || 1;
    host.innerHTML = '<div class="barlist">' +
      (opts.head ? '<div class="bl-head"><span>' + esc(opts.head[0]) + '</span><span>' + esc(opts.head[1]) + '</span></div>' : '') +
      rows.map((r, i) =>
        '<div class="bl-row" title="' + esc(r.title || '') + '">' +
          '<span class="bl-fill" style="width:' + ((r.value / max) * 100).toFixed(1) + '%;animation-delay:' + (i * 40) + 'ms"></span>' +
          '<span class="bl-name">' + (r.code ? '<span class="cc">' + esc(r.code) + '</span>' : '') + esc(r.name) + (r.sub ? ' <small>' + esc(r.sub) + '</small>' : '') + '</span>' +
          '<span class="bl-val">' + fmt(r.value) + (opts.pct === false ? '' : '<span>' + pct(r.value, total) + '</span>') + '</span>' +
        '</div>').join('') + '</div>';
  }

  const SECTION_NAMES = {
    header: 'top bar', menu: 'phone menu', divisions: 'divisions', process: 'process', work: 'work',
    services: 'services', studio: 'studio', contact: 'contact', faq: 'questions', footer: 'footer'
  };
  const KIND_NAMES = {
    cta: 'Button', phone: 'Call', email: 'Email', whatsapp: 'WhatsApp', social: 'Social profile',
    nav: 'Menu link', form: 'Form button', link: 'Link', filter: 'Work filter', faq: 'Question opened', button: 'Button'
  };

  function renderReport(R) {
    const T = R.totals, P = R.previous;
    const label = { today: 'yesterday', '7d': 'previous 7 days', '30d': 'previous 30 days', '90d': 'previous 90 days' }[R.range.key] || 'before';
    const conv = T.visitors ? ((T.leads / T.visitors) * 100) : 0;

    $('#anKpis').innerHTML =
      '<div class="kpi"><span class="kpi-label">Visitors</span><span class="kpi-value">' + fmt(T.visitors) + '</span>' + delta(T.visitors, P.visitors, label) + '</div>' +
      '<div class="kpi"><span class="kpi-label">Clicks</span><span class="kpi-value">' + fmt(T.clicks) + '</span>' + delta(T.clicks, P.clicks, label) + '</div>' +
      '<div class="kpi"><span class="kpi-label">Enquiries</span><span class="kpi-value">' + fmt(T.leads) + (T.visitors ? '<small class="rate">' + conv.toFixed(conv < 10 ? 1 : 0) + '% of visitors</small>' : '') + '</span>' + delta(T.leads, P.leads, label) + '</div>' +
      '<div class="kpi"><span class="kpi-label">Average time on site</span><span class="kpi-value">' + fmtTime(T.seconds) + '</span>' + (P.seconds ? delta(T.seconds, P.seconds, label) : '<span class="kpi-delta"><span>per visit</span></span>') + '</div>';

    $('#anChartSub').textContent = R.range.label + ' · ' + (R.range.buckets === 'hour' ? 'by hour' : 'by day') + ' · ' + fmt(T.pageviews) + ' page views';
    lineChart($('#anChart'), R.series, { hourly: R.range.buckets === 'hour' });

    const visitors = T.visitors || 1;
    barList($('#anSources'), R.sources.map((s) => ({ name: s.key, value: s.visitors })), { total: visitors, head: ['Source', 'Visitors'], empty: 'No visits yet for this period.' });
    barList($('#anCountries'), R.countries.map((c) => ({ name: c.name, code: c.key || '··', value: c.visitors })), { total: visitors, head: ['Country', 'Visitors'] });
    barList($('#anClicks'), R.clicks.map((c) => ({
      name: c.label,
      sub: (KIND_NAMES[c.kind] || '') ,
      value: c.clicks,
      title: c.target
    })), { head: ['What was clicked', 'Clicks'], empty: 'No clicks yet for this period.' });
    barList($('#anBrowsers'), R.browsers.map((b) => ({ name: b.key, value: b.visitors })), { total: visitors });
    barList($('#anCampaigns'), R.campaigns.map((c) => ({ name: c.key, value: c.visitors })), {
      total: visitors,
      empty: 'None yet. Add ?utm_campaign=name to a link you share (for example in an Instagram bio) and its visits are counted here.'
    });

    const K = R.kinds || {};
    const kinds = [['phone', 'Calls'], ['whatsapp', 'WhatsApp'], ['email', 'Emails'], ['form', 'Form buttons'], ['cta', 'Buttons'], ['social', 'Social profiles']];
    $('#anKinds').innerHTML = kinds.map((k) => '<div class="kind"><b>' + fmt(K[k[0]] || 0) + '</b><span>' + k[1] + '</span></div>').join('');

    const devNames = { desktop: 'Computer', mobile: 'Phone', tablet: 'Tablet' };
    const order = ['mobile', 'desktop', 'tablet'];
    const dev = order.map((k) => ({ k, v: (R.devices.find((d) => d.key === k) || {}).visitors || 0 }));
    const dsum = dev.reduce((s, d) => s + d.v, 0);
    $('#anDevices').innerHTML = dsum
      ? '<div class="stack" role="img" aria-label="Devices">' + dev.map((d) => '<i style="flex-grow:' + d.v + '"></i>').join('') + '</div>' +
        '<div class="legend">' + dev.map((d, i) => '<div class="legend-row"><span class="sw" style="background:var(--viz-' + (i + 1) + ')"></span>' + devNames[d.k] +
          '<span class="n">' + fmt(d.v) + '</span><span class="p">' + Math.round((d.v / dsum) * 100) + '%</span></div>').join('') + '</div>'
      : '<div class="bl-empty">Nothing yet for this period.</div>';

    const D = R.scroll;
    $('#anDepth').innerHTML = D.visits
      ? '<div class="depth">' + D.steps.map((s) => '<div class="depth-row"><span>' + esc(s.label) + '</span><div class="progress"><i style="width:' + s.pct + '%"></i></div><b>' + s.pct + '%</b></div>').join('') + '</div>'
      : '<div class="bl-empty">Nothing yet for this period.</div>';

    const live = $('#anLive');
    live.classList.toggle('is-zero', !R.live);
    $('span', live).textContent = R.live + (R.live === 1 ? ' person' : ' people') + ' on the site now';
  }

  if (page === 'analytics' && window.SBX_REPORT) (function analytics() {
    let R = window.SBX_REPORT;
    let range = R.range.key;
    renderReport(R);

    initSeg($('#anRange'), async (b) => {
      range = b.dataset.range;
      $('#anKpis').style.opacity = '.5';
      try {
        R = (await getJson('api/analytics.php?range=' + range)).report;
        renderReport(R);
        history.replaceState(null, '', '?range=' + range);
      } catch (e) { toast(e.message, 'bad'); }
      $('#anKpis').style.opacity = '';
      return true;
    });

    let rt;
    addEventListener('resize', () => { clearTimeout(rt); rt = setTimeout(() => lineChart($('#anChart'), R.series, { hourly: R.range.buckets === 'hour' }), 150); });

    // quietly refresh every minute while the page is open
    setInterval(async () => {
      if (document.hidden) return;
      try { R = (await getJson('api/analytics.php?range=' + range)).report; renderReport(R); } catch (e) {}
    }, 60000);

    const ignore = $('#anIgnore');
    if (ignore) ignore.addEventListener('click', () => toast('The site opened in a new tab — visits from this browser are no longer counted', 'ok'));
  })();

  /* ---------------- OVERVIEW ---------------- */
  $$('svg[data-spark]').forEach((svg) => {
    let v;
    try { v = JSON.parse(svg.dataset.spark); } catch (e) { return; }
    if (!v || v.length < 2) return;
    const max = Math.max.apply(null, v) || 1;
    const pts = v.map((n, i) => [(i / (v.length - 1)) * 200, 31 - (n / max) * 28]);
    const d = pts.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join(' ');
    svg.innerHTML =
      '<path d="' + d + ' L200 34 L0 34 Z" fill="var(--viz-1)" opacity=".1"/>' +
      '<path d="' + d + '" fill="none" stroke="var(--viz-1)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>';
  });

  /* ---------------- ACCOUNT ---------------- */
  const pwForm = $('#pwForm');
  if (pwForm) {
    let submitting = false;
    pwForm.addEventListener('submit', () => { submitting = true; });
    guard({
      isDirty: () => !submitting && $$('input[type=password]', pwForm).some((i) => i.value !== ''),
      save: () => { pwForm.requestSubmit(); return new Promise(() => {}); },
      discard: () => { $$('input[type=password]', pwForm).forEach((i) => { i.value = ''; }); },
      what: 'your password',
      isPrivate: true
    });
  }
})();
