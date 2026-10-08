<?php
/**
 * Full-screen photo viewer + album row arrows. Include once per page,
 * after any albums (partials/album.php) or post images.
 *
 *  - Each album row scrolls by one "page" of photos with its arrows
 *    (shown on phones too), or is simply swiped.
 *  - Clicking a photo or a news picture opens it large. Move through
 *    THAT album with the buttons, the arrow keys, or by swiping: the photo
 *    follows the finger and the next one slides in. The photos either
 *    side are loaded ahead.
 *  - Zoom: the + / − buttons, two fingers (pinch), a double tap / double
 *    click, or the mouse wheel. While zoomed in, drag to look around;
 *    swiping to the next photo works again once back to normal size.
 *  - "3 / 12" shows where you are; on a phone the big ‹ › buttons sit
 *    under the photo, where a thumb reaches them, never on top of it.
 *  - Protected photos (data-seed) arrive shuffled and are put back
 *    together on a canvas by js/photos.js; others use a normal image.
 *  - A transparent shield lies over the photo, so a long press or right
 *    click never reaches the picture itself, and carries the watermark.
 */
?>
<div id="lightbox" class="lightbox hidden" role="dialog" aria-modal="true" aria-label="相片檢視 Photo viewer">
  <div class="lb-top">
    <span class="lb-count" id="lbCount" aria-live="polite"></span>
    <div class="lb-zoombar" role="group" aria-label="縮放 Zoom">
      <button class="lb-zoom-out" aria-label="縮小 Zoom out"><?= icon('zoom-out') ?></button>
      <button class="lb-zoom-level" aria-label="還原大小 Reset size" id="lbZoomLevel">100%</button>
      <button class="lb-zoom-in" aria-label="放大 Zoom in"><?= icon('zoom-in') ?></button>
    </div>
    <button class="lb-close" aria-label="關閉 Close"><?= icon('x') ?></button>
  </div>
  <div class="lb-main">
    <button class="lb-prev lb-side" aria-label="上一張 Previous"><?= icon('chevron-left') ?></button>
    <div class="lb-stage" id="lbStage">
      <div class="lb-zoom" id="lbZoom">
        <img id="lbImage" src="" alt="" draggable="false">
        <canvas id="lbCanvas" role="img" hidden></canvas>
        <div class="lb-shield" aria-hidden="true"></div>
      </div>
    </div>
    <button class="lb-next lb-side" aria-label="下一張 Next"><?= icon('chevron-right') ?></button>
  </div>
  <div class="lb-bottom">
    <div id="lbCaption" class="lb-caption"></div>
    <div class="lb-pager">
      <button class="lb-prev" aria-label="上一張 Previous"><?= icon('chevron-left') ?> <span>上一張</span></button>
      <span class="lb-hint"><?= icon('hand') ?> 滑動 · 雙指放大</span>
      <button class="lb-next" aria-label="下一張 Next"><span>下一張</span> <?= icon('chevron-right') ?></button>
    </div>
  </div>
