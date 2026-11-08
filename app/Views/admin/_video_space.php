<?php
/**
 * 影片空間 Video storage bar — on 相簿管理 (a link to the full list) and on
 * the 影片空間 page itself. Updates by itself when videos are added or
 * deleted on another device (data-live).
 *
 * Expects: $videoUse  (VideoUploader::usage())
 * Optional: $spaceLink  true = the bar opens /admin/photos/videos
 */
$_vu   = $videoUse;
$_vp   = $_vu['quota'] > 0 ? min(100, $_vu['used'] / $_vu['quota'] * 100) : 100;
$_lvl  = $_vp >= 95 || $_vu['left'] < (50 << 20) ? 'is-full' : ($_vp >= 80 ? 'is-warn' : 'is-ok');
$_size = [App\Core\VideoUploader::class, 'size'];
$_tag  = !empty($spaceLink) ? 'a' : 'div';
?>
<<?= $_tag ?> class="vspace <?= $_lvl ?>" id="liveVideoSpace" data-live="event_photos settings" data-left="<?= (int) $_vu['left'] ?>"
  <?= !empty($spaceLink) ? 'href="' . url('/admin/photos/videos') . '" title="查看每段影片的大小 See every video and its size"' : '' ?>>
  <span class="vspace-head">
    <span class="vspace-title"><?= icon('play') ?> 影片空間 <span class="en">Video storage</span></span>
    <span class="vspace-nums"><b><?= h($_size($_vu['used'])) ?></b> / <?= h($_size($_vu['quota'])) ?>
      <small>· <?= (int) $_vu['count'] ?> 段 video<?= $_vu['count'] === 1 ? '' : 's' ?></small></span>
  </span>
  <span class="vspace-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) round($_vp) ?>"
        aria-label="已用 <?= (int) round($_vp) ?>% Used"><i style="width:<?= max($_vu['used'] > 0 ? 1 : 0, round($_vp, 1)) ?>%"></i></span>
  <span class="vspace-foot">
    <?php if ($_lvl === 'is-full'): ?>
      <span class="vspace-msg"><?= icon('alert') ?> 空間將滿：請先刪除舊影片才可再上傳。<span class="en">Almost full — delete old videos to upload more.</span></span>
    <?php elseif ($_lvl === 'is-warn'): ?>
      <span class="vspace-msg"><?= icon('alert') ?> 已用 <?= (int) round($_vp) ?>%，尚餘 <?= h($_size($_vu['left'])) ?>。<span class="en"><?= h($_size($_vu['left'])) ?> left.</span></span>
    <?php else: ?>
      <span class="vspace-msg">尚餘 <?= h($_size($_vu['left'])) ?> <span class="en">left</span></span>
    <?php endif; ?>
    <?php if (!empty($spaceLink)): ?><span class="vspace-go">查看影片大小 <span class="en">Details</span> <?= icon('chevron-right') ?></span><?php endif; ?>
  </span>
</<?= $_tag ?>>
