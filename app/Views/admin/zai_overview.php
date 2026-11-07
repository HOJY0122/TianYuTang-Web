<?php
/**
 * 施齋 Day 3 — 總覽 Overview.
 *
 * Three different counts, each labelled with what it counts:
 *   預約 Reserved  — PEOPLE booked, by where the booking came from
 *   已到 Arrived   — PEOPLE here (confirmed, groups arrived, waiting seated)
 *   座位 Seats     — SEATS taken; a 團體 group books whole tables, so each
 *                    group table counts as full whatever its head count
 * then any table over its seats, then every table.
 */
require BASE_PATH . '/app/Views/layouts/admin_header.php';
require BASE_PATH . '/app/Views/admin/_zai_head.php';
$kindLabel = ['person' => '已登記', 'group' => '團體', 'waiting' => '候補'];
$rv = $totals['reserved'];
$ar = $totals['arrived'];
$pct = static fn(int $part, int $whole): int => $whole > 0 ? (int) min(100, round($part / $whole * 100)) : 0;
$sources = [
    ['online',   'qr',        '網站 QR 登記', 'Website (QR)'],
    ['whatsapp', 'phone',     'WhatsApp',     'keyed in'],
    ['walkin',   'walk',      '現場登記',     'Walk-in'],
    ['groups',   'users',     '團體',         'Groups'],
    ['waiting',  'hourglass', '候補',         'Waiting list'],
];
$groupSeats = 0;
$over = [];
foreach ($seating as $no => $t) {
    foreach ($t['who'] as [$k, , $n]) {
        if ($k === 'group') {
            $groupSeats += $n;
        }
    }
    if ($t['seats'] > 0 && $t['used'] > $t['seats']) {
        $over[$no] = $t['used'] - $t['seats'];
    }
}
?>
<div id="liveZaiSummary" data-live="zai_people zai_groups zai_waiting zai_tables rsvp_groups rsvp_attendees">

  <div class="zov-cards">
    <!-- 預約 Reserved: people -->
    <section class="zov-card">
      <div class="zov-head"><?= icon('users') ?> 預約 <span class="en">Reserved</span></div>
      <div class="zov-big"><?= (int) $rv['total'] ?><small>人 people</small></div>
      <div class="zov-stack" role="img" aria-label="預約人數按來源 Reserved people by source">
        <?php foreach ($sources as [$key]): if ((int) $rv[$key] > 0): ?>
          <i class="s-<?= $key ?>" style="flex:<?= (int) $rv[$key] ?>"></i>
        <?php endif; endforeach; ?>
      </div>
      <ul class="zov-legend">
        <?php foreach ($sources as [$key, $ic, $zh, $en]): if ($key === 'walkin' && !(int) $rv[$key]) { continue; } ?>
          <li><span class="dot s-<?= $key ?>"></span><?= h($zh) ?> <span class="en"><?= h($en) ?></span><b><?= (int) $rv[$key] ?></b></li>
        <?php endforeach; ?>
      </ul>
    </section>

    <!-- 已到 Arrived: people -->
    <section class="zov-card">
      <div class="zov-head"><?= icon('check-circle') ?> 已到 <span class="en">Arrived</span></div>
      <div class="zov-big"><?= (int) $ar['total'] ?><small>/ <?= (int) $rv['total'] ?> 人 people</small></div>
      <div class="zov-bar"><i style="width:<?= $pct((int) $ar['total'], (int) $rv['total']) ?>%"></i></div>
      <ul class="zov-legend">
        <li>已登記 確認 <span class="en">Registered, confirmed</span><b><?= (int) $ar['people'] ?></b></li>
        <li>團體 已到 <span class="en">Groups arrived</span><b><?= (int) $totals['groups_arrived'] ?> / <?= (int) $totals['groups'] ?> <small>(<?= (int) $ar['groups'] ?> 人)</small></b></li>
        <li>候補 已入座 <span class="en">Waiting list, seated</span><b><?= (int) $ar['waiting'] ?></b></li>
      </ul>
    </section>

    <!-- 座位 Seats -->
    <section class="zov-card">
      <div class="zov-head"><?= icon('chair') ?> 座位 <span class="en">Seats</span></div>
      <div class="zov-big"><?= (int) $totals['used'] ?><small>/ <?= (int) $totals['seats'] ?> 位 seats</small></div>
      <div class="zov-bar seats<?= $totals['seats'] > 0 && $totals['used'] > $totals['seats'] ? ' is-over' : '' ?>"><i style="width:<?= $pct((int) $totals['used'], (int) $totals['seats']) ?>%"></i></div>
      <ul class="zov-legend">
        <li>有空位的桌 <span class="en">Tables with room</span><b><?= (int) $totals['tables_free'] ?> / <?= count($seating) ?></b></li>
        <li>團體包桌 <span class="en">Group tables (whole)</span><b><?= (int) $groupSeats ?> 位</b></li>
      </ul>
      <p class="zov-note">團體按整桌計，所以座位數可以比人數多。<span class="en">Groups book whole tables, so seats can exceed people.</span></p>
    </section>
  </div>

  <div class="zov-chips">
    <a class="zov-chip<?= (int) $totals['pending'] ? ' warn' : '' ?>" href="<?= url('/admin/zai/registered') . $evq ?>&amp;all=1">
      … 待定 <span class="en">Pending</span> <b><?= (int) $totals['pending'] ?></b></a>
    <a class="zov-chip<?= (int) $totals['waiting'] ? ' warn' : '' ?>" href="<?= url('/admin/zai/waiting') . $evq ?>">
      <?= icon('hourglass') ?> 候補中 <span class="en">Waiting</span> <b><?= (int) $totals['waiting'] ?></b> <small>(<?= (int) $totals['waiting_people'] ?> 人)</small></a>
    <a class="zov-chip" href="<?= url('/admin/walkin') . $evq ?>"><?= icon('phone') ?> WhatsApp 預約在「現場報名」輸入 <span class="en">Key in WhatsApp bookings on Walk-in register</span></a>
  </div>

  <?php if ($over): ?>
    <div class="zov-alert" role="alert">
      <?= icon('alert') ?>
      <div>
        <strong>有 <?= count($over) ?> 張桌超出座位 <span class="en"><?= count($over) ?> table<?= count($over) > 1 ? 's are' : ' is' ?> over capacity</span></strong>
        <div><?php foreach ($over as $no => $extra): ?>
          <a href="<?= url('/admin/zai/map') . $evq ?>&amp;table=<?= (int) $no ?>">第 <?= (int) $no ?> 桌 +<?= (int) $extra ?></a>
        <?php endforeach; ?></div>
        <small>點桌號到平面圖調整。<span class="en">Tap a table to fix it on the table map.</span></small>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="zov-tables-head">
    <h2><?= icon('chair') ?> 桌子 <span class="en">Tables</span></h2>
    <ul class="zov-key">
      <li><span class="k free"></span>有空位 <span class="en">Room</span></li>
      <li><span class="k full"></span>坐滿 <span class="en">Full</span></li>
      <li><span class="k over"></span>超出 <span class="en">Over</span></li>
      <li><span class="k grp"></span>團體桌 <span class="en">Group table</span></li>
    </ul>
  </div>
  <p class="help" style="margin-top:0">點一張桌看名單。<span class="en">Tap a table for its list.</span></p>
  <div class="zai-grid zov-grid" id="liveZaiGrid" data-live="zai_people zai_groups zai_waiting zai_tables">
    <?php foreach ($seating as $no => $t):
      $left = $t['seats'] - $t['used'];
      $cls = $t['seats'] === 0 ? 'is-unknown' : ($left > 0 ? 'is-free' : ($left === 0 ? 'is-full' : 'is-over'));
      $isGroup = ($t['kind'] ?? 'open') === 'group';
      foreach ($t['who'] as [$k]) { $isGroup = $isGroup || $k === 'group'; } ?>
      <details class="zai-table <?= $cls ?><?= $isGroup ? ' is-group' : '' ?>">
        <summary>
          <b><?= (int) $no ?></b>
          <span><?= (int) $t['used'] ?> / <?= (int) $t['seats'] ?></span>
          <i class="zov-fill"><i style="width:<?= $pct((int) $t['used'], (int) $t['seats']) ?>%"></i></i>
          <?php if ($isGroup): ?><em>團體</em><?php endif; ?>
        </summary>
        <ul>
          <?php foreach ($t['who'] as [$k, $label, $n]): ?>
            <li><span class="zai-kind k-<?= $k ?>"><?= $kindLabel[$k] ?></span> <?= h($label) ?><?= $n > 1 ? ' × ' . (int) $n : '' ?></li>
          <?php endforeach; ?>
          <?php if (!$t['who']): ?><li class="help">空桌 Empty</li><?php endif; ?>
        </ul>
      </details>
    <?php endforeach; ?>
  </div>
</div>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
