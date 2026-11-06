/**
 * 施齋 Day 3 admin — choose a table on the hall map instead of a drop-down.
 *
 *   <span class="tpick"><input type="hidden" data-tpick-value name="table_no">
 *     <button data-tpick data-need="2" data-who="陳大文">選桌</button></span>
 *       → tap the button, the map opens, tap a table: the field is filled.
 *
 *   <button type="submit" data-needs-table [data-table-optional]>✓ 確認</button>
 *       → pressed with no table chosen yet: the map opens first; tapping a
 *         table chooses it AND sends the form (with this button's value).
 *         data-table-optional adds "只確認，不選桌" (send without a table).
 *
 *   <button type="button" data-tpick-multi data-target="#tables5" data-need="12">
 *       → several tables (團體): tap to tick / untick, then 完成 writes "5, 6".
 *
 * Colours as in easy mode: green = enough seats, yellow = not enough,
 * red = full (switched off; the row's own table stays usable).
 * 團體桌 group tables (purple) are switched off for people / waiting —
 * a group buys or reserves its tables whole. In the 團體 multi-pick only
 * empty tables (or the group's own) can be ticked. The server checks too.
 */
(function () {
  'use strict';
  var modal = document.getElementById('tpickModal');
  if (!modal) return;
  var cells = [].slice.call(modal.querySelectorAll('.tp-cell'));
  var whoBox = modal.querySelector('[data-tpick-who]');
  var btnNone = modal.querySelector('[data-tpick-none]');
  var btnClear = modal.querySelector('[data-tpick-clear]');
  var btnDone = modal.querySelector('[data-tpick-done]');
  var state = null;       // what the open map is for
  var before = null;      // focus to return to

  function parts(v) { return String(v || '').split(/[^0-9]+/).filter(Boolean); }

  function paint() {
    var need = state.need, mine = state.current;
    cells.forEach(function (c) {
      var no = c.dataset.no, left = +c.dataset.left, own = mine.indexOf(no) > -1;
      var picked = state.multi && state.picked.indexOf(no) > -1;
      var seats = +c.dataset.seats, grp = c.dataset.kind === 'group';
      var off, cls, note;
      if (state.multi) {
        // 團體: whole tables — only an empty one, or one already the group's.
        off = !own && left < seats;
        cls = off ? 'full' : (grp ? 'grp' : 'fits');
        note = own ? '目前 Now' : (off ? (left <= 0 ? '滿 Full' : '有人 Taken') : (grp ? '空 · 團體桌' : '空桌 Empty'));
      } else if (grp && !own) {
        off = true; cls = 'grp is-off'; note = '團體桌 Group';      // never for individuals
      } else {
        off = left <= 0 && !own;
        cls = off ? 'full' : (left >= need || own ? 'fits' : 'some');
        note = own ? '目前 Now' : (left > 0 ? '剩 ' + left + ' 位' : '滿 Full');
      }
      c.className = 'tp-cell ' + cls + (own ? ' is-current' : '') + (picked ? ' is-picked' : '');
      c.disabled = off;
      c.setAttribute('aria-pressed', picked ? 'true' : 'false');
      c.querySelector('small').textContent = note;
    });
  }

  function open(s) {
    state = s;
    before = document.activeElement;
    whoBox.textContent = (s.who ? s.who + ' · ' : '') + s.need + ' 人 people' + (s.multi ? ' · 可選多張 Tap several' : '');
    btnNone.hidden = !(s.submitter && s.submitter.hasAttribute('data-table-optional'));
    btnClear.hidden = s.multi || !!s.submitter || !s.current.length;
    btnDone.hidden = !s.multi;
    paint();
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    var first = modal.querySelector('.tp-cell.is-current, .tp-cell.fits:not([disabled])');
    (first || modal.querySelector('[data-tpick-close]')).focus();
  }
  function close() {
    modal.hidden = true;
    document.body.style.overflow = '';
    state = null;
    if (before && before.focus) before.focus();
  }

  /** Put a table in the single picker next to the button. */
  function setSingle(wrap, no) {
    var input = wrap.querySelector('[data-tpick-value]');
    input.value = no || '';
    var btn = wrap.querySelector('[data-tpick]');
    btn.querySelector('[data-tpick-label]').textContent = no ? '第 ' + no + ' 桌' : '選桌 Choose table';
    btn.classList.toggle('has-table', !!no);
  }

  function choose(no) {
    var s = state;
    if (s.multi) {
      var i = s.picked.indexOf(no);
      if (i > -1) s.picked.splice(i, 1); else s.picked.push(no);
      paint();
      return;
    }
    close();
    setSingle(s.wrap, no);
    if (s.submitter) send(s.form, s.submitter);
  }

  function send(form, submitter) {
    form.dataset.tpickOk = '1';           // do not open the map again for this send
    if (form.requestSubmit) form.requestSubmit(submitter);
    else { var h = document.createElement('input'); h.type = 'hidden'; h.name = submitter.name; h.value = submitter.value; form.appendChild(h); form.submit(); }
  }

  cells.forEach(function (c) { c.addEventListener('click', function () { if (state && !c.disabled) choose(c.dataset.no); }); });
  modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
  modal.querySelectorAll('[data-tpick-close]').forEach(function (b) { b.addEventListener('click', close); });
  document.addEventListener('keydown', function (e) { if (state && e.key === 'Escape') close(); });
  btnClear.addEventListener('click', function () { var s = state; close(); setSingle(s.wrap, ''); });
  btnNone.addEventListener('click', function () { var s = state; close(); setSingle(s.wrap, ''); send(s.form, s.submitter); });
  btnDone.addEventListener('click', function () {
    var s = state, nos = s.picked.slice().sort(function (a, b) { return a - b; });
    close();
    s.target.value = nos.join(', ');
    s.target.dispatchEvent(new Event('input', { bubbles: true }));
  });

  // Delegated, so rows refreshed by live updates keep working.
  document.addEventListener('click', function (e) {
    var t = e.target.closest && e.target.closest('[data-tpick], [data-tpick-multi]');
    if (!t) return;
    e.preventDefault();
    if (t.hasAttribute('data-tpick-multi')) {
      var target = document.querySelector(t.dataset.target);
      var cur = parts(t.dataset.current);
      open({ multi: true, need: +t.dataset.need || 1, who: t.dataset.who || '', current: cur, picked: parts(target.value), target: target });
      return;
    }
    var wrap = t.closest('.tpick');
    open({ wrap: wrap, form: t.form || t.closest('form'), need: +t.dataset.need || 1, who: t.dataset.who || '',
           current: parts(wrap.querySelector('[data-tpick-value]').value) });
  });
  // An "assign" button pressed with no table yet: show the map first.
  document.addEventListener('submit', function (e) {
    var form = e.target, sub = e.submitter;
    if (form.dataset.tpickOk) { delete form.dataset.tpickOk; return; }
    if (!sub || !sub.hasAttribute('data-needs-table')) return;
    var input = form.querySelector('[data-tpick-value]');
    if (!input || input.value) return;
    e.preventDefault();
    var btn = form.querySelector('[data-tpick]');
    open({ wrap: input.closest('.tpick'), form: form, submitter: sub, need: +(btn && btn.dataset.need) || 1,
           who: (btn && btn.dataset.who) || '', current: [] });
  }, true);
})();
