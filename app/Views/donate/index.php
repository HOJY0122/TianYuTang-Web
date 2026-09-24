<?php
/**
 * Donation page. Tick merit seats, freewill, or both — the total is
 * shown live, but the server recalculates it from the event's price.
 */
$pageTitle = '功德布施 Donate';
$seatPrice = (float) $event['merit_table_price'];
$wantSeats = $old ? !empty($old['want_seats']) : true;
$wantFree  = !empty($old['want_free']);
require BASE_PATH . '/app/Views/layouts/header.php';
?>

<main id="main">
<section class="section narrow">
  <div class="section-title">
    <h2>功德布施<span class="en">Merit &amp; Donation</span></h2>
    <p>一份善念，一份布施，共種福田。<span class="en">Every act of giving plants a seed of merit.</span></p>
  </div>

  <?php if (!$window['open']): ?>
    <div class="card closed-card">
      <div class="closed-icon"><?= $window['reason'] === 'not_yet' ? '🕒' : '🔒' ?></div>
      <h3><?= $window['reason'] === 'not_yet' ? '布施尚未開放' : '線上布施已截止' ?>
        <span class="en"><?= $window['reason'] === 'not_yet' ? 'Online donation is not open yet' : 'Online donation has closed' ?></span></h3>
      <p><?= h(App\Models\Event::windowMessage($window, 'donation')) ?></p>
      <p class="help">歡迎於活動當日親臨櫃台布施。You are welcome to give at the counter on the day.</p>
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

    <div class="form-step"><b>1</b> 您的資料<span class="en">Your details</span></div>
    <div class="row">
      <div>
        <label for="donName">姓名<span class="en">Full name</span></label>
        <input id="donName" name="name" required maxlength="100" autocomplete="name" value="<?= h($old['name'] ?? '') ?>">
      </div>
      <div>
        <label for="donContact">聯絡號碼<span class="en">Contact No.</span></label>
        <input id="donContact" name="contact" required maxlength="30" inputmode="tel" autocomplete="tel"
               placeholder="例 e.g. 012 345 6789" value="<?= h($old['contact'] ?? '') ?>">
      </div>
    </div>

    <div class="form-step"><b>2</b> 布施方式（可選一項或兩項）<span class="en">Choose one or both</span></div>

    <div class="choice">
      <input type="checkbox" id="wantSeats" name="want_seats" value="1"<?= $wantSeats ? ' checked' : '' ?>>
      <div class="choice-body">
        <label class="choice-title" for="wantSeats"><strong>🪷 功德席 Merit Seats</strong>
          <span class="help">每席 RM <?= number_format($seatPrice, 2) ?>　RM <?= number_format($seatPrice, 2) ?> per seat</span></label>
        <div class="detail" data-for="wantSeats">
          <div class="stepper">
            <button type="button" data-seat="-1" aria-label="減少 Fewer">−</button>
            <input id="tableCount" name="table_count" type="number" inputmode="numeric" min="1"
                   max="<?= App\Models\Donation::MAX_SEATS ?>" value="<?= h(($old['table_count'] ?? '') !== '' ? $old['table_count'] : '1') ?>"
                   aria-label="席數 Number of seats">
            <button type="button" data-seat="1" aria-label="增加 More">+</button>
          </div>
        </div>
      </div>
    </div>

    <div class="choice">
      <input type="checkbox" id="wantFree" name="want_free" value="1"<?= $wantFree ? ' checked' : '' ?>>
      <div class="choice-body">
        <label class="choice-title" for="wantFree"><strong>🙏 隨喜布施 Freewill Donation</strong>
          <span class="help">任何金額皆可。Any amount you wish.</span></label>
        <div class="detail" data-for="wantFree">
          <label for="freeAmount" class="sr-only">金額 Amount (RM)</label>
          <input id="freeAmount" name="free_amount" type="number" inputmode="decimal" min="1" step="0.01"
                 placeholder="RM" value="<?= h($old['free_amount'] ?? '') ?>">
          <div class="quick-amounts">
            <?php foreach ([50, 100, 200, 500] as $amount): ?>
              <button type="button" data-amount="<?= $amount ?>">RM <?= $amount ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="total">
      <span>總額 Total<small id="totalBreakdown"></small></span>
      <strong id="donationTotal">RM 0.00</strong>
    </div>

    <button class="primary" type="submit">🙏 提交布施<span class="en">Submit Donation</span></button>
    <p class="help" style="text-align:center">提交後工作人員會與您聯繫確認付款。Our staff will contact you to arrange payment.</p>
  </form>
  <?php endif; ?>
</section>
</main>

<?php require BASE_PATH . '/app/Views/partials/modal.php'; ?>

<?php if ($window['open']): ?>
<script>
(function () {
  // Display only — the server recalculates the total from the event's price.
  var PRICE = <?= json_encode($seatPrice) ?>;
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
  }

  document.querySelectorAll('[data-seat]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      seatsEl.value = Math.max(1, (parseInt(seatsEl.value, 10) || 0) + Number(btn.dataset.seat));
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
      alert('請至少選擇一種布施方式。\nPlease choose at least one option.');
    }
  });
  update();
})();
</script>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
