<?php
/**
 * Registration page. One submission = one reference code for the whole
 * group, with each person's own details underneath it.
 *
 * What the form offers is set in System → 表單與字體 Forms & fonts:
 *   - individual / family, organisation group, or a choice of both
 *     (an organisation group adds the organisation's name; every person
 *     still gives their own name, IC and contact number)
 *   - an age rule ("66 and above only"), checked from each IC as it is
 *     typed and again on the server (App\Core\FormRules)
 */
use App\Core\FormRules;

$pageTitle   = '報名參加 Register';
$preview     = App\Models\Setting::isDraftPreview();
$typeNames   = [
    'individual'   => ['user',  '個人 / 家庭', 'Individual / family', '為自己或家人報名', 'For yourself or your family'],
    'organisation' => ['users', '團體 / 機構', 'Organisation group', '代表團體、公司或會館報名，每位參加者都要填寫資料', 'For an association, company or clan group — every person\'s details are needed'],
];
$startType   = in_array($old['reg_type'] ?? '', $types, true) ? $old['reg_type'] : $types[0];
$maxByType   = [];
foreach ($types as $tp) {
    $maxByType[$tp] = FormRules::maxPeople($event, $tp, $site);
}
$startCount  = min($maxByType[$startType], max(1, (int) ($old['count'] ?? 1)));

// The age rule in words, with the cut-off worked out for this event.
$ageText = null;
if ($ageRule !== null) {
    $ageOn = FormRules::ageDate($event);
    if ($ageRule['basis'] === 'year') {
        $cut = (int) $ageOn->format('Y') - $ageRule['min'];
        $ageText = ["本活動只限 {$ageRule['min']} 歲或以上（{$cut} 年或以前出生）",
                    "For ages {$ageRule['min']} and above (born in {$cut} or earlier)"];
    } else {
        $cut = $ageOn->modify('-' . $ageRule['min'] . ' years');
        $ageText = ["本活動只限 {$ageRule['min']} 歲或以上（" . $cut->format('Y年n月j日') . ' 或以前出生）',
                    "For ages {$ageRule['min']} and above (born on or before " . $cut->format('j M Y') . ')'];
    }
    if ($ageRule['who'] === 'any') {
        $ageText = ["每組須至少一位 {$ageRule['min']} 歲或以上的參加者", "Each group needs at least one person aged {$ageRule['min']} or above"];
    }
}
require BASE_PATH . '/app/Views/layouts/header.php';
?>