</div>
<script>
(function () {
  // ---- album rows: arrows page through, disabled at either end ----
  function bindRows(root) {
    root.querySelectorAll('.album-row:not([data-bound])').forEach(function (row) {
      row.setAttribute('data-bound', '');
      var strip = row.querySelector('.album-strip');
      var prev  = row.querySelector('.album-nav.prev');
      var next  = row.querySelector('.album-nav.next');
      function update() {
        prev.disabled = strip.scrollLeft <= 4;
        next.disabled = strip.scrollLeft + strip.clientWidth >= strip.scrollWidth - 4;
        row.classList.toggle('no-scroll', prev.disabled && next.disabled);
      }
      [prev, next].forEach(function (btn) {
        btn.addEventListener('click', function () {
          strip.scrollBy({ left: Number(btn.dataset.dir) * strip.clientWidth * 0.9, behavior: 'smooth' });
        });
      });
      strip.addEventListener('scroll', update, { passive: true });
      window.addEventListener('resize', update);
      update();
    });
  }

  // ---- lightbox ----
  var box    = document.getElementById('lightbox');
  var img    = document.getElementById('lbImage');
  var canvas = document.getElementById('lbCanvas');
  var stage  = document.getElementById('lbStage');
  var zoomEl = document.getElementById('lbZoom');
  var cap    = document.getElementById('lbCaption');
  var count  = document.getElementById('lbCount');
  var level  = document.getElementById('lbZoomLevel');
  var set = [], current = 0, lastFocus = null, cache = {};
  var MAX = 5;
  var z = { s: 1, x: 0, y: 0 };

  function preload(i) {
    if (set.length < 2) return;
    var it = set[(i + set.length) % set.length];
    if (!cache[it.full]) { cache[it.full] = new Image(); cache[it.full].src = it.full; }
  }

  // ---- zoom ----
  function clampPan() {
    var r = stage.getBoundingClientRect();
    var mx = Math.max(0, (r.width * z.s - r.width) / 2), my = Math.max(0, (r.height * z.s - r.height) / 2);
    z.x = Math.min(mx, Math.max(-mx, z.x));
    z.y = Math.min(my, Math.max(-my, z.y));
  }
  function applyZoom(animate) {
    if (z.s <= 1.01) { z.s = 1; z.x = 0; z.y = 0; }
    clampPan();
    zoomEl.style.transition = animate ? 'transform .2s ease' : 'none';
    zoomEl.style.transform = 'translate(' + z.x + 'px,' + z.y + 'px) scale(' + z.s + ')';
    level.textContent = Math.round(z.s * 100) + '%';
    box.classList.toggle('zoomed', z.s > 1);
    box.querySelector('.lb-zoom-out').disabled = z.s <= 1;
    box.querySelector('.lb-zoom-in').disabled = z.s >= MAX;
  }
  /** Zoom to scale s, keeping the point (px, py) on screen where it is. */
  function zoomTo(s, px, py, animate) {
    s = Math.min(MAX, Math.max(1, s));
    var r = stage.getBoundingClientRect();
    var cx = (px === undefined ? r.left + r.width / 2 : px) - (r.left + r.width / 2);
    var cy = (py === undefined ? r.top + r.height / 2 : py) - (r.top + r.height / 2);
    var k = s / z.s;
    z.x = cx - (cx - z.x) * k;
    z.y = cy - (cy - z.y) * k;
    z.s = s;
    applyZoom(animate);
  }
  function resetZoom() { z = { s: 1, x: 0, y: 0 }; applyZoom(false); }

  function show(i, dir) {
    current = (i + set.length) % set.length;
    var it = set[current];
    resetZoom();
    if (it.seed && window.TYTPhoto) {
      img.hidden = true; img.removeAttribute('src');
      canvas.hidden = false;
      canvas.setAttribute('aria-label', it.caption || '活動留影');
      canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
      TYTPhoto.draw(canvas, it.full, it.seed).catch(function () {});
    } else {
      canvas.hidden = true;
      img.hidden = false;
      img.src = it.full;
      img.alt = it.caption || '活動留影';
    }
    cap.textContent = it.caption || '';
    cap.hidden = !it.caption;
    count.textContent = set.length > 1 ? (current + 1) + ' / ' + set.length : '';
    box.classList.toggle('single', set.length < 2);
    if (dir) {
      stage.classList.remove('in-left', 'in-right');
      void stage.offsetWidth;
      stage.classList.add(dir > 0 ? 'in-right' : 'in-left');
    }
    if (!it.seed) { preload(current + 1); preload(current - 1); }
    if (box.classList.contains('hidden')) {
      lastFocus = document.activeElement;
      box.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
      box.querySelector('.lb-close').focus();
    }
  }
  function go(step) { if (set.length > 1) show(current + step, step); }
  function close() {
    box.classList.add('hidden');
    document.body.style.overflow = '';
    img.removeAttribute('src');
    canvas.width = canvas.height = 0;      // drop the drawn photo
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  function bindPhotos(root) {
    root.querySelectorAll('.album-strip:not([data-lb])').forEach(function (strip) {
      if (strip.classList.contains('news-strip')) return;      // news pictures: see the click handler below
      strip.setAttribute('data-lb', '');
      var figures = Array.prototype.slice.call(strip.querySelectorAll('figure[data-full]'));
      var items = figures.map(function (f) { return { full: f.dataset.full, seed: f.dataset.seed, caption: f.dataset.caption }; });
      figures.forEach(function (f, i) {
        f.tabIndex = 0;
        f.setAttribute('role', 'button');
        f.addEventListener('click', function () { set = items; show(i); });
        f.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); set = items; show(i); } });
      });
    });
  }
  bindRows(document);
  bindPhotos(document);
  document.addEventListener('live:swap', function (e) { bindRows(e.detail); bindPhotos(e.detail); });
  // News pictures (an <img> or, when protected, a <canvas>) open on their own.
  document.addEventListener('click', function (e) {
    var el = e.target.closest && e.target.closest('[data-lightbox]');
    if (!el || box.contains(el)) return;
    set = [{ full: el.dataset.lightbox, seed: el.dataset.seed, caption: el.getAttribute('alt') || el.getAttribute('aria-label') || '' }];
    show(0);
  });

  box.querySelector('.lb-close').addEventListener('click', close);
  box.querySelectorAll('.lb-prev').forEach(function (b) { b.addEventListener('click', function (e) { e.stopPropagation(); go(-1); }); });
  box.querySelectorAll('.lb-next').forEach(function (b) { b.addEventListener('click', function (e) { e.stopPropagation(); go(1); }); });
  box.querySelector('.lb-zoom-in').addEventListener('click', function () { zoomTo(z.s * 1.5, undefined, undefined, true); });
  box.querySelector('.lb-zoom-out').addEventListener('click', function () { zoomTo(z.s / 1.5, undefined, undefined, true); });
  level.addEventListener('click', function () { zoomTo(1, undefined, undefined, true); });
  box.addEventListener('click', function (e) {
    if (e.target === box || e.target.classList.contains('lb-main')) close();
  });
  document.addEventListener('keydown', function (e) {
    if (box.classList.contains('hidden')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft' && z.s === 1) go(-1);
    if (e.key === 'ArrowRight' && z.s === 1) go(1);
    if (e.key === '+' || e.key === '=') zoomTo(z.s * 1.5, undefined, undefined, true);
    if (e.key === '-') zoomTo(z.s / 1.5, undefined, undefined, true);
    if (e.key === '0') zoomTo(1, undefined, undefined, true);
  });

  // Mouse: wheel zooms around the pointer; double click toggles; drag pans when zoomed.
  stage.addEventListener('wheel', function (e) {
    e.preventDefault();
    zoomTo(z.s * (e.deltaY < 0 ? 1.2 : 1 / 1.2), e.clientX, e.clientY, false);
  }, { passive: false });
  stage.addEventListener('dblclick', function (e) { zoomTo(z.s > 1 ? 1 : 2.5, e.clientX, e.clientY, true); });
  var drag = null;
  stage.addEventListener('mousedown', function (e) {
    if (z.s <= 1 || e.button !== 0) return;
    e.preventDefault();
    drag = { x: e.clientX - z.x, y: e.clientY - z.y };
    stage.classList.add('panning');
  });
  window.addEventListener('mousemove', function (e) { if (drag) { z.x = e.clientX - drag.x; z.y = e.clientY - drag.y; applyZoom(false); } });
  window.addEventListener('mouseup', function () { drag = null; stage.classList.remove('panning'); });

  // Touch: one finger swipes (or pans when zoomed), two fingers pinch, double tap toggles.
  var t0 = null, pinch = null, lastTap = 0, sx = 0, sy = 0, dx = 0, dy = 0, horiz = null, st = 0;
  function dist(a, b) { return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY); }
  stage.addEventListener('touchstart', function (e) {
    if (e.touches.length === 2) {
      pinch = { d: dist(e.touches[0], e.touches[1]), s: z.s,
                cx: (e.touches[0].clientX + e.touches[1].clientX) / 2, cy: (e.touches[0].clientY + e.touches[1].clientY) / 2 };
      t0 = null;
      stage.style.transform = '';
      return;
    }
    if (e.touches.length !== 1) return;
    var p = e.touches[0];
    var now = Date.now();
    if (now - lastTap < 300) { zoomTo(z.s > 1 ? 1 : 2.5, p.clientX, p.clientY, true); lastTap = 0; t0 = null; return; }
    lastTap = now;
    t0 = { x: p.clientX - z.x, y: p.clientY - z.y };
    sx = p.clientX; sy = p.clientY; dx = dy = 0; horiz = null; st = now;
    stage.style.transition = 'none';
  }, { passive: true });
  stage.addEventListener('touchmove', function (e) {
    if (pinch && e.touches.length === 2) {
      e.preventDefault();
      var s = pinch.s * dist(e.touches[0], e.touches[1]) / pinch.d;
      zoomTo(s, pinch.cx, pinch.cy, false);
      return;
    }
    if (!t0 || e.touches.length !== 1) return;
    var p = e.touches[0];
    if (z.s > 1) {                                  // zoomed: look around
      e.preventDefault();
      z.x = p.clientX - t0.x; z.y = p.clientY - t0.y; applyZoom(false);
      return;
    }
    dx = p.clientX - sx; dy = p.clientY - sy;
    if (horiz === null && (Math.abs(dx) > 8 || Math.abs(dy) > 8)) horiz = Math.abs(dx) > Math.abs(dy);
    if (horiz && set.length > 1) stage.style.transform = 'translateX(' + dx + 'px)';
    else if (horiz === false && dy > 0) stage.style.transform = 'translateY(' + dy + 'px)';
  }, { passive: false });
  stage.addEventListener('touchend', function (e) {
    if (pinch) { if (e.touches.length < 2) pinch = null; return; }
    if (!t0) return;
    stage.style.transition = '';
    stage.style.transform = '';
    if (z.s === 1) {
      var fast = Date.now() - st < 250;
      if (horiz && set.length > 1 && (Math.abs(dx) > 60 || (fast && Math.abs(dx) > 25))) go(dx < 0 ? 1 : -1);
      else if (horiz === false && dy > 110) close();
    }
    t0 = null;
  });
})();
</script>
