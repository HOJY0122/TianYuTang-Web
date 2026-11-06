<?php
/** Easy mode ② — one group: how many people, which table(s), done → reminder to take the cheque photo. */
$title = $g['name']; $titleEn = '團體 Group';
require __DIR__ . '/_top.php';
$mine = App\Models\Zai::tableList((string) ($g['tables'] ?? ''));
// Seats left at each table, not counting this group's own people already there.
$ownSpread = App\Models\Zai::spread($g, array_map(static fn($t) => $t['seats'], $seating));
?>
<?php if (($_GET['from'] ?? '') === 'find'): $who = mb_substr(trim((string) ($_GET['who'] ?? '')), 0, 100); $fq = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 40); ?>
  <div class="ez-msg ez-info"><b><?= $who !== '' ? '「' . h($who) . '」' : '這個人' ?>屬於這個團體 ✓</b>
    <p>請在這裡跟整個團體一起處理。<br><span class="en"><?= $who !== '' ? h($who) : 'This person' ?> belongs to this group — handle the whole group here.</span></p>
    <?php if ($fq !== ''): ?><a class="ez-back-search" href="<?= url('/admin/zai/easy/find') ?>?q=<?= urlencode($fq) ?>">← 回到搜尋「<?= h($fq) ?>」 <span class="en">Back to the search</span></a><?php endif; ?></div>
<?php endif; ?>
<form method="POST" action="<?= url('/admin/zai/easy/group') ?>" class="ez-group">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $g['id'] ?>">

  <p class="ez-step"><span>1</span> 來了幾個人？<small>How many people came?</small></p>
  <div class="ez-stepper">
    <button type="button" class="ez-pm" data-step="-1" aria-label="減一 Minus one">−</button>
    <input type="number" name="people" id="ezPeople" value="<?= (int) $g['people'] ?>" min="0" max="5000" inputmode="numeric">
    <button type="button" class="ez-pm" data-step="1" aria-label="加一 Plus one">+</button>
  </div>

  <p class="ez-step"><span>2</span> 按桌號（可按多張）<small>Tap the table(s)<?= $mine ? ' — 已預先安排的已選好 the booked ones are already ticked' : '' ?></small></p>
  <?php
  // Drawn like the hall. Red = full: switched off so it cannot be ticked by
  // accident — except this group's own booked table(s), which stay usable.
  $hallNos = array_keys(array_filter($seating, static fn($t) => $t['seats'] > 0));
  $hallClass = 'hall-easy ez-tables-multi';
  $hallCell = static function (int $no) use ($seating, $mine, $ownSpread): string {
      $t = $seating[$no];
      $self = in_array($no, $mine, true);
      $left = $t['seats'] - $t['used'] + ($ownSpread[$no] ?? 0);
      $full = $left <= 0 && !$self;
      $cls = $full ? 'full' : ($left >= $t['seats'] ? 'fits' : ($left > 0 ? 'some' : 'full'));
      return '<label class="ez-tbl ' . $cls . (($t['kind'] ?? 'open') === 'group' && !$self && !$full ? ' grp' : '') . '"' . ($full ? ' aria-disabled="true"' : '') . '>'
          . '<input type="checkbox" name="tables[]" value="' . $no . '"' . ($self ? ' checked' : '') . ($full ? ' disabled' : '') . '>'
          . '<b>' . $no . '</b><small>' . ($left > 0 ? '剩 ' . $left : '滿 Full') . ($self ? ' · 已安排' : (($t['kind'] ?? 'open') === 'group' ? ' · 團體桌' : '')) . '</small>'
          . '</label>';
  };
  require BASE_PATH . '/app/Views/partials/zai_hall.php';
  ?>
  <p class="ez-hint" id="ezRoom"></p>
  <p class="ez-hint ez-legend"><i class="lg-fits"></i> 空桌 <i class="lg-some"></i> 有人 <i class="lg-grp"></i> 團體桌 <i class="lg-full"></i> 滿（不能按）
    <span class="en">Green = empty · Yellow = some taken · Purple = group table · Red = full (cannot be ticked)</span></p>

  <label class="ez-small">支票號碼（可不填）<span class="en">Cheque no. (optional)</span>
    <input name="cheque_no" value="<?= h($g['cheque_no'] ?? '') ?>" maxlength="40" inputmode="numeric"></label>

  <p class="ez-step"><span>3</span> 完成 <small>Finish</small></p>
  <button type="submit" class="ez-btn ez-ok">✓ 完成，已到 <small>Done — they are here</small></button>
</form>
<script>
(function () {
  var p = document.getElementById('ezPeople'), room = document.getElementById('ezRoom');
  document.querySelectorAll('.ez-pm').forEach(function (b) {
    b.addEventListener('click', function () { p.value = Math.max(0, (+p.value || 0) + +b.dataset.step); show(); });
  });
  function show() {
    var seats = 0, n = 0;
    document.querySelectorAll('.ez-tables-multi input:checked').forEach(function (c) {
      n++; seats += +(c.parentNode.querySelector('small').textContent.replace(/\D/g, '') || 0);
    });
    room.textContent = n ? '已選 ' + n + ' 張桌，空位 ' + seats + '，人數 ' + p.value + (seats < +p.value ? '　⚠ 位不夠 Not enough seats' : '　✓ 夠位 Enough') : '';
    room.className = 'ez-hint' + (n && seats < +p.value ? ' ez-bad' : '');
  }
  document.querySelectorAll('.ez-tables-multi input, #ezPeople').forEach(function (i) { i.addEventListener('input', show); i.addEventListener('change', show); });
  show();
})();
</script>
<?php require __DIR__ . '/_bottom.php'; ?>
