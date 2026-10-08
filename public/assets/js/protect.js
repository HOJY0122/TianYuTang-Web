/*
 * Photo protection on the public site (System → Forms & fonts → 相片保護).
 *
 * Together with signed photo addresses (app/Core/Media.php) this makes
 * saving the temple's photos hard for ordinary visitors:
 *   - no right-click / long-press menu ("Save image", "Open in new tab")
 *   - photos cannot be dragged out or selected
 *   - the usual save / view-source / developer-tools / print shortcuts
 *     are switched off (F12, Ctrl+S, Ctrl+U, Ctrl+Shift+I/J/C …)
 *   - Print Screen: the clipboard is cleared and the photo hidden for a moment
 *   - while the photo viewer is open and the window loses focus (a
 *     screen-snipping tool taking over), the photo is blurred
 *   - photos are left out of printouts (CSS @media print) — the
 *     registration confirmation still prints normally
 *   - optional watermark text over the full-size photo
 *
 * Each part (albums, news pictures, banner, Waze QR) and the shortcut
 * blocking can be switched on or off separately; parts left open keep the
 * browser's normal save / screenshot behaviour. The Waze QR is open by
 * default so visitors can save it and navigate.
 *
 * Honest limit: anything shown on a screen can still be photographed with
 * another phone, and a determined expert can always get a file the browser
 * has displayed. These steps stop casual copying, which is what matters.
 * Text boxes keep their normal menu so forms can still be filled and pasted.
 */
(function () {
  'use strict';
  var html = document.documentElement;
  if (!html.hasAttribute('data-protect')) return;

  // Which parts are protected: classes on <html> set from Forms & fonts.
  var AREAS = {
    albums: '.album',
    news:   '.post-image, .news-strip, .post-card',
    banner: '.banner-show',
    qr:     '.qr-frame'
  };
  var on = function (a) { return html.classList.contains('p-' + a); };
  // The full-size viewer shows album photos AND news pictures: guarded if either is.
  AREAS.viewer = '.lightbox';
  var viewerOn = on('albums') || on('news');
  var isOn = function (a) { return a === 'viewer' ? viewerOn : on(a); };
  var GUARD = Object.keys(AREAS).filter(isOn).map(function (a) { return AREAS[a]; }).join(', ');
  // Parts the system admin left open (e.g. the Waze QR): always free to save.
  var OPEN = Object.keys(AREAS).filter(function (a) { return !isOn(a); }).map(function (a) { return AREAS[a]; }).join(', ');
  var KEYS = on('keys');

  function inside(el, sel) { return !!(sel && el && el.closest && el.closest(sel)); }
  function editable(el) { return inside(el, 'input, textarea, select, [contenteditable="true"]'); }

  var toastEl = null, toastTimer = null;
  function toast() {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.className = 'protect-toast';
      toastEl.setAttribute('role', 'status');
      toastEl.textContent = '本網站內容受保護 · Content is protected';
      document.body.appendChild(toastEl);
    }
    toastEl.classList.add('on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('on'); }, 1800);
  }

  document.addEventListener('contextmenu', function (e) {
    if (editable(e.target) || inside(e.target, OPEN)) return;   // open parts keep their normal menu
    if (inside(e.target, GUARD)) { e.preventDefault(); toast(); return; }
    if (KEYS) e.preventDefault();
  });
  document.addEventListener('dragstart', function (e) {
    if (inside(e.target, GUARD) && !inside(e.target, OPEN)) e.preventDefault();
  });
  document.addEventListener('selectstart', function (e) {
    if (inside(e.target, GUARD) && !editable(e.target)) e.preventDefault();
  });

  if (KEYS) {
    document.addEventListener('keydown', function (e) {
      var k = (e.key || '').toLowerCase();
      var mod = e.ctrlKey || e.metaKey;
      var block = k === 'f12'
        || (mod && (k === 's' || k === 'u'))
        || (mod && e.shiftKey && (k === 'i' || k === 'j' || k === 'c' || k === 'k'))
        || (e.metaKey && e.altKey && (k === 'i' || k === 'j' || k === 'c' || k === 'u'));
      if (block) { e.preventDefault(); e.stopPropagation(); toast(); }
    }, true);
  }

  // Print Screen (Windows): clear what was captured, hide the protected photos briefly.
  // Open parts such as the Waze QR are not touched.
  if (GUARD) {
    document.addEventListener('keyup', function (e) {
      if (e.key !== 'PrintScreen') return;
      html.classList.add('protect-hide');
      try { navigator.clipboard && navigator.clipboard.writeText(''); } catch (err) {}
      toast();
      setTimeout(function () { html.classList.remove('protect-hide'); }, 1500);
    });
  }

  // Viewer open + window loses focus (snipping tools) → blur the photo.
  if (viewerOn) {
    window.addEventListener('blur', function () {
      var lb = document.getElementById('lightbox');
      if (lb && !lb.classList.contains('hidden')) html.classList.add('protect-blur');
    });
    window.addEventListener('focus', function () { html.classList.remove('protect-blur'); });
  }

  // Watermark: the text tiled across the full-size viewer.
  var wm = html.getAttribute('data-wm');
  if (wm && viewerOn) {
    var esc = wm.replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="200"><text x="160" y="100" text-anchor="middle" '
      + 'transform="rotate(-24 160 100)" font-family="sans-serif" font-size="22" font-weight="700" fill="rgba(255,255,255,0.28)" '
      + 'stroke="rgba(0,0,0,0.18)" stroke-width="0.6">' + esc + '</text></svg>';
    html.style.setProperty('--wm', 'url("data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg) + '")');
    html.classList.add('has-wm');
  }
})();
