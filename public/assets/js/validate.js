/*
 * Live validation for IC / passport and phone fields.
 *
 * Mirrors app/Core/Validate.php — the server re-checks everything, this
 * only lets the visitor see a problem while they are still typing.
 * Keep the two in step when changing either.
 *
 * Mark a field:   <input data-validate="ic">,  "phone"  or  "name"
 * and put an empty <p class="field-error"></p> right after it.
 *
 * A page can add its own rules on top (they run in turn, first message wins):
 *   TYTValidate.addExtra('ic', function (tidiedValue, input) { return '' or a message })
 *
 * Staff forms can also ask the server whether an IC is already used:
 *   <input data-validate="ic" data-exists-url="/admin/…/ic-check">
 * The URL answers {"message": ""} (free) or the reason; the message shows
 * under the field like any other problem. The server checks again on save.
 *
 * The age rule ("66 and above", from Forms settings) is shared by the
 * register page, easy mode and the walk-in forms:
 *   TYTValidate.useAge({min, basis, who, other, on})
 * checks each IC as it is typed and shows "born 1950 · age 76" under it.
 * Mirrors App\Core\FormRules (ageRule / birthDate / age).
 */
(function () {
  function ic(raw) {
    var c = String(raw || '').trim().replace(/[\s-]/g, '').toUpperCase();
    if (!c) return null;
    if (/^\d{12}$/.test(c)) {
      var yy = +c.slice(0, 2), mm = +c.slice(2, 4), dd = +c.slice(4, 6);
      var ok = [1900 + yy, 2000 + yy].some(function (y) {
        var d = new Date(y, mm - 1, dd);
        return d.getFullYear() === y && d.getMonth() === mm - 1 && d.getDate() === dd;
      });
      return ok ? c.slice(0, 6) + '-' + c.slice(6, 8) + '-' + c.slice(8) : null;
    }
    if (/[A-Z]/.test(c) && /^[A-Z0-9]{6,20}$/.test(c)) return c;
    return null;
  }

  function phone(raw) {
    raw = String(raw || '').trim();
    var d = raw.replace(/\D/g, '');
    if (!d) return null;
    var plus = raw.charAt(0) === '+';
    if (d.indexOf('60') === 0 && (plus || d.length >= 11)) d = '0' + d.slice(2);
    else if (plus) return (d.length >= 8 && d.length <= 15) ? '+' + d : null;
    var rest;
    if (/^01\d{8,9}$/.test(d)) { rest = d.slice(3); return d.slice(0, 3) + '-' + rest.slice(0, -4) + ' ' + rest.slice(-4); }
    if (/^0[3-9]\d{7,8}$/.test(d)) { rest = d.slice(2); return d.slice(0, 2) + '-' + rest.slice(0, -4) + ' ' + rest.slice(-4); }
    return null;
  }

  function name(raw) {
    var v = String(raw || '').trim().replace(/\s+/g, ' ');
    return v || null;
  }

  var MESSAGES = {
    name: '請填寫姓名。\nPlease enter a name.',
    ic: '身份證號碼格式不正確（例：650101-10-1234），或護照號碼須為 6–20 個英文字母／數字。\nIC should look like 650101-10-1234, or a passport number of 6–20 letters/digits.',
    phone: '聯絡號碼格式不正確（例：012-345 6789）。海外號碼請以 + 國碼開頭。\nContact number should look like 012-345 6789. Overseas numbers start with + and the country code.'
  };
  var CHECK = { ic: ic, phone: phone, name: name };
  var EXTRA = {};

  function errorBox(input) {
    // Only the box right after this field: two fields can share a parent
    // (the register card's name and IC), and must not share a message.
    var box = input.nextElementSibling;
    if (!box || !box.classList.contains('field-error')) {
      box = document.createElement('p');
      box.className = 'field-error';
      input.insertAdjacentElement('afterend', box);
    }
    return box;
  }

  /** Check one field; tidy it when valid. Returns true when OK (or empty and optional). */
  function check(input, tidy) {
    var kind = input.getAttribute('data-validate');
    var value = input.value.trim();
    var box = errorBox(input);
    if (!value) {
      input.setCustomValidity(input.required ? ' ' : '');
      box.textContent = '';
      input.removeAttribute('aria-invalid');
      return !input.required;
    }
    var ok = CHECK[kind](value);
    var more = ok && EXTRA[kind] ? EXTRA[kind](ok, input) : '';
    if (more) {
      if (tidy) input.value = ok;
      input.setCustomValidity(more.split('\n')[0]);
      input.setAttribute('aria-invalid', 'true');
      box.textContent = more;
      return false;
    }
    if (ok) {
      if (tidy) input.value = ok;
      input.setCustomValidity('');
      input.removeAttribute('aria-invalid');
      box.textContent = '';
      return true;
    }
    input.setCustomValidity(MESSAGES[kind].split('\n')[0]);
    input.setAttribute('aria-invalid', 'true');
    box.textContent = MESSAGES[kind];
    return false;
  }

  // Delegated, so fields added later (another attendee card) are covered.
  document.addEventListener('focusout', function (e) {
    if (e.target.matches && e.target.matches('[data-validate]')) {
      e.target.dataset.touched = '1';
      check(e.target, true);
    }
  });
  document.addEventListener('input', function (e) {
    // Only re-check while typing once the person has left the field once —
    // shouting "invalid" at the first keystroke is unkind.
    var el = e.target;
    if (!el.matches || !el.matches('[data-validate]')) return;
    // …except a complete MyKad number: check it straight away (format + age).
    if (!el.dataset.touched && el.getAttribute('data-validate') === 'ic' && el.value.replace(/\D/g, '').length >= 12) el.dataset.touched = '1';
    if (el.dataset.touched) check(el, false);
  });
  document.addEventListener('submit', function (e) {
    var bad = null;
    e.target.querySelectorAll('[data-validate]').forEach(function (input) {
      input.dataset.touched = '1';
      if (!check(input, true) && !bad) bad = input;
    });
    if (bad) {
      e.preventDefault();
      bad.focus();
      bad.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
  }, true);

  /** Add a rule for one kind of field; earlier rules are still checked after it. */
  function addExtra(kind, fn) {
    var prev = EXTRA[kind];
    EXTRA[kind] = function (v, input) { return fn(v, input) || (prev ? prev(v, input) : ''); };
  }
  /** The message from the added rules for this field as it stands ('' = fine), without showing it. */
  function extraProblem(input) {
    var kind = input.getAttribute('data-validate'), ok = CHECK[kind] && CHECK[kind](input.value);
    return ok && EXTRA[kind] ? EXTRA[kind](ok, input) : '';
  }

  // ---- "Is this IC already used?" asked of the server (staff forms) ----
  var EXISTS = {}, ASKING = {};
  addExtra('ic', function (value, input) {
    var url = input.getAttribute('data-exists-url');
    if (!url) return '';
    var key = url + '|' + value;
    if (EXISTS[key] !== undefined) return EXISTS[key];
    if (!ASKING[key] && window.fetch) {
      ASKING[key] = true;
      fetch(url + (url.indexOf('?') < 0 ? '?' : '&') + 'ic=' + encodeURIComponent(value), { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (j) {
          if (!j) return;                         // offline / busy: the server checks on save anyway
          EXISTS[key] = j.message || '';
          if (input.isConnected && CHECK.ic(input.value) === value) check(input, false);
        })
        .catch(function () {})
        .then(function () { ASKING[key] = false; });
    }
    return '';
  });

  // ---- Age from a MyKad number (YYMMDD…) ----
  function useAge(AGE) {
    var on = new Date(AGE.on + 'T00:00:00');
    function born(raw) {
      var d = String(raw).replace(/[\s-]/g, '');
      if (!/^\d{12}$/.test(d)) return null;
      var y = 2000 + +d.slice(0, 2);
      if (y > on.getFullYear()) y -= 100;
      var b = new Date(y, +d.slice(2, 4) - 1, +d.slice(4, 6));
      return b.getMonth() === +d.slice(2, 4) - 1 ? b : null;
    }
    function ageOf(b) {
      var a = on.getFullYear() - b.getFullYear();
      if (AGE.basis === 'event' && (on.getMonth() < b.getMonth() || (on.getMonth() === b.getMonth() && on.getDate() < b.getDate()))) a--;
      return a;
    }
    /** "✓ born 1950 · age 76" under the field (made if the page has none). */
    function tag(input) {
      var t = input.parentNode.querySelector('.age-tag');
      if (!t) { t = document.createElement('p'); t.className = 'age-tag'; errorBox(input).insertAdjacentElement('afterend', t); }
      var b = born(input.value);
      if (!b) { t.textContent = ''; t.className = 'age-tag'; return; }
      var a = ageOf(b), ok = a >= AGE.min;
      t.className = 'age-tag ' + (ok ? 'ok' : 'low');
      t.textContent = (ok ? '✓ ' : '') + b.getFullYear() + ' 年出生 · ' + a + ' 歲 — Born ' + b.getFullYear() + ', age ' + a;
    }
    /** '' when this IC meets the rule, otherwise the message. */
    function problem(value) {
      var b = born(value);
      if (!b) {
        return AGE.other === 'block'
          ? '本活動只接受大馬身份證號碼（用來核對 ' + AGE.min + ' 歲或以上）。\nOnly Malaysian IC numbers are accepted (to check the age of ' + AGE.min + ' and above).'
          : '';
      }
      var a = ageOf(b);
      if (AGE.who === 'all' && a < AGE.min) {
        return '本活動只限 ' + AGE.min + ' 歲或以上，此身份證為 ' + a + ' 歲。\nThis event is for ages ' + AGE.min + ' and above — this IC shows age ' + a + '.';
      }
      return '';
    }
    addExtra('ic', function (value, input) {
      setTimeout(function () { tag(input); }, 0);
      return problem(value);
    });
    return { born: born, ageOf: ageOf, tag: tag, problem: problem };
  }

  window.TYTValidate = { ic: ic, phone: phone, name: name, check: check, extra: EXTRA,
                         addExtra: addExtra, extraProblem: extraProblem, useAge: useAge };
})();
