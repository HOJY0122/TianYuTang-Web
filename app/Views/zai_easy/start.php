<?php
/** Easy mode — the first screen: who is in front of you? */
$title = '誰來了？'; $titleEn = 'Who is here?';
require __DIR__ . '/_top.php';
?>
<div class="ez-choices">
  <a class="ez-big ez-c1" href="<?= url('/admin/zai/easy/find') ?>">
    <span class="ez-num">1</span><span class="ez-ico"><?= icon('id-card') ?></span>
    <span class="ez-txt"><b>已經網上登記</b><small>Registered online · 有身份證</small></span></a>
  <a class="ez-big ez-c2" href="<?= url('/admin/zai/easy/groups') ?>">
    <span class="ez-num">2</span><span class="ez-ico"><?= icon('users') ?></span>
    <span class="ez-txt"><b>團體</b><small>A group · 帶支票</small></span></a>
  <a class="ez-big ez-c3" href="<?= url('/admin/zai/easy/wait') ?>">
    <span class="ez-num">3</span><span class="ez-ico"><?= icon('hand') ?></span>
    <span class="ez-txt"><b>沒有登記</b><small>Not registered · 排候補</small></span></a>
  <a class="ez-big ez-c4" href="<?= url('/admin/zai/easy/waitlist') ?>">
    <span class="ez-num">4</span><span class="ez-ico"><?= icon('hourglass') ?></span>
    <span class="ez-txt"><b>候補名單</b><small>Waiting list · 安排座位</small></span>
    <?php if ((int) $totals['waiting']): ?><span class="ez-count"><?= (int) $totals['waiting'] ?></span><?php endif; ?></a>
</div>
<p class="ez-foot">已入座 <b><?= (int) $totals['used'] ?></b> / <?= (int) $totals['seats'] ?> 座位 seats</p>
<?php require __DIR__ . '/_bottom.php'; ?>
