<?php
/**
 * Donation page. Tick merit seats, freewill, or both — the total is
 * shown live, but the server recalculates it from the event's price.
 */
$pageTitle = '功德布施 Donate';
$seatPrice = (float) $event['merit_table_price'];
$wantSeats = $old ? !empty($old['want_seats']) : true;
$wantFree  = !empty($old['want_free']);
$limits    = App\Models\Event::donationLimits($event);
require BASE_PATH . '/app/Views/layouts/header.php';
?>

<main id="main">
<section class="section narrow">
  <div class="section-title">
    <h2><?= tb('donate.title') ?></h2>
    <p><?= tb('donate.intro') ?></p>
  </div>

  <?php if (!$window['open']): ?>
    <div class="card closed-card">
      <div class="closed-icon"><?= $window['reason'] === 'not_yet' ? '🕒' : '🔒' ?></div>
      <h3><?= tb($window['reason'] === 'not_yet' ? 'donate.not_yet' : 'donate.closed') ?></h3>
      <p><?= h(App\Models\Event::windowMessage($window, 'donation')) ?></p>
      <p class="help"><?= h(t('donate.counter') . ' ' . t('donate.counter', 'en')) ?></p>
    </div>
  <?php else: ?>

  <form class="card form-card" action="<?= url('/donation/submit') ?>" method="POST" id="donationForm">
    <?= csrf_field() ?>

    <?php if (!empty($window['closes_at'])): ?>
      <div class="note">⏳ 線上布施將於 <strong><?= h(App\Models\Event::formatDateTime($window['closes_at'])) ?></strong> 截止。
        <span class="en">Online donation closes on <?= h(date('j M Y, g:i A', strtotime($window['closes_at']))) ?>.</span></div>
    <?php endif; ?>
    <?php if (!empty($event['donation_note'])): ?>
      <div class="note"><?= h($event['donation_note']) ?></div>
    <?php endif; ?>

    <div class="form-step"><b>1</b> <span><?= h(t('donate.step1')) ?></span><span class="en"><?= h(t('donate.step1', 'en')) ?></span></div>
    <div class="row">
      <div>
        <label for="donName">姓名<span class="en">Full name</span></label>
        <input id="donName" name="name" required maxlength="100" autocomplete="name" value="<?= h($old['name'] ?? '') ?>">
      </div>
      <div>
        <label for="donContact">聯絡號碼<span class="en">Contact No.</span></label>
        <input id="donContact" name="contact" required maxlength="30" inputmode="tel" autocomplete="tel" data-validate="phone"
               placeholder="例 e.g. 012 345 6789" value="<?= h($old['contact'] ?? '') ?>">
      </div>
    </div>

    <div class="form-step"><b>2</b> <span><?= h(t('donate.step2')) ?></span><span class="en"><?= h(t('donate.step2', 'en')) ?></span></div>

    <div class="choice">
      <input type="checkbox" id="wantSeats" name="want_seats" value="1"<?= $wantSeats ? ' checked' : '' ?>>
      <div class="choice-body">
        <label class="choice-title" for="wantSeats"><strong><?= h(t('donate.seats') . ' ' . t('donate.seats', 'en')) ?></strong>
          <span class="help">每席 RM <?= number_format($seatPrice, 2) ?>　RM <?= number_format($seatPrice, 2) ?> per seat</span></label>
        <div class="detail" data-for="wantSeats">
          <div class="stepper">
            <button type="button" data-seat="-1" aria-label="減少 Fewer">−</button>
            <input id="tableCount" name="table_count" type="number" inputmode="numeric" min="<?= (int) $limits['seats_min'] ?>"
                   max="<?= (int) $limits['seats_max'] ?>" value="<?= h(($old['table_count'] ?? '') !== '' ? $old['table_count'] : '1') ?>"
                   aria-label="席數 Number of seats">
            <button type="button" data-seat="1" aria-label="增加 More">+</button>
          </div>
          <div class="limit-note hidden" id="seatNote" role="status"></div>
        </div>
      </div>
    </div>

    <div class="choice">
      <input type="checkbox" id="wantFree" name="want_free" value="1"<?= $wantFree ? ' checked' : '' ?>>
      <div class="choice-body">
        <label class="choice-title" for="wantFree"><strong><?= h(t('donate.free') . ' ' . t('donate.free', 'en')) ?></strong>
          <span class="help"><?= h(t('donate.free_hint') . ' ' . t('donate.free_hint', 'en')) ?></span></label>
        <div class="detail" data-for="wantFree">
          <label for="freeAmount" class="sr-only">金額 Amount (RM)</label>
          <input id="freeAmount" name="free_amount" type="number" inputmode="decimal" min="<?= h((string) $limits['free_min']) ?>" step="0.01"
                 placeholder="RM" value="<?= h($old['free_amount'] ?? '') ?>">
          <div class="quick-amounts">
            <?php foreach ([50, 100, 200, 500] as $amount): if ($amount < $limits['free_min'] || $amount > $limits['free_max']) continue; ?>
              <button type="button" data-amount="<?= $amount ?>">RM <?= $amount ?></button>
            <?php endforeach; ?>
          </div>
          <div class="limit-note hidden" id="freeNote" role="status"></div>
        </div>
      </div>
    </div>

    <div class="total">
      <span>總額 Total<small id="totalBreakdown"></small></span>
      <strong id="donationTotal">RM 0.00</strong>
    </div>

    <button class="primary" type="submit"><?= tb('donate.submit') ?></button>
    <p class="help" style="text-align:center"><?= h(t('donate.after') . ' ' . t('donate.after', 'en')) ?></p>
  </form>
  <?php endif; ?>
