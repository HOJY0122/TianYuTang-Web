<?php
/** Easy mode — the first screen: who is in front of you? */
$title = '誰來了？'; $titleEn = 'Who is here?';
require __DIR__ . '/_top.php';
?>
<div class="ez-choices">
  <a class="ez-big ez-c1" href="<?= url('/admin/zai/easy/find') ?>">
    <span class="ez-num">1</span><span class="ez-ico"><?= icon('id-card') ?></span>
    <span class="ez-txt"><b>已經網上登記</b><small>Registered online · 有身份證</small></span></a>
  <a class="ez-big ez-c2" href="<?= url('/admin/zai/easy/groups') ?>">
    <span class="ez-num">2</span><span class="ez-ico"><?= icon('users') ?></span>
    <span class="ez-txt"><b>團體</b><small>A group · 帶支票</small></span></a>
  <a class="ez-big ez-c3" href="<?= url('/admin/zai/easy/wait') ?>">
    <span class="ez-num">3</span><span class="ez-ico"><?= icon('hand') ?></span>
    <span class="ez-txt"><b>沒有登記</b><small>Not registered · 排候補</small></span></a>
  <a class="ez-big ez-c4" href="<?= url('/admin/zai/easy/waitlist') ?>">
    <span class="ez-num">4</span><span class="ez-ico"><?= icon('hourglass') ?></span>
    <span class="ez-txt"><b>候補名單</b><small>Waiting list · 安排座位</small></span>
    <?php if ((int) $totals['waiting']): ?><span class="ez-count"><?= (int) $totals['waiting'] ?></span><?php endif; ?></a>
</div>
<?php
// How the day stands, updated by itself (live.js): people here, seats taken.
$ezPct = static fn(int $a, int $b): int => $b > 0 ? (int) min(100, round($a / $b * 100)) : 0;
$ezArr = (int) ($totals['arrived']['total'] ?? 0); $ezRes = (int) ($totals['reserved']['total'] ?? 0);
$ezUsed = (int) $totals['used']; $ezSeats = (int) $totals['seats'];
?>
<div class="ez-stats" id="liveEzStats" data-live="zai_people zai_groups zai_waiting zai_tables rsvp_groups rsvp_attendees">
  <a class="ez-stat" href="<?= url('/admin/zai/easy/roll') ?>">
    <span class="ez-stat-k"><?= icon('check-circle') ?> 已到 <small>Here</small><?= icon('chevron-right', 'ez-stat-go') ?></span>
    <span class="ez-stat-v"><b><?= $ezArr ?></b> / <?= $ezRes ?> 人</span>
    <span class="ez-stat-bar"><i style="width:<?= $ezPct($ezArr, $ezRes) ?>%"></i></span>
    <span class="ez-stat-sub">未到 <b><?= max(0, $ezRes - $ezArr) ?></b> 人 · 按這裡確認 <small>tap to confirm</small></span>
  </a>
  <a class="ez-stat<?= $ezSeats > 0 && $ezUsed >= $ezSeats ? ' is-full' : '' ?>" href="<?= url('/admin/zai/easy/tables') ?>">
    <span class="ez-stat-k"><?= icon('chair') ?> 座位 <small>Seats</small><?= icon('chevron-right', 'ez-stat-go') ?></span>
    <span class="ez-stat-v"><b><?= $ezUsed ?></b> / <?= $ezSeats ?></span>
    <span class="ez-stat-bar seats"><i style="width:<?= $ezPct($ezUsed, $ezSeats) ?>%"></i></span>
    <span class="ez-stat-sub">有空位的桌 <b><?= (int) $totals['tables_free'] ?></b> 張 <small>tables with room</small></span>
  </a>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
