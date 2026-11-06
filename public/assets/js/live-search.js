/**
 * Live search — results appear while you type; the Find button still works.
 *
 *   <form data-live-search="#results"> … <input name="q"> … </form>
 *     After a short pause in typing (from the first letter or digit)
 *     the same page is fetched with the new words and only #results is
 *     swapped in. The server does the matching, so it can search inside
 *     encrypted IC numbers ("10" → every IC starting with 10). Older
 *     answers that arrive late are dropped; the address bar follows, so
 *     Back and refresh keep the search.
 *
 *   <input data-filter-list="#list">
 *     Instant, no server: hides the rows of #list (its children, or
 *     [data-filter-row]) that do not contain the words typed.
 */
(function () {
  'use strict';
  var WAIT = 150;

  document.querySelectorAll('form[data-live-search]').forEach(function (form) {
    var input = form.querySelector('input[name="q"]');
    var target = document.querySelector(form.dataset.liveSearch);
    if (!input || !target || !window.fetch || !window.DOMParser) return;
    var timer = null, seq = 0, last = input.value.trim();

    function run() {
      var q = input.value.trim();
      if (q === last) return;
      last = q;
      var mine = ++seq;
      var params = new URLSearchParams(new FormData(form));
      var url = (form.getAttribute('action') || location.pathname) + '?' + params.toString();
      target.classList.add('is-searching');
      fetch(url, { credentials: 'same-origin', headers: { 'X-Live-Search': '1' } })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          if (mine !== seq) return;                     // a newer search has been sent since
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var fresh = doc.querySelector(form.dataset.liveSearch);
          if (fresh) target.innerHTML = fresh.innerHTML;
          history.replaceState(null, '', url);
          target.dispatchEvent(new CustomEvent('live:results', { bubbles: true }));
        })
        .catch(function () { /* keep what is shown; the Find button still works */ })
        .then(function () { if (mine === seq) target.classList.remove('is-searching'); });
    }
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(run, WAIT); });
    form.addEventListener('submit', function () { clearTimeout(timer); });
  });

  document.querySelectorAll('input[data-filter-list]').forEach(function (input) {
    var list = document.querySelector(input.dataset.filterList);
    if (!list) return;
    var empty = list.querySelector('[data-filter-empty]');
    input.addEventListener('input', function () {
      var words = input.value.trim().toLowerCase().split(/\s+/).filter(Boolean), shown = 0;
      var rows = list.querySelectorAll('[data-filter-row]');
      (rows.length ? rows : list.children).forEach(function (row) {
        if (row === empty) return;
        var text = row.textContent.toLowerCase(), hit = words.every(function (w) { return text.indexOf(w) > -1; });
        row.hidden = !hit;
        shown += hit ? 1 : 0;
      });
      if (empty) empty.hidden = shown > 0;
    });
  });
})();
