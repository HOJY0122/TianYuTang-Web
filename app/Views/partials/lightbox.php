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
 *  - Videos (figure[data-video]) play in the same viewer with the
 *    browser's own controls; zoom is off for them, swiping still moves
 *    on (except on the control bar), and the video stops when you leave.
 *    No download in the player's menu. When albums are protected the
 *    video arrives ENCRYPTED (Media::videoUrl(…, true)): it is fetched,
 *    decrypted here piece by piece (AES-CTR, data-vk / data-vi) and
 *    played from memory (blob:), with a loading ring meanwhile.
 *  - Vertical (phone) videos get a tall player, horizontal ones a wide
 *    one — no big black bars either side.
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
      <video id="lbVideo" class="lb-video" controls playsinline preload="metadata" controlslist="nodownload" disableremoteplayback hidden></video>
      <div class="lb-vload" id="lbVload" hidden>
        <span class="lb-vring" aria-hidden="true"><svg viewBox="0 0 48 48"><circle cx="24" cy="24" r="20"/><circle class="lb-vring-on" cx="24" cy="24" r="20"/></svg><b id="lbVpct">0%</b></span>
        <span class="lb-vload-text">載入影片 <span class="en">Loading video</span></span>
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
  var video  = document.getElementById('lbVideo');
  var vload  = document.getElementById('lbVload');
  var vpct   = document.getElementById('lbVpct');
  var vring  = vload.querySelector('.lb-vring-on');
  var vjob = null, blobs = [];          // the loading video; decrypted videos kept (last 2) for going back
  function isVideo() { return !!(set[current] && set[current].video); }
  function shape(w, h) {
    var tall = w > 0 && h > w;
    box.classList.toggle('v-tall', tall);
    video.style.aspectRatio = w > 0 && h > 0 ? w + ' / ' + h : '';
  }
  video.addEventListener('loadedmetadata', function () { if (video.videoWidth) shape(video.videoWidth, video.videoHeight); });
  function hex(s) { var a = new Uint8Array(s.length / 2); for (var i = 0; i < a.length; i++) a[i] = parseInt(s.substr(i * 2, 2), 16); return a; }
  /** Fetch the encrypted copy and decrypt it in 4 MB pieces (AES-CTR: piece at byte n uses counter prefix ‖ n/16) → Promise<Blob>. */
  function decrypt(it, progress, signal) {
    var iv = hex(it.vi);
    function counter(at) {
      var c = new Uint8Array(16), dv = new DataView(c.buffer), n = at / 16;
      c.set(iv, 0);
      dv.setUint32(8, Math.floor(n / 4294967296)); dv.setUint32(12, n >>> 0);
      return c;
    }
    return crypto.subtle.importKey('raw', hex(it.vk), 'AES-CTR', false, ['decrypt']).then(function (key) {
      return fetch(it.video, { credentials: 'same-origin', signal: signal }).then(function (r) {
        if (!r.ok || !r.body) throw new Error('HTTP ' + r.status);
        var total = +r.headers.get('Content-Length') || 0, reader = r.body.getReader();
        var SEG = 4 << 20, pending = [], have = 0, got = 0, offset = 0, parts = [], chain = Promise.resolve();
        function take(len) {
          var buf = new Uint8Array(len), p = 0;
          while (p < len) {
            var head = pending[0], n = Math.min(head.length, len - p);
            buf.set(head.subarray(0, n), p); p += n;
            if (n === head.length) pending.shift(); else pending[0] = head.subarray(n);
          }
          have -= len;
          var at = offset; offset += len;
          chain = chain.then(function () {
            return crypto.subtle.decrypt({ name: 'AES-CTR', counter: counter(at), length: 64 }, key, buf);
          }).then(function (plain) { parts.push(plain); });
        }
        function pump() {
          return reader.read().then(function (x) {
            if (x.done) { if (have) take(have); return chain; }
            pending.push(x.value); have += x.value.length; got += x.value.length;
            if (total) progress(got / total);
            while (have >= SEG) take(SEG);
            return pump();
          });
        }
        return pump().then(function () { return new Blob(parts, { type: it.vt || 'video/mp4' }); });
      });
    });
  }
  function ring(f) {
    vpct.textContent = Math.floor(f * 100) + '%';
    vring.style.strokeDashoffset = String(125.7 * (1 - f));
  }
  function playEncrypted(it) {
    var kept = blobs.filter(function (b) { return b.key === it.video; })[0];
    if (kept) { video.src = kept.url; video.play().catch(function () {}); return; }
    if (!window.crypto || !crypto.subtle || !window.ReadableStream) {
      vload.hidden = false;
      vload.classList.add('is-error');
      vload.querySelector('.lb-vload-text').innerHTML = '此瀏覽器未能播放受保護的影片，請更新瀏覽器。<span class="en">This browser cannot play protected videos — please update it.</span>';
      return;
    }
    var job = { ctl: window.AbortController ? new AbortController() : null };
    vjob = job;
    ring(0);
    vload.hidden = false;
    decrypt(it, function (f) { if (vjob === job) ring(f); }, job.ctl && job.ctl.signal).then(function (blob) {
      var url = URL.createObjectURL(blob);
      blobs.push({ key: it.video, url: url });
      while (blobs.length > 2) URL.revokeObjectURL(blobs.shift().url);
      if (vjob !== job) return;
      vjob = null;
      vload.hidden = true;
      video.src = url;
      var p = video.play();
      if (p && p.catch) p.catch(function () {});
    }).catch(function (e) {
      if (vjob !== job) return;
      vjob = null;
      vload.classList.add('is-error');
      vload.querySelector('.lb-vload-text').innerHTML = '影片未能載入，請稍後再試。<span class="en">The video could not load — please try again.</span>';
    });
  }
  function stopVideo() {
    if (vjob) { if (vjob.ctl) vjob.ctl.abort(); vjob = null; }
    vload.hidden = true;
    vload.classList.remove('is-error');
    vload.querySelector('.lb-vload-text').innerHTML = '載入影片 <span class="en">Loading video</span>';
    if (video.hidden) return;
    video.pause();
    video.removeAttribute('src');
    video.removeAttribute('poster');
    video.load();                          // stop downloading the rest
    video.hidden = true;
  }
  video.addEventListener('contextmenu', function (e) { e.preventDefault(); });
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
    if (isVideo()) return;
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
    stopVideo();
    box.classList.toggle('is-video', !!it.video);
    zoomEl.hidden = !!it.video;
    if (it.video) {
      video.setAttribute('aria-label', it.caption || '影片 Video');
      shape(+it.w || 0, +it.h || 0);
      if (it.poster) video.poster = it.poster;
      video.hidden = false;
      if (it.vk) {
        playEncrypted(it);
      } else {
        video.src = it.video;
        var p = video.play();              // a tap opened it: browsers allow sound
        if (p && p.catch) p.catch(function () {});
      }
    } else if (it.seed && window.TYTPhoto) {
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
    if (!it.seed && !it.video) { preload(current + 1); preload(current - 1); }
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
    stopVideo();
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
      var items = figures.map(function (f) {
        return { full: f.dataset.full, seed: f.dataset.seed, caption: f.dataset.caption, video: f.dataset.video, poster: f.dataset.poster,
                 vk: f.dataset.vk, vi: f.dataset.vi, vt: f.dataset.vt, w: f.dataset.w, h: f.dataset.h };
      });
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
    if (e.target === video && /^Arrow/.test(e.key)) return;   // the player's own seek keys
    if (e.key === 'ArrowLeft' && z.s === 1) go(-1);
    if (e.key === 'ArrowRight' && z.s === 1) go(1);
    if (e.key === '+' || e.key === '=') zoomTo(z.s * 1.5, undefined, undefined, true);
    if (e.key === '-') zoomTo(z.s / 1.5, undefined, undefined, true);
    if (e.key === '0') zoomTo(1, undefined, undefined, true);
  });

  // Mouse: wheel zooms around the pointer; double click toggles; drag pans when zoomed.
  stage.addEventListener('wheel', function (e) {
    if (isVideo()) return;
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
    if (isVideo()) {
      // The control bar (bottom of the video) is for the player: seeking
      // there must not swipe to the next item. No pinch or double tap.
      var vr = video.getBoundingClientRect(), y = e.touches[0].clientY;
      if (e.touches.length !== 1 || y > vr.bottom - Math.max(64, vr.height * 0.25)) { t0 = null; pinch = null; return; }
      var q = e.touches[0];
      t0 = { x: q.clientX, y: q.clientY };
      sx = q.clientX; sy = q.clientY; dx = dy = 0; horiz = null; st = Date.now();
      stage.style.transition = 'none';
      return;
    }
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
