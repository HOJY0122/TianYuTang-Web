<?php
/**
 * Public homepage. Every event-specific value comes from $event, the
 * active row in the events table — nothing here is hardcoded to 2026.
 */
$seatPrice = (float) $event['merit_table_price'];
$pageTitle = '天玉堂｜' . $event['year'] . ' ' . $event['name'];
require BASE_PATH . '/app/Views/layouts/header.php';
?>

<main id="home">
<?php
// An uploaded banner becomes the hero background. Without one the hero
// keeps its original gradient, so the site looks finished either way.
$bannerPath = $event['hero_banner_path'] ?? null;
$heroStyle  = $bannerPath
    ? ' style="background-image:linear-gradient(rgba(255,247,217,.86),rgba(255,250,240,.93)), url(\''
      . h(BASE_URL . '/' . $bannerPath) . '\');background-size:cover;background-position:center"'
    : '';
?>
<section class="hero<?= $bannerPath ? ' has-banner' : '' ?>"<?= $heroStyle ?>>
  <div>
    <div class="cloud">☁　☁　☁</div>
    <?php if (!empty($event['year_label'])): ?>
      <div class="year"><?= h($event['year']) ?> <?= h($event['year_label']) ?></div>
    <?php else: ?>
      <div class="year"><?= h($event['year']) ?></div>
    <?php endif; ?>
    <h1>天玉堂</h1>
    <h2><?= h($event['name']) ?></h2>
    <p>誠邀十方善信共襄盛舉，同結善緣，共種福田。</p>
    <?php if (!empty($event['subtitle'])): ?>
      <p><?= h($event['subtitle']) ?><?= $dateRange ? ' · ' . h($dateRange) : '' ?></p>
    <?php endif; ?>
    <div class="actions">
      <a class="cta" href="#rsvp"><div class="icon">📝</div><strong>報名參加</strong><span>我要參加活動 · Register to Attend</span></a>
      <a class="cta" href="#donation"><div class="icon">🙏</div><strong>功德布施</strong><span>我要支持活動 · Support the Event</span></a>
    </div>
  </div>
</section>

<section>
  <div class="section-title">
    <div class="eyebrow">EVENT INFORMATION</div>
    <h2>活動資料</h2>
    <p>清楚、簡單、方便長輩使用</p>
  </div>
  <div class="info-grid">
    <div class="info-card">
      <h3>📅 日期</h3>
      <p><?php foreach ($dateLines as $i => $line): ?><?= $i ? '<br>' : '' ?><?= h($line) ?><?php endforeach; ?></p>
    </div>
    <div class="info-card">
      <h3>📍 地點</h3>
      <p><?= nl2br(h($event['location'])) ?></p>
    </div>
    <div class="info-card">
      <h3>🙏 誠邀參與</h3>
      <p>席位有限，敬請提前登記。未能提前登記者，也可於活動當日親臨現場登記。
      <?php if (!empty($event['counter_note'])): ?>
        <br><span class="help"><?= h($event['counter_note']) ?></span>
      <?php endif; ?>
      </p>
    </div>
  </div>
</section>

<?php if (!empty($photoPreview)): ?>
<!-- ============ 相簿預覽 Gallery teaser ============ -->
<section id="photos">
  <div class="section-title">
    <div class="eyebrow">PHOTO ARCHIVE</div>
    <h2>📸 活動留影</h2>
    <p>法會精彩片刻，與十方善信共同回顧。</p>
  </div>
  <div class="photo-strip">
    <?php foreach ($photoPreview as $photo): ?>
      <a class="strip-item" href="<?= url('/gallery') ?>?year=<?= (int) $event['id'] ?>">
        <img src="<?= h(BASE_URL . '/' . $photo['thumb_path']) ?>"
             alt="<?= h($photo['caption'] ?? '法會留影') ?>" loading="lazy">
      </a>
    <?php endforeach; ?>
  </div>
  <div style="text-align:center;margin-top:24px">
    <a class="gallery-link" href="<?= url('/gallery') ?>">瀏覽完整相簿 View Full Gallery →</a>
  </div>
