/*
 * Live validation for IC / passport and phone fields.
 *
 * Mirrors app/Core/Validate.php — the server re-checks everything, this
 * only lets the visitor see a problem while they are still typing.
 * Keep the two in step when changing either.
 *
 * Mark a field:   <input data-validate="ic">   or   data-validate="phone"
 * and put an empty <p class="field-error"></p> right after it.
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

  var MESSAGES = {
    ic: '身份證號碼格式不正確（例：650101-10-1234），或護照號碼須為 6–20 個英文字母／數字。\nIC should look like 650101-10-1234, or a passport number of 6–20 letters/digits.',
    phone: '聯絡號碼格式不正確（例：012-345 6789）。海外號碼請以 + 國碼開頭。\nContact number should look like 012-345 6789. Overseas numbers start with + and the country code.'
  };
  var CHECK = { ic: ic, phone: phone };

  function errorBox(input) {
    var box = input.parentNode.querySelector('.field-error');
    if (!box) {
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
    if (e.target.matches && e.target.matches('[data-validate]') && e.target.dataset.touched) check(e.target, false);
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

  window.TYTValidate = { ic: ic, phone: phone, check: check };
})();
