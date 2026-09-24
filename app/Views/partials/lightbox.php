<?php
/**
 * Full-screen photo viewer + album row arrows. Include once per page,
 * after any albums (partials/album.php) or post images.
 *
 *  - Each album row scrolls by one "page" of photos with its arrows;
 *    on a phone the row is simply swiped.
 *  - Clicking a photo opens it large; ◀ ▶ (or arrow keys, or a swipe)
 *    move through THAT album only.
 *  - Any <img data-lightbox> (e.g. a news post picture) opens on its own.
 */
?>
<div id="lightbox" class="lightbox hidden" role="dialog" aria-modal="true" aria-label="相片檢視 Photo viewer">
  <button class="lb-close" aria-label="關閉 Close">&times;</button>
  <button class="lb-prev" aria-label="上一張 Previous">&#10094;</button>
  <img id="lbImage" src="" alt="">
  <button class="lb-next" aria-label="下一張 Next">&#10095;</button>
  <div id="lbCaption" class="lb-caption"></div>
</div>
<script>
(function () {
  // ---- album rows: arrows page through, disabled at either end ----
  document.querySelectorAll('.album-row').forEach(function (row) {
    var strip = row.querySelector('.album-strip');
    var prev  = row.querySelector('.album-nav.prev');
    var next  = row.querySelector('.album-nav.next');
    function update() {
      prev.disabled = strip.scrollLeft <= 4;
      next.disabled = strip.scrollLeft + strip.clientWidth >= strip.scrollWidth - 4;
    }
    [prev, next].forEach(function (btn) {
      btn.addEventListener('click', function () {
        strip.scrollBy({ left: Number(btn.dataset.dir) * strip.clientWidth, behavior: 'smooth' });
      });
    });
    strip.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
  });

  // ---- lightbox ----
  var box = document.getElementById('lightbox');
  var img = document.getElementById('lbImage');
  var cap = document.getElementById('lbCaption');
  var set = [], current = 0;

  function show(i) {
    current = (i + set.length) % set.length;
    img.src = set[current].full;
    img.alt = set[current].caption || '活動留影';
    cap.textContent = set[current].caption || '';
    cap.style.display = set[current].caption ? 'block' : 'none';
    box.querySelector('.lb-prev').style.display = set.length > 1 ? '' : 'none';
    box.querySelector('.lb-next').style.display = set.length > 1 ? '' : 'none';
    box.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }
  function close() {
    box.classList.add('hidden');
    document.body.style.overflow = '';
    img.src = '';   // stop a large download continuing in the background
  }

  document.querySelectorAll('.album-strip').forEach(function (strip) {
    var figures = Array.prototype.slice.call(strip.querySelectorAll('figure'));
    var items = figures.map(function (f) { return { full: f.dataset.full, caption: f.dataset.caption }; });
    figures.forEach(function (f, i) {
      f.addEventListener('click', function () { set = items; show(i); });
    });
  });
  document.querySelectorAll('img[data-lightbox]').forEach(function (el) {
    el.addEventListener('click', function () {
      set = [{ full: el.dataset.lightbox, caption: el.alt }]; show(0);
    });
  });

  box.querySelector('.lb-close').addEventListener('click', close);
  box.querySelector('.lb-prev').addEventListener('click', function (e) { e.stopPropagation(); show(current - 1); });
  box.querySelector('.lb-next').addEventListener('click', function (e) { e.stopPropagation(); show(current + 1); });
  box.addEventListener('click', function (e) { if (e.target === box) close(); });
  document.addEventListener('keydown', function (e) {
    if (box.classList.contains('hidden')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft') show(current - 1);
    if (e.key === 'ArrowRight') show(current + 1);
  });

  // Swipe left / right on a phone.
  var startX = null;
  box.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
  box.addEventListener('touchend', function (e) {
    if (startX === null || set.length < 2) return;
    var dx = e.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 50) show(current + (dx < 0 ? 1 : -1));
    startX = null;
  });
})();
</script>
