<?php
/**
 * Registration page. One submission = one reference code for the whole
 * group, with each person's own details underneath it.
 */
$pageTitle    = '報名參加 Register';
$maxAttendees = max(1, (int) $event['max_attendees']);
$startCount   = min($maxAttendees, max(1, (int) ($old['count'] ?? 1)));
require BASE_PATH . '/app/Views/layouts/header.php';
?>

<main id="main">
<section class="section narrow">
  <div class="section-title">
    <h2>報名參加<span class="en">Event Registration</span></h2>
    <p><?= h($event['year']) ?> <?= h($event['name']) ?></p>
  </div>

  <?php if (!$window['open']): ?>
    <div class="card closed-card">
      <div class="closed-icon"><?= $window['reason'] === 'not_yet' ? '🕒' : '🔒' ?></div>
      <h3><?= $window['reason'] === 'not_yet' ? '報名尚未開放' : '線上報名已截止' ?>
        <span class="en"><?= $window['reason'] === 'not_yet' ? 'Registration is not open yet' : 'Online registration has closed' ?></span></h3>
      <p><?= h(App\Models\Event::windowMessage($window, 'rsvp')) ?></p>
      <p class="help">歡迎於活動當日親臨現場登記。Walk-in registration is available at the counter on the day.</p>
    </div>
  <?php else: ?>

  <form class="card form-card" action="<?= url('/rsvp/submit') ?>" method="POST" id="rsvpForm">
    <?= csrf_field() ?>

    <?php if (!empty($window['closes_at'])): ?>
      <div class="note">⏳ 線上報名將於 <strong><?= h(App\Models\Event::formatDateTime($window['closes_at'])) ?></strong> 截止。
        <span class="en">Online registration closes on <?= h(date('j M Y, g:i A', strtotime($window['closes_at']))) ?>.</span></div>
    <?php endif; ?>
    <?php if (!empty($event['rsvp_note'])): ?>
      <div class="note"><?= h($event['rsvp_note']) ?></div>
    <?php endif; ?>

    <div class="form-step"><b>1</b> <span>幾位參加？<?= info_tip('一家人或一群朋友可以一起報名，全部共用一個報名編號，活動當日出示一次即可報到。', 'Family or friends can register together under one reference number and check in together on the day.') ?></span><span class="en">How many people?</span></div>
    <div class="stepper">
      <button type="button" data-step="-1" aria-label="減少 Fewer">−</button>
      <input id="attendeeCount" name="attendee_count" type="number" inputmode="numeric"
             min="1" max="<?= $maxAttendees ?>" value="<?= $startCount ?>" aria-label="人數 Number of people">
      <button type="button" data-step="1" aria-label="增加 More">+</button>
    </div>
    <p class="help">每次最多 <?= $maxAttendees ?> 位，全部共用一個報名編號。
      Up to <?= $maxAttendees ?> people, all under one reference number.</p>

    <div class="form-step"><b>2</b> <span>填寫每位參加者資料<?= info_tip('身份證號碼用於活動當日核對身份；聯絡號碼用於活動通知。資料只供本會使用，並依個人資料保護法令（PDPA）妥善保管。沒有身份證可填護照號碼。', 'Your IC is used to confirm who you are at check-in; your phone number is for event updates. Data is used only by us and protected under the PDPA. No IC? Use your passport number.') ?></span><span class="en">Details for each person</span></div>
    <div id="attendees"></div>

    <button class="primary" type="submit">📝 提交報名<span class="en">Submit Registration</span></button>
  </form>
  <?php endif; ?>
</section>
</main>

<?php require BASE_PATH . '/app/Views/partials/modal.php'; ?>

<?php if ($window['open']): ?>
<script src="<?= asset('js/validate.js') ?>"></script>
<script>
(function () {
  var MAX   = <?= $maxAttendees ?>;
  var OLD   = <?= json_encode([
      'names'    => $old['names'] ?? [],
      'ics'      => $old['ics'] ?? [],
      'contacts' => $old['contacts'] ?? [],
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var countEl = document.getElementById('attendeeCount');
  var box     = document.getElementById('attendees');

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // Keep whatever was already typed when the number changes.
  function collect() {
    var rows = box.querySelectorAll('.attendee');
    rows.forEach(function (row, i) {
      OLD.names[i]    = row.querySelector('[name="attendee_name[]"]').value;
      OLD.ics[i]      = row.querySelector('[name="attendee_ic[]"]').value;
      OLD.contacts[i] = row.querySelector('[name="attendee_contact[]"]').value;
    });
  }

  function render() {
    collect();
    var n = Math.min(MAX, Math.max(1, parseInt(countEl.value, 10) || 1));
    countEl.value = n;
    var html = '';
    for (var i = 0; i < n; i++) {
      html += '<div class="attendee">'
        + '<h4>第 ' + (i + 1) + ' 位' + (i === 0 ? '（聯絡人）' : '')
        + '<span class="en">Person ' + (i + 1) + (i === 0 ? ' (main contact)' : '') + '</span></h4>'
        + '<label>姓名<span class="en">Full name</span></label>'
        + '<input name="attendee_name[]" required maxlength="100" autocomplete="name" value="' + esc(OLD.names[i]) + '">'
        + '<div class="row">'
        + '<div><label>身份證 / 護照號碼<span class="en">IC / Passport No.</span></label>'
        + '<input name="attendee_ic[]" required maxlength="30" data-validate="ic" autocomplete="off" placeholder="例 e.g. 651020-10-2020" value="' + esc(OLD.ics[i]) + '"></div>'
        + '<div><label>聯絡號碼<span class="en">Contact No.</span></label>'
        + '<input name="attendee_contact[]" required maxlength="30" inputmode="tel" autocomplete="tel" data-validate="phone" placeholder="例 e.g. 012 345 6789" value="' + esc(OLD.contacts[i]) + '">'
        + (i > 0 ? '<button type="button" class="btn ghost same-contact" style="margin-top:.5rem">同上 Same as person 1</button>' : '')
        + '</div></div></div>';
    }
    box.innerHTML = html;
    document.querySelector('[data-step="-1"]').disabled = n <= 1;
    document.querySelector('[data-step="1"]').disabled  = n >= MAX;
  }

  document.querySelectorAll('[data-step]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      countEl.value = (parseInt(countEl.value, 10) || 1) + Number(btn.dataset.step);
      render();
    });
  });
  countEl.addEventListener('change', render);

  // Families usually share one phone number.
  box.addEventListener('click', function (e) {
    if (!e.target.classList.contains('same-contact')) return;
    var first = box.querySelector('[name="attendee_contact[]"]').value;
    var target = e.target.parentNode.querySelector('input');
    target.value = first;
    if (window.TYTValidate) { target.dataset.touched = '1'; window.TYTValidate.check(target, true); }
  });

  render();
})();
</script>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
