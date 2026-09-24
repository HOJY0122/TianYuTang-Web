/**
 * banner.js — the home page banner slideshow.
 *
 * Pictures change every data-interval seconds; they stop while the
 * pointer or keyboard focus is on the banner, while the tab is hidden,
 * after the ⏸ button, and (never start) for people who ask their device
 * to reduce motion. Arrows, dots, swipe and ← → keys move by hand.
 *
 * window.TYTBanner.go(el, n) and .refresh(el) are used by the admin
 * preview when settings change.
 */
(function () {
  'use strict';
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  function init(root) {
    if (root._banner) return root._banner;
    var slides = [].slice.call(root.querySelectorAll('.banner-slide'));
    var dots = [].slice.call(root.querySelectorAll('.banner-dots button'));
    var track = root.querySelector('.banner-track');
    var pauseBtn = root.querySelector('.banner-pause');
    var state = { i: 0, timer: null, hold: false, paused: false };

    function show(n) {
      if (!slides.length) return;
      state.i = (n + slides.length) % slides.length;
      slides.forEach(function (s, k) {
        s.classList.toggle('is-active', k === state.i);
        if (k === state.i) s.removeAttribute('aria-hidden'); else s.setAttribute('aria-hidden', 'true');
      });
      dots.forEach(function (d, k) { d.setAttribute('aria-selected', k === state.i ? 'true' : 'false'); });
      track.style.transform = root.classList.contains('fx-slide') ? 'translateX(' + (-100 * state.i) + '%)' : '';
    }
    function schedule() {
      clearTimeout(state.timer);
      if (slides.length < 2 || reduce || state.hold || state.paused || document.hidden) return;
      var secs = Math.max(2, parseFloat(root.dataset.interval) || 6);
      state.timer = setTimeout(function () { show(state.i + 1); schedule(); }, secs * 1000);
    }
    function go(n) { show(n); schedule(); }

    root.querySelector('.banner-nav.prev') && root.querySelector('.banner-nav.prev').addEventListener('click', function () { go(state.i - 1); });
    root.querySelector('.banner-nav.next') && root.querySelector('.banner-nav.next').addEventListener('click', function () { go(state.i + 1); });
    dots.forEach(function (d, k) { d.addEventListener('click', function () { go(k); }); });
    if (pauseBtn) pauseBtn.addEventListener('click', function () {
      state.paused = !state.paused;
      pauseBtn.setAttribute('aria-pressed', state.paused ? 'true' : 'false');
      pauseBtn.textContent = state.paused ? '▶' : '⏸';
      pauseBtn.setAttribute('aria-label', state.paused ? '播放 Play' : '暫停 Pause');
      schedule();
    });
    root.addEventListener('mouseenter', function () { state.hold = true; schedule(); });
    root.addEventListener('mouseleave', function () { state.hold = false; schedule(); });
    root.addEventListener('focusin', function () { state.hold = true; schedule(); });
    root.addEventListener('focusout', function () { state.hold = false; schedule(); });
    root.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { go(state.i - 1); e.preventDefault(); }
      if (e.key === 'ArrowRight') { go(state.i + 1); e.preventDefault(); }
    });
    document.addEventListener('visibilitychange', schedule);

    // Swipe on touch screens (a tap still follows a slide's link).
    var x0 = null, y0 = 0;
    root.addEventListener('pointerdown', function (e) { if (e.pointerType !== 'mouse') { x0 = e.clientX; y0 = e.clientY; } });
    root.addEventListener('pointerup', function (e) {
      if (x0 === null) return;
      var dx = e.clientX - x0, dy = e.clientY - y0; x0 = null;
      if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) go(state.i + (dx < 0 ? 1 : -1));
    });
    root.addEventListener('click', function (e) {
      if (root.hasAttribute('data-preview') && e.target.closest('a.banner-frame')) e.preventDefault();
    });

    // Load the other pictures once the page itself has finished.
    function eager() { root.querySelectorAll('img[loading="lazy"]').forEach(function (img) { img.loading = 'eager'; }); }
    if (document.readyState === 'complete') eager(); else window.addEventListener('load', eager);

    root._banner = { go: go, refresh: function () { show(state.i); schedule(); }, slides: slides };
    show(0); schedule();
    return root._banner;
  }

  document.querySelectorAll('.banner-show').forEach(init);
  window.TYTBanner = {
    init: init,
    go: function (root, n) { init(root).go(n); },
    refresh: function (root) { init(root).refresh(); }
  };
})();
