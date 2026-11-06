<?php
/**
 * Easy mode ③ — not registered: name, IC, phone, how many → a waiting number.
 * The IC of the person queuing is required (it is how a registered person is
 * spotted). With more people, one optional IC box appears per extra person.
 */
$title = '沒有登記：排候補'; $titleEn = 'Not registered — add to the waiting list';
require __DIR__ . '/_top.php';
?>
<?php if (!empty($error)): ?><div class="ez-msg ez-warn"><b><?= h($error) ?></b></div><?php endif; ?>
<form method="POST" action="<?= url('/admin/zai/easy/wait') ?>" class="ez-form">
  <?= csrf_field() ?>
  <label>姓名 <span class="en">Name</span><input name="name" required maxlength="100" autocomplete="off" autofocus></label>
  <label>身份證 <b class="ez-req">*必填</b> <span class="en">IC (required)</span>
    <input name="ic" required minlength="6" maxlength="20" inputmode="numeric" autocomplete="off" placeholder="例 700101-14-5678"></label>
  <label>電話 <span class="en">Phone</span><input name="contact" maxlength="30" inputmode="tel" autocomplete="off"></label>
  <p class="ez-step"><span>?</span> 幾個人？<small>How many people?</small></p>
  <div class="ez-stepper">
    <button type="button" class="ez-pm" data-step="-1" aria-label="減一 Minus one">−</button>
    <input type="number" name="people" id="ezPeople" value="1" min="1" max="50" inputmode="numeric">
    <button type="button" class="ez-pm" data-step="1" aria-label="加一 Plus one">+</button>
  </div>
  <div id="ezMoreIc" class="ez-more-ic" hidden>
    <p class="ez-hint">其他人的身份證可以不填，有就填，比較快找到有登記的人。<br>
      <span class="en">The others' ICs are optional — fill them in if you have them.</span></p>
  </div>
  <button type="submit" class="ez-btn ez-ok">✓ 給候補號碼 <small>Give a waiting number</small></button>
</form>
<script>
(function () {
  var people = document.getElementById('ezPeople'), box = document.getElementById('ezMoreIc'), MAX = 10;
  // One optional IC box per extra person (up to 10); typed values are kept when the count changes.
  function sync() {
    var want = Math.min(MAX, Math.max(0, (+people.value || 1) - 1)), have = box.querySelectorAll('label');
    for (var i = have.length; i < want; i++) {
      var l = document.createElement('label');
      l.innerHTML = '第 ' + (i + 2) + ' 位身份證（可不填）<span class="en">IC of person ' + (i + 2) + ' (optional)</span>'
        + '<input name="ic_more[]" maxlength="20" inputmode="numeric" autocomplete="off">';
      box.appendChild(l);
    }
    have = box.querySelectorAll('label');
    for (var j = 0; j < have.length; j++) {
      have[j].hidden = j >= want;
      have[j].querySelector('input').disabled = j >= want;   // hidden boxes are not sent
    }
    box.hidden = want === 0;
  }
  document.querySelectorAll('.ez-pm').forEach(function (b) {
    b.addEventListener('click', function () { people.value = Math.max(1, Math.min(50, (+people.value || 1) + +b.dataset.step)); sync(); });
  });
  people.addEventListener('input', sync);
  sync();
})();
</script>
<?php require __DIR__ . '/_bottom.php'; ?>
