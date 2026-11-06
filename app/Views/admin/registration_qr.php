<?php
/**
 * 報到 QR Code — the check-in pass staff send after checking a registration
 * (the public page no longer shows one). The QR carries the reference only.
 * The pass is drawn as one picture (event, reference, names, QR) to share
 * straight to WhatsApp, download, or print; then mark it sent.
 */
require BASE_PATH . '/app/Views/layouts/admin_header.php';
$ref       = $group['ref_code'];
$pending   = $group['status'] === 'pending';
$cancelled = $group['status'] === 'cancelled';
$lead      = $people[0]['name'] ?? '';
$dateLines = App\Models\Event::formatDateLines($event);
$dateText  = trim((string) ($event['date_text_zh'] ?? '')) ?: ($dateLines[0] ?? '');
$names     = array_column($people, 'name');
$msg = "您好 {$lead}，您的「{$event['year']} {$event['name']}」報名已確認 ✅\n報名編號：{$ref}\n人數：" . count($people) . " 位\n"
     . "活動當天請出示這個 QR Code 報到。\n\nHello {$lead}, your registration is confirmed. Reference {$ref}. Please show this QR code at check-in.";
$here = '/admin/registrations/qr?id=' . (int) $group['id'];
?>
<p style="margin:0 0 12px"><a class="mini-btn ghost" href="<?= url('/admin/registrations') ?>?event=<?= (int) $group['event_id'] ?>">← 報名紀錄 Registrations</a>
  <a class="mini-btn ghost" href="<?= url('/admin/registrations/edit') ?>?id=<?= (int) $group['id'] ?>"><?= icon('pencil') ?> 編輯 Edit</a></p>

<div class="page-grid">
<div class="page-col">
  <div class="panel qr-pass-panel">
    <h2 style="margin-top:0"><?= icon('qr') ?> 報到 QR Code <span class="en">Check-in QR</span> · <?= h($ref) ?></h2>
    <?php if ($cancelled): ?>
      <p class="flash error">這個報名已取消，不要發送 QR。This registration is cancelled — do not send a QR.</p>
    <?php elseif ($pending): ?>
      <p class="flash info"><?= icon('info') ?> 報名還是「待確認」。請先核對資料，按下面「確認並標記已發送」會一起確認。<br>
        <span class="en">Still pending — check the details first; “Confirm &amp; mark sent” below confirms it too.</span></p>
    <?php endif; ?>
    <canvas id="passCanvas" class="qr-pass" width="720" height="1000" role="img" aria-label="報到 QR Code <?= h($ref) ?>"></canvas>
  </div>
</div>
<div class="page-col">
  <div class="panel">
    <h2 style="margin-top:0"><?= icon('phone') ?> 發送 <span class="en">Send it</span></h2>
    <p><strong><?= h($lead) ?></strong> · <?= h($people[0]['contact_no'] ?? '—') ?> · <?= count($people) ?> 位 people</p>
    <div class="qr-send">
      <button type="button" class="primary" id="shareBtn"<?= $cancelled ? ' disabled' : '' ?>><?= icon('phone') ?> 分享到 WhatsApp <span class="en">Share</span></button>
      <button type="button" class="mini-btn btn-lg" id="dlBtn"><?= icon('download') ?> 下載圖片 <span class="en">Download picture</span></button>
      <?php if ($phone): ?>
        <a class="mini-btn ghost btn-lg" id="waLink" target="_blank" rel="noopener" href="https://wa.me/<?= h($phone) ?>?text=<?= h(rawurlencode($msg)) ?>"><?= icon('external') ?> 開 WhatsApp 對話 <span class="en">Open chat</span></a>
      <?php endif; ?>
      <button type="button" class="mini-btn ghost btn-lg" onclick="window.print()"><?= icon('printer') ?> 列印 <span class="en">Print</span></button>
    </div>
    <p class="help">手機上「分享」會把 QR 圖片直接傳到 WhatsApp。電腦上：先「下載圖片」，再「開 WhatsApp 對話」把圖片拖進去。<br>
      <span class="en">On a phone, Share sends the picture straight to WhatsApp. On a computer: download the picture, open the chat, and drop it in.</span></p>
    <label for="waMsg" style="font-weight:800">訊息 <span class="en">Message</span></label>
    <textarea id="waMsg" rows="6" style="width:100%;padding:10px;border:1px solid #d8bf79;border-radius:10px;background:#fffdf6;font:inherit"><?= h($msg) ?></textarea>

    <div class="qr-sent-box">
      <?php if ($group['qr_sent_at']): ?>
        <p><span class="badge ok">✓ 已發送 Sent</span> <?= h(date('d/m H:i', strtotime($group['qr_sent_at']))) ?> · <?= h($group['qr_sent_by'] ?? '') ?></p>
        <form method="POST" action="<?= url('/admin/registrations/qr/sent') ?>" style="margin:0">
          <?= csrf_field() ?><input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>"><input type="hidden" name="sent" value="0"><input type="hidden" name="return" value="<?= h($here) ?>">
          <button class="mini-btn ghost" type="submit">↺ 改回未發送 <span class="en">Mark not sent</span></button></form>
      <?php elseif (!$cancelled): ?>
        <form method="POST" action="<?= url('/admin/registrations/qr/sent') ?>" style="margin:0">
          <?= csrf_field() ?><input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>"><input type="hidden" name="sent" value="1"><input type="hidden" name="return" value="<?= h($here) ?>">
          <?php if ($pending): ?><input type="hidden" name="confirm" value="1"><?php endif; ?>
          <button class="primary" type="submit">✓ <?= $pending ? '確認並標記已發送 Confirm &amp; mark sent' : '標記已發送 Mark as sent' ?></button></form>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>

