<?php
/** 施齋 Day 3 — 總覽: every table, seats used / free, and who sits there. */
require BASE_PATH . '/app/Views/layouts/admin_header.php';
require BASE_PATH . '/app/Views/admin/_zai_head.php';
$kindLabel = ['person' => '已登記', 'group' => '團體', 'waiting' => '候補'];
?>
<div class="kpis" id="liveZaiKpis" data-live="zai_people zai_groups zai_waiting zai_tables rsvp_attendees">
  <div class="kpi"><div class="k-label">已坐 / 座位<span class="en">Seated / seats</span></div><div class="k-value"><?= (int) $totals['used'] ?> / <?= (int) $totals['seats'] ?></div></div>
  <div class="kpi"><div class="k-label">有空位的桌<span class="en">Tables with room</span></div><div class="k-value"><?= (int) $totals['tables_free'] ?></div></div>
  <div class="kpi"><div class="k-label">已登記 確認 / 待定<span class="en">Registered confirmed / pending</span></div><div class="k-value"><?= (int) $totals['confirmed'] ?> / <?= (int) $totals['pending'] ?></div></div>
  <div class="kpi"><div class="k-label">團體 已到 / 全部<span class="en">Groups arrived / all</span></div><div class="k-value"><?= (int) $totals['groups_arrived'] ?> / <?= (int) $totals['groups'] ?></div></div>
  <a class="kpi" href="<?= url('/admin/zai/waiting') . $evq ?>"><div class="k-label">候補中<span class="en">Waiting</span></div>
    <div class="k-value<?= (int) $totals['waiting'] ? ' warn-text' : '' ?>"><?= (int) $totals['waiting'] ?> <small>(<?= (int) $totals['waiting_people'] ?> 人)</small></div></a>
</div>

<div class="panel">
  <h2><?= icon('chair') ?> 桌子 <span class="en">Tables</span></h2>
  <p class="help" style="margin-top:0">綠色 = 有空位，黃色 = 坐滿，紅色 = 超出。點一張桌看名單。<span class="en">Green = room, yellow = full, red = over. Tap a table for its list.</span></p>
  <div class="zai-grid" id="liveZaiGrid" data-live="zai_people zai_groups zai_waiting zai_tables">
    <?php foreach ($seating as $no => $t):
      $left = $t['seats'] - $t['used'];
      $cls = $t['seats'] === 0 ? 'is-unknown' : ($left > 0 ? 'is-free' : ($left === 0 ? 'is-full' : 'is-over')); ?>
      <details class="zai-table <?= $cls ?>">
        <summary><b><?= (int) $no ?></b><span><?= (int) $t['used'] ?> / <?= (int) $t['seats'] ?></span></summary>
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
