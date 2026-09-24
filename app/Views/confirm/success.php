<?php
$isRsvp    = ($kind === 'rsvp');
$refCode   = $confirmation['ref_code'];
$pageTitle = ($isRsvp ? '報名成功' : '感恩您的布施') . '｜天玉堂';
require BASE_PATH . '/app/Views/layouts/header.php';
?>

<main>
<section class="confirm-wrap">
  <div class="confirm-card">
    <div class="confirm-icon"><?= $isRsvp ? '📝' : '🙏' ?></div>
    <h1><?= $isRsvp ? '報名成功' : '感恩您的布施' ?></h1>
    <p class="confirm-sub">
      <?= $isRsvp
          ? '您的報名已完成，請保留此編號以便活動當日報到。'
          : '您的布施資料已收到，我們的工作人員會盡快與您聯繫確認付款方式。' ?>
    </p>

    <!-- The QR carries the reference code only — nothing personal. -->
    <div class="qr-box">
      <canvas id="qrCanvas" aria-label="報名編號 QR Code"></canvas>
      <div class="qr-ref"><?= h($refCode) ?></div>
      <div class="qr-hint">現場報到時出示此畫面即可</div>
    </div>

    <dl class="confirm-details">
      <?php if ($isRsvp): ?>
        <div><dt>報名編號</dt><dd><?= h($refCode) ?></dd></div>
        <div><dt>參加人數</dt><dd><?= (int) $confirmation['count'] ?> 位</dd></div>
        <?php if (!empty($confirmation['lead'])): ?>
          <div><dt>代表姓名</dt><dd><?= h($confirmation['lead']) ?></dd></div>
        <?php endif; ?>
      <?php else: ?>
        <div><dt>布施編號</dt><dd><?= h($refCode) ?></dd></div>
        <div><dt>姓名</dt><dd><?= h($confirmation['name']) ?></dd></div>
        <div><dt>方式</dt><dd>
          <?= $confirmation['method'] === 'table'
              ? '功德席 ' . (int) $confirmation['seats'] . ' 席'
              : '隨喜布施' ?>
        </dd></div>
        <div><dt>金額</dt><dd><?= rm((float) $confirmation['amount']) ?></dd></div>
      <?php endif; ?>
      <div><dt>活動</dt><dd><?= h($event['year']) ?> <?= h($event['name']) ?></dd></div>
    </dl>

    <div class="confirm-actions">
      <button class="primary no-print" onclick="window.print()">🖨️ 列印 / 存成 PDF</button>
      <a class="secondary-btn no-print" href="<?= url('/') ?>">返回首頁</a>
    </div>

    <p class="confirm-note no-print">
      建議截圖保存此畫面。離開後本頁面將無法再次開啟。
    </p>
  </div>
</section>
</main>

<script src="<?= asset('js/qrcode.min.js') ?>"></script>
<script>
(function () {
  const canvas = document.getElementById('qrCanvas');
  if (!canvas || !window.TYTQRCode) return;

  // Encode the reference code ONLY. A URL here would be a lookup anyone
  // could walk through by incrementing the number.
  window.TYTQRCode.toCanvas(canvas, <?= json_encode($refCode, JSON_UNESCAPED_UNICODE) ?>, {
    width: 220,
    margin: 2,
    errorCorrectionLevel: 'M',
    color: { dark: '#241b16', light: '#ffffff' }
  }).catch(function () {
    // If the canvas cannot be drawn the code is still printed below it,
    // so the visitor is never left with nothing.
    canvas.style.display = 'none';
  });
})();
</script>

<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
