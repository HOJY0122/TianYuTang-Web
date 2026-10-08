<?php
/**
 * Full-screen photo viewer + album row arrows. Include once per page,
 * after any albums (partials/album.php) or post images.
 *
 *  - Each album row scrolls by one "page" of photos with its arrows
 *    (shown on phones too), or is simply swiped.
 *  - Clicking a photo opens it large. Move through THAT album with the
 *    buttons, the arrow keys, or by swiping: the photo follows the finger
 *    and the next one slides in. The photos either side are loaded
 *    ahead, so the next one appears at once.
 *  - "3 / 12" shows where you are; on a phone the big ‹ › buttons sit
 *    under the photo, where a thumb reaches them, never on top of it.
 *  - Any <img data-lightbox> (e.g. a news post picture) opens on its own.
 *  - A transparent shield lies over the photo, so a long press or right
 *    click never reaches the picture itself (see js/protect.js), and
 *    carries the optional watermark.
 */
?>
<div id="lightbox" class="lightbox hidden" role="dialog" aria-modal="true" aria-label="相片檢視 Photo viewer">
  <div class="lb-top">
    <span class="lb-count" id="lbCount" aria-live="polite"></span>
    <button class="lb-close" aria-label="關閉 Close"><?= icon('x') ?></button>
  </div>
  <div class="lb-main">
    <button class="lb-prev lb-side" aria-label="上一張 Previous"><?= icon('chevron-left') ?></button>
    <div class="lb-stage" id="lbStage">
      <img id="lbImage" src="" alt="" draggable="false">
      <div class="lb-shield" aria-hidden="true"></div>
    </div>
    <button class="lb-next lb-side" aria-label="下一張 Next"><?= icon('chevron-right') ?></button>
  </div>
  <div class="lb-bottom">
    <div id="lbCaption" class="lb-caption"></div>
    <div class="lb-pager">
      <button class="lb-prev" aria-label="上一張 Previous"><?= icon('chevron-left') ?> <span>上一張</span></button>
      <span class="lb-hint"><?= icon('hand') ?> 左右滑動 Swipe</span>
      <button class="lb-next" aria-label="下一張 Next"><span>下一張</span> <?= icon('chevron-right') ?></button>
    </div>
  </div>
</div>
<script>
(function () {
  // ---- album rows: arrows page through, disabled at either end ----
  // bindRows / bindPhotos run on the page and again on any part that
  // arrives with a realtime update (live:swap); each element once.
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
  var box   = document.getElementById('lightbox');
  var img   = document.getElementById('lbImage');
  var stage = document.getElementById('lbStage');
  var cap   = document.getElementById('lbCaption');
  var count = document.getElementById('lbCount');
  var set = [], current = 0, lastFocus = null;
  var cache = {};

  function preload(i) {
    if (set.length < 2) return;
    var it = set[(i + set.length) % set.length];
    if (!cache[it.full]) { cache[it.full] = new Image(); cache[it.full].src = it.full; }
  }

  function show(i, dir) {
    current = (i + set.length) % set.length;
    var it = set[current];
    img.src = it.full;
    img.alt = it.caption || '活動留影';
    cap.textContent = it.caption || '';
    cap.hidden = !it.caption;
    count.textContent = set.length > 1 ? (current + 1) + ' / ' + set.length : '';
    box.classList.toggle('single', set.length < 2);
    if (dir) {
      stage.classList.remove('in-left', 'in-right');
      void stage.offsetWidth;                         // restart the slide-in
      stage.classList.add(dir > 0 ? 'in-right' : 'in-left');
    }
    preload(current + 1); preload(current - 1);
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
    img.src = '';   // stop a large download continuing in the background
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  function bindPhotos(root) {
    root.querySelectorAll('.album-strip:not([data-lb])').forEach(function (strip) {
      strip.setAttribute('data-lb', '');
      var figures = Array.prototype.slice.call(strip.querySelectorAll('figure[data-full]'));
      var items = figures.map(function (f) { return { full: f.dataset.full, caption: f.dataset.caption }; });
      figures.forEach(function (f, i) {
        f.tabIndex = 0;
        f.setAttribute('role', 'button');
        f.addEventListener('click', function () { set = items; show(i); });
        f.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); set = items; show(i); } });
      });
    });
    root.querySelectorAll('img[data-lightbox]:not([data-lb])').forEach(function (el) {
      el.setAttribute('data-lb', '');
      el.addEventListener('click', function () {
        set = [{ full: el.dataset.lightbox, caption: el.alt }]; show(0);
      });
    });
  }
  bindRows(document);
  bindPhotos(document);
  document.addEventListener('live:swap', function (e) { bindRows(e.detail); bindPhotos(e.detail); });

  box.querySelector('.lb-close').addEventListener('click', close);
  box.querySelectorAll('.lb-prev').forEach(function (b) { b.addEventListener('click', function (e) { e.stopPropagation(); go(-1); }); });
  box.querySelectorAll('.lb-next').forEach(function (b) { b.addEventListener('click', function (e) { e.stopPropagation(); go(1); }); });
  box.addEventListener('click', function (e) {
    if (e.target === box || e.target.classList.contains('lb-main')) close();
  });
  document.addEventListener('keydown', function (e) {
    if (box.classList.contains('hidden')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft') go(-1);
    if (e.key === 'ArrowRight') go(1);
  });

  // ---- swipe: the photo follows the finger; far enough → next / previous,
  // a quick flick also counts; swipe down closes ----
  var sx = null, sy = null, st = 0, dx = 0, dy = 0, horiz = null;
  stage.addEventListener('touchstart', function (e) {
    if (e.touches.length !== 1) { sx = null; return; }
    sx = e.touches[0].clientX; sy = e.touches[0].clientY; st = Date.now(); dx = dy = 0; horiz = null;
    stage.style.transition = 'none';
  }, { passive: true });
  stage.addEventListener('touchmove', function (e) {
    if (sx === null) return;
    dx = e.touches[0].clientX - sx; dy = e.touches[0].clientY - sy;
    if (horiz === null && (Math.abs(dx) > 8 || Math.abs(dy) > 8)) horiz = Math.abs(dx) > Math.abs(dy);
    if (horiz && set.length > 1) stage.style.transform = 'translateX(' + dx + 'px)';
    else if (horiz === false && dy > 0) stage.style.transform = 'translateY(' + dy + 'px)';
  }, { passive: true });
  stage.addEventListener('touchend', function () {
    if (sx === null) return;
    var fast = Date.now() - st < 250;
    stage.style.transition = '';
    stage.style.transform = '';
    if (horiz && set.length > 1 && (Math.abs(dx) > 60 || (fast && Math.abs(dx) > 25))) go(dx < 0 ? 1 : -1);
    else if (horiz === false && dy > 110) close();
    sx = null;
  });
})();
</script>
