<?php
/** Easy mode ② — one group: how many people, which table(s), photo with the cheque, done. */
$title = $g['name']; $titleEn = '團體 Group';
require __DIR__ . '/_top.php';
$mine = App\Models\Zai::tableList((string) ($g['tables'] ?? ''));
// Seats left at each table, not counting this group's own people already there.
$ownSpread = App\Models\Zai::spread($g, array_map(static fn($t) => $t['seats'], $seating));
?>
<form method="POST" action="<?= url('/admin/zai/easy/group') ?>" enctype="multipart/form-data" class="ez-group">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $g['id'] ?>">

  <p class="ez-step"><span>1</span> 來了幾個人？<small>How many people came?</small></p>
  <div class="ez-stepper">
    <button type="button" class="ez-pm" data-step="-1" aria-label="減一 Minus one">−</button>
    <input type="number" name="people" id="ezPeople" value="<?= (int) $g['people'] ?>" min="0" max="5000" inputmode="numeric">
    <button type="button" class="ez-pm" data-step="1" aria-label="加一 Plus one">+</button>
  </div>

  <p class="ez-step"><span>2</span> 按桌號（可按多張）<small>Tap the table(s)<?= $mine ? ' — 已預先安排的已選好 the booked ones are already ticked' : '' ?></small></p>
  <div class="ez-tables ez-tables-multi">
    <?php foreach ($seating as $no => $t): if ($t['seats'] === 0) continue;
      $self = in_array((int) $no, $mine, true);
      $left = $t['seats'] - $t['used'] + ($ownSpread[$no] ?? 0); ?>
      <label class="ez-tbl <?= $left >= $t['seats'] ? 'fits' : ($left > 0 ? 'some' : 'full') ?>">
        <input type="checkbox" name="tables[]" value="<?= (int) $no ?>"<?= $self ? ' checked' : '' ?>>
        <b><?= (int) $no ?></b><small><?= $left > 0 ? '剩 ' . $left : '滿 Full' ?><?= $self ? ' · 已安排' : (($t['kind'] ?? 'open') === 'group' ? ' · 團體桌' : '') ?></small>
      </label>
    <?php endforeach; ?>
  </div>
  <p class="ez-hint" id="ezRoom"></p>

  <p class="ez-step"><span>3</span> 與支票拍照 <small>Take a photo with the cheque</small></p>
  <?php if ($g['cheque_path']): ?>
    <div class="ez-msg ez-info"><b>✓ 已有相片 Photo already taken</b><p>要重拍就再按下面。Tap below to retake.</p>
      <img class="ez-photo" src="<?= url('/admin/zai/cheque') ?>?id=<?= (int) $g['id'] ?>&amp;v=<?= substr(md5((string) $g['cheque_path']), 0, 6) ?>" alt="支票 Cheque"></div>
  <?php endif; ?>
  <input type="file" name="cheque" accept="image/jpeg,image/png,image/webp" capture="environment" data-no-editor>
  <label class="ez-small">支票號碼（可不填）<span class="en">Cheque no. (optional)</span>
    <input name="cheque_no" value="<?= h($g['cheque_no'] ?? '') ?>" maxlength="40" inputmode="numeric"></label>

  <p class="ez-step"><span>4</span> 完成 <small>Finish</small></p>
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
<script src="<?= asset('js/image-editor.js') ?>"></script>
<?php require __DIR__ . '/_bottom.php'; ?>
