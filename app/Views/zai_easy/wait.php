<?php
/** Easy mode ③ — not registered: name, phone, how many → a waiting number. */
$title = '沒有登記：排候補'; $titleEn = 'Not registered — add to the waiting list';
require __DIR__ . '/_top.php';
?>
<form method="POST" action="<?= url('/admin/zai/easy/wait') ?>" class="ez-form">
  <?= csrf_field() ?>
  <label>姓名 <span class="en">Name</span><input name="name" required maxlength="100" autocomplete="off" autofocus></label>
  <label>電話 <span class="en">Phone</span><input name="contact" maxlength="30" inputmode="tel" autocomplete="off"></label>
  <p class="ez-step"><span>?</span> 幾個人？<small>How many people?</small></p>
  <div class="ez-stepper">
    <button type="button" class="ez-pm" data-step="-1" aria-label="減一 Minus one">−</button>
    <input type="number" name="people" id="ezPeople" value="1" min="1" max="50" inputmode="numeric">
    <button type="button" class="ez-pm" data-step="1" aria-label="加一 Plus one">+</button>
  </div>
  <label>身份證（可不填）<span class="en">IC (optional)</span><input name="ic" maxlength="20" inputmode="numeric" autocomplete="off"></label>
  <button type="submit" class="ez-btn ez-ok">✓ 給候補號碼 <small>Give a waiting number</small></button>
</form>
<script>
document.querySelectorAll('.ez-pm').forEach(function (b) {
  b.addEventListener('click', function () { var p = document.getElementById('ezPeople'); p.value = Math.max(1, (+p.value || 1) + +b.dataset.step); });
});
</script>
<?php require __DIR__ . '/_bottom.php'; ?>
