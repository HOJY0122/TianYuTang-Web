/**
 * uat.js — the UAT announcements take turns every data-interval seconds,
 * sliding up; they pause while the pointer is on the bar. Bars that arrive
 * with a realtime update (live:swap) start too.
 */
(function () {
  'use strict';
  function init(bar) {
    if (bar._uat) return;
    bar._uat = true;
    var items = [].slice.call(bar.querySelectorAll('.uat-track li'));
    var dots = [].slice.call(bar.querySelectorAll('.uat-dots i'));
    if (items.length < 2) return;
    var i = 0, hold = false;
    var secs = Math.max(2, parseFloat(bar.dataset.interval) || 4);
    function next() {
      if (hold || document.hidden || !document.body.contains(bar)) return;
      var cur = items[i];
      i = (i + 1) % items.length;
      cur.classList.remove('is-on'); cur.classList.add('is-out');
      items[i].classList.remove('is-out'); items[i].classList.add('is-on');
      setTimeout(function () { cur.classList.remove('is-out'); }, 600);
      dots.forEach(function (d, k) { d.classList.toggle('on', k === i); });
    }
    bar.addEventListener('mouseenter', function () { hold = true; });
    bar.addEventListener('mouseleave', function () { hold = false; });
    var timer = setInterval(function () { if (!document.body.contains(bar)) { clearInterval(timer); return; } next(); }, secs * 1000);
  }
  // The site's sticky header sits just below the pinned bar: keep --uat-h
  // equal to the bar's real height (it changes with the size setting, the
  // screen width, and when UAT is switched on or off on an open page).
  var html = document.documentElement;
  function measure() {
    var wrap = document.getElementById('liveUat');
    var bar = wrap && wrap.querySelector('.uat-bar');
    html.style.setProperty('--uat-h', (bar ? Math.round(wrap.getBoundingClientRect().height) : 0) + 'px');
  }
  if (window.ResizeObserver) {
    var ro = new ResizeObserver(measure);
    var watch = function () { var w = document.getElementById('liveUat'); if (w) ro.observe(w); };
    watch();
    document.addEventListener('live:swap', function () { ro.disconnect(); watch(); measure(); });
  }
  window.addEventListener('resize', measure);
  measure();

  function scan(root) { (root.matches && root.matches('.uat-bar') ? [root] : []).concat([].slice.call(root.querySelectorAll('.uat-bar'))).forEach(init); }
  scan(document);
  document.addEventListener('live:swap', function (e) { scan(e.detail); });
})();
