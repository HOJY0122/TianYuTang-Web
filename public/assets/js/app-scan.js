/**
 * Staff app bridge (Android app in mobile/).
 *
 * Does nothing in a normal browser. Inside the 天玉堂 Staff app it adds a
 * "📷 掃描 Scan" button next to:
 *   - every IC box (data-validate="ic")            → reads the IC number
 *   - every box marked data-app-scan="ic|qr|any"   → IC number and/or QR code
 * The phone's camera reads the card or QR itself (nothing is uploaded) and
 * the result is typed into the box, exactly as if the staff had typed it,
 * so the server checks it the same way. data-app-scan-submit also sends
 * the form (search boxes).
 *
 * The browser's own camera button ([data-scan], check-in / counter) is
 * hidden in the app: the native scanner replaces it.
 */
(function () {
  'use strict';
  var cap = window.Capacitor;
  if (!cap || typeof cap.isNativePlatform !== 'function' || !cap.isNativePlatform()) return;
  var plugin = cap.Plugins && cap.Plugins.TytScan;
  if (!plugin) return;

  document.documentElement.classList.add('in-app');

  // Sign-in page: let staff point the app at another address (live ↔ test).
  var setup = cap.Plugins.TytSetup;
  var card = document.querySelector('body.login .login-card');
  if (setup && card) {
    var change = document.createElement('button');
    change.type = 'button';
    change.className = 'app-change-site';
    change.textContent = '更換網站 Change site';
    change.addEventListener('click', function () {
      if (window.confirm('更換這個 App 連接的網站？\nChange the website this app opens?')) setup.resetSite();
    });
    card.appendChild(change);
  }

  /** A registration / donation reference inside a scanned QR, else the text itself. */
  function refFrom(text) {
    var m = String(text).toUpperCase().match(/[A-Z0-9]*-?(?:RSVP|DON)-\d+/);
    return m ? m[0] : String(text).trim();
  }

  function fill(input, value) {
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    // IC boxes check themselves when the cursor leaves them (validate.js).
    input.dispatchEvent(new Event('blur'));
  }

  function attach(input) {
    if (input.dataset.appScanReady) return;
    input.dataset.appScanReady = '1';
    var mode = input.dataset.appScan || (input.dataset.validate === 'ic' ? 'ic' : 'any');

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'app-scan-btn';
    btn.textContent = mode === 'qr' ? '📷 掃描 QR' : '📷 掃描 Scan';
    btn.setAttribute('aria-label', '用相機掃描 Scan with the camera');
    input.insertAdjacentElement('afterend', btn);

    btn.addEventListener('click', function () {
      btn.disabled = true;
      plugin.scan({ mode: mode, title: input.dataset.appScanTitle || '' }).then(function (r) {
        btn.disabled = false;
        if (!r || r.cancelled || !r.value) return;
        var value = r.type === 'qr' ? refFrom(r.value) : String(r.value).trim();
        fill(input, value);
        if (input.hasAttribute('data-app-scan-submit') && input.form) {
          if (typeof input.form.requestSubmit === 'function') input.form.requestSubmit();
          else input.form.submit();
        } else {
          input.focus();
        }
      }, function () {
        btn.disabled = false;
      });
    });
  }

  var SELECTOR = 'input[data-app-scan], input[data-validate="ic"]';
  function scanAll(root) {
    if (root.matches && root.matches(SELECTOR)) attach(root);
    if (root.querySelectorAll) root.querySelectorAll(SELECTOR).forEach(attach);
  }

  scanAll(document);
  // Walk-in rows are added by script ("add a person"): give them buttons too.
  new MutationObserver(function (changes) {
    changes.forEach(function (c) {
      c.addedNodes.forEach(function (n) { if (n.nodeType === 1) scanAll(n); });
    });
  }).observe(document.body, { childList: true, subtree: true });
})();
