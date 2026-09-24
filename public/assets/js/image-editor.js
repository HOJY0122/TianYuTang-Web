/**
 * Image editor for admin uploads — see the picture before it is sent,
 * and crop / zoom / rotate it right in the browser.
 *
 * Works on any <input type="file" accept="image/…"> in the admin area.
 * Options, as data- attributes on the input:
 *   data-aspects="original,1:1,16:9"  crop shapes offered (first = default)
 *   data-max-width="1920"             longest side of the saved picture
 *   data-no-editor                    leave this input alone
 * A `multiple` input gets thumbnails only (editing 30 photos one by one
 * would be a chore); a single-picture input opens the editor on choosing.
 *
 * How it works: the chosen file is drawn on a canvas; the white frame is
 * the part that will be kept. Drag the picture to move it, use the slider
 * (or pinch / mouse wheel) to zoom. "Use this picture" draws only the
 * framed part onto a new canvas and puts that file back into the input,
 * so the normal form submit uploads the edited picture — the server needs
 * no changes and still checks and re-encodes everything it receives.
 */
(function () {
  'use strict';
  if (!window.DataTransfer || !HTMLCanvasElement.prototype.toBlob) return;   // very old browser: plain upload still works

  function say(msg) { return window.TYTDialog ? TYTDialog.alert(msg, { type: 'error' }) : alert(msg); }

  var LABELS = {
    'original': '原比例 Original', '1:1': '正方 1:1', '4:3': '4:3', '3:4': '直 3:4',
    '16:9': '寬 16:9', '3:1': '橫幅 3:1', 'free': '自由 Free'
  };

  function el(tag, cls, html) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (html != null) e.innerHTML = html;
    return e;
  }

  // ---------------------------------------------------------------- preview
  function previewBox(input) {
    var box = input.parentNode.querySelector('.ie-preview[data-for="' + input.name + '"]');
    if (!box) {
      box = el('div', 'ie-preview');
      box.dataset.for = input.name;
      input.insertAdjacentElement('afterend', box);
    }
    return box;
  }

  function showThumbs(input) {
    var box = previewBox(input);
    box.innerHTML = '';
    var files = Array.prototype.slice.call(input.files || []);
    if (!files.length) { box.hidden = true; return; }
    box.hidden = false;
    var grid = el('div', 'ie-thumbs');
    files.slice(0, 60).forEach(function (f) {
      var fig = el('figure', 'ie-thumb');
      var img = new Image();
      img.alt = f.name;
      img.src = URL.createObjectURL(f);
      img.onload = function () { URL.revokeObjectURL(img.src); };
      fig.appendChild(img);
      fig.appendChild(el('figcaption', null, '')).textContent = f.name + ' · ' + size(f.size);
      grid.appendChild(fig);
    });
    box.appendChild(el('div', 'ie-note', '已選 ' + files.length + ' 張，上傳前預覽。' + files.length + ' selected — preview before upload.'));
    box.appendChild(grid);
  }

  function showSingle(input, file, original) {
    var box = previewBox(input);
    box.innerHTML = '';
    box.hidden = false;
    var img = new Image();
    img.src = URL.createObjectURL(file);
    img.alt = '預覽 Preview';
    var info = el('div', 'ie-info');
    img.onload = function () {
      info.textContent = file.name + ' · ' + img.naturalWidth + '×' + img.naturalHeight + 'px · ' + size(file.size);
    };
    var actions = el('div', 'ie-actions');
    var edit = el('button', 'mini-btn ghost', '✏️ 裁切 / 縮放 / 旋轉 Edit');
    edit.type = 'button';
    edit.addEventListener('click', function () { openEditor(input, original || file); });
    var clear = el('button', 'mini-btn ghost', '✖ 不上傳 Remove');
    clear.type = 'button';
    clear.addEventListener('click', function () {
      input.value = '';
      box.hidden = true; box.innerHTML = '';
    });
    actions.appendChild(edit);
    actions.appendChild(clear);
    var frame = el('div', 'ie-frame');
    frame.appendChild(img);
    box.appendChild(el('div', 'ie-note', '✅ 將上傳這張圖片（儲存後生效）Will be uploaded when you save:'));
    box.appendChild(frame);
    box.appendChild(info);
    box.appendChild(actions);
  }

  function size(bytes) {
    return bytes > 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
  }

  // ----------------------------------------------------------------- editor
  var modal, canvas, ctx, zoomEl, shapeRow, state;

  function buildModal() {
    modal = el('div', 'ie-modal');
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-label', '編輯圖片 Edit picture');
    modal.hidden = true;
    modal.innerHTML =
      '<div class="ie-box">' +
      '  <div class="ie-head"><strong>✏️ 編輯圖片 <span class="en">Edit picture</span></strong>' +
      '    <button type="button" class="ie-x" data-act="cancel" aria-label="關閉 Close">×</button></div>' +
      '  <div class="ie-stage"><canvas></canvas></div>' +
      '  <p class="ie-tip">拖動圖片調整位置，白框內為保留範圍。Drag to move — what is inside the frame is kept.</p>' +
      '  <div class="ie-shapes" role="group" aria-label="裁切比例 Crop shape"></div>' +
      '  <div class="ie-controls">' +
      '    <label class="ie-zoom">🔍 縮放 Zoom <input type="range" min="1" max="4" step="0.01" value="1"></label>' +
      '    <button type="button" class="mini-btn ghost" data-act="rotate">↻ 旋轉 Rotate</button>' +
      '    <button type="button" class="mini-btn ghost" data-act="reset">↺ 重設 Reset</button>' +
      '  </div>' +
      '  <div class="ie-foot">' +
      '    <button type="button" class="mini-btn ghost btn-lg" data-act="cancel">取消 Cancel</button>' +
      '    <button type="button" class="mini-btn btn-lg" data-act="apply">✅ 使用這張 Use this picture</button>' +
      '  </div>' +
      '</div>';
    document.body.appendChild(modal);
    canvas = modal.querySelector('canvas');
    ctx = canvas.getContext('2d');
    zoomEl = modal.querySelector('.ie-zoom input');
    shapeRow = modal.querySelector('.ie-shapes');

    zoomEl.addEventListener('input', function () { setZoom(parseFloat(zoomEl.value)); });
    modal.addEventListener('click', function (e) {
      var act = e.target.closest && e.target.closest('[data-act]');
      if (e.target === modal) return close();
      if (!act) return;
      var a = act.dataset.act;
      if (a === 'cancel') close();
      if (a === 'rotate') { state.rot = (state.rot + 90) % 360; layout(true); }
      if (a === 'reset') { state.rot = 0; layout(true); }
      if (a === 'apply') apply();
    });
    document.addEventListener('keydown', function (e) { if (!modal.hidden && e.key === 'Escape') close(); });

    // Drag to move (mouse, pen and finger alike), pinch / wheel to zoom.
    var pointers = {}, pinchStart = null;
    canvas.addEventListener('pointerdown', function (e) {
      canvas.setPointerCapture(e.pointerId);
      pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
    });
    canvas.addEventListener('pointermove', function (e) {
      var p = pointers[e.pointerId];
      if (!p) return;
      var ids = Object.keys(pointers);
      if (ids.length === 2) {
        pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
        var a = pointers[ids[0]], b = pointers[ids[1]];
        var d = Math.hypot(a.x - b.x, a.y - b.y);
        if (!pinchStart) pinchStart = { d: d, z: state.zoom };
        setZoom(pinchStart.z * d / pinchStart.d);
        return;
      }
      var k = canvas.width / canvas.clientWidth;   // CSS px → canvas px
      state.ox += (e.clientX - p.x) * k;
      state.oy += (e.clientY - p.y) * k;
      pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
      clamp(); draw();
    });
    function up(e) { delete pointers[e.pointerId]; pinchStart = null; }
    canvas.addEventListener('pointerup', up);
    canvas.addEventListener('pointercancel', up);
    canvas.addEventListener('wheel', function (e) {
      e.preventDefault();
      setZoom(state.zoom * (e.deltaY < 0 ? 1.08 : 1 / 1.08));
    }, { passive: false });
  }

  function openEditor(input, file) {
    if (!modal) buildModal();
    var img = new Image();
    img.onload = function () {
      var aspects = (input.dataset.aspects || 'original,1:1,4:3,16:9').split(',');
      state = { input: input, file: file, img: img, rot: 0, zoom: 1, ox: 0, oy: 0, aspect: aspects[0],
                maxW: parseInt(input.dataset.maxWidth || '1920', 10) };
      shapeRow.innerHTML = '';
      aspects.forEach(function (a) {
        var b = el('button', 'ie-shape');
        b.type = 'button';
        b.textContent = LABELS[a] || a;
        b.setAttribute('aria-pressed', a === state.aspect ? 'true' : 'false');
        b.addEventListener('click', function () {
          state.aspect = a;
          shapeRow.querySelectorAll('button').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
          layout(true);
        });
        shapeRow.appendChild(b);
      });
      shapeRow.hidden = aspects.length < 2;
      modal.hidden = false;
      document.body.classList.add('ie-open');
      layout(true);
      modal.querySelector('[data-act="apply"]').focus();
    };
    img.onerror = function () { say('無法讀取這張圖片，請換一張。\nThis picture could not be read — please choose another.'); };
    img.src = URL.createObjectURL(file);
  }

  function close() {
    modal.hidden = true;
    document.body.classList.remove('ie-open');
    if (state && state.img) URL.revokeObjectURL(state.img.src);
  }

  /** Picture size after rotation. */
  function rotSize() {
    var w = state.img.naturalWidth, h = state.img.naturalHeight;
    return state.rot % 180 ? { w: h, h: w } : { w: w, h: h };
  }

  /** Size the canvas and the crop frame for the current shape. */
  function layout(resetView) {
    var stage = modal.querySelector('.ie-stage');
    var cw = Math.max(280, stage.clientWidth) * 2, ch = Math.max(220, stage.clientHeight) * 2;   // 2× for sharp edges
    canvas.width = cw; canvas.height = ch;
    var r = rotSize();
    var ratio = state.aspect === 'original' || state.aspect === 'free' ? r.w / r.h
      : (function (p) { return parseFloat(p[0]) / parseFloat(p[1]); })(state.aspect.split(':'));
    var pad = 0.08;
    var fw = cw * (1 - 2 * pad), fh = fw / ratio;
    if (fh > ch * (1 - 2 * pad)) { fh = ch * (1 - 2 * pad); fw = fh * ratio; }
    state.frame = { x: (cw - fw) / 2, y: (ch - fh) / 2, w: fw, h: fh };
    // Smallest zoom = the picture just covers the frame.
    state.base = Math.max(fw / r.w, fh / r.h);
    if (resetView) { state.zoom = 1; state.ox = 0; state.oy = 0; zoomEl.value = 1; }
    clamp(); draw();
  }

  function setZoom(z) {
    state.zoom = Math.min(4, Math.max(1, z));
    zoomEl.value = state.zoom;
    clamp(); draw();
  }

  /** Keep the frame fully covered by the picture — no empty corners. */
  function clamp() {
    var r = rotSize(), s = state.base * state.zoom;
    var mx = Math.max(0, (r.w * s - state.frame.w) / 2), my = Math.max(0, (r.h * s - state.frame.h) / 2);
    state.ox = Math.min(mx, Math.max(-mx, state.ox));
    state.oy = Math.min(my, Math.max(-my, state.oy));
  }

  /** Draw the picture, rotated, scaled and moved, onto a 2D context. */
  function paint(c, cx, cy, s) {
    c.save();
    c.translate(cx, cy);
    c.rotate(state.rot * Math.PI / 180);
    c.scale(s, s);
    c.drawImage(state.img, -state.img.naturalWidth / 2, -state.img.naturalHeight / 2);
    c.restore();
  }

  function draw() {
    var f = state.frame, s = state.base * state.zoom;
    ctx.fillStyle = '#2b211b';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    paint(ctx, canvas.width / 2 + state.ox, canvas.height / 2 + state.oy, s);
    // Dim everything outside the frame, then outline it.
    ctx.fillStyle = 'rgba(20,12,8,.6)';
    ctx.beginPath();
    ctx.rect(0, 0, canvas.width, canvas.height);
    ctx.rect(f.x, f.y, f.w, f.h);
    ctx.fill('evenodd');
    ctx.strokeStyle = '#fff';
    ctx.lineWidth = 3;
    ctx.strokeRect(f.x, f.y, f.w, f.h);
    // Rule-of-thirds guides help line up a face or a title.
    ctx.strokeStyle = 'rgba(255,255,255,.35)';
    ctx.lineWidth = 1;
    for (var i = 1; i < 3; i++) {
      ctx.beginPath(); ctx.moveTo(f.x + f.w * i / 3, f.y); ctx.lineTo(f.x + f.w * i / 3, f.y + f.h); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(f.x, f.y + f.h * i / 3); ctx.lineTo(f.x + f.w, f.y + f.h * i / 3); ctx.stroke();
    }
  }

  /** Render only the framed part at full quality and put it in the input. */
  function apply() {
    var f = state.frame, s = state.base * state.zoom;
    // Size of the framed region in ORIGINAL picture pixels, capped at maxW.
    var srcW = f.w / s, srcH = f.h / s;
    var k = Math.min(1, state.maxW / Math.max(srcW, srcH));
    var out = document.createElement('canvas');
    out.width = Math.max(1, Math.round(srcW * k));
    out.height = Math.max(1, Math.round(srcH * k));
    var oc = out.getContext('2d');
    var type = /png|gif|webp/.test(state.file.type) ? 'image/png' : 'image/jpeg';   // keep transparency for logos
    if (type === 'image/jpeg') { oc.fillStyle = '#fff'; oc.fillRect(0, 0, out.width, out.height); }
    oc.imageSmoothingQuality = 'high';
    var q = out.width / f.w;   // frame px → output px
    paint(oc, (canvas.width / 2 + state.ox - f.x) * q, (canvas.height / 2 + state.oy - f.y) * q, s * q);
    out.toBlob(function (blob) {
      if (!blob) return say('無法處理這張圖片。\nThis picture could not be processed.');
      var name = state.file.name.replace(/\.[^.]+$/, '') + (type === 'image/png' ? '.png' : '.jpg');
      var edited = new File([blob], name, { type: type, lastModified: Date.now() });
      var dt = new DataTransfer();
      dt.items.add(edited);
      state.input.dataset.ieBusy = '1';
      state.input.files = dt.files;
      delete state.input.dataset.ieBusy;
      showSingle(state.input, edited, state.file);
      close();
    }, type, 0.9);
  }

  // ------------------------------------------------------------------ wire
  function wire(input) {
    if (input.dataset.noEditor !== undefined || input.dataset.ieWired) return;
    input.dataset.ieWired = '1';
    input.addEventListener('change', function () {
      if (input.dataset.ieBusy) return;
      if (input.multiple) return showThumbs(input);
      var f = input.files && input.files[0];
      if (!f) { var b = previewBox(input); b.hidden = true; return; }
      if (!/^image\//.test(f.type)) return;
      showSingle(input, f, f);
      openEditor(input, f);
    });
  }
  document.querySelectorAll('input[type="file"][accept*="image"]').forEach(wire);
})();
