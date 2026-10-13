/*
 * 布施憑單 Donation vouchers — the batch page.
 *   • tick boxes choose which vouchers "Print ticked" opens;
 *   • the Paid box saves at once (no page reload).
 */
(function () {
  'use strict';
  var all = document.getElementById('tickAll'),
      btn = document.getElementById('printTicked'),
      num = document.getElementById('tickedN'),
      ticks = [].slice.call(document.querySelectorAll('.v-tick'));

  function count() {
    var n = ticks.filter(function (t) { return t.checked; }).length;
    if (num) num.textContent = n;
    if (btn) btn.disabled = n === 0;
    if (all) { all.checked = n > 0 && n === ticks.length; all.indeterminate = n > 0 && n < ticks.length; }
  }
  ticks.forEach(function (t) { t.addEventListener('change', count); });
  if (all) all.addEventListener('change', function () { ticks.forEach(function (t) { t.checked = all.checked; }); count(); });
  count();

  var csrf = (document.querySelector('input[name="csrf_token"]') || {}).value || '';
  var base = document.documentElement.dataset.base || '';
  [].forEach.call(document.querySelectorAll('[data-paid]'), function (box) {
    box.addEventListener('change', function () {
      var label = box.parentNode.querySelector('span'), want = box.checked;
      var body = new FormData();
      body.append('csrf_token', csrf);
      body.append('id', box.dataset.paid);
      body.append('paid', want ? '1' : '0');
      box.disabled = true;
      fetch(base + '/admin/vouchers/paid', { method: 'POST', body: body, credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(function () {
          var d = new Date();
          label.textContent = want ? ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) : '未付 No';
        })
        .catch(function () { box.checked = !want; alert('未能儲存，請重新載入頁面。Could not save — please reload the page.'); })
        .then(function () { box.disabled = false; });
    });
  });
})();