<main id="main">
<section class="section narrow" id="liveRegister" data-live="events settings" data-live-mode="reload"><?php live_sig_start(); ?>
  <div class="section-title">
    <h2><?= tb('register.title') ?></h2>
    <p><?= h($event['year']) ?> <?= h($event['name']) ?></p>
  </div>

  <?php if (!$window['open'] && !$preview): ?>
    <div class="card closed-card">
      <div class="closed-icon"><?= icon($window['reason'] === 'not_yet' ? 'clock' : 'lock', 'xl') ?></div>
      <h3><?= tb($window['reason'] === 'not_yet' ? 'register.not_yet' : 'register.closed') ?></h3>
      <p><?= h(App\Models\Event::windowMessage($window, 'rsvp')) ?></p>
      <p class="help"><?= h(t('register.walkin') . ' ' . t('register.walkin', 'en')) ?></p>
      <?php $timerForm = ''; require BASE_PATH . '/app/Views/partials/window_timer.php'; ?>
    </div>
  <?php else: ?>

  <form class="card form-card" action="<?= url('/rsvp/submit') ?>" method="POST" id="rsvpForm">
    <!-- Left empty by people (it is hidden); bots fill it in. -->
    <div class="hp-field" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <?= csrf_field() ?>

    <?php $timerForm = 'rsvpForm'; require BASE_PATH . '/app/Views/partials/window_timer.php'; ?>
    <?php if (!empty($window['closes_at'])): ?>
      <div class="note"><?= icon('hourglass') ?> 線上報名將於 <strong><?= h(App\Models\Event::formatDateTime($window['closes_at'])) ?></strong> 截止。
        <span class="en">Online registration closes on <?= h(date('j M Y, g:i A', strtotime($window['closes_at']))) ?>.</span></div>
    <?php endif; ?>
    <?php if (!empty($event['rsvp_note'])): ?>
      <div class="note"><?= h($event['rsvp_note']) ?></div>
    <?php endif; ?>
    <?php if ($ageText !== null): ?>
      <div class="note age-note"><?= icon('id-card') ?> <strong><?= h($ageText[0]) ?></strong><span class="en"><?= h($ageText[1]) ?></span></div>
    <?php endif; ?>

    <?php $step = 1; ?>
    <?php if (count($types) > 1): ?>
      <div class="form-step"><b><?= $step++ ?></b> <span>報名方式</span><span class="en">How are you registering?</span></div>
      <div class="reg-types" role="radiogroup">
        <?php foreach ($types as $tp): [$tIcon, $tZh, $tEn, $tDescZh, $tDescEn] = $typeNames[$tp]; ?>
          <label class="reg-type">
            <input type="radio" name="reg_type" value="<?= $tp ?>"<?= $tp === $startType ? ' checked' : '' ?>>
            <span class="reg-type-icon"><?= icon($tIcon, 'lg') ?></span>
            <span class="reg-type-text"><strong><?= h($tZh) ?></strong><span class="en"><?= h($tEn) ?></span>
              <small><?= h($tDescZh) ?><span class="en"><?= h($tDescEn) ?></span></small></span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <input type="hidden" name="reg_type" value="<?= h($types[0]) ?>">
    <?php endif; ?>

    <div class="org-field"<?= $startType === 'organisation' ? '' : ' hidden' ?>>
      <label for="orgName">團體 / 機構名稱 <span class="en">Organisation name</span></label>
      <input id="orgName" name="org_name" maxlength="150" autocomplete="organization" value="<?= h($old['org_name'] ?? '') ?>"
             placeholder="例 e.g. 吉隆坡某某會館"<?= $startType === 'organisation' ? ' required' : '' ?>>
    </div>

    <input type="hidden" id="attendeeCount" name="attendee_count" value="<?= $startCount ?>">
    <div class="form-step"><b><?= $step++ ?></b> <span><?= h(t('register.step2')) ?></span><span class="en"><?= h(t('register.step2', 'en')) ?></span></div>
    <div class="people-head">
      <span class="people-count" id="peopleCount" aria-live="polite"></span>
      <span class="help" id="limitText"></span>
    </div>
    <div id="attendees" class="people-list"></div>
    <div class="people-tools">
      <button type="button" class="add-person" id="addPerson"><span class="ap-plus"><?= icon('plus') ?></span><span class="ap-text">加一位參加者<span class="en">Add a person</span></span></button>
      <label class="use-first all-same" id="allSameBox" hidden>
        <input type="checkbox" id="allSame">
        <span class="uf-box"><?= icon('check') ?></span>
        <span>全部用第一位的電話 <span class="en">Everyone uses person 1's number</span></span>
      </label>
    </div>

    <?php if ($preview): ?>
      <p class="note"><?= icon('eye') ?> 預覽模式：不能提交。<span class="en">Preview only — this form cannot be sent.</span></p>
    <?php endif; ?>
    <button class="primary" type="submit"<?= $preview ? ' disabled' : '' ?>><?= icon('register') ?> <?= tb('register.submit') ?></button>
  </form>
  <?php endif; ?>
<?php live_sig_end(); ?></section>
</main>

<?php require BASE_PATH . '/app/Views/partials/modal.php'; ?>

