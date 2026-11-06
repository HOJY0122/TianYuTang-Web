<?php
/** Easy mode — tap a table. Green = enough room for everyone, grey = not enough. */
$title = '請按一張桌'; $titleEn = 'Tap a table';
require __DIR__ . '/_top.php';
?>
<div class="ez-who"><?= icon('users') ?> <b><?= h(implode('、', $who)) ?></b> <span>· <?= (int) $need ?> 人 people</span></div>
<?php if (!$seating): ?>
  <div class="ez-msg ez-warn"><b>還沒有設定桌子</b><p>請負責人先在「完整版 → 桌子」設定。Ask the supervisor to set up the tables.</p></div>
<?php endif; ?>
<form method="POST" action="<?= url('/admin/zai/easy/seat') ?>" class="ez-tables">
  <?= csrf_field() ?>
  <input type="hidden" name="for" value="<?= h($for) ?>">
  <?php if ($for === 'wait'): ?><input type="hidden" name="id" value="<?= (int) $ids[0] ?>">
  <?php else: ?><input type="hidden" name="ids" value="<?= h(implode(',', $ids)) ?>"><?php endif; ?>
  <?php
  // General tables first; 團體桌 group tables are kept for groups, shown apart and asked about.
  $open = array_filter($seating, static fn($t) => $t['seats'] > 0 && ($t['kind'] ?? 'open') !== 'group');
  $grp  = array_filter($seating, static fn($t) => $t['seats'] > 0 && ($t['kind'] ?? 'open') === 'group');
  $btn = static function (int $no, array $t, int $need, bool $groupTable): string {
      $left = $t['seats'] - $t['used'];
      $fits = $left >= $need && !$groupTable;
      $ask = $groupTable ? '第 ' . $no . ' 桌是團體桌，還是要坐這裡？&#10;Table ' . $no . ' is kept for a group — seat them here anyway?'
           : (!$fits ? '第 ' . $no . ' 桌只剩 ' . max(0, $left) . ' 個位，還是要坐這裡？&#10;Only ' . max(0, $left) . ' seat(s) left — seat them here anyway?' : '');
      return '<button type="submit" name="table" value="' . $no . '" class="ez-tbl ' . ($groupTable ? 'grp' : ($fits ? 'fits' : ($left > 0 ? 'some' : 'full'))) . '"'
          . ($ask ? ' data-confirm="' . $ask . '"' : '') . '><b>' . $no . '</b><small>' . ($left > 0 ? '剩 ' . $left . ' 位' : '滿 Full') . '</small></button>';
  };
  foreach ($open as $no => $t) { echo $btn((int) $no, $t, $need, false); }
  ?>
  <?php if ($grp): ?>
    <p class="ez-step ez-step-sub">團體桌（給團體用）<small>Group tables — only if the supervisor says so</small></p>
    <?php foreach ($grp as $no => $t) { echo $btn((int) $no, $t, $need, true); } ?>
  <?php endif; ?>
</form>
<p class="ez-hint">綠色 = 夠位。灰色 = 位不夠。<span class="en">Green = enough seats. Grey = not enough.</span></p>
<?php require __DIR__ . '/_bottom.php'; ?>
