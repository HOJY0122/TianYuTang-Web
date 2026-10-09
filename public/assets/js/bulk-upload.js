/*
 * Bulk upload — 50, 100, 300 pictures in one go (album photos, receipt books).
 *
 * The server only ever sees ONE picture per request, so the host's limits
 * (max_file_uploads, post_max_size, nginx's client_max_body_size) no
 * longer decide how many can be chosen at once. Each picture is:
 *
 *   1. shrunk in the browser first (longest side → maxSide, JPEG) — the
 *      server resizes to 1600–2000px anyway, so sending a 12 MB original
 *      over a phone's data line only wastes time;
 *   2. sent with fetch, a few at a time, asking for a JSON reply;
 *   3. ticked off in a list with a progress bar. One bad file does not
 *      stop the rest; the failed ones can be tried again.
 *
 *   TYTBulk.shrink(file, maxSide)      → Promise<File>
 *   TYTBulk.send(url, formData)        → Promise<json>  (rejects with a readable Error)
 *   TYTBulk.queue(items, worker, n)    → Promise        (runs worker(item, i), n at a time)
 *   TYTBulk.limiter(n)                 → run(fn) → Promise  (at most n fn()s at once — e.g. AI reads)
 *   TYTBulk.panel(container, total)    → { row(name), step(ok), finish() }
 */
(function () {
  'use strict';

  function shrink(file, maxSide) {
    maxSide = maxSide || 2400;
    // GIF may be animated; anything not an image is left for the server to refuse.
    if (!/^image\/(jpeg|png|webp)$/.test(file.type) || !HTMLCanvasElement.prototype.toBlob) {
      return Promise.resolve(file);
    }
    return load(file).then(function (src) {
      var w = src.width, h = src.height, scale = Math.min(1, maxSide / Math.max(w, h));
      if (scale === 1 && file.size <= 1.5 * 1024 * 1024) { close(src); return file; }
      var c = document.createElement('canvas');
      c.width = Math.round(w * scale); c.height = Math.round(h * scale);
      var ctx = c.getContext('2d');
      ctx.fillStyle = '#fff';                       // transparent PNG → white, not black, as JPEG
      ctx.fillRect(0, 0, c.width, c.height);
      ctx.drawImage(src, 0, 0, c.width, c.height);
      close(src);
      return new Promise(function (resolve) {
        c.toBlob(function (blob) {
          c.width = c.height = 0;
          if (!blob || blob.size >= file.size) { resolve(file); return; }
          resolve(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
        }, 'image/jpeg', 0.88);
      });
    }).catch(function () { return file; });         // cannot decode here (e.g. HEIC): send as is
  }

  // createImageBitmap turns phone photos the right way up (EXIF) and
  // decodes off the main thread; an <img> is the fallback.
  function load(file) {
    if (window.createImageBitmap) {
      return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () { return viaImg(file); });
    }
    return viaImg(file);
  }
  function viaImg(file) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file), img = new Image();
      img.onload = function () { URL.revokeObjectURL(url); resolve(img); };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('decode')); };
      img.src = url;
    });
  }
  function close(src) { if (src && src.close) src.close(); }

  function send(url, data) {
    return fetch(url, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        return r.text().then(function (t) {
          var j = null;
          try { j = JSON.parse(t); } catch (e) { /* not JSON: a server error page */ }
          if (j && r.ok) return j;
          var msg = j && j.error ? j.error
            : r.status === 419 ? '表單已過期，請重新載入頁面。Form expired — reload the page.'
            : r.status === 413 ? '檔案太大 File too large'
            : r.status === 401 || r.status === 302 ? '請重新登入 Please sign in again'
            : '伺服器錯誤 Server error (' + r.status + ')';
          var err = new Error(msg); err.status = r.status; err.data = j;
          throw err;
        });
      }, function () { throw new Error('網絡中斷 Connection lost'); });
  }

  function queue(items, worker, n) {
    var next = 0, running = 0;
    return new Promise(function (resolve) {
      if (!items.length) { resolve(); return; }
      function pump() {
        while (running < n && next < items.length) {
          var i = next++;
          running++;
          Promise.resolve().then(worker.bind(null, items[i], i)).catch(function () {}).then(function () {
            running--;
            if (next >= items.length && running === 0) resolve(); else pump();
          });
        }
      }
      pump();
    });
  }

  function limiter(n) {
    var active = 0, waiting = [];
    function next() {
      if (active >= n || !waiting.length) return;
      var job = waiting.shift();
      active++;
      Promise.resolve().then(job.fn).then(job.ok, job.no).then(function () { active--; next(); });
    }
    return function (fn) {
      return new Promise(function (ok, no) { waiting.push({ fn: fn, ok: ok, no: no }); next(); });
    };
  }

  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return '&#' + c.charCodeAt(0) + ';'; }); }

  function panel(box, total) {
    box.hidden = false;
    box.innerHTML =
      '<div class="bulk-head"><strong class="bulk-count">0 / ' + total + '</strong>' +
      '<span class="bulk-fails" hidden></span></div>' +
      '<div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="' + total + '" aria-valuenow="0"><span style="width:0"></span></div>' +
      '<ol class="bulk-list"></ol>';
    var bar = box.querySelector('.progress'), fill = bar.firstChild, count = box.querySelector('.bulk-count'),
        fails = box.querySelector('.bulk-fails'), list = box.querySelector('.bulk-list'), done = 0, bad = 0;
    var busy = function (e) { e.preventDefault(); e.returnValue = ''; };
    window.addEventListener('beforeunload', busy);      // closing the tab half-way loses the rest
    return {
      row: function (name) {
        var li = document.createElement('li');
        li.className = 'is-wait';
        li.innerHTML = '<span class="bulk-name">' + esc(name) + '</span><span class="bulk-state">等候 Waiting</span>';
        list.appendChild(li);
        return {
          set: function (cls, html) {
            li.className = cls;
            li.lastChild.innerHTML = html;
            if (cls === 'is-busy' && li.offsetTop > list.scrollTop + list.clientHeight - 40) list.scrollTop = li.offsetTop - 60;
          },
          el: li
        };
      },
      step: function (ok) {
        done++; if (!ok) bad++;
        count.textContent = done + ' / ' + total;
        fill.style.width = (done / total * 100) + '%';
        bar.setAttribute('aria-valuenow', done);
        if (bad) { fails.hidden = false; fails.textContent = bad + ' 個未成功 failed'; }
      },
      finish: function () { window.removeEventListener('beforeunload', busy); box.classList.add('is-done'); return { done: done, bad: bad }; }
    };
  }

  window.TYTBulk = { shrink: shrink, send: send, queue: queue, limiter: limiter, panel: panel, esc: esc };
})();
