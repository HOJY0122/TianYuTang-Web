/*
 * Album videos (admin → 相簿管理 Photos).
 *
 *  ① Pick or drop a video. It plays right here (nothing is sent yet);
 *    its length and size are shown, and a cover picture is taken from it
 *    automatically — "用目前畫面 Use this frame" takes the frame on screen.
 *  ② Upload: the video goes up in pieces (App\Core\VideoUploader), so
 *    big phone videos pass every server limit. A bar shows how far, how
 *    fast and how long is left; a piece that fails is tried again.
 *  ③ On a video already in the album, "播放 Play" opens it to watch, and
 *    to pick a new cover from any frame.
 *
 * If this browser cannot play the file (often iPhone "High Efficiency"
 * HEVC video), it says so: many visitors could not play it either.
 */
(function () {
  'use strict';
  var me = document.currentScript;
  var form = document.getElementById('videoUpload');
  if (!form || !window.fetch || !window.FormData || !HTMLCanvasElement.prototype.toBlob) return;

  var $ = function (id) { return document.getElementById(id); };
  var input = $('videoFile'), drop = $('videoDrop'), card = $('videoCard'), preview = $('videoPreview'),
      cover = $('videoCover'), warn = $('videoWarn'), sendBtn = $('videoSend'), cancelBtn = $('videoCancel'),
      grabBtn = $('videoGrab'), bar = $('videoBar'), pct = $('videoPct'), speed = $('videoSpeed'), prog = $('videoProgress');
  var csrf = form.querySelector('input[name="csrf_token"]').value;
  var MAX = +form.dataset.max;
  var file = null, url = null, poster = null, playable = false, busy = false, xhr = null, uploadId = null, cancelled = false;

  function mb(n) { return (n / 1048576).toFixed(n < 10485760 ? 1 : 0) + ' MB'; }
  function clock(s) {
    s = Math.max(0, Math.round(s));
    return s >= 3600 ? Math.floor(s / 3600) + ':' + ('0' + Math.floor(s % 3600 / 60)).slice(-2) + ':' + ('0' + s % 60).slice(-2)
                     : Math.floor(s / 60) + ':' + ('0' + s % 60).slice(-2);
  }
  function alertBox(msg, type) { return window.TYTDialog ? TYTDialog.alert(msg, { type: type || 'error' }) : Promise.resolve(alert(msg)); }
  function setWarn(html) { warn.hidden = !html; warn.innerHTML = html || ''; }

  /** Draw the video's current frame (longest side ≤ 1600) → Promise<Blob>. */
  function frame(v) {
    var w = v.videoWidth, h = v.videoHeight;
    if (!w || !h) return Promise.reject(new Error('no frame'));
    var k = Math.min(1, 1600 / Math.max(w, h)), c = document.createElement('canvas');
    c.width = Math.round(w * k); c.height = Math.round(h * k);
    c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
    return new Promise(function (ok, bad) {
      c.toBlob(function (b) { c.width = c.height = 0; b ? ok(b) : bad(new Error('no frame')); }, 'image/jpeg', 0.9);
    });
  }
  /** A plain cover when the video cannot be decoded here: temple red, a play mark and the name. */
  function plainCover(name) {
    var c = document.createElement('canvas'), x = c.getContext('2d');
    c.width = 1280; c.height = 720;
    var g = x.createLinearGradient(0, 0, 0, 720); g.addColorStop(0, '#9f211b'); g.addColorStop(1, '#5a120d');
    x.fillStyle = g; x.fillRect(0, 0, 1280, 720);
    x.strokeStyle = '#f3df9c'; x.lineWidth = 6; x.strokeRect(28, 28, 1224, 664);
    x.fillStyle = '#fff3c2'; x.beginPath(); x.moveTo(590, 280); x.lineTo(590, 440); x.lineTo(720, 360); x.closePath(); x.fill();
    x.font = '600 40px sans-serif'; x.textAlign = 'center'; x.fillText(name.slice(0, 40), 640, 540);
    return new Promise(function (ok) { c.toBlob(ok, 'image/jpeg', 0.9); });
  }
  function showCover(blob) {
    poster = blob;
    var img = new Image(), u = URL.createObjectURL(blob);
    img.onload = function () {
      var w = cover.width, h = Math.round(w * img.naturalHeight / img.naturalWidth);
      cover.height = Math.min(h, 200);
      var ctx = cover.getContext('2d');
      ctx.clearRect(0, 0, cover.width, cover.height);
      var k = Math.max(cover.width / img.naturalWidth, cover.height / img.naturalHeight);
      ctx.drawImage(img, (cover.width - img.naturalWidth * k) / 2, (cover.height - img.naturalHeight * k) / 2, img.naturalWidth * k, img.naturalHeight * k);
      URL.revokeObjectURL(u);
    };
    img.src = u;
  }

  function reset() {
    if (url) URL.revokeObjectURL(url);
    preview.removeAttribute('src'); preview.load();
    file = url = poster = null; playable = false; uploadId = null;
    card.hidden = true; drop.hidden = false; prog.hidden = true; setWarn('');
    card.classList.remove('is-portrait', 'is-done', 'is-busy');
    input.value = ''; $('videoCaption').value = '';
  }

  function choose(f) {
    if (busy || !f) return;
    if (!/^video\//.test(f.type) && !/\.(mp4|mov|m4v|webm)$/i.test(f.name)) {
      alertBox('這不是影片檔。請選 MP4、MOV 或 WebM。\nThat is not a video file — choose an MP4, MOV or WebM.');
      return;
    }
    if (f.size > MAX) {
      alertBox('影片 ' + mb(f.size) + '，超過 ' + mb(MAX) + '。請先剪短或壓縮。\nThe video is ' + mb(f.size) + ', over ' + mb(MAX) + '. Trim or compress it first.');
      return;
    }
    reset();
    file = f;
    url = URL.createObjectURL(f);
    $('videoName').textContent = f.name;
    $('videoMeta').textContent = mb(f.size);
    drop.hidden = true; card.hidden = false;
    sendBtn.disabled = true;
    var done = false;
    preview.onloadedmetadata = function () {
      playable = !!preview.videoWidth;
      var vw = preview.videoWidth, vh = preview.videoHeight, tall = vh > vw;
      card.classList.toggle('is-portrait', playable && tall);
      $('videoMeta').innerHTML = '';
      $('videoMeta').appendChild(document.createTextNode(mb(f.size) + ' · ' + clock(preview.duration || 0) + (vw ? ' · ' + vw + '×' + vh : '')));
      if (vw) {
        var o = document.createElement('span');
        o.className = 'vid-orient' + (tall ? ' is-tall' : vw === vh ? ' is-square' : '');
        o.innerHTML = tall ? '直式 <span class="en">Vertical</span>' : vw === vh ? '正方 <span class="en">Square</span>' : '橫式 <span class="en">Horizontal</span>';
        $('videoMeta').appendChild(o);
      }
      if (preview.duration > 600) setWarn('這段影片超過 10 分鐘，訪客用手機看會很慢。可以先剪短嗎？<span class="en">Over 10 minutes — slow for visitors on phones. Could you trim it?</span>');
      // A frame a little way in (the first is often black).
      try { preview.currentTime = Math.min(1, (preview.duration || 0) / 10); } catch (e) {}
    };
    preview.onseeked = function () {
      if (done) return;
      done = true;
      frame(preview).then(showCover, function () {}).then(function () { sendBtn.disabled = false; });
    };
    preview.onerror = function () {
      if (done) return;
      done = true;
      playable = false;
      setWarn('<strong>這個瀏覽器播放不了這段影片</strong>（可能是 iPhone 的 HEVC「高效率」格式），很多訪客也會播放不了。建議改用 MP4 (H.264)：iPhone 設定 → 相機 → 格式 → 最相容。仍可上傳。'
        + '<span class="en"><strong>This browser cannot play this video</strong> (often iPhone HEVC "High Efficiency"), so many visitors could not either. Best to use MP4 (H.264). You can still upload it.</span>');
      grabBtn.disabled = true;
      plainCover(f.name).then(showCover).then(function () { sendBtn.disabled = false; });
    };
    grabBtn.disabled = false;
    preview.src = url;
  }

  preview.addEventListener('contextmenu', function (e) { e.preventDefault(); });
  input.addEventListener('change', function () { choose(input.files[0]); });
  ['dragenter', 'dragover'].forEach(function (t) {
    drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('is-over'); });
  });
  ['dragleave', 'drop'].forEach(function (t) {
    drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.remove('is-over'); });
  });
  drop.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files[0]) choose(e.dataTransfer.files[0]); });
  grabBtn.addEventListener('click', function () {
    frame(preview).then(function (b) { showCover(b); grabBtn.classList.add('is-done'); setTimeout(function () { grabBtn.classList.remove('is-done'); }, 900); },
      function () { alertBox('這一格未能擷取，請播放一下再試。\nCould not take this frame — play a moment and try again.'); });
  });

  // ---------- sending ----------
  function post(action, fields, onProgress) {
    return new Promise(function (ok, bad) {
      var fd = new FormData();
      fd.append('csrf_token', csrf);
      Object.keys(fields).forEach(function (k) {
        var v = fields[k];
        if (v instanceof Blob) fd.append(k, v, k === 'poster' ? 'cover.jpg' : 'piece'); else fd.append(k, v);
      });
      xhr = new XMLHttpRequest();
      xhr.open('POST', action);
      xhr.setRequestHeader('Accept', 'application/json');
      if (onProgress) xhr.upload.onprogress = function (e) { if (e.lengthComputable) onProgress(e.loaded); };
      xhr.onload = function () {
        var j = null;
        try { j = JSON.parse(xhr.responseText); } catch (e) {}
        if (j && j.ok) ok(j);
        else { var err = new Error((j && j.error) || ('伺服器錯誤 Server error ' + xhr.status)); err.final = !!j || xhr.status === 413 || xhr.status === 403; bad(err); }
      };
      xhr.onerror = function () { bad(new Error('網絡中斷 Connection lost')); };
      xhr.onabort = function () { var e = new Error('cancelled'); e.cancelled = true; bad(e); };
      xhr.send(fd);
    });
  }
  function retry(fn, tries) {
    return fn().catch(function (e) {
      if (e.cancelled || e.final || tries <= 1 || cancelled) throw e;
      speed.textContent = '網絡不穩，重試中… Retrying…';
      return new Promise(function (r) { setTimeout(r, 2500); }).then(function () { return retry(fn, tries - 1); });
    });
  }

  sendBtn.addEventListener('click', function () {
    if (busy || !file || !poster) return;
    busy = true; cancelled = false;
    sendBtn.disabled = true; grabBtn.disabled = true; input.disabled = true;
    form.querySelectorAll('#videoCat, #videoCaption').forEach(function (el) { el.disabled = true; });
    prog.hidden = false; card.classList.add('is-busy');
    var total = file.size, t0 = Date.now();
    function show(sent) {
      var p = Math.min(100, sent / total * 100), secs = (Date.now() - t0) / 1000, rate = sent / Math.max(secs, 0.5);
      bar.style.width = p.toFixed(1) + '%';
      pct.textContent = Math.floor(p) + '%';
      speed.textContent = mb(sent) + ' / ' + mb(total) + (secs > 2 && p < 100 ? ' · ' + mb(rate) + '/s · 尚餘 ' + clock((total - sent) / rate) + ' left' : '');
    }
    show(0);
    post(form.dataset.start, { event_id: form.event_id.value, size: total }).then(function (j) {
      uploadId = j.id;
      var size = j.chunk, offset = 0;
      function next() {
        if (cancelled) { var e = new Error('cancelled'); e.cancelled = true; throw e; }
        if (offset >= total) return null;
        var piece = file.slice(offset, Math.min(total, offset + size)), from = offset;
        return retry(function () {
          return post(form.dataset.chunk, { id: uploadId, offset: from, chunk: piece }, function (n) { show(from + n); });
        }, 4).then(function (r) { offset = r.received; show(offset); return next(); });
      }
      return next();
    }).then(function () {
      pct.textContent = '100%'; speed.textContent = '完成中… Finishing…';
      return retry(function () {
        return post(form.dataset.finish, {
          id: uploadId, event_id: form.event_id.value, category_id: $('videoCat').value,
          caption: $('videoCaption').value, duration: isFinite(preview.duration) ? preview.duration : 0, poster: poster,
          width: preview.videoWidth || 0, height: preview.videoHeight || 0
        });
      }, 2);
    }).then(function () {
      speed.textContent = '已上傳 Uploaded';
      card.classList.add('is-done');
      setTimeout(function () { location.reload(); }, 600);
    }).catch(function (e) {
      busy = false;
      card.classList.remove('is-busy');
      sendBtn.disabled = false; grabBtn.disabled = !playable; input.disabled = false;
      form.querySelectorAll('#videoCat, #videoCaption').forEach(function (el) { el.disabled = false; });
      if (uploadId) { post(form.dataset.abort, { id: uploadId }).catch(function () {}); uploadId = null; }
      if (e.cancelled) { reset(); return; }
      prog.hidden = true;
      alertBox('影片未上傳：' + e.message + '\nThe video was not uploaded.');
    });
  });
  cancelBtn.addEventListener('click', function () {
    if (!busy) { reset(); return; }
    cancelled = true;
    if (xhr) xhr.abort();
  });
  window.addEventListener('beforeunload', function (e) { if (busy) { e.preventDefault(); e.returnValue = ''; } });

  // ---------- ③ a video already in the album: watch it, pick a new cover ----------
  var dlg = null;
  function openPlayer(btn) {
    if (!dlg) {
      dlg = document.createElement('div');
      dlg.className = 'vid-modal';
      dlg.setAttribute('role', 'dialog');
      dlg.setAttribute('aria-modal', 'true');
      dlg.setAttribute('aria-label', '影片 Video');
      dlg.innerHTML = '<div class="vid-modal-box"><video controls playsinline preload="metadata" controlslist="nodownload" disablepictureinpicture></video>'
        + '<p class="help">拖到想要的畫面，按「用目前畫面當封面」。<span class="en">Go to the frame you like, then use it as the cover.</span></p>'
        + '<div class="vid-modal-actions"><button type="button" class="primary" data-grab>用目前畫面當封面 <span class="en">Use as cover</span></button>'
        + '<button type="button" class="mini-btn ghost" data-close>關閉 <span class="en">Close</span></button></div></div>';
      document.body.appendChild(dlg);
      var mv = dlg.querySelector('video');
      mv.addEventListener('contextmenu', function (e) { e.preventDefault(); });
      mv.addEventListener('loadedmetadata', function () { dlg.classList.toggle('is-portrait', mv.videoHeight > mv.videoWidth); });
      dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('[data-close]')) dlg.close(); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !dlg.hidden) dlg.close(); });
    }
    var v = dlg.querySelector('video'), grab = dlg.querySelector('[data-grab]'), item = btn.closest('.admin-photo');
    function close() { v.pause(); v.removeAttribute('src'); v.load(); dlg.hidden = true; document.body.style.overflow = ''; btn.focus(); }
    dlg.close = close;
    dlg.hidden = false;
    document.body.style.overflow = 'hidden';
    v.src = btn.dataset.video;
    v.play().catch(function () {});
    grab.disabled = false;
    grab.onclick = function () {
      grab.disabled = true;
      v.pause();
      frame(v).then(function (b) {
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('photo_id', btn.dataset.videoCover);
        fd.append('photo', b, 'cover.jpg');
        return fetch(me.dataset.replace, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
          .then(function (r) { return r.json().catch(function () { return { ok: false, error: r.status }; }); });
      }).then(function (j) {
        if (!j.ok) throw new Error(j.error || '未成功 Failed');
        item.querySelector('.drag-area img').src = j.thumb;
        close();
      }).catch(function (err) {
        grab.disabled = false;
        alertBox('封面未更新：' + err.message + '\nThe cover was not changed.');
      });
    };
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-video-cover]');
    if (b) openPlayer(b);
  });
})();
