<?php
/**
 * 施齋 Day 3 — 候補 waiting list: people who came without registering.
 * Record them (they wait in the waiting area); onsite staff seat them
 * when a place is found, or mark that they left.
 */
require BASE_PATH . '/app/Views/layouts/admin_header.php';
require BASE_PATH . '/app/Views/admin/_zai_head.php';
$waitingNow = array_filter($rows, static fn($r) => $r['status'] === 'waiting');
$statusLabel = ['waiting' => ['pending', '候補中 Waiting'], 'seated' => ['ok', '已入座 Seated'], 'left' => ['cancelled', '已離開 Left']];
?>
<div class="panel form-panel" style="max-width:none">
  <h2 style="margin-top:0"><?= icon('plus') ?> 加入候補 <span class="en">Add to the waiting list</span></h2>
  <form method="POST" action="<?= url('/admin/zai/waiting/add') ?>" class="zai-fields">
    <?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
    <label>姓名 <span class="en">Name</span> *<input name="name" required maxlength="100" autocomplete="off"></label>
    <label>身份證 <span class="en">IC</span> *<input name="ic" required maxlength="30" autocomplete="off" inputmode="numeric" data-validate="ic" data-exists-url="<?= h(url('/admin/zai/easy/ic-check')) ?>" placeholder="例 650101-10-1234"></label>
    <label>電話 <span class="en">Phone</span><input name="contact" maxlength="30" inputmode="tel" autocomplete="off" data-validate="phone"></label>
    <label class="wide">備註 <span class="en">Notes</span><input name="notes" maxlength="300"></label>
    <div class="form-actions wide" style="margin-top:4px;padding-top:12px"><button class="primary" type="submit"><?= icon('plus') ?> 加入，給號碼 <span class="en">Add &amp; give a number</span></button></div>
  </form>
  <p class="help">候補 1 人 1 身份證（必填），年齡規定與網上報名相同。如果已經網上登記，會帶你去「已登記」。只存身份證的雜湊與後 4 位。<br>
    <span class="en">One person, one IC (required), same age rule as online registration. If they registered online you are taken to Registered. Only a keyed hash and the last 4 digits are kept.</span></p>
</div>

<div class="panel">
  <h2 style="margin-top:0"><?= icon('hourglass') ?> 候補名單 <span class="en">Waiting list</span> · <?= count($waitingNow) ?></h2>
  <?php if (!$rows): ?><p class="empty" style="margin:0">還沒有人候補。No one is waiting.</p><?php endif; ?>
  <div class="zai-wait-list" id="liveZaiWait" data-live="zai_waiting">
  <?php foreach ($rows as $r): $wid = (int) $r['id']; [$cls, $lab] = $statusLabel[$r['status']]; ?>
    <form method="POST" action="<?= url('/admin/zai/waiting/update') ?>" class="zai-wait is-<?= $r['status'] ?>" id="w<?= $wid ?>">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $wid ?>">
      <div class="zai-who">
        <span class="zai-qno">#<?= (int) $r['queue_no'] ?></span>
        <strong><?= h($r['name']) ?></strong><?= (int) $r['people'] > 1 ? ' <span class="badge">' . (int) $r['people'] . ' 人</span>' : '' ?>
        <span class="badge <?= $cls ?>"><?= $lab ?><?= $r['status'] === 'seated' ? ' · 第 ' . (int) $r['table_no'] . ' 桌' : '' ?></span>
        <span class="help"><?= !empty($r['ic_no']) ? 'IC ' . h($r['ic_no']) . ' · ' : (($l4 = App\Models\Zai::waitingLast4($r)) ? 'IC …' . h(implode('、…', $l4)) . ' · ' : '') ?><?= $r['contact'] ? h($r['contact']) . ' · ' : '' ?><?= h(date('H:i', strtotime($r['created_at']))) ?><?= $r['notes'] ? ' · ' . h($r['notes']) : '' ?></span>
      </div>
      <div class="zai-act">
        <?= $tableSelect('table_no', $r['table_no'] !== null ? (int) $r['table_no'] : null, 'aria-label="桌號 Table"', (int) $r['people'], '#' . (int) $r['queue_no'] . ' ' . $r['name']) ?>
        <button class="mini-btn" name="status" value="seated" type="submit" data-needs-table>✓ 安排入座 <span class="en">Seat</span></button>
        <?php if ($r['status'] !== 'waiting'): ?><button class="mini-btn ghost" name="status" value="waiting" type="submit">↺ 改回候補</button><?php endif; ?>
        <?php if ($r['status'] !== 'left'): ?><button class="mini-btn ghost" name="status" value="left" type="submit"
          data-confirm="<?= h('#' . (int) $r['queue_no'] . ' ' . $r['name'] . ' 離開了？' . ($r['status'] === 'seated' ? "\n第 " . (int) $r['table_no'] . ' 桌會空出 ' . (int) $r['people'] . ' 個位。' : '') . "\n\nMark as left?") ?>"
          data-confirm-title="離開 Left" data-danger>已離開 <span class="en">Left</span></button><?php endif; ?>
      </div>
    </form>
  <?php endforeach; ?>
  </div>
</div>
<?php require BASE_PATH . '/app/Views/partials/ic_validate.php'; ?>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
