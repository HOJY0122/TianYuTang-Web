/*
 * Ticket-sale timing for the registration and donation forms
 * (partials/window_timer.php). The SERVER decides every submission to the
 * second (Event::windowStatus: the closing second itself is still
 * accepted, one second later is not). This script keeps the screen in step:
 *
 *   - follows the server's clock, not the phone's: the page carries the
 *     server time it was made at, then one quick /live check measures the
 *     round trip and corrects the difference
 *   - before opening: "opens in 00:04:59", and the form opens by itself on
 *     the second (the page reloads; the server now shows the form)
 *   - while open: "closes in …" (from 24 hours before; red in the last
 *     5 minutes); after the closing second the Submit button locks and
 *     says so — nobody fills in a form that can no longer be sent
 *   - one tap sends: Submit shows "提交中… Sending…" and cannot be pressed
 *     twice in a rush
 */
(function () {
  'use strict';
  var BASE = document.documentElement.getAttribute('data-base') || '';

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function span(ms) {
    var s = Math.max(0, Math.ceil(ms / 1000));
    var d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60), sec = s % 60;
    return (d ? d + ' 天 d ' : '') + pad(h) + ':' + pad(m) + ':' + pad(sec);
  }

  document.querySelectorAll('.window-timer').forEach(function (el) {
    var offset = (+el.dataset.now || Date.now()) - Date.now();   // server − device, first guess
    var opens = el.dataset.opens ? +el.dataset.opens : null;
    var closes = el.dataset.closes ? +el.dataset.closes : null;
    var form = el.dataset.form ? document.getElementById(el.dataset.form) : null;
    var label = el.querySelector('.wt-label'), time = el.querySelector('.wt-time');
    var locked = false, reloading = false, timer = null;
    function now() { return Date.now() + offset; }

    // Better guess: one /live round trip, taking the middle of it.
    var t0 = Date.now();
    fetch(BASE + '/live?t=', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (j) { var t1 = Date.now(); if (j && j.t) offset = j.t - (t0 + t1) / 2; tick(); })
      .catch(function () {});

    function lock() {
      if (locked || !form) return;
      locked = true;
      form.classList.add('is-closed');
      form.querySelectorAll('button[type="submit"]').forEach(function (b) {
        b.disabled = true;
        b.innerHTML = '已截止 <span class="en">Closed</span>';
      });
      var note = document.createElement('div');
      note.className = 'note closed-now';
      note.setAttribute('role', 'alert');
      note.innerHTML = '<strong>已截止，不能再提交。</strong><span class="en">The deadline has passed — this form can no longer be sent.</span>';
      form.insertBefore(note, form.firstChild);
    }

    function tick() {
      var t = now();
      if (opens && t < opens) {                         // not open yet
        el.hidden = false;
        el.className = 'window-timer is-before';
        label.textContent = '距離開放 Opens in';
        time.textContent = span(opens - t);
      } else if (opens && !form && !reloading) {        // the opening second: show the form
        reloading = true;
        label.textContent = '開放了！Open now';
        time.textContent = '';
        setTimeout(function () { location.reload(); }, 150 + Math.random() * 350);
        return;
      } else if (closes && t < closes + 1000) {         // open; the closing second still counts
        var left = closes + 1000 - t;
        el.hidden = left > 86400000;
        el.className = 'window-timer' + (left <= 300000 ? ' is-urgent' : '');
        label.textContent = '距離截止 Closes in';
        time.textContent = span(left - 1000);
      } else if (closes) {
        el.hidden = false;
        el.className = 'window-timer is-closed';
        label.textContent = '已截止 Closed';
        time.textContent = '';
        lock();
        clearInterval(timer);
        return;
      }
    }

    // Before sending: the synced clock says it is too late → stop here
    // (the server would refuse it anyway). Otherwise send exactly once.
    if (form) {
      form.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        if (closes && now() >= closes + 1000) { e.preventDefault(); lock(); return; }
        var btn = form.querySelector('button[type="submit"]');
        if (form.dataset.sending) { e.preventDefault(); return; }
        setTimeout(function () {                        // after other checks had their say
          if (e.defaultPrevented) return;
          form.dataset.sending = '1';
          if (btn) { btn.disabled = true; btn.classList.add('is-sending'); btn.innerHTML = '提交中… <span class="en">Sending…</span>'; }
        }, 0);
      });
    }

    // Back button after sending: the page comes back from the browser's
    // memory with Submit still saying "Sending…" — make it usable again.
    window.addEventListener('pageshow', function (e) {
      if (!e.persisted || !form || locked) return;
      delete form.dataset.sending;
      form.querySelectorAll('button[type="submit"].is-sending').forEach(function (b) {
        b.disabled = false; b.classList.remove('is-sending'); b.innerHTML = b.dataset.label || b.innerHTML;
      });
    });
    if (form) form.querySelectorAll('button[type="submit"]').forEach(function (b) { b.dataset.label = b.innerHTML; });

    tick();
    timer = setInterval(tick, 250);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) tick(); });
  });
})();