</section>
<?php endif; ?>

<!-- ============ 報名 RSVP ============ -->
<section id="rsvp">
  <div class="section-title">
    <div class="eyebrow">REGISTRATION</div>
    <h2>📝 報名參加</h2>
    <p>請填寫參加者資料，每位參加者均需完整填寫。</p>
  </div>
  <div class="form-wrap">
    <?php if (!$rsvpWindow['open']): ?>
      <div class="form-card closed-card">
        <div class="closed-icon"><?= $rsvpWindow['reason'] === 'not_yet' ? '🕒' : '🔒' ?></div>
        <h3><?= $rsvpWindow['reason'] === 'not_yet' ? '報名尚未開放' : '線上報名已截止' ?></h3>
        <p><?= h(App\Models\Event::windowMessage($rsvpWindow, 'rsvp')) ?></p>
      </div>
    <?php else: ?>
    <form class="form-card" action="<?= url('/rsvp/submit') ?>" method="POST">
      <?= csrf_field() ?>
      <h3>參加者資料</h3>
      <?php if (!empty($rsvpWindow['closes_at'])): ?>
        <div class="note">
          ⏳ 線上報名將於 <strong><?= h(App\Models\Event::formatDateTime($rsvpWindow['closes_at'])) ?></strong> 截止。
        </div>
      <?php endif; ?>
      <div class="note">第一步：選擇參加人數。網站會自動產生相應的參加者資料欄位。</div>

      <label for="attendeeCount">報名人數｜Number of Attendees</label>
      <select id="attendeeCount" name="attendee_count" onchange="renderAttendees()">
        <?php
        $selectedCount = (int) ($oldInput['rsvp_count'] ?? 1);
        $maxAttendees  = (int) $event['max_attendees'];
        for ($i = 1; $i <= $maxAttendees; $i++):
        ?>
          <option value="<?= $i ?>"<?= $i === $selectedCount ? ' selected' : '' ?>><?= $i ?> 位</option>
        <?php endfor; ?>
      </select>

      <div id="attendees"></div>
      <button class="primary" type="submit">📝 提交報名</button>
    </form>
    <?php endif; ?>
  </div>
</section>

