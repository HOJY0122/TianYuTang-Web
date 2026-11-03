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
<a class="ez-btn ez-ok" href="<?= url($d['next'][0]) ?>" autofocus><?= h($d['next'][1]) ?> →</a>
<a class="ez-btn ez-alt" href="<?= url('/admin/zai/easy') ?>"><?= icon('home') ?> 回首頁 <small>Home</small></a>
<?php require __DIR__ . '/_bottom.php'; ?>
