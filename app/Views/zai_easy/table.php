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
  <?php foreach ($seating as $no => $t): if ($t['seats'] === 0) continue;
    $left = $t['seats'] - $t['used']; $fits = $left >= $need; ?>
    <button type="submit" name="table" value="<?= (int) $no ?>" class="ez-tbl <?= $fits ? 'fits' : ($left > 0 ? 'some' : 'full') ?>"
      <?= $fits ? '' : 'data-confirm="第 ' . (int) $no . ' 桌只剩 ' . max(0, $left) . ' 個位，還是要坐這裡？&#10;Only ' . max(0, $left) . ' seat(s) left — seat them here anyway?"' ?>>
      <b><?= (int) $no ?></b><small><?= $left > 0 ? '剩 ' . $left . ' 位' : '滿 Full' ?></small>
    </button>
  <?php endforeach; ?>
</form>
<p class="ez-hint">綠色 = 夠位。灰色 = 位不夠。<span class="en">Green = enough seats. Grey = not enough.</span></p>
<?php require __DIR__ . '/_bottom.php'; ?>