<!-- ============ 布施 Donation ============ -->
<section id="donation">
  <div class="section-title">
    <div class="eyebrow">MERIT &amp; DONATION</div>
    <h2>🙏 功德布施</h2>
    <p>一份善念，一份布施，共種福田，共結善緣。</p>
  </div>
  <div class="form-wrap">
    <?php if (!$donationWindow['open']): ?>
      <div class="form-card closed-card">
        <div class="closed-icon"><?= $donationWindow['reason'] === 'not_yet' ? '🕒' : '🔒' ?></div>
        <h3><?= $donationWindow['reason'] === 'not_yet' ? '布施尚未開放' : '線上布施已截止' ?></h3>
        <p><?= h(App\Models\Event::windowMessage($donationWindow, 'donation')) ?></p>
      </div>
    <?php else: ?>
    <form class="form-card" action="<?= url('/donation/submit') ?>" method="POST">
      <?= csrf_field() ?>
      <h3>布施資料</h3>

      <?php if (!empty($donationWindow['closes_at'])): ?>
        <div class="note">
          ⏳ 線上布施將於 <strong><?= h(App\Models\Event::formatDateTime($donationWindow['closes_at'])) ?></strong> 截止。
        </div>
      <?php endif; ?>

      <div class="row">
        <div>
          <label for="donName">姓名｜Name</label>
          <input id="donName" name="name" required maxlength="100"
                 value="<?= h($oldInput['don_name'] ?? '') ?>" placeholder="請輸入姓名">
        </div>
        <div>
          <label for="donContact">聯絡號碼｜Contact No.</label>
          <input id="donContact" name="contact" required maxlength="30"
                 value="<?= h($oldInput['don_contact'] ?? '') ?>" placeholder="例如：012 345 6789">
        </div>
      </div>

      <label for="donationMethod">布施方式｜Donation Method</label>
      <?php $oldMethod = $oldInput['don_method'] ?? 'free'; ?>
      <select id="donationMethod" name="method" onchange="updateDonation()">
        <option value="free"<?= $oldMethod === 'free' ? ' selected' : '' ?>>🙏 隨喜布施 / Freewill Donation</option>
        <option value="table"<?= $oldMethod === 'table' ? ' selected' : '' ?>>🪷 布施功德席 RM<?= number_format($seatPrice, 0) ?> / 席</option>
      </select>

      <div id="freeDonation">
        <label for="freeAmount">布施金額｜Donation Amount (RM)</label>
        <input id="freeAmount" name="free_amount" type="number" min="1" step="0.01"
               placeholder="請輸入金額" oninput="updateDonation()">
      </div>

      <div id="tableDonation" class="hidden">
        <label for="tableCount">功德席數量｜Number of Merit Tables</label>
        <input id="tableCount" name="table_count" type="number" min="1" max="200" value="1" oninput="updateDonation()">
        <p class="help">每席 RM<?= number_format($seatPrice, 0) ?>，例如：2 席 = RM<?= number_format(2 * $seatPrice) ?></p>
      </div>

      <div class="total"><span>總額｜Total</span><strong id="donationTotal">RM 0</strong></div>
      <button class="primary" type="submit">🙏 提交布施</button>
    </form>
    <?php endif; ?>
  </div>
</section>
</main>

<?php require BASE_PATH . '/app/Views/partials/modal.php'; ?>

<script>
// Price comes from the event row, so changing it in admin updates the
// running total here too — no code edit needed.
const MERIT_SEAT_PRICE = <?= (float) $seatPrice ?>;

function renderAttendees(){
  const countEl = document.getElementById('attendeeCount');
  const box     = document.getElementById('attendees');
  // The form is absent when registration is closed — nothing to build.
  if (!countEl || !box) return;

  const n = Number(countEl.value);
  box.innerHTML = '';
  for (let i = 1; i <= n; i++){
    box.insertAdjacentHTML('beforeend', `
      <div class="attendee">
        <h4>參加者 ${i}</h4>
        <label>姓名｜Name</label>
        <input name="attendee_name[]" required maxlength="100" placeholder="請輸入姓名">
        <div class="row">
          <div><label>身份證號碼｜IC No.</label><input name="attendee_ic[]" required maxlength="30" placeholder="例如：651020-10-2020"></div>
          <div><label>聯絡號碼｜Contact No.</label><input name="attendee_contact[]" required maxlength="30" placeholder="例如：012 345 6789"></div>
        </div>
      </div>`);
  }
}

function updateDonation(){
  const methodEl = document.getElementById('donationMethod');
  // The form is absent when donations are closed.
  if (!methodEl) return;

  const method     = methodEl.value;
  const freeAmount = document.getElementById('freeAmount');
  const tableCount = document.getElementById('tableCount');

  document.getElementById('freeDonation').classList.toggle('hidden', method !== 'free');
  document.getElementById('tableDonation').classList.toggle('hidden', method !== 'table');

  // Only the field in use should be submitted / required.
  freeAmount.disabled = (method !== 'free');
  tableCount.disabled = (method !== 'table');

  const total = (method === 'free')
    ? (Number(freeAmount.value) || 0)
    : (Number(tableCount.value) || 0) * MERIT_SEAT_PRICE;

  document.getElementById('donationTotal').textContent = 'RM ' + total.toLocaleString('en-MY');
}

renderAttendees();
updateDonation();
</script>

<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
