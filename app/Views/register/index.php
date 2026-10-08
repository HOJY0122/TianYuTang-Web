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
    </div>
  <?php else: ?>

  <form class="card form-card" action="<?= url('/rsvp/submit') ?>" method="POST" id="rsvpForm">
    <!-- Left empty by people (it is hidden); bots fill it in. -->
    <div class="hp-field" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <?= csrf_field() ?>

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

    <div class="form-step"><b><?= $step++ ?></b> <span><?= h(t('register.step1')) ?></span><span class="en"><?= h(t('register.step1', 'en')) ?></span></div>
    <div class="stepper">
      <button type="button" data-step="-1" aria-label="減少 Fewer">−</button>
      <input id="attendeeCount" name="attendee_count" type="number" inputmode="numeric"
             min="1" max="<?= $maxByType[$startType] ?>" value="<?= $startCount ?>" aria-label="人數 Number of people">
      <button type="button" data-step="1" aria-label="增加 More">+</button>
    </div>
    <p class="help" id="limitText"></p>

    <div class="form-step"><b><?= $step++ ?></b> <span><?= h(t('register.step2')) ?></span><span class="en"><?= h(t('register.step2', 'en')) ?></span></div>
    <div class="people-bar" id="peopleBar" hidden>
      <div class="people-chips" id="peopleChips" aria-label="填寫進度 Progress"></div>
      <button type="button" class="link-btn" id="allSame"><?= icon('phone') ?> 全部用第一位的電話 <span class="en">Everyone uses person 1's number</span></button>
    </div>
    <div id="attendees"></div>

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
  var chips   = document.getElementById('peopleChips');
  var bar     = document.getElementById('peopleBar');
  var linked  = [];        // linked[i] = true: person i uses person 1's number

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

  // Keep whatever was already typed when the number changes.
  function collect() {
    box.querySelectorAll('.attendee').forEach(function (row, i) {
      OLD.names[i]    = row.querySelector('[name="attendee_name[]"]').value;
      OLD.ics[i]      = row.querySelector('[name="attendee_ic[]"]').value;
      OLD.contacts[i] = row.querySelector('[name="attendee_contact[]"]').value;
    });
  }

  // 1 → 一, 12 → 十二: Chinese numerals suit the brush heading font; the
  // digit itself goes in the round badge, in the plain font.
  function zhNum(n) {
    var d = ['', '一', '二', '三', '四', '五', '六', '七', '八', '九'];
    if (n < 10) return d[n];
    if (n >= 100) return String(n);
    return (n >= 20 ? d[Math.floor(n / 10)] : '') + '十' + d[n % 10];
  }

  function render() {
    collect();
    var M = max();
    var n = Math.min(M, Math.max(1, parseInt(countEl.value, 10) || 1));
    countEl.value = n;
    countEl.max = M;
    document.getElementById('limitText').textContent = LIMIT.zh.replace('{max}', M) + ' ' + LIMIT.en.replace('{max}', M);
    var org = type() === 'organisation';
    var html = '';
    for (var i = 0; i < n; i++) {
      var lead = i === 0;
      html += '<div class="attendee" id="person' + (i + 1) + '">'
        + '<div class="attendee-head"><h4><span class="num-pill">' + (i + 1) + '</span>第' + zhNum(i + 1) + '位'
        + (lead ? (org ? '（團體聯絡人）' : '（聯絡人）') : '')
        + '<span class="en">Person ' + (i + 1) + (lead ? (org ? ' (group contact)' : ' (main contact)') : '') + '</span></h4>'
        + '<span class="done-tag" aria-hidden="true">' + '<?= icon('check') ?>' + ' 已填好 Done</span></div>'
        + '<label>姓名<span class="en">Full name</span></label>'
        + '<input name="attendee_name[]" required maxlength="100" autocomplete="' + (lead ? 'name' : 'off') + '" value="' + esc(OLD.names[i]) + '">'
        + '<div class="row">'
        + '<div><label>身份證 / 護照號碼<span class="en">IC / Passport No.</span></label>'
        + '<input name="attendee_ic[]" required maxlength="30" data-validate="ic" autocomplete="off" placeholder="例 e.g. 651020-10-2020" value="' + esc(OLD.ics[i]) + '">'
        + (AGE ? '<p class="age-tag"></p>' : '') + '</div>'
        + '<div><label>聯絡號碼<span class="en">Contact No.</span></label>';
      if (!lead) {
        html += '<div class="same-toggle" role="radiogroup" aria-label="聯絡號碼 Contact number">'
          + '<button type="button" data-same="0" class="' + (linked[i] ? '' : 'is-on') + '">自己填寫 <span>Own number</span></button>'
          + '<button type="button" data-same="1" class="' + (linked[i] ? 'is-on' : '') + '"><?= icon('phone') ?> 同第一位 <span>Same as person 1</span></button>'
          + '</div>';
      }
      html += '<input name="attendee_contact[]" required maxlength="30" inputmode="tel" autocomplete="' + (lead ? 'tel' : 'off') + '" data-validate="phone"'
        + ' placeholder="例 e.g. 012 345 6789" value="' + esc(OLD.contacts[i]) + '"' + (linked[i] ? ' readonly class="is-linked"' : '') + '>'
        + '</div></div></div>';
    }
    box.innerHTML = html;
    syncLinked();
    box.querySelectorAll('[name="attendee_ic[]"]').forEach(function (inp) { if (inp.value) ageTag(inp); });
    document.querySelector('[data-step="-1"]').disabled = n <= 1;
    document.querySelector('[data-step="1"]').disabled  = n >= M;
    bar.hidden = n < 2;
    document.getElementById('allSame').hidden = n < 3;
    refreshChips();
  }

  // ---- "Same as person 1": the number follows person 1's as it is typed ----
  function syncLinked() {
    var first = box.querySelector('[name="attendee_contact[]"]');
    box.querySelectorAll('.attendee').forEach(function (row, i) {
      if (i && linked[i]) row.querySelector('[name="attendee_contact[]"]').value = first ? first.value : '';
    });
  }
  function setLinked(i, on) {
    linked[i] = on;
    var row = box.querySelectorAll('.attendee')[i];
    if (!row) return;
    var inp = row.querySelector('[name="attendee_contact[]"]');
    row.querySelectorAll('[data-same]').forEach(function (b) { b.classList.toggle('is-on', (b.dataset.same === '1') === on); });
    inp.readOnly = on;
    inp.classList.toggle('is-linked', on);
    if (on) syncLinked(); else { inp.value = ''; inp.focus(); }
    if (window.TYTValidate && inp.value) { inp.dataset.touched = '1'; window.TYTValidate.check(inp, true); }
    refreshChips();
  }
  box.addEventListener('click', function (e) {
    var b = e.target.closest('[data-same]');
    if (!b) return;
    var rows = [].slice.call(box.querySelectorAll('.attendee'));
    setLinked(rows.indexOf(b.closest('.attendee')), b.dataset.same === '1');
  });
  document.getElementById('allSame').addEventListener('click', function () {
    var rows = box.querySelectorAll('.attendee');
    var all = true;
    for (var i = 1; i < rows.length; i++) if (!linked[i]) all = false;
    for (var j = 1; j < rows.length; j++) setLinked(j, !all);
  });

  // ---- Progress chips: which people are filled in ----
  function complete(row) {
    var ok = true;
    row.querySelectorAll('input[required]').forEach(function (inp) {
      var v = inp.value.trim();
      if (!v) ok = false;
      else if (inp.dataset.validate && window.TYTValidate) {
        var good = window.TYTValidate[inp.dataset.validate](v);
        if (!good) ok = false;
        else if (inp.dataset.validate === 'ic' && ageProblem(good)) ok = false;
      }
    });
    return ok;
  }
  function refreshChips() {
    var rows = box.querySelectorAll('.attendee'), html = '';
    rows.forEach(function (row, i) {
      var ok = complete(row);
      row.classList.toggle('is-done', ok);
      html += '<a href="#person' + (i + 1) + '" class="' + (ok ? 'ok' : '') + '" aria-label="第' + (i + 1) + '位 Person ' + (i + 1) + (ok ? ' ✓' : '') + '">' + (i + 1) + '</a>';
    });
    chips.innerHTML = html;
  }
  box.addEventListener('input', function (e) {
    if (e.target.name === 'attendee_contact[]' && e.target === box.querySelector('[name="attendee_contact[]"]')) syncLinked();
    refreshChips();
  });
  box.addEventListener('focusout', function () { setTimeout(refreshChips, 0); });

  // ---- Individual / organisation ----
  form.querySelectorAll('[name="reg_type"]').forEach(function (r) {
    r.addEventListener('change', function () {
      var org = type() === 'organisation';
      var f = form.querySelector('.org-field');
      f.hidden = !org;
      document.getElementById('orgName').required = org;
      render();
    });
  });

  document.querySelectorAll('[data-step]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      countEl.value = (parseInt(countEl.value, 10) || 1) + Number(btn.dataset.step);
      render();
    });
  });
  countEl.addEventListener('change', render);

  // Built-in checks first (empty boxes), then ours; "at least one aged …" is a group rule.
  form.addEventListener('submit', function (e) {
    var org = document.getElementById('orgName');
    if (org.required && org.value.trim().length < 2) {
      e.preventDefault(); org.focus(); org.reportValidity && org.reportValidity(); return;
    }
    var empty = [].slice.call(form.querySelectorAll('#attendees input[required]')).filter(function (i) { return !i.value.trim(); })[0];
    if (empty) { e.preventDefault(); empty.focus(); empty.scrollIntoView({ block: 'center', behavior: 'smooth' }); return; }
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

  render();
})();
</script>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
