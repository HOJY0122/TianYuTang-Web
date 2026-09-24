/**
 * Camera QR scanner for the on-the-day desks (check-in and counter).
 *
 * Manual typing is always the main path — the camera needs HTTPS, a
 * permission grant and decent light, none certain in a temple hall — so
 * this only adds a "📷 Scan" button on top of an ordinary GET form.
 *
 * A scanned reference goes to the right desk automatically: a donation
 * QR (…DON-0012) scanned at check-in opens the counter, and a
 * registration QR (…RSVP-0007) scanned at the counter opens check-in.
 *
 * TYTScanner({ form, here: 'rsvp'|'don', urls: {rsvp, don}, eventId })
 * Needs js/jsqr.min.js (window.TYTScanQR) loaded first.
 */
window.TYTScanner = function (opt) {
  'use strict';
  var form   = opt.form;
  var input  = form.querySelector('input[name="ref"]');
  var btn    = form.querySelector('[data-scan]');
  var panel  = document.getElementById('scanPanel');
  var video  = panel.querySelector('video');
  var canvas = panel.querySelector('canvas');
  var status = panel.querySelector('[data-scan-status]');
  var stopBtn= panel.querySelector('[data-scan-stop]');
  var stream = null, raf = null;

  var secure = window.isSecureContext || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !secure || !window.TYTScanQR) {
    btn.disabled = true;
    btn.title = secure ? '此裝置不支援相機 This device has no usable camera'
                       : '相機需要 HTTPS，請改用手動輸入。The camera needs HTTPS — please type the reference.';
  }

  btn.addEventListener('click', start);
  stopBtn.addEventListener('click', stop);

  function say(zh, en) { status.textContent = zh + '　' + en; }

  async function start() {
    panel.hidden = false;
    say('正在啟動相機…', 'Starting camera…');
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });   // rear camera
      video.srcObject = stream;
      await video.play();
      say('請將 QR Code 對準畫面', 'Point the camera at the QR code');
      tick();
    } catch (e) {
      say('無法使用相機（' + (e.name || 'error') + '），請改用手動輸入。', 'Camera unavailable — please type the reference.');
    }
  }

  function stop() {
    if (raf) cancelAnimationFrame(raf);
    raf = null;
    if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
    stream = null;
    panel.hidden = true;
  }

  /** Which desk a scanned reference belongs to, or null if unknown. */
  function kindOf(code) {
    if (/(^|-)DON-\d+$/i.test(code)) return 'don';
    if (/(^|-)RSVP-\d+$/i.test(code)) return 'rsvp';
    return null;
  }

  function tick() {
    if (!stream) return;
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      var ctx = canvas.getContext('2d', { willReadFrequently: true });
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      var img = ctx.getImageData(0, 0, canvas.width, canvas.height);
      var hit = window.TYTScanQR(img.data, img.width, img.height);
      if (hit && hit.data) {
        // The QR holds only the reference code. It is put in a form
        // field the server validates like anything typed by hand.
        var code = hit.data.trim().toUpperCase();
        var kind = kindOf(code);
        stop();
        if (kind && kind !== opt.here && opt.urls[kind]) {
          say('這是' + (kind === 'don' ? '布施' : '報名') + '編號，正在轉到對應頁面…', 'Opening the right desk for ' + code + '…');
          location.href = opt.urls[kind] + '?event=' + opt.eventId + '&ref=' + encodeURIComponent(code);
          return;
        }
        input.value = code;
        say('已讀取 ' + code, 'Read ' + code);
        form.submit();
        return;
      }
    }
    raf = requestAnimationFrame(tick);
  }
};
