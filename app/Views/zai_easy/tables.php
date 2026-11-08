<?php
/**
 * Easy mode — 桌子 (from the 座位 card on the first screen): every table,
 * how full it is, and who sits there (tap a table).
 */
$title = '桌子'; $titleEn = 'Tables — tap one to see who sits there';
require __DIR__ . '/_top.php';
$kindLabel = ['person' => '已登記', 'group' => '團體', 'waiting' => '候補'];
?>
<p class="ez-hint" style="margin-top:0">已用 <b><?= (int) $totals['used'] ?></b> / <?= (int) $totals['seats'] ?> 座位 · 有空位的桌 <b><?= (int) $totals['tables_free'] ?></b> 張
  <span class="en"><?= (int) $totals['used'] ?> of <?= (int) $totals['seats'] ?> seats · <?= (int) $totals['tables_free'] ?> tables with room</span></p>
<div class="ez-tview-list" id="liveEzTables" data-live="zai_people zai_groups zai_waiting zai_tables">
  <?php foreach ($seating as $no => $t):
    $left = $t['seats'] - $t['used'];
    $isGroup = ($t['kind'] ?? 'open') === 'group';
    foreach ($t['who'] as $w) { $isGroup = $isGroup || $w[0] === 'group'; }
    $cls = $t['seats'] === 0 ? '' : ($left > 0 ? 'is-free' : ($left === 0 ? 'is-full' : 'is-over'));
    $pct = $t['seats'] > 0 ? (int) min(100, round($t['used'] / $t['seats'] * 100)) : 0; ?>
    <details class="ez-tview <?= $cls ?><?= $isGroup ? ' is-group' : '' ?>">
      <summary>
        <span class="ez-tno"><?= (int) $no ?></span>
        <span class="ez-tmid">
          <span class="ez-tline"><b><?= (int) $t['used'] ?></b> / <?= (int) $t['seats'] ?> 位
            <?php if ($isGroup): ?><em class="ez-ttag grp">團體桌</em><?php endif; ?>
            <?php if ($left < 0): ?><em class="ez-ttag over">超出 +<?= -$left ?></em><?php elseif ($left === 0 && $t['seats'] > 0): ?><em class="ez-ttag full">滿 Full</em><?php elseif ($t['seats'] > 0): ?><em class="ez-ttag free">剩 <?= $left ?> 位</em><?php endif; ?></span>
          <span class="ez-tbar"><i style="width:<?= $pct ?>%"></i></span>
        </span>
        <?= icon('chevron-down', 'ez-tchev') ?>
      </summary>
      <ul>
        <?php foreach ($t['who'] as [$k, $label, $cnt]): ?>
          <li><span class="zai-kind k-<?= h($k) ?>"><?= h($kindLabel[$k] ?? $k) ?></span> <?= h($label) ?><?= $cnt > 1 ? ' <b>× ' . (int) $cnt . '</b>' : '' ?></li>
        <?php endforeach; ?>
        <?php if (!$t['who']): ?><li class="ez-tempty">空桌 <small>Empty</small></li><?php endif; ?>
      </ul>
    </details>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
