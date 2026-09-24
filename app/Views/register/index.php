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
    <h2><?= tb('register.title') ?></h2>
    <p><?= h($event['year']) ?> <?= h($event['name']) ?></p>
  </div>

  <?php if (!$window['open']): ?>
    <div class="card closed-card">
      <div class="closed-icon"><?= $window['reason'] === 'not_yet' ? '🕒' : '🔒' ?></div>
      <h3><?= tb($window['reason'] === 'not_yet' ? 'register.not_yet' : 'register.closed') ?></h3>
      <p><?= h(App\Models\Event::windowMessage($window, 'rsvp')) ?></p>
      <p class="help"><?= h(t('register.walkin') . ' ' . t('register.walkin', 'en')) ?></p>
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

    <div class="form-step"><b>1</b> <span><?= h(t('register.step1')) ?></span><span class="en"><?= h(t('register.step1', 'en')) ?></span></div>
    <div class="stepper">
      <button type="button" data-step="-1" aria-label="減少 Fewer">−</button>
      <input id="attendeeCount" name="attendee_count" type="number" inputmode="numeric"
             min="1" max="<?= $maxAttendees ?>" value="<?= $startCount ?>" aria-label="人數 Number of people">
      <button type="button" data-step="1" aria-label="增加 More">+</button>
    </div>
    <p class="help"><?= h(t('register.limit', 'zh', ['max' => $maxAttendees]) . ' ' . t('register.limit', 'en', ['max' => $maxAttendees])) ?></p>

    <div class="form-step"><b>2</b> <span><?= h(t('register.step2')) ?></span><span class="en"><?= h(t('register.step2', 'en')) ?></span></div>
    <div id="attendees"></div>

    <button class="primary" type="submit"><?= tb('register.submit') ?></button>
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
    var n = Math.min(MAX, Math.max(1, parseInt(countEl.value, 10) || 1));
    countEl.value = n;
    var html = '';
    for (var i = 0; i < n; i++) {
      html += '<div class="attendee">'
        + '<h4><span class="num-pill">' + (i + 1) + '</span>第' + zhNum(i + 1) + '位' + (i === 0 ? '（聯絡人）' : '')
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