<script src="<?= asset('js/qrcode.min.js') ?>"></script>
<script>
(function () {
  var D = <?= json_encode([
      'ref' => $ref, 'event' => $event['year'] . ' ' . $event['name'], 'date' => $dateText,
      'names' => $names, 'site' => (new App\Models\Setting())->site()['site_name'] ?? '', 'pending' => $pending,
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var canvas = document.getElementById('passCanvas'), ctx = canvas.getContext('2d');
  var font = '"Noto Sans TC","Microsoft JhengHei",sans-serif';
  // Draw the pass: one picture that says what it is even when forwarded on its own.
  function draw() {
    var W = canvas.width, H = canvas.height;
    ctx.fillStyle = '#fffaf0'; ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = '#9f211b'; ctx.fillRect(0, 0, W, 150);
    ctx.fillStyle = '#fff'; ctx.textAlign = 'center';
    ctx.font = '900 40px ' + font; ctx.fillText(D.site, W / 2, 66);
    ctx.font = '700 26px ' + font; ctx.fillText('報到 QR Code · Check-in pass', W / 2, 114);
    ctx.fillStyle = '#2b1d14';
    ctx.font = '800 30px ' + font; ctx.fillText(D.event, W / 2, 205);
    if (D.date) { ctx.font = '500 22px ' + font; ctx.fillStyle = '#6a5a48'; ctx.fillText(D.date, W / 2, 242); }
    var q = document.createElement('canvas');
    return TYTQRCode.toCanvas(q, D.ref, { width: 440, margin: 2, errorCorrectionLevel: 'M', color: { dark: '#241b16', light: '#ffffff' } }).then(function () {
      ctx.fillStyle = '#fff'; ctx.fillRect(W / 2 - 236, 268, 472, 472);
      ctx.strokeStyle = '#e3d3a8'; ctx.lineWidth = 3; ctx.strokeRect(W / 2 - 236, 268, 472, 472);
      ctx.drawImage(q, W / 2 - 220, 284, 440, 440);
      ctx.fillStyle = '#9f211b'; ctx.font = '900 46px ' + font; ctx.fillText(D.ref, W / 2, 800);
      ctx.fillStyle = '#2b1d14'; ctx.font = '600 24px ' + font;
      var line = D.names.slice(0, 6).join('、') + (D.names.length > 6 ? ' 等 ' + D.names.length + ' 位' : '');
      ctx.fillText(line.length > 34 ? line.slice(0, 33) + '…' : line, W / 2, 846);
      ctx.fillStyle = '#6a5a48'; ctx.font = '500 21px ' + font;
      ctx.fillText('活動當天出示此 QR Code 報到', W / 2, 900);
      ctx.fillText('Show this QR code at the check-in counter', W / 2, 932);
    });
  }
  function file() {
    return new Promise(function (res) { canvas.toBlob(function (b) { res(new File([b], D.ref + '-QR.png', { type: 'image/png' })); }, 'image/png'); });
  }
  function download() {
    file().then(function (f) {
      var a = document.createElement('a'); a.href = URL.createObjectURL(f); a.download = f.name;
      document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
    });
  }
  var ready = (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve()).then(draw);
  document.getElementById('dlBtn').addEventListener('click', function () { ready.then(download); });
  var share = document.getElementById('shareBtn'), msg = document.getElementById('waMsg'), wa = document.getElementById('waLink');
  msg.addEventListener('input', function () { if (wa) wa.href = wa.href.split('?')[0] + '?text=' + encodeURIComponent(msg.value); });
  share.addEventListener('click', function () {
    ready.then(file).then(function (f) {
      // Phones: the share sheet sends the picture + message to WhatsApp in one go.
      if (navigator.canShare && navigator.canShare({ files: [f] })) {
        return navigator.share({ files: [f], text: msg.value }).catch(function () {});
      }
      download();
      if (wa) window.open(wa.href, '_blank', 'noopener');
    });
  });
})();
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
