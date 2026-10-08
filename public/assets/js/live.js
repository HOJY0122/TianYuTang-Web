/**
 * live.js — realtime updates for any page (public, admin, system admin).
 *
 * Mark the parts of a page that show data:
 *     <div id="donationList" data-live="donations">…</div>
 *     <section id="regForm" data-live="events settings" data-live-mode="reload">…</section>
 *
 * Every few seconds the page asks /live whether those tables have
 * changed (see App\Core\Live). When one has:
 *   - normal parts are fetched fresh and swapped in place, with a short
 *     highlight, and a "live:swap" event so page scripts can re-attach;
 *   - data-live-mode="reload" parts (forms whose layout depends on the
 *     data, e.g. registration opening or closing) reload the page;
 *   - data-live-mode="form" parts (edit forms: event, donation, settings …)
 *     are compared field by field with fresh content and reload only when
 *     THEIR OWN values changed — another record changing does nothing.
 *     If someone is already editing, they are warned instead
 *     ("someone else changed this — tap to load the latest").
 * A part is never replaced while someone is typing in it or has filled
 * in a form inside it: a small "new information — tap to refresh" note
 * appears instead, and the swap happens once they are done.
 *
 * Checks pause while the tab is hidden and resume (with one immediate
 * check) when it comes back.
 *
 * <html data-live-quiet> (the public site): updates happen silently —
 * no note, no highlight, no "tap to refresh"; a part in use simply
 * waits until it is free. The admin side keeps the notices.
 */
