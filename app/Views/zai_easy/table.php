<?php
/** Easy mode — tap a table, on a map like the hall. Green = room for everyone; red = full (switched off). */
$title = '請按一張桌'; $titleEn = 'Tap a table';
require __DIR__ . '/_top.php';
?>
<div class="ez-who"><?= icon('users') ?> <b><?= h(implode('、', $who)) ?></b> <span>· <?= (int) $need ?> 人 people</span></div>
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
  $hallCell = static function (int $no) use ($seating, $need): string {
      $t = $seating[$no];
      $left = $t['seats'] - $t['used'];
      $groupTable = ($t['kind'] ?? 'open') === 'group';
      if ($left <= 0) {
          return '<button type="button" class="ez-tbl full" disabled aria-disabled="true" title="第 ' . $no . ' 桌已滿 Full">'
              . '<b>' . $no . '</b><small>滿 Full</small></button>';
      }
      $fits = $left >= $need && !$groupTable;
      $ask = $groupTable ? '第 ' . $no . ' 桌是團體桌，還是要坐這裡？&#10;Table ' . $no . ' is kept for a group — seat them here anyway?'
           : (!$fits ? '第 ' . $no . ' 桌只剩 ' . $left . ' 個位，還是要坐這裡？&#10;Only ' . $left . ' seat(s) left — seat them here anyway?' : '');
      return '<button type="submit" name="table" value="' . $no . '" class="ez-tbl ' . ($groupTable ? 'grp' : ($fits ? 'fits' : 'some')) . '"'
          . ($ask ? ' data-confirm="' . $ask . '"' : '') . '><b>' . $no . '</b><small>剩 ' . $left . ' 位' . ($groupTable ? ' · 團體' : '') . '</small></button>';
  };
  require BASE_PATH . '/app/Views/partials/zai_hall.php';
  ?>
</form>
<p class="ez-hint ez-legend"><i class="lg-fits"></i> 夠位 <i class="lg-some"></i> 位不夠 <i class="lg-grp"></i> 團體桌 <i class="lg-full"></i> 滿（不能按）<br>
  <span class="en">Green = enough seats · Yellow = not enough · Purple = group table · Red = full (cannot be pressed)</span></p>
<?php require __DIR__ . '/_bottom.php'; ?>
