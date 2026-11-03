/**
 * Uploads in the admin area — a proper upload box, a preview of every
 * picture, and an editor to crop / zoom / rotate before it is sent.
 *
 * 1. Every <input type="file"> becomes a drop box ("選擇圖片 Choose a
 *    picture — or drop it here") instead of the browser's plain button.
 *    The real input sits invisibly on top of the box, so clicking,
 *    dropping, `required` and the normal form submit all still work.
 *    data-plain on an input leaves it as it is.
 * 2. Pictures are shown, not just their names:
 *      one picture   → a preview with 編輯 Edit / 不上傳 Remove;
 *      many pictures → thumbnails, each with ✎ (edit that one) and ×.
 *    data-no-editor: no editor (the picture is still previewed).
 * 3. data-current="<url>": the picture already saved. It is shown with
 *    "編輯目前的圖片 Edit this picture"; the edited copy goes into the
 *    input and replaces the old one when the form is saved.
 * 4. The editor: drag to move, zoom − / slider / + (or pinch / wheel),
 *    rotate ↺ ↻, crop shapes (data-aspects="original,1:1,16:9"; "自由
 *    Free" is always offered — drag the frame's corners).
 *    data-max-width="1920" caps the saved picture's longest side.
 *
 * Other pages can use the editor directly (album photos already uploaded):
 *   TYTImageEditor.edit(file, { aspects: 'original,1:1', maxWidth: 2560 }) → Promise<File|null>
 *   TYTImageEditor.fromUrl(url, name)                                     → Promise<File>
 *
 * The edited picture is drawn on a canvas in the browser and handed back
 * as a new file; the server still checks and re-encodes everything.
 */
