/*
 * Number boxes that only take what the server accepts (App\Models\Donation
 * parseSeats / parseAmount decide; this only stops mistakes while typing).
 *
 *   <input data-int>    merit seats: digits only — no decimal point, no
 *                       "e", no minus; pasted "2.5" is cut at the point → "2"
 *   <input data-money>  freewill: at most two decimals; typing a third is
 *                       ignored, pasted 12.095 becomes 12.10 (half up)
 */
(function () {
  'use strict';
  function money(v) {
    var m = String(v).trim().replace(/[^\d.]/g, '').match(/^(\d*)(?:\.(\d*))?/);
    if (!m || (m[1] === '' && !m[2])) return '';
    var whole = m[1] || '0', frac = ((m[2] || '') + '000').slice(0, 3);
    var sen = +whole * 100 + Math.floor(+frac / 10) + (+frac[2] >= 5 ? 1 : 0);
    return (Math.floor(sen / 100)) + '.' + ('0' + sen % 100).slice(-2);
  }
  // A short note under the box when a key is refused (e.g. "." in seats).
  function hint(el, text) {
    var tip = el.parentNode.querySelector('.num-hint');
    if (!tip) {
      tip = document.createElement('p');
      tip.className = 'num-hint';
      tip.setAttribute('role', 'status');
      (el.closest('.stepper') || el).insertAdjacentElement('afterend', tip);
      tip = (el.closest('.stepper') || el).nextElementSibling;
    }
    tip.textContent = text;
    tip.classList.add('on');
    clearTimeout(tip._t);
    tip._t = setTimeout(function () { tip.classList.remove('on'); }, 2500);
  }

  document.addEventListener('keydown', function (e) {
    var el = e.target;
    if (!el.matches || e.ctrlKey || e.metaKey || e.key.length !== 1) return;
    if (el.matches('[data-int]') && !/\d/.test(e.key)) {
      e.preventDefault();
      if (e.key === '.' || e.key === ',') hint(el, '只可輸入整數 Whole numbers only');
      return;
    }
    if (el.matches('[data-money]')) {
      if (!/[\d.]/.test(e.key)) { e.preventDefault(); return; }
      var v = el.value, dot = v.indexOf('.');
      // type=number hides the selection, so only guard the clear cases
      if (e.key === '.' && dot >= 0) { e.preventDefault(); return; }
      var caret = null;
      try { caret = el.selectionStart; } catch (err) {}        // type=number has no caret position
      if (/\d/.test(e.key) && dot >= 0 && v.length - dot > 2 && (caret == null || caret > dot)) { e.preventDefault(); }
    }
  }, true);
  document.addEventListener('input', function (e) {
    var el = e.target;
    if (!el.matches) return;
    if (el.matches('[data-int]')) {
      var clean = String(el.value).split(/[.,]/)[0].replace(/\D/g, '');
      if (clean !== el.value) el.value = clean;
    } else if (el.matches('[data-money]')) {
      var v = String(el.value), dot = v.indexOf('.');
      if (dot >= 0 && v.length - dot > 3) el.value = v.slice(0, dot + 3);   // a third decimal typed: drop it
    }
  }, true);
  document.addEventListener('paste', function (e) {
    var el = e.target;
    if (!el.matches || !el.matches('[data-int], [data-money]')) return;
    var text = (e.clipboardData || window.clipboardData).getData('text');
    e.preventDefault();
    el.value = el.matches('[data-int]') ? text.split(/[.,]/)[0].replace(/\D/g, '') : money(text);
    el.dispatchEvent(new Event('input', { bubbles: true }));
  }, true);
  // Leaving the box: a money amount is shown as 12.10, never 12.1 or 12.095.
  document.addEventListener('change', function (e) {
    var el = e.target;
    if (el.matches && el.matches('[data-money]') && el.value !== '') {
      var f = money(el.value);
      if (f !== el.value) { el.value = f; el.dispatchEvent(new Event('input', { bubbles: true })); }
    }
  }, true);
  window.TYTMoney = money;
})();
