<?php
/** Easy mode — the big ✓ after every action, then "next". */
$title = $d['tone'] === 'warn' ? '請注意' : '完成'; $titleEn = $d['tone'] === 'warn' ? 'Please check' : 'Done';
require __DIR__ . '/_top.php';
?>
<div class="ez-done tone-<?= h($d['tone']) ?>">
  <div class="ez-done-icon" aria-hidden="true"><?= $d['tone'] === 'warn' ? '!' : '✓' ?></div>
  <div class="ez-done-big"><?= h($d['big']) ?></div>
  <div class="ez-done-title"><?= h($d['title']) ?></div>
  <p class="ez-done-text"><?= nl2br(h($d['text'])) ?></p>
</div>
<?php if (!empty($d['cheque'])): ?>
  <!-- A group just checked in: the photographer there takes the cheque photo;
       this only reminds everyone (nothing is uploaded). -->
  <div class="ez-msg ez-warn ez-cheque-note"><b>📷 請拍支票照片</b>
    <p>請團體代表拿著支票。<br><span class="en">Please ask the group's representative to hold the cheque.</span></p></div>
  <div class="ez-pop" id="ezChequePop" role="alertdialog" aria-modal="true" aria-labelledby="ezChequeT">
    <div class="ez-pop-box">
      <div class="ez-pop-icon" aria-hidden="true">📷</div>
      <h2 id="ezChequeT">請拍支票照片 <span class="en">Cheque photo</span></h2>
      <p>請團體代表拿著支票。</p>
      <p class="en">Please ask the group's representative to hold the cheque.</p>
      <button type="button" class="ez-btn ez-ok" id="ezChequeOk">✓ 知道了 <small>OK, got it</small></button>
    </div>
  </div>
  <script>
  (function () {
    var pop = document.getElementById('ezChequePop'), ok = document.getElementById('ezChequeOk');
    ok.focus();
    ok.addEventListener('click', function () { pop.hidden = true; });
  })();
  </script>
<?php endif; ?>
<a class="ez-btn ez-ok" href="<?= url($d['next'][0]) ?>"<?= empty($d['cheque']) ? ' autofocus' : '' ?>><?= h($d['next'][1]) ?> →</a>
<a class="ez-btn ez-alt" href="<?= url('/admin/zai/easy') ?>"><?= icon('home') ?> 回首頁 <small>Home</small></a>
<?php require __DIR__ . '/_bottom.php'; ?>
