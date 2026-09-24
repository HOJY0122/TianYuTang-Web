<?php
$pageTitle = '現場報到 Check-in';
$nav = 'checkin';
require BASE_PATH . '/app/Views/layouts/admin_header.php';
?>
<div class="wrap checkin-wrap">


  <?php if ($event['is_test']): ?>
    <div class="flash test">🧪 測試活動 — 此處的報到紀錄不列入正式統計。</div>
  <?php endif; ?>

  <!-- ---------- Running count ---------- -->
  <div class="checkin-stats">
    <div><small>已報到 Arrived</small><strong><?= (int) $stats['arrived'] ?></strong></div>
    <div><small>應到 Expected</small><strong><?= (int) $stats['expected'] ?></strong></div>
    <div><small>尚未報到 Remaining</small>
      <strong><?= max(0, (int) $stats['expected'] - (int) $stats['arrived']) ?></strong></div>
  </div>

  <!-- ---------- Lookup ---------- -->
  <div class="panel">
    <form method="GET" action="<?= url('/admin/checkin') ?>" class="lookup-form">
      <input type="hidden" name="event" value="<?= (int) $event['id'] ?>">
      <input type="text" name="ref" value="<?= h($ref) ?>" autofocus
             autocomplete="off" autocapitalize="characters" spellcheck="false"
             placeholder="輸入報名編號，例如 RSVP-0007">
      <button class="mini-btn" type="submit">查詢</button>
      <button class="mini-btn ghost" type="button" id="scanBtn">📷 掃描 QR</button>
    </form>

    <!-- Camera panel. Hidden until asked for: it needs HTTPS, a
         permission grant and decent light, none of which are certain
         in a temple hall — so it never blocks the manual path. -->
    <div id="scanPanel" class="scan-panel hidden">
      <video id="scanVideo" playsinline muted></video>
      <canvas id="scanCanvas" class="hidden"></canvas>
      <div class="scan-row">
        <span id="scanStatus" class="help">正在啟動相機…</span>
        <button class="mini-btn ghost" type="button" id="scanStop">停止</button>
      </div>
    </div>
  </div>

  <!-- ---------- Result ---------- -->
  <?php if ($notFound): ?>
    <div class="panel result-miss">
      <h2>查無此編號</h2>
      <p class="help">
        找不到 <strong><?= h($ref) ?></strong>。請確認編號是否正確，
        或該報名是否屬於其他年度的活動。
      </p>
    </div>

  <?php elseif ($group): ?>
    <?php
      $arrived = 0;
      foreach ($people as $p) { if (!empty($p['checked_in_at'])) $arrived++; }
      $allIn = $arrived === count($people);
    ?>
    <div class="panel result-hit<?= $allIn ? ' all-in' : '' ?>">
      <div class="result-head">
        <div>
          <h2><?= h($group['ref_code']) ?></h2>
          <div class="help">
            <?= (int) $group['attendee_count'] ?> 位 ·
            <?= $group['status'] === 'confirmed' ? '已確認' : ($group['status'] === 'cancelled' ? '已取消' : '待確認') ?>
            · 已報到 <?= $arrived ?>/<?= count($people) ?>
          </div>
        </div>
        <?php if (!$allIn): ?>
          <form method="POST" action="<?= url('/admin/checkin/group') ?>" style="margin:0">
            <?= csrf_field() ?>
            <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
            <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
            <input type="hidden" name="ref" value="<?= h($ref) ?>">
            <button class="big-btn" type="submit">✅ 全部報到</button>
          </form>
        <?php else: ?>
          <span class="badge">全部已報到</span>
        <?php endif; ?>
      </div>

      <div class="person-list">
        <?php foreach ($people as $p): ?>
          <?php $in = !empty($p['checked_in_at']); ?>
          <div class="person<?= $in ? ' is-in' : '' ?>">
            <div class="person-info">
              <strong><?= h($p['name']) ?></strong>
              <span class="help"><?= h($p['ic_no']) ?> · <?= h($p['contact_no']) ?></span>
              <?php if ($in): ?>
                <span class="help arrived-at">
                  已報到 <?= h(date('H:i', strtotime($p['checked_in_at']))) ?>
                </span>
              <?php endif; ?>
            </div>
            <form method="POST" action="<?= url('/admin/checkin/person') ?>" style="margin:0">
              <?= csrf_field() ?>
              <input type="hidden" name="attendee_id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
              <input type="hidden" name="ref" value="<?= h($ref) ?>">
              <?php if ($in): ?>
                <input type="hidden" name="undo" value="1">
                <button class="mini-btn ghost" type="submit">取消報到</button>
              <?php else: ?>
                <button class="big-btn" type="submit">報到</button>
              <?php endif; ?>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- ---------- Recent arrivals ---------- -->
  <?php if ($recent): ?>
    <div class="panel">
      <h2>最近報到</h2>
      <div class="recent-list">
        <?php foreach ($recent as $r): ?>
          <div class="recent-item">
            <span><?= h($r['name']) ?></span>
            <span class="help"><?= h($r['ref_code']) ?> · <?= h(date('H:i', strtotime($r['checked_in_at']))) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

</div>

<script src="<?= asset('js/jsqr.min.js') ?>"></script>
<script>
(function () {
  const btn    = document.getElementById('scanBtn');
  const panel  = document.getElementById('scanPanel');
  const video  = document.getElementById('scanVideo');
  const canvas = document.getElementById('scanCanvas');
  const status = document.getElementById('scanStatus');
  const stopBtn= document.getElementById('scanStop');
  const form   = document.querySelector('.lookup-form');
  const input  = form.querySelector('input[name="ref"]');

  let stream = null;
  let raf    = null;

  // Cameras need a secure context. Say so plainly rather than letting
  // the volunteer tap a button that silently does nothing.
  const secure = window.isSecureContext ||
                 location.hostname === 'localhost' ||
                 location.hostname === '127.0.0.1';

  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !secure) {
    btn.disabled = true;
    btn.title = secure ? '此裝置不支援相機' : '相機需要 HTTPS 才能使用，請改用手動輸入編號';
  }

  btn.addEventListener('click', start);
  stopBtn.addEventListener('click', stop);

  async function start() {
    panel.classList.remove('hidden');
    status.textContent = '正在啟動相機…';
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment' }   // rear camera on a phone
      });
      video.srcObject = stream;
      await video.play();
      status.textContent = '請將 QR Code 對準畫面';
      tick();
    } catch (e) {
      status.textContent = '無法使用相機（' + (e.name || 'error') + '）。請改用手動輸入編號。';
    }
  }

  function stop() {
    if (raf) cancelAnimationFrame(raf);
    raf = null;
    if (stream) stream.getTracks().forEach(t => t.stop());
    stream = null;
    panel.classList.add('hidden');
  }

  function tick() {
    if (!stream) return;
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
      canvas.width  = video.videoWidth;
      canvas.height = video.videoHeight;
      const ctx = canvas.getContext('2d', { willReadFrequently: true });
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

      const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const hit = window.TYTScanQR(img.data, img.width, img.height);

      if (hit && hit.data) {
        // The QR carries just the reference code, so this is a direct
        // lookup — no parsing, nothing to trust from the scanned value
        // beyond putting it in a field the server validates anyway.
        input.value = hit.data.trim();
        status.textContent = '已讀取：' + hit.data;
        stop();
        form.submit();
        return;
      }
    }
    raf = requestAnimationFrame(tick);
  }
})();
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
