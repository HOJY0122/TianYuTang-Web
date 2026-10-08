/*
 * Shuffled photos → whole again, on a <canvas> (see app/Core/Media.php).
 *
 * A protected photo arrives as <img class="scr" data-src="/media?…&x=1"
 * data-scr="12345">: the file behind data-src is the photo cut into
 * square tiles and shuffled. This script loads it, works out the same
 * order from data-scr (mulberry32 + Fisher–Yates, exactly as the server)
 * and draws every tile back in its place on a canvas that takes the
 * <img>'s place, classes and all. DevTools → Sources / Network, or any
 * download tool, only ever sees the jumbled file.
 *
 *   TYTPhoto.upgrade(root)          convert every img.scr under root (lazy)
 *   TYTPhoto.draw(canvas, url, key) draw one photo into a canvas → Promise
 *   TYTPhoto.copy(from, to)         copy a drawn canvas (e.g. into a dialog)
 */
(function () {
  'use strict';

  function order(seed, n) {
    var a = seed >>> 0, p = [];
    for (var i = 0; i < n; i++) p.push(i);
    function r() {
      a = (a + 0x6D2B79F5) >>> 0;
      var t = Math.imul(a ^ (a >>> 15), a | 1) >>> 0;
      t = ((t + Math.imul(t ^ (t >>> 7), t | 61)) >>> 0 ^ t) >>> 0;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    }
    for (var k = n - 1; k > 0; k--) {
      var j = Math.floor(r() * (k + 1));
      var x = p[k]; p[k] = p[j]; p[j] = x;
    }
    return p;
  }
  function tile(w, h) { return Math.max(16, Math.floor(Math.floor(Math.min(w, h) / 8) / 16) * 16); }

  function draw(canvas, url, key) {
    return new Promise(function (resolve, reject) {
      var img = new Image();
      img.decoding = 'async';
      img.onload = function () {
        var w = img.naturalWidth, h = img.naturalHeight, t = tile(w, h);
        var cols = Math.floor(w / t), rows = Math.floor(h / t);
        canvas.width = w; canvas.height = h;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0);                          // edges past the last whole tile
        var ord = order(+key, cols * rows);
        for (var i = 0; i < ord.length; i++) {
          var from = ord[i];
          ctx.drawImage(img, (i % cols) * t, Math.floor(i / cols) * t, t, t,
                             (from % cols) * t, Math.floor(from / cols) * t, t, t);
        }
        img.src = '';                                      // let the jumbled copy go
        canvas.classList.add('is-drawn');
        resolve(canvas);
      };
      img.onerror = reject;
      img.src = url;
    });
  }

  function copy(from, to) {
    to.width = from.width; to.height = from.height;
    to.getContext('2d').drawImage(from, 0, 0);
  }

  var io = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (!e.isIntersecting) return;
      io.unobserve(e.target);
      draw(e.target, e.target.dataset.src, e.target.dataset.scr).catch(function () {});
    });
  }, { rootMargin: '300px 300px' }) : null;

  function upgrade(root) {
    (root || document).querySelectorAll('img.scr[data-src]').forEach(function (img) {
      var c = document.createElement('canvas');
      c.className = img.className;
      Array.prototype.forEach.call(img.attributes, function (a) {
        if (/^(data-|aria-|title$)/.test(a.name)) c.setAttribute(a.name, a.value);
      });
      c.setAttribute('role', 'img');
      if (img.alt) c.setAttribute('aria-label', img.alt);
      if (img.getAttribute('width')) { c.width = +img.getAttribute('width'); c.height = +img.getAttribute('height'); }
      img.replaceWith(c);
      if (io) io.observe(c); else draw(c, c.dataset.src, c.dataset.scr).catch(function () {});
    });
  }

  window.TYTPhoto = { upgrade: upgrade, draw: draw, copy: copy };
  upgrade(document);
  document.addEventListener('live:swap', function (e) { upgrade(e.detail); });
})();
