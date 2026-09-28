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
 *     data, e.g. registration opening or closing) reload the page.
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
  document.addEventListener('input', function (e) { var f = e.target.form || e.target.closest('form'); if (f) dirty.add(f); }, true);
  document.addEventListener('submit', function (e) { dirty.delete(e.target); }, true);
  function inUse(region) {
    var a = document.activeElement;
    if (a && region.contains(a) && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName)) return true;
    var forms = region.querySelectorAll('form');
    for (var i = 0; i < forms.length; i++) if (dirty.has(forms[i])) return true;
    if (region.tagName === 'FORM' && dirty.has(region)) return true;
    return region.querySelector('dialog[open]') !== null;
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
  var note = null;
  function showNote() {
    if (note || QUIET) return;
    note = document.createElement('button');
    note.type = 'button';
    note.className = 'live-note';
    note.textContent = '🔄 有新資料，點此更新 New information — tap to refresh';
    note.addEventListener('click', function () { location.reload(); });
    document.body.appendChild(note);
  }

  // ---- swapping ----
  function refresh(changed) {
    var affected = regions.filter(function (r) {
      return document.body.contains(r) && topicsOf(r).some(function (t) { return changed[t]; });
    });
    if (!affected.length) return Promise.resolve();

    var waiting = affected.filter(inUse);
    var reload = affected.filter(function (r) { return r.dataset.liveMode === 'reload' && !inUse(r); });
    if (reload.length && !waiting.length) {
      try { sessionStorage.setItem('tyt-live-scroll', String(window.scrollY)); } catch (e) {}
      location.reload();
      return Promise.resolve();
    }
    waiting.forEach(function (r) { pending[r.id] = true; });
    if (waiting.length) showNote();

    var swap = affected.filter(function (r) { return !inUse(r) && r.dataset.liveMode !== 'reload'; });
    if (!swap.length) return Promise.resolve();
    return fetch(location.href, { credentials: 'same-origin', headers: { 'X-Live': '1' }, cache: 'no-store' })
      .then(function (res) { return res.ok ? res.text() : Promise.reject(res.status); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var swapped = 0;
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
        if (swapped) toast('🔄 已更新 Updated');
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
        if (note && !Object.keys(pending).length) { note.remove(); note = null; }
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
