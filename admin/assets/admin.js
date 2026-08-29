/* =========================================================
   SMASHBOX — STUDIO DESK
   One file. No framework, no build step: whoever picks this
   up next can open it and read it top to bottom.
   ========================================================= */

(function () {
  'use strict';

  var CFG = window.SBX || {};
  var $  = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* -------------------------------------------------------
     Toast
     ------------------------------------------------------- */
  var toastEl = $('#toast');
  var toastTimer;

  function toast(msg, bad) {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.classList.toggle('is-bad', !!bad);
    toastEl.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('is-on'); }, 3600);
  }

  /* -------------------------------------------------------
     Fetch helpers
     ------------------------------------------------------- */
  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': CFG.csrf
      },
      body: JSON.stringify(body)
    }).then(readJson);
  }

  function readJson(res) {
    return res.json().catch(function () {
      throw new Error('The server sent something unreadable.');
    }).then(function (data) {
      if (!res.ok || !data.ok) {
        throw new Error(data.error || 'That did not work.');
      }
      return data;
    });
  }

  /* -------------------------------------------------------
     Drawer plumbing — shared by images and leads
     ------------------------------------------------------- */
  var scrim = $('#scrim');
  var openDrawer = null;
  var lastFocus = null;

  function drawerOpen(el) {
    lastFocus = document.activeElement;
    openDrawer = el;
    el.classList.add('is-open');
    if (scrim) scrim.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    var first = el.querySelector('input, textarea, button');
    if (first) setTimeout(function () { first.focus(); }, 60);
  }

  function drawerClose() {
    if (!openDrawer) return;
    openDrawer.classList.remove('is-open');
    if (scrim) scrim.classList.remove('is-open');
    document.body.style.overflow = '';
    openDrawer = null;
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  if (scrim) scrim.addEventListener('click', drawerClose);

  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-close]')) drawerClose();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (openDrawer) { drawerClose(); return; }
    if (notif && notif.classList.contains('is-open')) closeNotif();
  });

  // keep tab focus inside an open drawer
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Tab' || !openDrawer) return;
    var f = $$('a[href], button:not([disabled]), input, textarea, select', openDrawer)
      .filter(function (el) { return el.offsetParent !== null; });
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  /* -------------------------------------------------------
     Notification centre
     ------------------------------------------------------- */
  var bell = $('#bell');
  var notif = $('#notif');
  var bellDot = $('#bellDot');
  var notifList = $('#notifList');
  var lastUnseen = bellDot ? parseInt(bellDot.textContent, 10) || 0 : 0;

  function closeNotif() {
    if (!notif) return;
    notif.classList.remove('is-open');
    bell.setAttribute('aria-expanded', 'false');
  }

  if (bell && notif) {
    bell.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = notif.classList.toggle('is-open');
      bell.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
      if (!notif.contains(e.target) && !bell.contains(e.target)) closeNotif();
    });
  }

  var markAll = $('#markAllRead');
  if (markAll) {
    markAll.addEventListener('click', function () {
      post('api/leads.php', { action: 'seen_all' })
        .then(function (d) {
          paintCounts(d);
          if (notifList) {
            notifList.innerHTML =
              '<div class="notif-empty">No new leads.<br>New enquiries from the site land here.</div>';
          }
          $$('.lead.is-unseen').forEach(function (el) { el.classList.remove('is-unseen'); });
          toast('All caught up.');
        })
        .catch(function (err) { toast(err.message, true); });
    });
  }

  function paintCounts(d) {
    if (bellDot) {
      bellDot.textContent = d.unseen;
      bellDot.classList.toggle('is-zero', d.unseen === 0);
    }
    var leadCount = $('[data-count="leads"]');
    if (leadCount) {
      leadCount.textContent = d.new;
      leadCount.classList.toggle('is-zero', d.new === 0);
    }
  }

  function renderNotifs(items) {
    if (!notifList) return;
    if (!items.length) {
      notifList.innerHTML =
        '<div class="notif-empty">No new leads.<br>New enquiries from the site land here.</div>';
      return;
    }
    notifList.innerHTML = items.map(function (i) {
      return '<a class="notif-item" href="leads.php?open=' + i.id + '">' +
        '<span class="mark"></span><span class="body">' +
        '<b>' + esc(i.title) + '</b><p>' + esc(i.preview) + '</p>' +
        '<time>' + esc(i.when) + ' · ' + esc(i.source) + '</time>' +
        '</span></a>';
    }).join('');
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // poll every 45s so a lead that lands while the tab is open still rings
  function pollLeads() {
    if (document.hidden) return;
    fetch('api/leads.php?action=unseen', { headers: { 'X-CSRF-Token': CFG.csrf } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) return;
        if (d.unseen > lastUnseen && bell) {
          bell.classList.add('is-ringing');
          setTimeout(function () { bell.classList.remove('is-ringing'); }, 800);
          toast(d.unseen - lastUnseen === 1 ? 'A new lead just came in.' : (d.unseen - lastUnseen) + ' new leads just came in.');
        }
        lastUnseen = d.unseen;
        paintCounts(d);
        renderNotifs(d.items);
      })
      .catch(function () { /* offline is not an error worth shouting about */ });
  }

  if (bell) setInterval(pollLeads, 45000);

  /* =======================================================
     IMAGES
     ======================================================= */
  var imgDrawer = $('#imgDrawer');

  if (imgDrawer) {
    var current = null;
    var preview   = $('#imgPreview');
    var dropZone  = $('#imgDrop');
    var fileInput = $('#imgFile');
    var altInput  = $('#imgAlt');
    var urlInput  = $('#imgUrl');
    var saveBtn   = $('#imgSave');
    var resetBtn  = $('#imgReset');
    var pendingValue = null;

    $$('.frame').forEach(function (frame) {
      frame.addEventListener('click', function () {
        current = frame;
        pendingValue = null;

        $('#imgDrawerSub').textContent =
          frame.dataset.section + ' · ' + frame.dataset.frame +
          (frame.dataset.kind === 'hover' ? ' · hover frame' : '');

        preview.src = toPreview(frame.dataset.value);
        altInput.value = frame.dataset.alt || '';
        urlInput.value = '';
        altInput.parentElement.hidden = frame.dataset.kind === 'hover';
        resetBtn.hidden = !frame.classList.contains('is-changed');

        drawerOpen(imgDrawer);
      });
    });

    function toPreview(v) {
      if (!v) return '';
      if (/^(https?:)?\/\//i.test(v) || v.indexOf('data:') === 0) return v;
      return '../' + v.replace(/^\//, '');
    }

    /* ---- picking a file ---- */
    dropZone.addEventListener('click', function (e) {
      if (e.target !== fileInput) { e.preventDefault(); fileInput.click(); }
    });

    ['dragenter', 'dragover'].forEach(function (ev) {
      dropZone.addEventListener(ev, function (e) {
        e.preventDefault();
        dropZone.classList.add('is-over');
      });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      dropZone.addEventListener(ev, function (e) {
        e.preventDefault();
        dropZone.classList.remove('is-over');
      });
    });
    dropZone.addEventListener('drop', function (e) {
      var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
      if (f) upload(f);
    });

    fileInput.addEventListener('change', function () {
      if (fileInput.files && fileInput.files[0]) upload(fileInput.files[0]);
    });

    urlInput.addEventListener('input', function () {
      var v = urlInput.value.trim();
      if (/^https?:\/\//i.test(v)) {
        pendingValue = v;
        preview.src = v;
      }
    });

    function upload(file) {
      if (file.size > 10 * 1024 * 1024) {
        toast('That file is bigger than the 10 MB limit.', true);
        return;
      }

      var fd = new FormData();
      fd.append('file', file);

      dropZone.querySelector('b').textContent = 'Uploading…';

      fetch('api/upload.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': CFG.csrf },
        body: fd
      })
        .then(readJson)
        .then(function (d) {
          pendingValue = d.path;
          preview.src = d.url;
          urlInput.value = '';
          dropZone.querySelector('b').textContent = 'Uploaded — ' + d.width + '×' + d.height + ', ' + d.size;
        })
        .catch(function (err) {
          toast(err.message, true);
          dropZone.querySelector('b').textContent = 'Drop a picture here, or click to choose';
        });
    }

    /* ---- saving ---- */
    saveBtn.addEventListener('click', function () {
      if (!current) return;

      var value = pendingValue || current.dataset.value;
      if (!value) { toast('Choose a picture first.', true); return; }

      saveBtn.disabled = true;
      saveBtn.textContent = 'Publishing…';

      post('api/content.php', {
        id: current.dataset.frame,
        value: value,
        alt: altInput.value
      })
        .then(function (d) {
          current.dataset.value = d.value;
          current.dataset.alt = d.alt || '';
          current.querySelector('img').src = toPreview(d.value);
          markChanged(current, true);
          drawerClose();
          toast('Published. The site is updated.');
        })
        .catch(function (err) { toast(err.message, true); })
        .finally(function () {
          saveBtn.disabled = false;
          saveBtn.textContent = 'Save & publish';
        });
    });

    resetBtn.addEventListener('click', function () {
      if (!current) return;
      resetBtn.disabled = true;

      post('api/content.php', { id: current.dataset.frame, reset: true })
        .then(function (d) {
          current.dataset.value = d.value;
          current.dataset.alt = d.alt || '';
          current.querySelector('img').src = toPreview(d.value);
          markChanged(current, false);
          drawerClose();
          toast('Original restored.');
        })
        .catch(function (err) { toast(err.message, true); })
        .finally(function () { resetBtn.disabled = false; });
    });

    function markChanged(frame, on) {
      frame.classList.toggle('is-changed', on);
      var well = frame.querySelector('.frame-well');
      var badge = frame.querySelector('.frame-badge');
      if (on && !badge) {
        badge = document.createElement('span');
        badge.className = 'frame-badge';
        badge.innerHTML = '<span class="pill pill-changed">Replaced</span>';
        well.insertBefore(badge, well.firstChild);
      } else if (!on && badge) {
        badge.remove();
      }
    }
  }

  /* =======================================================
     CLIENTS — the marquee list
     ======================================================= */
  var clientList = $('#clientList');

  if (clientList) {
    var clientEmpty = $('#clientEmpty');
    var clientCount = $('#clientCount');
    var addForm     = $('#clientAdd');
    var newInput    = $('#clientNew');
    var saveClients = $('#clientSave');
    var resetBtn2   = $('#clientReset');
    var dragRow     = null;

    /* ---- one row of markup, matching what clients.php renders ---- */
    function clientRow(name) {
      var li = document.createElement('li');
      li.className = 'client-row';
      li.draggable = true;
      li.innerHTML =
        '<span class="client-grip" aria-hidden="true"><span></span><span></span><span></span></span>' +
        '<span class="client-no mono"></span>' +
        '<input type="text" class="client-name" maxlength="60" aria-label="Client name">' +
        '<span class="client-move">' +
          '<button class="btn btn-sm btn-quiet" type="button" data-up aria-label="Move up">&uarr;</button>' +
          '<button class="btn btn-sm btn-quiet" type="button" data-down aria-label="Move down">&darr;</button>' +
        '</span>' +
        '<button class="btn btn-sm btn-danger" type="button" data-remove>Remove</button>';
      li.querySelector('.client-name').value = name;
      wireRow(li);
      return li;
    }

    function rows() {
      return $$('.client-row', clientList);
    }

    function names() {
      return rows()
        .map(function (r) { return r.querySelector('.client-name').value.trim(); })
        .filter(function (v) { return v !== ''; });
    }

    function renumber() {
      var all = rows();
      all.forEach(function (r, i) { r.querySelector('.client-no').textContent = i + 1; });
      if (clientEmpty) clientEmpty.hidden = all.length > 0;
      var n = names().length;
      if (clientCount) clientCount.textContent = n + (n === 1 ? ' name' : ' names');
      var badge = $('[data-client-n]');
      if (badge) badge.textContent = n;
    }

    function wireRow(li) {
      li.querySelector('[data-remove]').addEventListener('click', function () {
        li.remove();
        renumber();
      });

      li.querySelector('[data-up]').addEventListener('click', function () {
        var prev = li.previousElementSibling;
        if (prev) { clientList.insertBefore(li, prev); renumber(); }
      });

      li.querySelector('[data-down]').addEventListener('click', function () {
        var next = li.nextElementSibling;
        if (next) { clientList.insertBefore(next, li); renumber(); }
      });

      li.querySelector('.client-name').addEventListener('input', renumber);

      li.addEventListener('dragstart', function (e) {
        dragRow = li;
        li.classList.add('is-dragging');
        if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move';
      });

      li.addEventListener('dragend', function () {
        dragRow = null;
        li.classList.remove('is-dragging');
        rows().forEach(function (r) { r.classList.remove('is-over'); });
        renumber();
      });

      li.addEventListener('dragover', function (e) {
        if (!dragRow || dragRow === li) return;
        e.preventDefault();
        li.classList.add('is-over');
      });

      li.addEventListener('dragleave', function () { li.classList.remove('is-over'); });

      li.addEventListener('drop', function (e) {
        if (!dragRow || dragRow === li) return;
        e.preventDefault();
        li.classList.remove('is-over');
        var after = li.compareDocumentPosition(dragRow) & Node.DOCUMENT_POSITION_PRECEDING;
        clientList.insertBefore(dragRow, after ? li.nextSibling : li);
        renumber();
      });
    }

    rows().forEach(wireRow);

    /* ---- adding ---- */
    addForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var v = newInput.value.trim().replace(/\s+/g, ' ');
      if (!v) return;

      var clash = names().some(function (n) { return n.toLowerCase() === v.toLowerCase(); });
      if (clash) {
        toast('“' + v + '” is already on the strip.', true);
        return;
      }

      clientList.appendChild(clientRow(v));
      newInput.value = '';
      newInput.focus();
      renumber();
    });

    /* ---- saving ---- */
    saveClients.addEventListener('click', function () {
      var list = names();

      if (!list.length && !window.confirm('Save an empty list? The client strip comes off the site until you add a name back.')) {
        return;
      }

      saveClients.disabled = true;
      saveClients.textContent = 'Publishing…';

      post('api/clients.php', { names: list })
        .then(function (d) {
          paintClients(d.names);
          if (resetBtn2) resetBtn2.hidden = false;
          toast('Published. The strip is updated.');
        })
        .catch(function (err) { toast(err.message, true); })
        .finally(function () {
          saveClients.disabled = false;
          saveClients.innerHTML = 'Save &amp; publish';
        });
    });

    if (resetBtn2) {
      resetBtn2.addEventListener('click', function () {
        resetBtn2.disabled = true;

        post('api/clients.php', { reset: true })
          .then(function (d) {
            paintClients(d.names);
            resetBtn2.hidden = true;
            toast('Original list restored.');
          })
          .catch(function (err) { toast(err.message, true); })
          .finally(function () { resetBtn2.disabled = false; });
      });
    }

    /* ---- redraw from what the server actually stored ---- */
    function paintClients(list) {
      clientList.innerHTML = '';
      (list || []).forEach(function (n) { clientList.appendChild(clientRow(n)); });
      renumber();
    }

    renumber();
  }

  /* =======================================================
     TEXT
     ======================================================= */
  $$('.trow').forEach(function (row) {
    var btn = row.querySelector('[data-edit]');
    if (!btn) return;

    btn.addEventListener('click', function () { startEdit(row); });
    row.querySelector('[data-value]').addEventListener('dblclick', function () { startEdit(row); });
  });

  function startEdit(row) {
    if (row.classList.contains('is-editing')) return;

    var body = row.querySelector('.trow-body');
    var valueEl = row.querySelector('[data-value]');
    var original = valueEl.innerHTML.trim();

    row.classList.add('is-editing');

    var wrap = document.createElement('div');
    wrap.className = 'editor';
    wrap.innerHTML =
      '<textarea spellcheck="true"></textarea>' +
      '<div class="editor-bar">' +
        '<span class="hint">Enter saves · Esc cancels</span>' +
        (row.classList.contains('is-changed')
          ? '<button class="btn btn-sm btn-danger" data-restore type="button">Restore original</button>'
          : '') +
        '<button class="btn btn-sm" data-cancel type="button">Cancel</button>' +
        '<button class="btn btn-sm btn-primary" data-save type="button">Save &amp; publish</button>' +
      '</div>';

    var ta = wrap.querySelector('textarea');
    ta.value = original;

    body.hidden = true;
    row.querySelector('.trow-actions').hidden = true;
    body.parentNode.insertBefore(wrap, body.nextSibling);

    ta.focus();
    ta.setSelectionRange(ta.value.length, ta.value.length);
    autoGrow(ta);
    ta.addEventListener('input', function () { autoGrow(ta); });

    function finish() {
      wrap.remove();
      body.hidden = false;
      row.querySelector('.trow-actions').hidden = false;
      row.classList.remove('is-editing');
    }

    wrap.querySelector('[data-cancel]').addEventListener('click', finish);

    wrap.querySelector('[data-save]').addEventListener('click', save);

    var restore = wrap.querySelector('[data-restore]');
    if (restore) {
      restore.addEventListener('click', function () {
        restore.disabled = true;
        post('api/content.php', { id: row.dataset.row, reset: true })
          .then(function (d) {
            valueEl.innerHTML = d.value;
            row.classList.remove('is-changed');
            var tag = row.querySelector('.trow-tag');
            var edited = tag.querySelector('span');
            if (edited) { edited.remove(); tag.querySelector('br') && tag.querySelector('br').remove(); }
            finish();
            toast('Original restored.');
          })
          .catch(function (err) { toast(err.message, true); restore.disabled = false; });
      });
    }

    ta.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); finish(); }
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); save(); }
    });

    function save() {
      var next = ta.value.trim();
      if (next === original) { finish(); return; }

      var saveBtn = wrap.querySelector('[data-save]');
      saveBtn.disabled = true;
      saveBtn.textContent = 'Publishing…';

      post('api/content.php', { id: row.dataset.row, value: next })
        .then(function (d) {
          valueEl.innerHTML = d.value;
          if (!row.classList.contains('is-changed')) {
            row.classList.add('is-changed');
            var tag = row.querySelector('.trow-tag');
            tag.insertAdjacentHTML('beforeend', '<br><span style="color:var(--warn)">edited</span>');
          }
          finish();
          toast('Published. The site is updated.');
        })
        .catch(function (err) {
          toast(err.message, true);
          saveBtn.disabled = false;
          saveBtn.textContent = 'Save & publish';
        });
    }
  }

  function autoGrow(ta) {
    ta.style.height = 'auto';
    ta.style.height = Math.max(88, ta.scrollHeight + 2) + 'px';
  }

  /* =======================================================
     LEADS
     ======================================================= */
  var leadDrawer = $('#leadDrawer');

  if (leadDrawer) {
    var lead = null;
    var subjectEl = $('#mailSubject');
    var bodyEl = $('#mailBody');
    var noteEl = $('#leadNote');

    $$('.lead').forEach(function (el) {
      el.addEventListener('click', function () { openLead(el); });
    });

    function openLead(el) {
      lead = JSON.parse(el.dataset.json);
      lead._el = el;

      $('#leadDrawerTitle').textContent = lead.name || lead.email;
      $('#leadDrawerSub').textContent = lead.source + ' · lead #' + lead.id;

      $('#lfEmail').innerHTML = '<a href="mailto:' + esc(lead.email) + '">' + esc(lead.email) + '</a>';
      $('#lfName').textContent = lead.name || '—';
      $('#lfPhone').textContent = lead.phone || '—';
      $('#lfSource').textContent = lead.source;
      $('#lfWhen').textContent = new Date(lead.created_at.replace(' ', 'T')).toLocaleString();

      var mw = $('#lfMessageWrap');
      if (lead.message) {
        mw.hidden = false;
        $('#lfMessage').textContent = lead.message;
      } else {
        mw.hidden = true;
      }

      noteEl.value = lead.note || '';

      var firstTmpl = $('.tmpl');
      if (firstTmpl) applyTemplate(firstTmpl);

      drawerOpen(leadDrawer);

      // opening it counts as reading it
      if (el.classList.contains('is-unseen')) {
        post('api/leads.php', { action: 'seen', id: lead.id })
          .then(function (d) {
            el.classList.remove('is-unseen');
            paintCounts(d);
            var item = $('.notif-item[href$="open=' + lead.id + '"]');
            if (item) item.remove();
          })
          .catch(function () {});
      }
    }

    /* ---- templates ---- */
    function fill(s) {
      return String(s)
        .replace(/\{name\}/g, lead.name || 'there')
        .replace(/\{email\}/g, lead.email)
        .replace(/\{studio\}/g, CFG.studioName || 'Smashbox Productions');
    }

    function applyTemplate(btn) {
      $$('.tmpl').forEach(function (b) { b.classList.toggle('is-on', b === btn); });
      subjectEl.value = fill(btn.dataset.subject);
      bodyEl.value = fill(btn.dataset.body);
    }

    $$('.tmpl').forEach(function (btn) {
      btn.addEventListener('click', function () { applyTemplate(btn); });
    });

    /* ---- hand the draft to a mail app ---- */
    $$('.platform').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!lead) return;

        var to = encodeURIComponent(lead.email);
        var su = encodeURIComponent(subjectEl.value);
        var bo = encodeURIComponent(bodyEl.value);
        var url;

        switch (btn.dataset.platform) {
          case 'gmail':
            url = 'https://mail.google.com/mail/?view=cm&fs=1&to=' + to + '&su=' + su + '&body=' + bo;
            break;
          case 'outlook':
            url = 'https://outlook.live.com/mail/0/deeplink/compose?to=' + to +
                  '&subject=' + su + '&body=' + bo;
            break;
          case 'yahoo':
            url = 'https://compose.mail.yahoo.com/?to=' + to + '&subject=' + su + '&body=' + bo;
            break;
          default:
            url = 'mailto:' + to + '?subject=' + su + '&body=' + bo;
        }

        if (btn.dataset.platform === 'mailto') {
          window.location.href = url;
        } else {
          window.open(url, '_blank', 'noopener');
        }

        toast('Draft opened. Press send there when it reads right.');
      });
    });

    /* ---- status + note ---- */
    $('#leadReplied').addEventListener('click', function () {
      setStatus('replied', 'Marked as replied.');
    });

    $('#leadArchive').addEventListener('click', function () {
      setStatus('archived', 'Archived.');
    });

    function setStatus(status, msg) {
      if (!lead) return;
      post('api/leads.php', { action: 'status', id: lead.id, status: status })
        .then(function (d) {
          paintCounts(d);
          var pill = lead._el.querySelector('.pill');
          if (pill) {
            pill.className = 'pill pill-' + status;
            pill.textContent = status;
          }
          lead._el.classList.remove('is-unseen');
          drawerClose();
          toast(msg);
        })
        .catch(function (err) { toast(err.message, true); });
    }

    $('#leadSaveNote').addEventListener('click', function () {
      if (!lead) return;
      post('api/leads.php', { action: 'note', id: lead.id, note: noteEl.value })
        .then(function () {
          lead.note = noteEl.value;
          lead._el.dataset.json = JSON.stringify(
            Object.keys(lead).reduce(function (o, k) {
              if (k !== '_el') o[k] = lead[k];
              return o;
            }, {})
          );
          toast('Note saved.');
        })
        .catch(function (err) { toast(err.message, true); });
    });

    /* ---- deep link from a notification ---- */
    if (window.SBX_OPEN_LEAD) {
      var target = $('.lead[data-lead="' + window.SBX_OPEN_LEAD + '"]');
      if (target) {
        target.scrollIntoView({ block: 'center' });
        openLead(target);
      }
    }
  }
})();
