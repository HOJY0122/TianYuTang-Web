<?php
$dateLines = App\Models\Event::formatDateLines($event);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>活動 QR Code｜<?= h($event['year']) ?> <?= h($event['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@700;800;900&family=Noto+Sans+TC:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/print.css') ?>">
<style>
  .qr-poster{text-align:center;padding:26px 20px;border:2px solid #333;border-radius:14px;max-width:560px;margin:0 auto}
  .qr-poster h1{font-family:"Noto Serif TC",serif;font-size:27px;margin:0 0 4px}
  .qr-poster .sub{font-size:16px;color:#444;margin-bottom:6px}
  .qr-poster .dates{font-size:14px;color:#444;line-height:1.8;margin-bottom:18px}
  .qr-poster canvas{display:block;margin:0 auto}
  .qr-poster .scan{margin-top:14px;font-size:17px;font-weight:800}
  .qr-poster .scan small{display:block;font-weight:400;font-size:13px;color:#555;margin-top:5px}
  .qr-url{margin-top:12px;font-size:12px;color:#666;word-break:break-all}
  .dl-row{display:flex;gap:10px;justify-content:center;margin-top:18px;flex-wrap:wrap}
</style>
</head>
<body>

<div class="toolbar no-print">
  <button class="primary" onclick="window.print()">🖨️ 列印海報</button>
  <button onclick="downloadQr()">⬇️ 下載 PNG</button>
  <a href="<?= url('/admin/dashboard') ?>?event=<?= (int) $event['id'] ?>">← 返回後台</a>
  <span class="hint">可貼在佈告欄，或下載 PNG 放進 WhatsApp、海報、傳單。</span>
</div>

<div class="qr-poster">
  <h1>天玉堂</h1>
  <div class="sub"><?= h($event['year']) ?> <?= h($event['name']) ?></div>
  <div class="dates">
    <?php foreach ($dateLines as $i => $line): ?><?= $i ? '<br>' : '' ?><?= h($line) ?><?php endforeach; ?>
  </div>

  <canvas id="eventQr"></canvas>

  <div class="scan">
    掃描 QR Code 線上報名 · 功德布施
    <small>Scan to register and donate online</small>
  </div>
  <div class="qr-url"><?= h($siteUrl) ?></div>
</div>

<script src="<?= asset('js/qrcode.min.js') ?>"></script>
<script>
const SITE_URL = <?= json_encode($siteUrl, JSON_UNESCAPED_SLASHES) ?>;

window.TYTQRCode.toCanvas(document.getElementById('eventQr'), SITE_URL, {
  width: 300,
  margin: 2,
  // Posters get scuffed, photographed at an angle, and printed small.
  // High correction still scans with roughly 30% of the code obscured.
  errorCorrectionLevel: 'H',
  color: { dark: '#000000', light: '#ffffff' }
});

function downloadQr() {
  // Render larger than the on-screen version so the PNG holds up when
  // someone drops it into a printed banner.
  window.TYTQRCode.toDataURL(SITE_URL, {
    width: 1200, margin: 2, errorCorrectionLevel: 'H'
  }).then(function (url) {
    const a = document.createElement('a');
    a.href = url;
    a.download = 'tianyutang-<?= (int) $event['year'] ?>-qr.png';
    a.click();
  });
}
</script>

</body>
</html>