(function () {
  'use strict';
  var regions = [].slice.call(document.querySelectorAll('[data-live][id]'));
  if (!regions.length || !window.fetch || !window.DOMParser) return;

  var BASE = (document.documentElement.getAttribute('data-base') || '');
  var QUIET = document.documentElement.hasAttribute('data-live-quiet');
  var EVERY = 4000;                       // ms between checks while the page is visible
  var topics = {};
  regions.forEach(function (r) { r.dataset.live.split(/\s+/).forEach(function (t) { if (t) topics[t] = 1; }); });
  var url = BASE + '/live?t=' + encodeURIComponent(Object.keys(topics).join(','));
  var known = null, timer = null, busy = false, pending = {};

  // ---- forms someone has started filling in are left alone ----
  var dirty = new WeakSet();
  function touched(e) { var f = e.target.form || (e.target.closest && e.target.closest('form')); if (f) dirty.add(f); }
  document.addEventListener('input', touched, true);
  document.addEventListener('change', touched, true);
  // Helper buttons inside a form (↺ default, quick amounts …) edit it too.
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('button[type="button"]');
    if (btn) touched({ target: btn });
  }, true);
  document.addEventListener('submit', function (e) { dirty.delete(e.target); }, true);
  function inUse(region) {
    var a = document.activeElement;
    // Swapping would lose the cursor, so a focused box counts — except in
    // edit forms (which reload), where only real edits count: a form that
    // opens with the cursor in its first box is not being edited yet.
    if (region.dataset.liveMode !== 'form' && a && region.contains(a) && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName)) return true;
    var forms = region.querySelectorAll('form');
    for (var i = 0; i < forms.length; i++) if (dirty.has(forms[i])) return true;
    if (region.tagName === 'FORM' && dirty.has(region)) return true;
    if (document.body.classList.contains('is-sorting')) return true;   // a drag in progress
    return region.querySelector('dialog[open]') !== null;
  }

  // ---- has an edit form's own data changed? ----
  // Compares the values the server sent then (defaultValue / defaultChecked,
  // which typing does not change) with the values it sends now.
  function controls(root) {
    return [].slice.call(root.querySelectorAll('input,textarea,select')).filter(function (c) {
      return c.name && c.name !== 'csrf_token' && c.type !== 'file' && c.type !== 'password' && c.name.charAt(0) !== '_';
    });
  }
  function sentNow(c) {
    if (c.type === 'checkbox' || c.type === 'radio') return c.hasAttribute('checked') ? '1' : '0';
    if (c.tagName === 'TEXTAREA') return c.textContent;
    if (c.tagName === 'SELECT') { var o = c.querySelector('option[selected]') || c.querySelector('option'); return o ? o.value : ''; }
    return c.getAttribute('value') || '';
  }
  function sentThen(c) {
    if (c.type === 'checkbox' || c.type === 'radio') return c.defaultChecked ? '1' : '0';
    if (c.tagName === 'TEXTAREA') return c.defaultValue;
    if (c.tagName === 'SELECT') {
      for (var i = 0; i < c.options.length; i++) if (c.options[i].defaultSelected) return c.options[i].value;
      return c.options.length ? c.options[0].value : '';
    }
    return c.defaultValue;
  }
  function formChanged(old, fresh) {
    var a = controls(old), b = controls(fresh);
    if (a.length !== b.length) return true;
    for (var i = 0; i < a.length; i++) {
      if (a[i].name !== b[i].name || sentThen(a[i]) !== sentNow(b[i])) return true;
    }
    return false;
  }

  function topicsOf(region) { return region.dataset.live.split(/\s+/); }

  // ---- small notices ----
  function toast(text) {
    if (QUIET) return;
    var t = document.createElement('div');
    t.className = 'live-toast';
    t.setAttribute('role', 'status');
    t.textContent = text;
    document.body.appendChild(t);
    setTimeout(function () { t.classList.add('out'); }, 1800);
    setTimeout(function () { t.remove(); }, 2300);
  }
  var note = null, conflict = false;
  function showNote(text) {
    if (QUIET) return;
    if (note) { if (text) { note.textContent = text; conflict = true; } return; }
    conflict = !!text;
    note = document.createElement('button');
    note.type = 'button';
    note.className = 'live-note' + (text ? ' warn' : '');
    note.textContent = text || '有新資料，點此更新 New information — tap to refresh';
    note.addEventListener('click', function () { location.reload(); });
    document.body.appendChild(note);
  }

  // ---- swapping ----
  function refresh(changed) {
    var affected = regions.filter(function (r) {
      return document.body.contains(r) && topicsOf(r).some(function (t) { return changed[t]; });
    });
    if (!affected.length) return Promise.resolve();

    var forms = affected.filter(function (r) { return r.dataset.liveMode === 'form'; });
    affected = affected.filter(function (r) { return r.dataset.liveMode !== 'form'; });
    var waiting = affected.filter(inUse);
    var reload = affected.filter(function (r) { return r.dataset.liveMode === 'reload' && !inUse(r); });
    // Parts with a fingerprint (live_sig_end in PHP) reload only if it changed: checked after the fetch.
    var signed = reload.filter(function (r) { return r.querySelector('[data-live-sig]'); });
    reload = reload.filter(function (r) { return !r.querySelector('[data-live-sig]'); });
    if (reload.length && !waiting.length) {
      try { sessionStorage.setItem('tyt-live-scroll', String(window.scrollY)); } catch (e) {}
      location.reload();
      return Promise.resolve();
    }
    forms = forms.concat(signed);
    waiting.forEach(function (r) { pending[r.id] = true; });
    if (waiting.length) showNote();

    var swap = affected.filter(function (r) { return !inUse(r) && r.dataset.liveMode !== 'reload'; });
    if (!swap.length && !forms.length) return Promise.resolve();
    return fetch(location.href, { credentials: 'same-origin', headers: { 'X-Live': '1' }, cache: 'no-store' })
      .then(function (res) { return res.ok ? res.text() : Promise.reject(res.status); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var swapped = 0;
        // Edit forms: act only when this form's own saved values changed.
        for (var i = 0; i < forms.length; i++) {
          var fresh = doc.getElementById(forms[i].id);
          if (!fresh) continue;
          var sig = forms[i].querySelector('[data-live-sig]');
          if (sig) {
            var freshSig = fresh.querySelector('[data-live-sig]');
            if (freshSig && freshSig.dataset.liveSig === sig.dataset.liveSig) continue;
          } else if (!formChanged(forms[i], fresh)) continue;
          if (inUse(forms[i])) {
            showNote('此資料剛被其他人更改，點此載入最新 Someone else just changed this — tap to load the latest');
          } else {
            try { sessionStorage.setItem('tyt-live-scroll', String(window.scrollY)); } catch (e) {}
            location.reload();
            return;
          }
        }
        swap.forEach(function (old) {
          var fresh = doc.getElementById(old.id);
          if (!fresh) return;
          if (fresh.innerHTML === old.innerHTML) return;          // nothing visible changed
          var node = document.importNode(fresh, true);
          old.replaceWith(node);
          regions[regions.indexOf(old)] = node;
          delete pending[old.id];
          if (!QUIET) {
            node.classList.add('live-flash');
            setTimeout(function () { node.classList.remove('live-flash'); }, 1600);
          }
          document.dispatchEvent(new CustomEvent('live:swap', { detail: node }));
          swapped++;
        });
        if (swapped) toast('已更新 Updated');
      })
      .catch(function () {});
  }

  // ---- checking ----
  function check() {
    if (busy || document.hidden) return;
    busy = true;
    fetch(url, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (res) { return res.ok ? res.json() : Promise.reject(res.status); })
      .then(function (data) {
        var v = data.v || {}, changed = {}, any = false;
        if (known) {
          Object.keys(v).forEach(function (t) { if (v[t] !== known[t]) { changed[t] = true; any = true; } });
        }
        // Parts that were busy last time get another go.
        Object.keys(pending).forEach(function (id) {
          var r = document.getElementById(id);
          if (r && !inUse(r)) topicsOf(r).forEach(function (t) { changed[t] = true; any = true; });
        });
        known = v;
        return any ? refresh(changed) : null;
      })
      .catch(function () {})
      .then(function () {
        busy = false;
        if (note && !conflict && !Object.keys(pending).length) { note.remove(); note = null; }
      });
  }
  function start() { clearInterval(timer); timer = setInterval(check, EVERY); }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { check(); start(); } });

  // Back where we were after a reload caused by a change.
  try {
    var y = sessionStorage.getItem('tyt-live-scroll');
    if (y !== null) { sessionStorage.removeItem('tyt-live-scroll'); window.scrollTo(0, +y); }
  } catch (e) {}

  check(); start();
  window.TYTLive = { check: check };
})();
