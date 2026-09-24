/**
 * banner-tuner.js — 首頁橫幅 Home banner admin page.
 *
 * Every change is mirrored in the live preview (the real slideshow) at
 * once; nothing is stored until Save.
 *   - height, speed, effect, arrows: change the preview's settings
 *   - drag a picture (or arrow keys) to choose the point kept in view;
 *     🔍 zooms in around that point
 * The tuning frames take the same shape as the banner on the chosen
 * device, so what is inside the frame is what visitors will see.
 */
(function () {
  'use strict';
  var form = document.getElementById('bannerForm');
  if (!form) return;
  var preview = document.querySelector('#bnPreview .banner-show');
  var wrap = document.getElementById('bnPreview');
  var device = 'desktop';

  function firstCard() { return form.querySelector('.bn-card'); }
  function ratioFor(dev) {
    var whole = form.querySelector('[name="whole_' + dev + '"]').checked;
    var h = form.querySelector('[name="height_' + dev + '"]').value;
    if (!whole) return '100 / ' + h;
    var c = firstCard();
    return c ? (c.dataset.w + ' / ' + c.dataset.h) : '3 / 1';
  }
  function applyShape() {
    var d = ratioFor('desktop'), m = ratioFor('mobile');
    if (preview) {
      preview.style.setProperty('--ar-d', d);
      preview.style.setProperty('--ar-m', m);
      preview.classList.toggle('as-phone', device === 'mobile');
      preview.classList.toggle('as-desktop', device === 'desktop');
    }
    if (wrap) wrap.classList.toggle('phone', device === 'mobile');
    form.querySelectorAll('.bn-tuner').forEach(function (t) { t.style.aspectRatio = device === 'mobile' ? m : d; });
  }

  // ---- ② settings ----
  form.querySelectorAll('[data-height]').forEach(function (fs) {
    var dev = fs.dataset.height;
    var whole = fs.querySelector('[name="whole_' + dev + '"]');
    var range = fs.querySelector('input[type=range]');
    var out = fs.querySelector('output');
    whole.addEventListener('change', function () {
      fs.querySelector('.bn-range').hidden = whole.checked;
      device = dev; syncDevice(); applyShape();
    });
    range.addEventListener('input', function () {
      out.textContent = '高度 = 寬度的 ' + range.value + '% · height ' + range.value + '% of width';
      if (device !== dev) { device = dev; syncDevice(); }
      applyShape();
    });
  });
  var interval = form.querySelector('[name=interval]');
  interval.addEventListener('input', function () {
    interval.nextElementSibling.textContent = '每 ' + interval.value + ' 秒 · every ' + interval.value + ' s';
    if (preview) { preview.dataset.interval = interval.value; TYTBanner.refresh(preview); }
  });
  form.querySelectorAll('[name=effect]').forEach(function (r) {
    r.addEventListener('change', function () {
      if (!preview) return;
      preview.classList.toggle('fx-slide', r.value === 'slide' && r.checked);
      preview.classList.toggle('fx-fade', r.value === 'fade' && r.checked);
      TYTBanner.refresh(preview);
    });
  });
  form.querySelectorAll('[name=controls]').forEach(function (r) {
    r.addEventListener('change', function () { if (preview) preview.classList.toggle('no-controls', r.value === '0' && r.checked); });
  });
  function syncDevice() {
    form.querySelectorAll('[name=_device]').forEach(function (r) { r.checked = r.value === device; });
  }
  form.querySelectorAll('[name=_device]').forEach(function (r) {
    r.addEventListener('change', function () { if (r.checked) { device = r.value; applyShape(); } });
  });

  // ---- ③ each picture ----
  function previewSlide(card) {
    return preview ? preview.querySelector('[data-slide="' + card.dataset.id + '"]') : null;
  }
  function showInPreview(card) {
    var s = previewSlide(card);
    if (!s) return;
    var all = [].slice.call(preview.querySelectorAll('.banner-slide'));
    TYTBanner.go(preview, all.indexOf(s));
  }
  function paint(card) {
    var x = +card.querySelector('[data-pos=x]').value, y = +card.querySelector('[data-pos=y]').value;
    var z = +card.querySelector('[data-zoom]').value / 100;
    var css = function (img) {
      img.style.objectPosition = x + '% ' + y + '%';
      img.style.transform = 'scale(' + z + ')';
      img.style.transformOrigin = x + '% ' + y + '%';
    };
    css(card.querySelector('.bn-tuner img'));
    var cross = card.querySelector('.bn-cross');
    cross.style.left = x + '%'; cross.style.top = y + '%';
    card.querySelector('.bn-zoom output').textContent = Math.round(z * 100) + '%';
    var s = previewSlide(card);
    if (s) css(s.querySelector('img'));
  }
  function setPos(card, x, y) {
    card.querySelector('[data-pos=x]').value = Math.round(Math.max(0, Math.min(100, x)));
    card.querySelector('[data-pos=y]').value = Math.round(Math.max(0, Math.min(100, y)));
    paint(card);
  }

  form.querySelectorAll('.bn-card').forEach(function (card) {
    var tuner = card.querySelector('.bn-tuner');
    var img = tuner.querySelector('img');
    var zoom = card.querySelector('[data-zoom]');
    var start = null;

    // How far the picture can move in each direction, in pixels, so a
    // drag moves it with the finger.
    function spare() {
      var fw = tuner.clientWidth, fh = tuner.clientHeight;
      var iw = +card.dataset.w || img.naturalWidth || 1, ih = +card.dataset.h || img.naturalHeight || 1;
      var k = Math.max(fw / iw, fh / ih) * (+zoom.value / 100);
      return { x: Math.max(iw * k - fw, fw * 0.08), y: Math.max(ih * k - fh, fh * 0.08) };
    }
    tuner.addEventListener('pointerdown', function (e) {
      start = { px: e.clientX, py: e.clientY,
                x: +card.querySelector('[data-pos=x]').value, y: +card.querySelector('[data-pos=y]').value, room: spare() };
      tuner.setPointerCapture(e.pointerId);
      tuner.classList.add('dragging');
      showInPreview(card);
      e.preventDefault();
    });
    tuner.addEventListener('pointermove', function (e) {
      if (!start) return;
      setPos(card, start.x - (e.clientX - start.px) / start.room.x * 100, start.y - (e.clientY - start.py) / start.room.y * 100);
    });
    function end() { start = null; tuner.classList.remove('dragging'); }
    tuner.addEventListener('pointerup', end);
    tuner.addEventListener('pointercancel', end);
    tuner.addEventListener('keydown', function (e) {
      var d = e.shiftKey ? 10 : 2, x = +card.querySelector('[data-pos=x]').value, y = +card.querySelector('[data-pos=y]').value;
      var map = { ArrowLeft: [d, 0], ArrowRight: [-d, 0], ArrowUp: [0, d], ArrowDown: [0, -d] };
      if (!map[e.key]) return;
      e.preventDefault();
      setPos(card, x + map[e.key][0], y + map[e.key][1]);
      showInPreview(card);
    });
    zoom.addEventListener('input', function () { paint(card); showInPreview(card); });
    card.querySelector('[data-center]').addEventListener('click', function () { setPos(card, 50, 50); showInPreview(card); });
    card.querySelector('[data-reset]').addEventListener('click', function () { zoom.value = 100; setPos(card, 50, 50); showInPreview(card); });
    card.querySelector('[data-active]').addEventListener('change', function (e) { card.classList.toggle('is-off', !e.target.checked); });
    card.querySelectorAll('[data-cap]').forEach(function (inp) {
      inp.addEventListener('input', function () {
        var s = previewSlide(card);
        if (!s) return;
        var cap = s.querySelector('figcaption');
        cap.querySelector(inp.dataset.cap === 'zh' ? 'strong' : 'span').textContent = inp.value;
        cap.hidden = !card.querySelector('[data-cap=zh]').value.trim() && !card.querySelector('[data-cap=en]').value.trim();
        showInPreview(card);
      });
    });
  });

  // Keep the numbers in the card headings in step after a drag.
  var list = form.querySelector('.bn-list');
  if (list && window.MutationObserver) {
    new MutationObserver(function () {
      list.querySelectorAll('.bn-card .bn-no').forEach(function (b, i) { b.textContent = i + 1; });
    }).observe(list, { childList: true });
  }

  applyShape();
})();
