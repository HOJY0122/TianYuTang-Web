<?php
$dateLines = App\Models\Event::formatDateLines($event);
$qrSite    = (new App\Models\Setting())->site();
$qrLogo    = $qrSite['site_logo_path'] ?: $qrSite['site_favicon_path'];
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
  <button class="primary" onclick="window.print()">🖨️ 列印海報 Print poster</button>
  <button onclick="downloadQr()">⬇️ 下載 PNG Download</button>
  <?php if ($qrLogo): ?>
    <label style="display:inline-flex;align-items:center;gap:6px;font-weight:700">
      <input type="checkbox" id="withLogo" checked> 置中標誌 Logo in the middle
    </label>
  <?php endif; ?>
  <a href="<?= url('/admin/dashboard') ?>?event=<?= (int) $event['id'] ?>">← 返回後台</a>
  <span class="hint">可貼在佈告欄，或下載 PNG 放進 WhatsApp、海報、傳單。</span>
</div>

<div class="qr-poster">
  <h1><?= h($qrSite['site_name']) ?></h1>
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
const SITE_URL = <?= json_encode($siteUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
const LOGO_URL = <?= json_encode($qrLogo ? BASE_URL . '/' . $qrLogo : null, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;

// Error correction H: the code still scans with about 30% of it hidden —
// which is what lets a logo sit in the middle. The logo and its white pad
// take 22% of the width (about 5% of the area), well inside that margin.
const QR_OPTS  = { margin: 2, errorCorrectionLevel: 'H', color: { dark: '#000000', light: '#ffffff' } };
const LOGO_PAD = 0.22;
let logoImg = null;

function drawLogo(canvas) {
  const box = document.getElementById('withLogo');
  if (!logoImg || (box && !box.checked)) return;
  const ctx  = canvas.getContext('2d');
  const size = canvas.width * LOGO_PAD;
  const x = (canvas.width - size) / 2, y = (canvas.height - size) / 2, r = size * 0.18;
  // White rounded pad: scanners read a clean block far better than a
  // logo blended into the black modules.
  ctx.fillStyle = '#ffffff';
  ctx.beginPath();
  ctx.moveTo(x + r, y); ctx.arcTo(x + size, y, x + size, y + size, r); ctx.arcTo(x + size, y + size, x, y + size, r);
  ctx.arcTo(x, y + size, x, y, r); ctx.arcTo(x, y, x + size, y, r); ctx.closePath(); ctx.fill();
  const inner = size * 0.84, ratio = Math.min(inner / logoImg.width, inner / logoImg.height);
  const w = logoImg.width * ratio, h = logoImg.height * ratio;
  ctx.drawImage(logoImg, (canvas.width - w) / 2, (canvas.height - h) / 2, w, h);
}

function render() {
  const canvas = document.getElementById('eventQr');
  // Drawn at 2× and shown at 300px, so it stays crisp when printed.
  window.TYTQRCode.toCanvas(canvas, SITE_URL, Object.assign({ width: 600 }, QR_OPTS)).then(function () {
    canvas.style.width = '300px'; canvas.style.height = '300px';
    drawLogo(canvas);
  });
}

function downloadQr() {
  // Larger than the on-screen version so it holds up on a printed banner.
  const big = document.createElement('canvas');
  window.TYTQRCode.toCanvas(big, SITE_URL, Object.assign({ width: 1200 }, QR_OPTS)).then(function () {
    drawLogo(big);
    const a = document.createElement('a');
    a.href = big.toDataURL('image/png');
    a.download = 'tianyutang-<?= (int) $event['year'] ?>-qr.png';
    a.click();
  });
}

if (LOGO_URL) {
  const img = new Image();
  img.onload = function () { logoImg = img; render(); };
  img.onerror = render;              // no logo? still show a plain QR
  img.src = LOGO_URL;
  document.getElementById('withLogo').addEventListener('change', render);
}
render();
</script>

</body>
</html>