<?php if ($window['open'] || $preview): ?>
<script src="<?= asset('js/validate.js') ?>"></script>
<script>
(function () {
  var MAX_BY  = <?= json_encode($maxByType) ?>;
  var LIMIT   = <?= json_encode([
      'zh' => t('register.limit', 'zh', ['max' => '{max}']),
      'en' => t('register.limit', 'en', ['max' => '{max}']),
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var AGE     = <?= json_encode($ageRule === null ? null : $ageRule + [
      'on' => FormRules::ageDate($event)->format('Y-m-d'),
  ]) ?>;
  var OLD   = <?= json_encode([
      'names'    => $old['names'] ?? [],
      'ics'      => $old['ics'] ?? [],
      'contacts' => $old['contacts'] ?? [],
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var form    = document.getElementById('rsvpForm');
  var countEl = document.getElementById('attendeeCount');
  var box     = document.getElementById('attendees');

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function type() {
    var r = form.querySelector('[name="reg_type"]:checked') || form.querySelector('[name="reg_type"]');
    return r ? r.value : 'individual';
  }
  function max() { return MAX_BY[type()] || 1; }

  // ---- Age from a MyKad number (YYMMDD…), mirroring App\Core\FormRules ----
  function born(ic) {
    var d = String(ic).replace(/[\s-]/g, '');
    if (!/^\d{12}$/.test(d)) return null;
    var on = new Date(AGE.on + 'T00:00:00');
    var y = 2000 + +d.slice(0, 2);
    if (y > on.getFullYear()) y -= 100;
    var b = new Date(y, +d.slice(2, 4) - 1, +d.slice(4, 6));
    return b.getMonth() === +d.slice(2, 4) - 1 ? b : null;
  }
  function ageOf(b) {
    var on = new Date(AGE.on + 'T00:00:00');
    var a = on.getFullYear() - b.getFullYear();
    if (AGE.basis === 'event' && (on.getMonth() < b.getMonth() || (on.getMonth() === b.getMonth() && on.getDate() < b.getDate()))) a--;
    return a;
  }
  function ageTag(input) {
    var tag = input.parentNode.querySelector('.age-tag');
    if (!AGE || !tag) return;
    var b = born(input.value);
    if (!b) { tag.textContent = ''; tag.className = 'age-tag'; return; }
    var a = ageOf(b), ok = a >= AGE.min;
    tag.className = 'age-tag ' + (ok ? 'ok' : 'low');
    tag.textContent = (ok ? '✓ ' : '') + b.getFullYear() + ' 年出生 · ' + a + ' 歲 — Born ' + b.getFullYear() + ', age ' + a;
  }
  /** '' when this IC meets the age rule, otherwise the message. */
  function ageProblem(value) {
    if (!AGE) return '';
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
  if (AGE && window.TYTValidate) {
    window.TYTValidate.extra.ic = function (value, input) {
      setTimeout(function () { ageTag(input); }, 0);
      return ageProblem(value);
    };
  }

  // 1 → 一, 12 → 十二: Chinese numerals suit the brush heading font; the
  // digit itself goes in the round badge, in the plain font.
  function zhNum(n) {
    var d = ['', '一', '二', '三', '四', '五', '六', '七', '八', '九'];
    if (n < 10) return d[n];
    if (n >= 100) return String(n);
    return (n >= 20 ? d[Math.floor(n / 10)] : '') + '十' + d[n % 10];
  }
  function maskIc(v) { v = String(v || '').trim(); return v.length > 4 ? '••••' + v.slice(-4) : v; }

  // ---- One card per person. Only one is open at a time; the others fold
  // into a one-line summary (name · IC · phone) with Edit / Remove. ----
  var CHECK_ICON = '<?= icon('check') ?>', EDIT_ICON = '<?= icon('pencil') ?>', DEL_ICON = '<?= icon('trash') ?>';
  function cardHtml(d) {
    return '<div class="attendee">'
      + '<div class="attendee-head" role="button" tabindex="0">'
      +   '<span class="num-pill"></span>'
      +   '<span class="att-title"><strong class="att-name"></strong><span class="att-sub"></span></span>'
      +   '<span class="done-tag" title="已填好 Done">' + CHECK_ICON + '<span class="dt-text"> 已填好 Done</span></span>'
      +   '<span class="todo-tag">未完成 To finish</span>'
      +   '<button type="button" class="att-edit" aria-label="修改 Edit">' + EDIT_ICON + '<span> 修改</span></button>'
      +   '<button type="button" class="att-remove" aria-label="移除 Remove">' + DEL_ICON + '</button>'
      + '</div>'
      + '<div class="attendee-body">'
      +   '<label>姓名<span class="en">Full name</span></label>'
      +   '<input name="attendee_name[]" required maxlength="100" autocomplete="off" value="' + esc(d.name) + '">'
      +   '<div class="row">'
      +     '<div><label>身份證 / 護照號碼<span class="en">IC / Passport No.</span></label>'
      +     '<input name="attendee_ic[]" required maxlength="30" data-validate="ic" autocomplete="off" placeholder="例 e.g. 651020-10-2020" value="' + esc(d.ic) + '">'
      +     (AGE ? '<p class="age-tag"></p>' : '') + '</div>'
      +     '<div><label>聯絡號碼<span class="en">Contact No.</span></label>'
      +     '<label class="use-first"><input type="checkbox" class="same-first"' + (d.linked ? ' checked' : '') + '>'
      +       '<span class="uf-box">' + CHECK_ICON + '</span>'
      +       '<span>用第一位的電話 <span class="en">Use person 1\'s number</span> <b class="first-num"></b></span></label>'
      +     '<input name="attendee_contact[]" required maxlength="30" inputmode="tel" autocomplete="off" data-validate="phone"'
      +     ' placeholder="例 e.g. 012 345 6789" value="' + esc(d.contact) + '">'
      +     '</div>'
      +   '</div>'
      +   '<div class="att-actions"><button type="button" class="att-done">' + CHECK_ICON + ' 完成 <span class="en">Done</span></button></div>'
      + '</div></div>';
  }
  /** Is this person filled in correctly (without showing messages)? */
  function complete(row) {
    return [].every.call(row.querySelectorAll('input[required]'), function (inp) {
      var v = inp.value.trim();
      if (!v) return false;
      if (inp.dataset.validate && window.TYTValidate) {
        var good = window.TYTValidate[inp.dataset.validate](v);
        if (!good) return false;
        if (inp.dataset.validate === 'ic' && ageProblem(good)) return false;
      }
      return true;
    });
  }
  function rows() { return [].slice.call(box.querySelectorAll('.attendee')); }
  function field(row, n) { return row.querySelector('[name="attendee_' + n + '[]"]'); }

  function renumber() {
    var list = rows(), M = max(), org = type() === 'organisation';
    var firstTel = list[0] ? field(list[0], 'contact').value.trim() : '';
    list.forEach(function (row, i) {
      var lead = i === 0;
      row.querySelector('.num-pill').textContent = i + 1;
      var name = field(row, 'name').value.trim();
      row.querySelector('.att-name').textContent = name || ('第' + zhNum(i + 1) + '位' + (lead ? (org ? '（團體聯絡人）' : '（聯絡人）') : ''));
      var sub = [];
      if (!name) sub.push('Person ' + (i + 1) + (lead ? (org ? ' (group contact)' : ' (main contact)') : ''));
      if (field(row, 'ic').value.trim()) sub.push(maskIc(field(row, 'ic').value));
      if (field(row, 'contact').value.trim()) sub.push(field(row, 'contact').value.trim());
      row.querySelector('.att-sub').textContent = sub.join(' · ');
      row.querySelector('.use-first').hidden = lead;
      row.querySelector('.first-num').textContent = firstTel ? '(' + firstTel + ')' : '';
      row.querySelector('.att-remove').hidden = list.length < 2;
      if (lead) row.querySelector('.same-first').checked = false;
    });
    countEl.value = list.length;
    document.getElementById('peopleCount').textContent = '共 ' + list.length + ' 位 · ' + list.length + (list.length > 1 ? ' people' : ' person');
    var over = list.length > M;
    var lt = document.getElementById('limitText');
    lt.textContent = over ? ('人數超過上限 ' + M + ' 位，請移除 ' + (list.length - M) + ' 位。 Too many people — the limit is ' + M + '.')
                          : (LIMIT.zh.replace('{max}', M) + ' ' + LIMIT.en.replace('{max}', M));
    lt.classList.toggle('is-over', over);
    var add = document.getElementById('addPerson');
    add.disabled = list.length >= M;
    add.hidden = M < 2;
    document.getElementById('allSameBox').hidden = list.length < 3;
    document.getElementById('allSame').checked = list.length > 1 && list.slice(1).every(function (r) { return r.querySelector('.same-first').checked; });
    list.forEach(function (row) { row.classList.toggle('is-done', complete(row)); });
  }

  function syncLinked() {
    var list = rows(); if (!list.length) return;
    var first = field(list[0], 'contact').value;
    list.forEach(function (row, i) {
      var linked = i > 0 && row.querySelector('.same-first').checked;
      var inp = field(row, 'contact');
      inp.readOnly = linked;
      inp.classList.toggle('is-linked', linked);
      if (linked) inp.value = first;
    });
  }

  function openCard(row, focus) {
    rows().forEach(function (r) { r.classList.toggle('is-open', r === row); });
    if (row && focus) {
      var empty = [].filter.call(row.querySelectorAll('input[required]'), function (i) { return !i.value.trim() && !i.readOnly; })[0];
      setTimeout(function () { (empty || field(row, 'name')).focus(); row.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }, 30);
    }
  }
  function addCard(d, focus) {
    box.insertAdjacentHTML('beforeend', cardHtml(d || {}));
    var row = rows().pop();
    syncLinked(); renumber();
    if (focus !== false) openCard(row, true);
    return row;
  }
  /** Check one person's boxes; shows the messages. True when all good. */
  function checkRow(row) {
    var ok = true, firstBad = null;
    row.querySelectorAll('input[required]').forEach(function (inp) {
      var good = inp.value.trim() !== '';
      if (good && inp.dataset.validate && window.TYTValidate) { inp.dataset.touched = '1'; good = window.TYTValidate.check(inp, true); }
      inp.toggleAttribute('aria-invalid', !good);
      if (!good) { ok = false; firstBad = firstBad || inp; }
    });
    if (firstBad) { openCard(row, false); firstBad.focus(); }
    return ok;
  }

  box.addEventListener('click', function (e) {
    var row = e.target.closest('.attendee'); if (!row) return;
    if (e.target.closest('.att-remove')) {
      e.stopPropagation();
      var hasData = [].some.call(row.querySelectorAll('input[required]'), function (i) { return i.value.trim() && !i.readOnly; });
      var go = function () { var wasOpen = row.classList.contains('is-open'); row.remove(); syncLinked(); renumber(); if (wasOpen) openCard(rows()[0], false); };
      if (hasData && window.TYTDialog) window.TYTDialog.confirm('移除這位參加者？\nRemove this person?', { danger: true }).then(function (y) { if (y) go(); });
      else go();
      return;
    }
    if (e.target.closest('.att-done')) {
      if (checkRow(row)) {
        row.classList.remove('is-open');
        var next = rows().filter(function (r) { return !complete(r); })[0];
        if (next) openCard(next, true); else document.getElementById('addPerson').focus();
        renumber();
      }
      return;
    }
    if (e.target.closest('.attendee-head') && !row.classList.contains('is-open')) openCard(row, true);
  });
  box.addEventListener('keydown', function (e) {
    var head = e.target.closest('.attendee-head');
    if (head && (e.key === 'Enter' || e.key === ' ') && e.target === head) { e.preventDefault(); openCard(head.closest('.attendee'), true); }
  });
  box.addEventListener('change', function (e) {
    if (e.target.classList.contains('same-first')) {
      var inp = field(e.target.closest('.attendee'), 'contact');
      if (!e.target.checked) { inp.value = ''; }
      syncLinked();
      if (e.target.checked && window.TYTValidate && inp.value) { inp.dataset.touched = '1'; window.TYTValidate.check(inp, true); }
      if (!e.target.checked) inp.focus();
      renumber();
    }
  });
  box.addEventListener('input', function (e) {
    if (e.target.name === 'attendee_contact[]' && e.target === field(rows()[0], 'contact')) syncLinked();
    renumber();
  });
  box.addEventListener('focusout', function () { setTimeout(renumber, 0); });
  document.getElementById('addPerson').addEventListener('click', function () {
    var open = rows().filter(function (r) { return r.classList.contains('is-open'); })[0];
    if (open && !checkRow(open)) return;              // finish the one being filled first
    if (open) open.classList.remove('is-open');
    addCard({ linked: rows().length >= 1 && field(rows()[0], 'contact').value.trim() !== '' });
  });
  document.getElementById('allSame').addEventListener('change', function () {
    var on = this.checked;
    rows().slice(1).forEach(function (r) { var c = r.querySelector('.same-first'); if (c.checked !== on) { c.checked = on; if (!on) field(r, 'contact').value = ''; } });
    syncLinked(); renumber();
  });

  // ---- Individual / organisation ----
  form.querySelectorAll('[name="reg_type"]').forEach(function (r) {
    r.addEventListener('change', function () {
      var org = type() === 'organisation';
      form.querySelector('.org-field').hidden = !org;
      document.getElementById('orgName').required = org;
      renumber();
    });
  });

  // ---- Start: the people sent back after an error, or one empty card ----
  var startN = Math.max(1, parseInt(countEl.value, 10) || 1);
  for (var k = 0; k < startN; k++) {
    addCard({ name: OLD.names[k], ic: OLD.ics[k], contact: OLD.contacts[k],
              linked: k > 0 && OLD.contacts[k] && OLD.contacts[k] === OLD.contacts[0] }, false);
  }
  rows().forEach(function (row) { field(row, 'ic').value && ageTag(field(row, 'ic')); });
  openCard(rows().filter(function (r) { return !complete(r); })[0] || null, false);

  // Built-in checks first (empty boxes), then ours; "at least one aged …" is a group rule.
  form.addEventListener('submit', function (e) {
    var org = document.getElementById('orgName');
    if (org.required && org.value.trim().length < 2) {
      e.preventDefault(); org.focus(); org.reportValidity && org.reportValidity(); return;
    }
    if (rows().length > max()) {
      e.preventDefault();
      document.getElementById('limitText').scrollIntoView({ block: 'center', behavior: 'smooth' });
      return;
    }
    // Every person must be complete; open the first one that is not.
    var bad = rows().filter(function (r) { return !complete(r); })[0];
    if (bad) { e.preventDefault(); checkRow(bad); bad.scrollIntoView({ block: 'center', behavior: 'smooth' }); return; }
    if (AGE && AGE.who === 'any') {
      var ics = [].slice.call(form.querySelectorAll('[name="attendee_ic[]"]'));
      var checked = ics.filter(function (i) { return born(i.value); });
      var anyOk = checked.some(function (i) { return ageOf(born(i.value)) >= AGE.min; });
      if (checked.length && !anyOk) {
        e.preventDefault();
        var msg = '每組須至少一位 ' + AGE.min + ' 歲或以上的參加者。\nEach group needs at least one person aged ' + AGE.min + ' or above.';
        if (window.TYTDialog) window.TYTDialog.alert(msg, { type: 'error' }); else alert(msg);
      }
    }
  });

})();
</script>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
