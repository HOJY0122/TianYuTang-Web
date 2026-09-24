<?php
$isRsvp    = ($kind === 'rsvp');
$refCode   = $confirmation['ref_code'];
$pageTitle = $isRsvp ? '報名成功 Registered' : '感恩您的布施 Thank you';
require BASE_PATH . '/app/Views/layouts/header.php';
?>

<main id="main">
<section class="confirm-wrap">
  <div class="card confirm-card">
    <div class="confirm-icon"><?= $isRsvp ? '✅' : '🙏' ?></div>
    <h1><?= $isRsvp ? '報名成功' : '感恩您的布施' ?><span class="en"><?= $isRsvp ? 'Registration complete' : 'Thank you for your donation' ?></span></h1>
    <p class="confirm-sub">
      <?php if ($isRsvp): ?>
        請截圖或列印此頁，活動當日出示 QR Code 即可報到。
        <span class="en">Please screenshot or print this page and show the QR code at the counter.</span>
      <?php else: ?>
        您的布施資料已收到，工作人員會盡快與您聯繫確認付款。
        <span class="en">We have received your donation details. Our staff will contact you about payment.</span>
      <?php endif; ?>
    </p>

    <!-- The QR carries the reference code only — nothing personal. -->
    <div class="qr-box">
      <canvas id="qrCanvas" aria-label="QR Code"></canvas>
      <div class="qr-ref"><?= h($refCode) ?></div>
      <div class="qr-hint"><?= $isRsvp ? '報到編號 Check-in reference' : '布施編號 Donation reference' ?></div>
    </div>

    <?php if ($isRsvp): ?>
      <dl class="confirm-details">
        <div><dt>報名編號 Reference</dt><dd><?= h($refCode) ?></dd></div>
        <div><dt>參加人數 People</dt><dd><?= (int) $confirmation['count'] ?> 位</dd></div>
        <div><dt>活動 Event</dt><dd><?= h($event['year']) ?> <?= h($event['name']) ?></dd></div>
      </dl>

      <div class="people">
        <h3>參加者名單 <span class="en" style="display:inline">Attendees</span></h3>
        <?php foreach (($confirmation['attendees'] ?? []) as $i => $person): ?>
          <div class="person">
            <span class="num"><?= $i + 1 ?></span>
            <strong><?= h($person['name']) ?></strong>
            <span>🪪 <?= h($person['ic']) ?></span>
            <span>📞 <?= h($person['contact']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <dl class="confirm-details">
        <div><dt>布施編號 Reference</dt><dd><?= h($refCode) ?></dd></div>
        <div><dt>姓名 Name</dt><dd><?= h($confirmation['name']) ?></dd></div>
        <?php if (!empty($confirmation['seats'])): ?>
          <div><dt>功德席 Merit seats</dt>
            <dd><?= (int) $confirmation['seats'] ?> 席 × <?= rm((float) ($confirmation['seat_price'] ?? 0)) ?></dd></div>
        <?php endif; ?>
        <?php if (!empty($confirmation['free_amount'])): ?>
          <div><dt>隨喜布施 Freewill</dt><dd><?= rm((float) $confirmation['free_amount']) ?></dd></div>
        <?php endif; ?>
        <div><dt>總額 Total</dt><dd style="color:var(--red);font-size:1.2em"><?= rm((float) $confirmation['amount']) ?></dd></div>
        <div><dt>活動 Event</dt><dd><?= h($event['year']) ?> <?= h($event['name']) ?></dd></div>
      </dl>
    <?php endif; ?>

    <div class="confirm-actions no-print">
      <button class="btn" onclick="window.print()">🖨️ 列印 / 存成 PDF　Print</button>
      <a class="btn ghost" href="<?= url('/') ?>">🏠 返回首頁 Home</a>
    </div>

    <p class="confirm-note no-print">
      離開後本頁面將無法再次開啟，請先截圖保存。
      <span class="en">This page cannot be opened again once you leave — please save a screenshot.</span>
    </p>
  </div>
</section>
</main>

<script src="<?= asset('js/qrcode.min.js') ?>"></script>
<script>
(function () {
  var canvas = document.getElementById('qrCanvas');
  if (!canvas || !window.TYTQRCode) return;

  // Encode the reference code ONLY. A URL here would be a lookup anyone
  // could walk through by incrementing the number.
  window.TYTQRCode.toCanvas(canvas, <?= json_encode($refCode, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>, {
    width: 220, margin: 2, errorCorrectionLevel: 'M',
    color: { dark: '#241b16', light: '#ffffff' }
  }).catch(function () {
    // The code is printed beneath, so the visitor is never left with nothing.
    canvas.style.display = 'none';
  });
})();
</script>

<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
