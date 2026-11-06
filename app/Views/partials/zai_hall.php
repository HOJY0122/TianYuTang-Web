<?php
/**
 * The hall, drawn like the floor plan: tables in rows ($hall['cols'] per
 * row), a walkway after every $hall['aisle'] rows, and the 櫃台 counter at
 * $hall['counter']. Shared by the admin map and the easy screens.
 *
 * Needs:  $hall   App\Models\Zai::hallLayout()
 *         $hallNos  table numbers to draw, in order
 *         $hallCell fn(int $no): string   the HTML for one table
 * Optional: $hallClass  extra class on the wrapper
 */
[$cPos, $cSide] = array_pad(explode('-', $hall['counter']), 2, 'left');
$counterBox = $hall['counter'] === 'none' ? '' :
    '<div class="hall-counter-row side-' . h($cSide) . '"><div class="hall-counter">' . icon('map-pin') . ' 櫃台 <span class="en">Counter</span></div></div>';
?>
<div class="hall <?= h($hallClass ?? '') ?>" style="--cols:<?= (int) $hall['cols'] ?>">
  <?= $cPos === 'top' ? $counterBox : '' ?>
  <?php foreach (App\Models\Zai::hallRows($hallNos, $hall['cols'], $hall['aisle']) as $row): ?>
    <?php if ($row === null): ?>
      <div class="hall-aisle" aria-hidden="true"><span>走道 Walkway</span></div>
    <?php else: ?>
      <div class="hall-row">
        <?php foreach ($row as $no) { echo $hallCell((int) $no); } ?>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>
  <?= $cPos === 'bottom' ? $counterBox : '' ?>
</div>
