<?php
/**
 * 影片空間 Video storage — how much space the album videos use, the limit,
 * and every video with its size (biggest first on request) to free space.
 * System admins can change the limit, up to what the server's disk allows.
 */
$pageTitle = '影片空間 Video storage';
$nav = 'photos';
require BASE_PATH . '/app/Views/layouts/admin_header.php';
$size = [App\Core\VideoUploader::class, 'size'];
$videoUse = $use;
$maxGb = floor($use['max'] / (1 << 30) * 2) / 2;          // in 0.5 GB steps
$minGb = App\Core\VideoUploader::MIN_QUOTA_MB / 1024;
?>
<div class="wrap">
  <p style="margin:0 0 12px"><a class="mini-btn ghost" href="<?= url('/admin/photos') ?>"><?= icon('chevron-left') ?> 返回相簿 <span class="en">Back to Photos</span></a></p>

  <div class="panel">
    <?php $spaceLink = false; require BASE_PATH . '/app/Views/admin/_video_space.php'; ?>
    <div class="vs-facts">
      <div><small>影片 <span class="en">Videos</span></small><b><?= h($size($use['used'])) ?></b></div>
      <div><small>加密副本 <span class="en">Protected copies</span></small><b><?= h($size($use['cache'])) ?></b></div>
      <div><small>伺服器剩餘 <span class="en">Server disk free</span></small><b><?= h($size($use['free'])) ?></b></div>
      <div><small>上限最多可設 <span class="en">Largest limit possible</span></small><b><?= h($size((int) ($maxGb * (1 << 30)))) ?></b></div>
    </div>
    <p class="help" style="margin:10px 0 0">相簿受保護時，每段影片在第一次播放時會多存一份同樣大小的加密副本，所以上限最多是伺服器空間的一半。
      <span class="en">When albums are protected, each video also keeps an encrypted copy of the same size (made when first played), so the limit can be at most half the server's space.</span></p>

    <?php if ($leftover['files']): ?>
      <div class="vs-leftover">
        <span><?= icon('alert') ?> 有 <?= count($leftover['files']) ?> 個用不著的檔案佔用 <b><?= h($size($leftover['bytes'])) ?></b>（已刪除影片留下的檔案或舊的加密副本）。
          <span class="en"><?= count($leftover['files']) ?> unused file<?= count($leftover['files']) === 1 ? '' : 's' ?> take <?= h($size($leftover['bytes'])) ?> (left from deleted videos, or old protected copies).</span></span>
        <?php if ($isSystem): ?>
          <form method="POST" action="<?= url('/admin/photos/videos/clean') ?>" style="margin:0"
                data-confirm="移除這些用不著的檔案，釋出 <?= h($size($leftover['bytes'])) ?>？相簿裡的影片不受影響。&#10;Remove these unused files? Videos in the albums are not affected.">
            <?= csrf_field() ?><button class="mini-btn" type="submit"><?= icon('trash') ?> 清理 <span class="en">Clean up</span></button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($isSystem): ?>
      <form method="POST" action="<?= url('/admin/photos/videos/quota') ?>" class="vs-quota">
        <?= csrf_field() ?>
        <div class="vs-quota-grid">
          <div>
            <label for="quotaGb">影片空間上限 <span class="en">Video space (all videos)</span></label>
            <div class="vs-quota-row">
              <input type="number" id="quotaGb" name="quota_gb" min="<?= h((string) $minGb) ?>" max="<?= h((string) max($minGb, $maxGb)) ?>" step="0.5"
                     value="<?= h(rtrim(rtrim(number_format($use['quota'] / (1 << 30), 2, '.', ''), '0'), '.')) ?>" inputmode="decimal" required>
              <span class="vs-unit">GB</span>
            </div>
            <p class="help">可設 <?= h((string) $minGb) ?> – <?= h((string) max($minGb, $maxGb)) ?> GB（依伺服器剩餘空間）。<span class="en">Allowed: <?= h((string) $minGb) ?> – <?= h((string) max($minGb, $maxGb)) ?> GB, from the server's free space.</span></p>
          </div>
          <div>
            <label for="maxMb">單段影片上限 <span class="en">Largest single video</span></label>
            <div class="vs-quota-row">
              <input type="number" id="maxMb" name="max_mb" min="<?= App\Core\VideoUploader::MIN_MAX_MB ?>" max="<?= App\Core\VideoUploader::TOP_MAX_MB ?>" step="10"
                     value="<?= App\Core\VideoUploader::maxMb() ?>" inputmode="numeric" required>
              <span class="vs-unit">MB</span>
            </div>
            <p class="help">可設 <?= App\Core\VideoUploader::MIN_MAX_MB ?> – <?= App\Core\VideoUploader::TOP_MAX_MB ?> MB。建議 300MB 以內：相簿受保護時，手機要先下載整段影片才播放。
              <span class="en">Allowed: <?= App\Core\VideoUploader::MIN_MAX_MB ?> – <?= App\Core\VideoUploader::TOP_MAX_MB ?> MB. Best under 300 MB: when albums are protected, phones download the whole video before it plays.</span></p>
            <p class="vs-bigwarn" id="maxMbWarn" hidden><?= icon('alert') ?> 超過 500MB：訪客用手機數據觀看會很慢，舊手機可能播放不了。<span class="en">Over 500 MB: slow on mobile data, and older phones may not manage to play it.</span></p>
          </div>
        </div>
        <button class="primary" type="submit"><?= icon('save') ?> 儲存 <span class="en">Save</span></button>
      </form>
    <?php else: ?>
      <p class="help" style="margin-top:12px">上限由系統管理員設定。<span class="en">A system admin sets the limit.</span></p>
    <?php endif; ?>
  </div>

  <div class="panel" id="liveVideoList" data-live="event_photos">
    <div class="vs-list-head">
      <h2 style="margin:0"><?= icon('list') ?> 所有影片 <span class="en">All videos</span> · <?= count($videos) ?></h2>
      <nav class="cat-tabs" aria-label="排序 Sort">
        <a href="<?= url('/admin/photos/videos') ?>"<?= $sort === 'new' ? ' class="is-on" aria-current="page"' : '' ?>>最新 <span class="en">Newest</span></a>
        <a href="<?= url('/admin/photos/videos') ?>?sort=size"<?= $sort === 'size' ? ' class="is-on" aria-current="page"' : '' ?>>最大 <span class="en">Largest</span></a>
      </nav>
    </div>
    <?php if (!$videos): ?>
      <p class="help">尚未上傳任何影片。No videos yet.</p>
    <?php else: ?>
      <div class="vs-list">
        <?php foreach ($videos as $v):
          $share = $use['quota'] > 0 ? min(100, $v['bytes'] / $use['quota'] * 100) : 0;
          $tall = (int) $v['video_h'] > (int) $v['video_w']; ?>
          <div class="vs-item">
            <a class="vs-thumb" href="<?= url('/admin/photos') ?>?event=<?= (int) $v['event_id'] ?>" title="到相簿 Open in Photos">
              <img src="<?= h(media_url($v['thumb_path'])) ?>" alt="" loading="lazy"><span class="vs-play"><?= icon('play') ?></span>
              <?php if ($len = video_length((int) $v['duration_sec'])): ?><span class="vs-len"><?= h($len) ?></span><?php endif; ?>
            </a>
            <div class="vs-info">
              <strong><?= h($v['caption'] ?: '（未有說明 No caption）') ?></strong>
              <span class="help">
                <?= h($v['year']) ?> · <?= h($v['event_name']) ?><?= $v['is_test'] ? ' (測試 Test)' : '' ?>
                · <?= h($v['cat_zh'] ?? '其他 Others') ?>
                <?php if ((int) $v['video_w']): ?> · <?= $tall ? '直式 Vertical' : '橫式 Horizontal' ?> <?= (int) $v['video_w'] ?>×<?= (int) $v['video_h'] ?><?php endif; ?>
                · <?= h(date('Y-m-d', strtotime($v['created_at']))) ?>
              </span>
              <span class="vs-share" title="佔上限 <?= round($share, 1) ?>% of the limit"><i style="width:<?= max(1, round($share, 1)) ?>%"></i></span>
            </div>
            <div class="vs-size"><b><?= h($size($v['bytes'])) ?></b><small><?= round($share, 1) ?>%</small></div>
            <form method="POST" action="<?= url('/admin/photos/videos/delete') ?>" style="margin:0"
                  data-confirm="刪除這段影片（<?= h($size($v['bytes'])) ?>）？此操作無法復原。&#10;Delete this video (<?= h($size($v['bytes'])) ?>)? This cannot be undone." data-danger>
              <?= csrf_field() ?><input type="hidden" name="photo_id" value="<?= (int) $v['id'] ?>"><input type="hidden" name="sort" value="<?= h($sort) ?>">
              <button class="mini-btn danger" type="submit" title="刪除 Delete"><?= icon('trash') ?> <span class="hide-sm">刪除 Delete</span></button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<script>
// A gentle warning beside the per-video limit when it is set very high.
(function () {
  var box = document.getElementById('maxMb'), warn = document.getElementById('maxMbWarn');
  if (!box || !warn) return;
  function check() { warn.hidden = !(+box.value > 500); }
  box.addEventListener('input', check);
  check();
})();
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