</section>
</main>

<?php require BASE_PATH . '/app/Views/partials/modal.php'; ?>

<?php if ($window['open']): ?>
<script src="<?= asset('js/validate.js') ?>"></script>
<script>
(function () {
  // Display only — the server recalculates the total from the event's price.
  var PRICE = <?= json_encode($seatPrice) ?>;
  // Limits set by the committee for this event (the server checks them too).
  var LIM = <?= json_encode($limits) ?>;
  var wantSeats = document.getElementById('wantSeats');
  var wantFree  = document.getElementById('wantFree');
  var seatsEl   = document.getElementById('tableCount');
  var freeEl    = document.getElementById('freeAmount');
  var money = function (n) { return 'RM ' + n.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

  function update() {
    // Only the ticked parts are shown, required and counted.
    document.querySelector('[data-for="wantSeats"]').classList.toggle('hidden', !wantSeats.checked);
    document.querySelector('[data-for="wantFree"]').classList.toggle('hidden', !wantFree.checked);
    seatsEl.required = wantSeats.checked;
    freeEl.required  = wantFree.checked;

    var seats = wantSeats.checked ? Math.max(0, parseInt(seatsEl.value, 10) || 0) : 0;
    var free  = wantFree.checked ? Math.max(0, parseFloat(freeEl.value) || 0) : 0;
    var parts = [];
    if (seats) parts.push(seats + ' 席 seats × ' + money(PRICE));
    if (free)  parts.push('隨喜 freewill ' + money(free));
    document.getElementById('totalBreakdown').textContent = parts.join(' + ');
    document.getElementById('donationTotal').textContent = money(seats * PRICE + free);
    checkLimits(seats, free);
  }

  // Over a maximum is generosity, not a mistake: thank them first, then
  // say how to give the rest. Under a minimum is a plain note.
  function note(id, text) {
    var el = document.getElementById(id);
    el.textContent = text || '';
    el.classList.toggle('hidden', !text);
  }
  function checkLimits(seats, free) {
    var problems = 0, s = '', f = '';
    if (seats && seats < LIM.seats_min) { s = '功德席最少 ' + LIM.seats_min + ' 席。\nThe minimum is ' + LIM.seats_min + ' seats.'; problems++; }
    else if (seats > LIM.seats_max) {
      s = '🙏 感恩您的大力護持！線上每次最多 ' + LIM.seats_max + ' 席。請先提交 ' + LIM.seats_max + ' 席，再提交一次餘下的席數，或於活動當日親臨櫃台辦理。'
        + '\nThank you for your generous support! Online, each submission is up to ' + LIM.seats_max + ' seats — please submit again for the rest, or visit our counter on the event day.';
      problems++;
    } else if (seats && seats === LIM.seats_max && LIM.seats_max < 200) {
      s = '已達線上每次上限 ' + LIM.seats_max + ' 席。如需更多，可再提交一次或於活動當日到櫃台辦理。\nThis is the online maximum per submission. For more, submit again or visit the counter on the day.';
    }
    if (free && free < LIM.free_min) { f = '隨喜金額最少 ' + money(LIM.free_min) + '。\nThe minimum freewill amount is ' + money(LIM.free_min) + '.'; problems++; }
    else if (free > LIM.free_max) {
      f = '🙏 感恩您的大力護持！線上每次隨喜最多 ' + money(LIM.free_max) + '。請先提交此金額，再提交一次餘額，或於活動當日親臨櫃台辦理。'
        + '\nThank you for your generous support! Online, each freewill gift is up to ' + money(LIM.free_max) + ' — please submit again for the rest, or visit our counter on the event day.';
      problems++;
    }
    note('seatNote', s); note('freeNote', f);
    document.querySelector('[data-seat="1"]').disabled = seats >= LIM.seats_max;
    return problems;
  }

  document.querySelectorAll('[data-seat]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      seatsEl.value = Math.min(LIM.seats_max, Math.max(LIM.seats_min, (parseInt(seatsEl.value, 10) || 0) + Number(btn.dataset.seat)));
      update();
    });
  });
  document.querySelectorAll('[data-amount]').forEach(function (btn) {
    btn.addEventListener('click', function () { freeEl.value = btn.dataset.amount; update(); });
  });
  [wantSeats, wantFree, seatsEl, freeEl].forEach(function (el) {
    el.addEventListener('input', update);
    el.addEventListener('change', update);
  });

  // At least one of the two must be ticked.
  document.getElementById('donationForm').addEventListener('submit', function (e) {
    if (!wantSeats.checked && !wantFree.checked) {
      e.preventDefault();
      (window.TYTDialog ? TYTDialog.alert('請至少選擇一種布施方式：功德席、隨喜，或兩者。\nPlease choose merit seats, a freewill amount, or both.', { type: 'info', title: '請選擇布施方式 Choose an option' }) : alert('請至少選擇一種布施方式。'));
      return;
    }
    var seats = wantSeats.checked ? parseInt(seatsEl.value, 10) || 0 : 0;
    var free  = wantFree.checked ? parseFloat(freeEl.value) || 0 : 0;
    if (checkLimits(seats, free)) {
      e.preventDefault();
      (document.querySelector('.limit-note:not(.hidden)') || seatsEl).scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
  });
  update();
})();
</script>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
