<?php
/** Easy mode — tap a table, on a map like the hall. Green = room for everyone; red = full (switched off). */
$title = '請按一張桌'; $titleEn = 'Tap a table';
require __DIR__ . '/_top.php';
?>
<div class="ez-who"><?= icon('users') ?> <b><?= h(implode('、', $who)) ?></b> <span>· <?= (int) $need ?> 人 people</span></div>
<?php if (!empty($current)): ?>
  <div class="ez-msg ez-info"><b>換桌 Change table</b><p>目前在第 <?= (int) $current ?> 桌，請按新的桌號。<br><span class="en">Now at table <?= (int) $current ?> — tap the new table.</span></p></div>
<?php endif; ?>
<?php if (!$seating): ?>
  <div class="ez-msg ez-warn"><b>還沒有設定桌子</b><p>請負責人先在「完整版 → 桌子」設定。Ask the supervisor to set up the tables.</p></div>
<?php endif; ?>
<form method="POST" action="<?= url('/admin/zai/easy/seat') ?>" class="ez-seat">
  <?= csrf_field() ?>
  <input type="hidden" name="for" value="<?= h($for) ?>">
  <?php if ($for === 'wait'): ?><input type="hidden" name="id" value="<?= (int) $ids[0] ?>">
  <?php else: ?><input type="hidden" name="ids" value="<?= h(implode(',', $ids)) ?>"><?php endif; ?>
  <?php
  // Drawn like the hall (rows, walkway, counter). Red = full: the button is
  // switched off so it cannot be pressed by accident. Yellow = some room but
  // not for everyone (asks first). Purple = kept for groups (asks first).
  $hallNos = array_keys(array_filter($seating, static fn($t) => $t['seats'] > 0));
  $hallClass = 'hall-easy';
  $hallCell = static function (int $no) use ($seating, $need, $current): string {
      $t = $seating[$no];
      if (!empty($current) && $no === (int) $current) {
          return '<button type="button" class="ez-tbl fits is-current" disabled aria-disabled="true"><b>' . $no . '</b><small>目前 Now</small></button>';
      }
      $left = $t['seats'] - $t['used'];
      $groupTable = ($t['kind'] ?? 'open') === 'group';
      if ($groupTable) {
          // Bought / reserved by a 團體 group: never for individuals.
          return '<button type="button" class="ez-tbl grp is-off" disabled aria-disabled="true" title="第 ' . $no . ' 桌是團體桌 Group table">'
              . '<b>' . $no . '</b><small>團體桌 Group</small></button>';
      }
      if ($left <= 0) {
          return '<button type="button" class="ez-tbl full" disabled aria-disabled="true" title="第 ' . $no . ' 桌已滿 Full">'
              . '<b>' . $no . '</b><small>滿 Full</small></button>';
      }
      $fits = $left >= $need;
      $ask = !$fits ? '第 ' . $no . ' 桌只剩 ' . $left . ' 個位，還是要坐這裡？&#10;Only ' . $left . ' seat(s) left — seat them here anyway?' : '';
      return '<button type="submit" name="table" value="' . $no . '" class="ez-tbl ' . ($fits ? 'fits' : 'some') . '"'
          . ($ask ? ' data-confirm="' . $ask . '"' : '') . '><b>' . $no . '</b><small>剩 ' . $left . ' 位</small></button>';
  };
  require BASE_PATH . '/app/Views/partials/zai_hall.php';
  ?>
</form>
<div class="ez-legend" aria-label="顏色說明 Colour key">
  <span><i class="lg-fits"></i>夠位 <small>Enough</small></span>
  <span><i class="lg-some"></i>位不夠 <small>Not enough</small></span>
  <span><i class="lg-grp"></i>團體桌 <small>Group only</small></span>
  <span><i class="lg-full"></i>滿 <small>Full</small></span>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
