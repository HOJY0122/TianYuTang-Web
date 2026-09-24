<?php
$pageTitle = '現場報到 Check-in';
$nav = 'checkin';
require BASE_PATH . '/app/Views/layouts/admin_header.php';
?>
<div class="wrap checkin-wrap">


  <?php if ($event['is_test']): ?>
    <div class="flash test">🧪 測試活動 — 此處的報到紀錄不列入正式統計。Test event — check-ins here are not counted.</div>
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
    <h2 style="margin-top:0">🔎 查詢報名 <span class="en">Find a registration</span></h2>
    <form method="GET" action="<?= url('/admin/checkin') ?>" class="lookup-form">
      <input type="hidden" name="event" value="<?= (int) $event['id'] ?>">
      <input type="text" name="ref" value="<?= h($ref) ?>" autofocus aria-label="報名編號 Reference"
             autocomplete="off" autocapitalize="characters" spellcheck="false"
             placeholder="報名編號 Reference，例 e.g. RSVP-0007">
      <button class="mini-btn btn-lg" type="submit">查詢 Find</button>
      <button class="mini-btn ghost btn-lg" type="button" data-scan>📷 掃描 Scan QR</button>
    </form>
    <p class="help" style="margin-bottom:0">掃到布施 QR 會自動轉到「現場布施」。A donation QR opens the counter page automatically.</p>

    <!-- Camera panel. Hidden until asked for: it needs HTTPS, a
         permission grant and decent light, none of which are certain
         in a temple hall — so it never blocks the manual path. -->
    <div id="scanPanel" class="scan-panel" hidden>
      <video playsinline muted></video>
      <canvas class="hidden"></canvas>
      <div class="scan-row">
        <span data-scan-status class="help"></span>
        <button class="mini-btn ghost" type="button" data-scan-stop>停止 Stop</button>
      </div>
    </div>
  </div>

  <!-- ---------- Result ---------- -->
  <?php if ($notFound): ?>
    <div class="panel result-miss">
      <h2>查無此編號 <span class="en">Not found</span></h2>
      <p class="help">
        找不到 <strong><?= h($ref) ?></strong>。請確認編號是否正確，或該報名是否屬於其他年度的活動。<br>
        No registration <strong><?= h($ref) ?></strong> in this event. Check the number, or whether it belongs to another year.
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
            <?= (int) $group['attendee_count'] ?> 位 people ·
            <?= $group['status'] === 'confirmed' ? '已確認 Confirmed' : ($group['status'] === 'cancelled' ? '已取消 Cancelled' : '待確認 Pending') ?>
            · 已報到 Arrived <?= $arrived ?>/<?= count($people) ?>
          </div>
        </div>
        <?php if (!$allIn): ?>
          <form method="POST" action="<?= url('/admin/checkin/group') ?>" style="margin:0">
            <?= csrf_field() ?>
            <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">
            <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
            <input type="hidden" name="ref" value="<?= h($ref) ?>">
            <button class="big-btn" type="submit">✅ 全部報到 Check in all</button>
          </form>
        <?php else: ?>
          <span class="badge">全部已報到 All arrived</span>
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
                  ✓ 已報到 Arrived <?= h(date('H:i', strtotime($p['checked_in_at']))) ?>
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
                <button class="mini-btn ghost" type="submit">取消報到 Undo</button>
              <?php else: ?>
                <button class="big-btn" type="submit">報到 Check in</button>
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
      <h2>最近報到 <span class="en">Recent arrivals</span></h2>
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
<script src="<?= asset('js/scanner.js') ?>"></script>
<script>
TYTScanner({
  form: document.querySelector('.lookup-form'), here: 'rsvp', eventId: <?= (int) $event['id'] ?>,
  urls: { rsvp: <?= json_encode(url('/admin/checkin')) ?>, don: <?= json_encode(url('/admin/counter')) ?> }
});
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
