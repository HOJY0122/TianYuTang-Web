<?php
/**
 * Easy mode ② — one 團體 group: its details, then the table(s) it takes.
 * A group buys / reserves whole tables, so no head count is asked: tick the
 * table(s), done. Only empty tables (or the group's own) can be ticked.
 */
$title = $g['name']; $titleEn = '團體 Group';
require __DIR__ . '/_top.php';
$mine = App\Models\Zai::tableList((string) ($g['tables'] ?? ''));
// Already booked: the tables are locked so a stray tap cannot add or drop
// one. "換桌 Change tables" unlocks them on purpose (?change=1).
$locked = $mine && empty($_GET['change']);
?>
<?php if (($_GET['from'] ?? '') === 'find'): $who = mb_substr(trim((string) ($_GET['who'] ?? '')), 0, 100); $fq = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 40); ?>
  <div class="ez-msg ez-info"><b><?= $who !== '' ? '「' . h($who) . '」' : '這個人' ?>屬於這個團體 ✓</b>
    <p>請在這裡跟整個團體一起處理。<br><span class="en"><?= $who !== '' ? h($who) : 'This person' ?> belongs to this group — handle the whole group here.</span></p>
    <?php if ($fq !== ''): ?><a class="ez-back-search" href="<?= url('/admin/zai/easy/find') ?>?q=<?= urlencode($fq) ?>">← 回到搜尋「<?= h($fq) ?>」 <span class="en">Back to the search</span></a><?php endif; ?></div>
<?php endif; ?>
<form method="POST" action="<?= url('/admin/zai/easy/group') ?>" class="ez-group">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
  <?php if ($locked): foreach ($mine as $no): ?><input type="hidden" name="tables[]" value="<?= (int) $no ?>"><?php endforeach; else: ?>
    <?php if ($mine): ?><input type="hidden" name="change" value="1"><?php endif; ?>
  <?php endif; ?>

  <div class="ez-gcard">
    <b><?= icon('users') ?> <?= h($g['name']) ?></b>
    <span><?= !empty($g['ref_code']) ? h($g['ref_code']) : ($g['list_no'] ? '名單 #' . h($g['list_no']) : '團體 Group') ?>
      <?= $g['contact'] ? ' · ' . icon('phone') . ' ' . h($g['contact']) : '' ?></span>
    <span><?= $mine ? '已安排 Booked: 第 ' . h(implode('、', $mine)) . ' 桌' : '還沒有安排桌子 No table booked yet' ?>
      <?= $g['status'] === 'arrived' ? ' · <em class="ok">✓ 已到 Arrived</em>' : '' ?></span>
  </div>

  <?php if ($locked): ?>
    <p class="ez-step"><span>1</span> 已安排的桌 <small>Booked tables (locked)</small></p>
    <a class="ez-change ez-change-big" href="<?= url('/admin/zai/easy/group') ?>?id=<?= (int) $g['id'] ?>&amp;change=1"><?= icon('refresh') ?> 換桌 <small>Change tables</small></a>
  <?php else: ?>
    <?php if ($mine): ?><div class="ez-msg ez-info"><b>換桌 Change tables</b><p>目前第 <?= h(implode('、', $mine)) ?> 桌。可加減桌子，按完成儲存。<br><span class="en">Now tables <?= h(implode(', ', $mine)) ?> — add or remove tables, then press Done.</span></p></div><?php endif; ?>
    <p class="ez-step"><span>1</span> 按桌號（可按多張）<small>Tap the table(s) they take<?= $mine ? ' — 已預先安排的已選好 the booked ones are already ticked' : '' ?></small></p>
  <?php endif; ?>
  <?php
  // Drawn like the hall. A group takes WHOLE tables: only an empty table, or
  // one already booked for this group, can be ticked. A table with anyone
  // else at it is red and switched off, so tables are never shared by mistake.
  $hallNos = array_keys(array_filter($seating, static fn($t) => $t['seats'] > 0));
  $hallClass = 'hall-easy ez-tables-multi';
  $hallCell = static function (int $no) use ($seating, $mine, $g, $locked): string {
      $t = $seating[$no];
      $self = in_array($no, $mine, true);
      if ($locked) {
          // Booked: own tables shown ticked, every other table switched off.
          return '<label class="ez-tbl ' . ($self ? 'fits mine' : 'off') . '" aria-disabled="true">'
              . '<input type="checkbox" value="' . $no . '"' . ($self ? ' checked' : '') . ' disabled>'
              . '<b>' . $no . '</b><small>' . ($self ? '已安排 Booked' : '—') . '</small></label>';
      }
      $free = App\Models\Zai::tableFreeForGroup($t, $g);
      $isGrp = ($t['kind'] ?? 'open') === 'group';
      $cls = !$free ? 'full' : ($self ? 'fits' : ($isGrp ? 'grp' : 'fits'));
      $note = $self ? '已安排' : (!$free ? ($t['used'] >= $t['seats'] ? '滿 Full' : '有人 Taken') : ($isGrp ? '空 · 團體桌' : '空桌 Empty'));
      return '<label class="ez-tbl ' . $cls . '"' . (!$free ? ' aria-disabled="true"' : '') . '>'
          . '<input type="checkbox" name="tables[]" value="' . $no . '"' . ($self ? ' checked' : '') . (!$free ? ' disabled' : '') . '>'
          . '<b>' . $no . '</b><small>' . $note . '</small></label>';
  };
  require BASE_PATH . '/app/Views/partials/zai_hall.php';
  ?>
  <p class="ez-hint" id="ezRoom"></p>
  <?php if (!$locked): ?>
  <p class="ez-hint ez-legend"><i class="lg-fits"></i> 空桌 <i class="lg-grp"></i> 空的團體桌 <i class="lg-full"></i> 已有人（不能按）
    <span class="en">Green = empty · Purple = empty group table · Red = taken (cannot be ticked)</span></p>
  <?php endif; ?>


  <p class="ez-step"><span>2</span> 完成 <small>Finish</small></p>
  <button type="submit" class="ez-btn ez-ok">✓ 完成，已到 <small>Done — they are here</small></button>
</form>
<script>
(function () {
  // How many tables are ticked (the group takes them whole).
  var room = document.getElementById('ezRoom');
  function show() {
    var n = document.querySelectorAll('.ez-tables-multi input:checked').length;
    if (!room) return;
    room.textContent = n ? '已選 ' + n + ' 張桌 · ' + n + ' table' + (n > 1 ? 's' : '') + ' ticked' : '請按桌號 Tap a table';
    room.className = 'ez-hint' + (n ? '' : ' ez-bad');
  }
  document.querySelectorAll('.ez-tables-multi input').forEach(function (i) { i.addEventListener('change', show); });
  show();
})();
</script>
<?php require __DIR__ . '/_bottom.php'; ?>