(function () {
  'use strict';
  var CAN_EDIT = !!(window.DataTransfer && HTMLCanvasElement.prototype.toBlob);

  function say(msg) { return window.TYTDialog ? TYTDialog.alert(msg, { type: 'error' }) : alert(msg); }

  var LABELS = {
    'original': '原比例 Original', '1:1': '正方 1:1', '4:3': '4:3', '3:4': '直 3:4', '3:2': '3:2', '2:3': '直 2:3',
    '16:9': '寬 16:9', '3:1': '橫幅 3:1', 'free': '自由 Free'
  };
  var UPLOAD_SVG = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m0 0-4.5 4.5M12 4l4.5 4.5M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  var FILE_SVG = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8zM14 3v5h5" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>';

  function el(tag, cls, html) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (html != null) e.innerHTML = html;
    return e;
  }
  function btn(cls, html, title) {
    var b = el('button', cls, html);
    b.type = 'button';
    if (title) { b.title = title; b.setAttribute('aria-label', title); }
    return b;
  }
  function size(bytes) {
    return bytes > 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
  }
  /** Editor options from an input's data- attributes (data-fixed-shape: no "Free" crop, e.g. a square favicon). */
  function opts(input, maxW) {
    return { aspects: input.dataset.aspects, maxWidth: input.dataset.maxWidth || maxW, fixed: input.dataset.fixedShape !== undefined };
  }
  function isImageInput(input) { return /image/.test(input.accept || ''); }
  function filesOf(input) { return Array.prototype.slice.call(input.files || []); }

  /** Put a new list of files into an input and tell the page (counters, etc.). */
  function setFiles(input, list) {
    var dt = new DataTransfer();
    list.forEach(function (f) { dt.items.add(f); });
    input.dataset.ieBusy = '1';
    input.files = dt.files;
    input.dispatchEvent(new Event('change', { bubbles: true }));
    delete input.dataset.ieBusy;
  }

  // ------------------------------------------------------------ upload box
  function dressUp(input) {
    if (input.dataset.plain !== undefined || input.closest('.fp')) return;
    var image = isImageInput(input), many = input.multiple;
    var box = el('div', 'fp' + (image ? ' fp-image' : ''));
    var face = el('div', 'fp-face');
    var title = image
      ? (many ? '選擇相片（可一次選很多張）<span class="en">Choose photos — many at once</span>'
              : (input.hasAttribute('capture') ? '拍照或選擇相片 <span class="en">Take or choose a photo</span>'
                                               : '選擇圖片 <span class="en">Choose a picture</span>'))
      : '選擇檔案 <span class="en">Choose a file</span>';
    var types = (input.accept || '').split(',').map(function (t) {
      t = t.trim();
      return t.indexOf('image/') === 0 ? t.slice(6).replace('jpeg', 'JPG').toUpperCase() : t.replace(/^\./, '').toUpperCase();
    }).filter(function (t, i, a) { return t && t !== '*' && a.indexOf(t) === i; }).join(' · ');
    face.innerHTML = '<span class="fp-icon">' + UPLOAD_SVG + '</span>' +
      '<span class="fp-text"><strong>' + title + '</strong>' +
      '<span class="fp-sub">按這裡，或把' + (image ? '圖片' : '檔案') + '拖到這裡 · <span class="en">Tap here, or drop ' + (image ? 'pictures' : 'a file') + ' here</span></span>' +
      (types ? '<small class="fp-types">' + types + '</small>' : '') + '</span>' +
      '<span class="fp-picked" aria-live="polite"></span>';
    input.parentNode.insertBefore(box, input);
    box.appendChild(face);
    box.appendChild(input);
    input.classList.add('fp-input');
    input.classList.remove('file-input');

    var picked = face.querySelector('.fp-picked');
    function update() {
      var fs = filesOf(input);
      box.classList.toggle('has-files', fs.length > 0);
      if (!fs.length) { picked.textContent = ''; return; }
      picked.innerHTML = fs.length > 1
        ? '已選 <b>' + fs.length + '</b> 個 · ' + fs.length + ' chosen — <u>更換 Change</u>'
        : (image ? '' : FILE_SVG) + '<b></b> · ' + size(fs[0].size) + ' — <u>更換 Change</u>';
      if (fs.length === 1) picked.querySelector('b').textContent = fs[0].name;
    }
    input.addEventListener('change', update);
    ['dragenter', 'dragover'].forEach(function (t) { box.addEventListener(t, function () { box.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (t) { box.addEventListener(t, function () { box.classList.remove('is-over'); }); });
    update();
  }

  // ---------------------------------------------------------------- preview
  function previewBox(input) {
    var anchor = input.closest('.fp') || input;
    var box = anchor.nextElementSibling;
    if (!box || !box.classList.contains('ie-preview')) {
      box = el('div', 'ie-preview');
      box.hidden = true;
      anchor.insertAdjacentElement('afterend', box);
    }
    return box;
  }

  var MAX_THUMBS = 150;
  function showThumbs(input) {
    var box = previewBox(input);
    box.innerHTML = '';
    var files = filesOf(input);
    if (!files.length) { box.hidden = true; return; }
    box.hidden = false;
    var editable = CAN_EDIT && input.dataset.noEditor === undefined;
    box.appendChild(el('div', 'ie-note', '已選 ' + files.length + ' 張' + (editable ? '，可按 ✎ 裁切 / 旋轉個別相片' : '') + '。' +
      '<span class="en">' + files.length + ' chosen' + (editable ? ' — tap ✎ to crop / rotate one' : '') + '.</span>'));
    var grid = el('div', 'ie-thumbs');
    files.slice(0, MAX_THUMBS).forEach(function (f, i) {
      var fig = el('figure', 'ie-thumb');
      var img = new Image();
      img.alt = f.name;
      img.loading = 'lazy';
      img.decoding = 'async';
      img.src = URL.createObjectURL(f);
      img.onload = img.onerror = function () { URL.revokeObjectURL(img.src); };
      fig.appendChild(img);
      var cap = el('figcaption');
      cap.textContent = f.name + ' · ' + size(f.size);
      fig.appendChild(cap);
      var tools = el('div', 'ie-thumb-tools');
      if (editable && /^image\/(jpeg|png|webp|gif)$/.test(f.type)) {
        var e = btn('ie-tbtn', '✎', '裁切 / 旋轉這張 Edit this one');
        e.addEventListener('click', function () {
          edit(f, opts(input, 3200)).then(function (out) {
            if (!out) return;
            var list = filesOf(input);
            list[i] = out;
            setFiles(input, list);
          });
        });
        tools.appendChild(e);
      }
      var x = btn('ie-tbtn', '×', '不上傳這張 Leave this one out');
      x.addEventListener('click', function () {
        var list = filesOf(input);
        list.splice(i, 1);
        setFiles(input, list);
      });
      tools.appendChild(x);
      fig.appendChild(tools);
      grid.appendChild(fig);
    });
    box.appendChild(grid);
    if (files.length > MAX_THUMBS) {
      box.appendChild(el('div', 'ie-info', '另有 ' + (files.length - MAX_THUMBS) + ' 張未顯示，仍會上傳。' +
        (files.length - MAX_THUMBS) + ' more not shown — they are still uploaded.'));
    }
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
    if (CAN_EDIT && input.dataset.noEditor === undefined) {
      var e = btn('mini-btn ghost', '✎ 裁切 / 縮放 / 旋轉 <span class="en">Edit</span>');
      e.addEventListener('click', function () { openFor(input, original || file); });
      actions.appendChild(e);
    }
    var clear = btn('mini-btn ghost', '× 不上傳 <span class="en">Remove</span>');
    clear.addEventListener('click', function () { setFiles(input, []); });
    actions.appendChild(clear);
    var frame = el('div', 'ie-frame');
    frame.appendChild(img);
    box.appendChild(el('div', 'ie-note', '將上傳這張圖片（按儲存後生效）<span class="en">Will be uploaded when you save:</span>'));
    box.appendChild(frame);
    box.appendChild(info);
    box.appendChild(actions);
  }

  /** The picture already saved (data-current): show it, offer to edit it. */
  function showCurrent(input) {
    var url = input.dataset.current;
    if (!url || !CAN_EDIT || input.dataset.noEditor !== undefined) return;
    var anchor = input.closest('.fp') || input;
    var row = el('div', 'ie-current');
    var b = btn('mini-btn ghost', '✎ 編輯目前的圖片 <span class="en">Crop / rotate the current picture</span>');
    b.addEventListener('click', function () {
      b.disabled = true;
      fromUrl(url, input.dataset.currentName || 'picture').then(function (f) {
        return edit(f, opts(input));
      }).then(function (out) {
        if (out) { setFiles(input, [out]); showSingle(input, out, out); }
      }, function () {
        say('無法開啟目前的圖片。\nThe current picture could not be opened.');
      }).then(function () { b.disabled = false; });
    });
    row.appendChild(b);
    anchor.insertAdjacentElement('beforebegin', row);
  }

  /**
   * A saved picture as a file. Loaded as an <img> (not fetch): protected
   * album photos are only served to images on our own pages
   * (App\Core\Media), and a same-site <img> can still be copied to a canvas.
   */
  function fromUrl(url, name) {
    return new Promise(function (resolve, reject) {
      var img = new Image();
      img.onload = function () {
        var c = document.createElement('canvas');
        c.width = img.naturalWidth; c.height = img.naturalHeight;
        var png = /\.png(\?|&|$)|signature/i.test(url) || /\.png(%|$)/i.test(url);
        var cx = c.getContext('2d');
        if (!png) { cx.fillStyle = '#fff'; cx.fillRect(0, 0, c.width, c.height); }
        cx.drawImage(img, 0, 0);
        var type = png ? 'image/png' : 'image/jpeg';
        c.toBlob(function (blob) {
          if (!blob) return reject(new Error('blob'));
          resolve(new File([blob], String(name).replace(/\.[^.]+$/, '') + (png ? '.png' : '.jpg'), { type: type, lastModified: Date.now() }));
        }, type, 0.95);
      };
      img.onerror = function () { reject(new Error('load')); };
      img.src = url;
    });
  }

  // ----------------------------------------------------------------- editor
  var modal, canvas, ctx, zoomEl, shapeRow, state;
  var MAX_ZOOM = 6, HANDLE = 34;   // handle reach, canvas px

  function buildModal() {
    modal = el('div', 'ie-modal');
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-label', '編輯圖片 Edit picture');
    modal.hidden = true;
    modal.innerHTML =
      '<div class="ie-box">' +
      '  <div class="ie-head"><strong>編輯圖片 <span class="en">Edit picture</span></strong>' +
      '    <button type="button" class="ie-x" data-act="cancel" aria-label="關閉 Close">×</button></div>' +
      '  <div class="ie-stage"><canvas></canvas></div>' +
      '  <p class="ie-tip">拖動圖片調整位置，白框內為保留範圍；選「自由」可拖動白框四角裁切。<span class="en">Drag to move — what is inside the frame is kept. With “Free”, drag the frame’s corners to crop.</span></p>' +
      '  <div class="ie-shapes" role="group" aria-label="裁切比例 Crop shape"></div>' +
      '  <div class="ie-controls">' +
      '    <div class="ie-zoom"><button type="button" class="ie-zbtn" data-act="zoom-out" aria-label="縮小 Zoom out">−</button>' +
      '      <input type="range" min="1" max="' + MAX_ZOOM + '" step="0.01" value="1" aria-label="縮放 Zoom">' +
      '      <button type="button" class="ie-zbtn" data-act="zoom-in" aria-label="放大 Zoom in">+</button></div>' +
      '    <button type="button" class="mini-btn ghost" data-act="rotate-left" title="向左轉 Rotate left">↺ 左轉</button>' +
      '    <button type="button" class="mini-btn ghost" data-act="rotate">↻ 右轉 <span class="en">Rotate</span></button>' +
      '    <button type="button" class="mini-btn ghost" data-act="reset">重設 <span class="en">Reset</span></button>' +
      '  </div>' +
      '  <div class="ie-foot">' +
      '    <button type="button" class="mini-btn ghost btn-lg" data-act="cancel">取消 Cancel</button>' +
      '    <button type="button" class="mini-btn btn-lg" data-act="apply">✓ 使用這張 Use this picture</button>' +
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
      if (e.target === modal) return close(null);
      if (!act) return;
      var a = act.dataset.act;
      if (a === 'cancel') close(null);
      if (a === 'zoom-in') setZoom(state.zoom * 1.2);
      if (a === 'zoom-out') setZoom(state.zoom / 1.2);
      if (a === 'rotate') { state.rot = (state.rot + 90) % 360; layout(true); }
      if (a === 'rotate-left') { state.rot = (state.rot + 270) % 360; layout(true); }
      if (a === 'reset') { state.rot = 0; layout(true); }
      if (a === 'apply') apply();
    });
    document.addEventListener('keydown', function (e) { if (!modal.hidden && e.key === 'Escape') close(null); });
    window.addEventListener('resize', function () { if (!modal.hidden) layout(true); });

    // Drag to move (mouse, pen and finger alike), drag a corner to crop
    // (Free), pinch / wheel to zoom.
    var pointers = {}, pinchStart = null, corner = null;
    function pos(e) {
      var r = canvas.getBoundingClientRect(), k = canvas.width / r.width;
      return { x: (e.clientX - r.left) * k, y: (e.clientY - r.top) * k };
    }
    function cornerAt(p) {
      if (state.aspect !== 'free') return null;
      var f = state.frame, cs = [['nw', f.x, f.y], ['ne', f.x + f.w, f.y], ['sw', f.x, f.y + f.h], ['se', f.x + f.w, f.y + f.h]];
      for (var i = 0; i < cs.length; i++) {
        if (Math.abs(p.x - cs[i][1]) < HANDLE && Math.abs(p.y - cs[i][2]) < HANDLE) return cs[i][0];
      }
      return null;
    }
    canvas.addEventListener('pointerdown', function (e) {
      canvas.setPointerCapture(e.pointerId);
      pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
      corner = Object.keys(pointers).length === 1 ? cornerAt(pos(e)) : null;
    });
    canvas.addEventListener('pointermove', function (e) {
      var p = pointers[e.pointerId];
      if (!p) {
        canvas.style.cursor = cornerAt(pos(e)) ? 'nwse-resize' : '';
        return;
      }
      var ids = Object.keys(pointers);
      if (ids.length === 2) {
        pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
        var a = pointers[ids[0]], b = pointers[ids[1]];
        var d = Math.hypot(a.x - b.x, a.y - b.y);
        if (!pinchStart) pinchStart = { d: d, z: state.zoom };
        setZoom(pinchStart.z * d / pinchStart.d);
        return;
      }
      if (corner) { resizeFrame(corner, pos(e)); pointers[e.pointerId] = { x: e.clientX, y: e.clientY }; return; }
      var k = canvas.width / canvas.clientWidth;   // CSS px → canvas px
      state.ox += (e.clientX - p.x) * k;
      state.oy += (e.clientY - p.y) * k;
      pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
      clamp(); draw();
    });
    function up(e) {
      delete pointers[e.pointerId]; pinchStart = null;
      if (corner) { corner = null; rebase(); }
    }
    canvas.addEventListener('pointerup', up);
    canvas.addEventListener('pointercancel', up);
    canvas.addEventListener('wheel', function (e) {
      e.preventDefault();
      setZoom(state.zoom * (e.deltaY < 0 ? 1.08 : 1 / 1.08));
    }, { passive: false });
  }

  var pending = null;   // resolve() of the open edit()

  /** Open the editor on a file; resolves with the edited file, or null if cancelled. */
  function edit(file, o) {
    var opts = o || {};
    if (!CAN_EDIT) return Promise.resolve(null);
    if (!modal) buildModal();
    return new Promise(function (resolve) {
      var img = new Image();
      img.onload = function () {
        var aspects = String(opts.aspects || 'original,1:1,4:3,16:9').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        if (aspects.indexOf('free') < 0 && !opts.fixed) aspects.push('free');
        state = { file: file, img: img, rot: 0, zoom: 1, ox: 0, oy: 0, aspect: aspects[0],
                  maxW: parseInt(opts.maxWidth || '1920', 10) || 1920 };
        shapeRow.innerHTML = '';
        aspects.forEach(function (a) {
          var b = btn('ie-shape', LABELS[a] || a);
          b.setAttribute('aria-pressed', a === state.aspect ? 'true' : 'false');
          b.addEventListener('click', function () {
            state.aspect = a;
            shapeRow.querySelectorAll('button').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
            layout(true);
          });
          shapeRow.appendChild(b);
        });
        pending = resolve;
        modal.hidden = false;
        document.body.classList.add('ie-open');
        layout(true);
        modal.querySelector('[data-act="apply"]').focus();
      };
      img.onerror = function () {
        URL.revokeObjectURL(img.src);
        say('無法讀取這張圖片，請換一張。\nThis picture could not be read — please choose another.');
        resolve(null);
      };
      img.src = URL.createObjectURL(file);
    });
  }

  /** Editor opened from a single-picture input: the result goes back into it. */
  function openFor(input, file) {
    edit(file, opts(input)).then(function (out) {
      if (!out) return;
      setFiles(input, [out]);
      showSingle(input, out, file);
    });
  }

  function close(result) {
    modal.hidden = true;
    document.body.classList.remove('ie-open');
    if (state && state.img) URL.revokeObjectURL(state.img.src);
    if (pending) { var p = pending; pending = null; p(result); }
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
    var cx = state.frame.x + state.frame.w / 2 - canvas.width / 2, cy = state.frame.y + state.frame.h / 2 - canvas.height / 2;
    var old = state.zoom;
    state.zoom = Math.min(MAX_ZOOM, Math.max(1, z));
    // Zoom around the middle of the frame, so what you look at stays put.
    var k = state.zoom / old;
    state.ox = cx + (state.ox - cx) * k;
    state.oy = cy + (state.oy - cy) * k;
    zoomEl.value = state.zoom;
    clamp(); draw();
  }

  /** Keep the frame fully covered by the picture — no empty corners. */
  function clamp() {
    var r = rotSize(), s = state.base * state.zoom, f = state.frame;
    var hw = r.w * s / 2, hh = r.h * s / 2, cx = canvas.width / 2, cy = canvas.height / 2;
    // picture spans (cx+ox-hw … cx+ox+hw); it must reach both edges of the frame
    state.ox = Math.min(f.x - cx + hw, Math.max(f.x + f.w - cx - hw, state.ox));
    state.oy = Math.min(f.y - cy + hh, Math.max(f.y + f.h - cy - hh, state.oy));
  }

  /** Free crop: move one corner of the frame, staying on the picture. */
  function resizeFrame(which, p) {
    var f = state.frame, r = rotSize(), s = state.base * state.zoom;
    var px1 = canvas.width / 2 + state.ox - r.w * s / 2, py1 = canvas.height / 2 + state.oy - r.h * s / 2;
    var px2 = px1 + r.w * s, py2 = py1 + r.h * s;
    var x1 = f.x, y1 = f.y, x2 = f.x + f.w, y2 = f.y + f.h, min = 60;
    var lim = function (v, lo, hi) { return Math.min(hi, Math.max(lo, v)); };
    if (which.indexOf('w') >= 0) x1 = lim(p.x, Math.max(0, px1), x2 - min); else x2 = lim(p.x, x1 + min, Math.min(canvas.width, px2));
    if (which.indexOf('n') >= 0) y1 = lim(p.y, Math.max(0, py1), y2 - min); else y2 = lim(p.y, y1 + min, Math.min(canvas.height, py2));
    state.frame = { x: x1, y: y1, w: x2 - x1, h: y2 - y1 };
    draw();
  }

  /** After a free crop the picture keeps its size: zoom becomes relative to the new frame. */
  function rebase() {
    var r = rotSize(), s = state.base * state.zoom, f = state.frame;
    state.base = Math.max(f.w / r.w, f.h / r.h);
    state.zoom = Math.max(1, s / state.base);
    zoomEl.value = Math.min(MAX_ZOOM, state.zoom);
    clamp(); draw();
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
    // Free crop: corner handles to drag.
    if (state.aspect === 'free') {
      ctx.fillStyle = '#fff';
      [[f.x, f.y], [f.x + f.w, f.y], [f.x, f.y + f.h], [f.x + f.w, f.y + f.h]].forEach(function (c) {
        ctx.fillRect(c[0] - 9, c[1] - 9, 18, 18);
      });
    }
  }

  /** Render only the framed part at full quality and hand it back as a file. */
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
      if (!blob) { say('無法處理這張圖片。\nThis picture could not be processed.'); return; }
      var name = state.file.name.replace(/\.[^.]+$/, '') + (type === 'image/png' ? '.png' : '.jpg');
      close(new File([blob], name, { type: type, lastModified: Date.now() }));
    }, type, 0.92);
  }

  // ------------------------------------------------------------------ wire
  function wire(input) {
    if (input.dataset.ieWired) return;
    input.dataset.ieWired = '1';
    dressUp(input);
    if (!isImageInput(input)) return;
    showCurrent(input);
    input.addEventListener('change', function () {
      if (input.multiple) return showThumbs(input);
      if (input.dataset.ieBusy) {                    // our own setFiles(): keep the preview in step
        if (!filesOf(input).length) { previewBox(input).hidden = true; }
        return;
      }
      var f = input.files && input.files[0];
      if (!f) { previewBox(input).hidden = true; return; }
      if (!/^image\//.test(f.type)) return;
      showSingle(input, f, f);
      if (CAN_EDIT && input.dataset.noEditor === undefined) openFor(input, f);
    });
  }
  document.querySelectorAll('input[type="file"]').forEach(wire);

  window.TYTImageEditor = { edit: edit, fromUrl: fromUrl };
})();
