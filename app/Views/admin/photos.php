<?php
$pageTitle = '相簿管理 Photos';
$nav = 'photos';
require BASE_PATH . '/app/Views/layouts/admin_header.php';
?>
<div class="wrap">


  <?php if ($event['is_test']): ?>
    <div class="flash test">🧪 這是測試活動的相簿，不會出現在正式網站上。This is a test event's album — it is not shown on the live site.</div>
  <?php endif; ?>

  <!-- ---------- Which event ---------- -->
  <div class="panel eventbar">
    <div>
      <h2 style="margin:0 0 4px"><?= h($event['year']) ?> · <?= h($event['name']) ?></h2>
      <div class="help">目前相簿共 <?= count($photos) ?> 張相片 · <?= count($photos) ?> photo<?= count($photos) === 1 ? '' : 's' ?></div>
    </div>
    <div class="eventbar-actions">
      <?php if (count($allEvents) > 1): ?>
        <form method="GET" action="<?= url('/admin/photos') ?>" style="margin:0">
          <select name="event" onchange="this.form.submit()">
            <?php foreach ($allEvents as $e): ?>
              <option value="<?= (int) $e['id'] ?>"<?= (int) $e['id'] === (int) $event['id'] ? ' selected' : '' ?>>
                <?= h($e['year']) ?> — <?= h($e['name']) ?><?= $e['is_test'] ? ' (測試 Test)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>
      <a class="mini-btn ghost" href="<?= url('/gallery') ?>?year=<?= (int) $event['id'] ?>" target="_blank">預覽前台相簿 Preview ↗</a>
    </div>
  </div>

  <!-- ---------- Upload ---------- -->
  <div class="panel">
    <h2>上傳相片 <span class="en">Upload photos</span></h2>
    <form method="POST" action="<?= url('/admin/photos/upload') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">

      <input type="file" name="photos[]" multiple required
             accept="image/jpeg,image/png,image/gif,image/webp"
             class="file-input">

      <p class="help" style="margin-top:10px">
        可一次選擇多張相片（按住 Ctrl 或 Shift 多選）。單張 5MB 以內，
        一次最多 <?= (int) $maxFiles ?> 張，且整批總大小不可超過 <?= h($postMax) ?>。
        手機拍的相片通常每張 2–4MB，建議<strong>每次上傳 3–5 張</strong>較保險。
        系統會自動產生縮圖，前台不會載入原始大圖。<br>
        <span class="en">Choose several at once (up to <?= (int) $maxFiles ?>, 5 MB each, <?= h($postMax) ?> in total — 3–5 phone photos per upload is safest). Thumbnails are made automatically.</span>
      </p>

      <button class="mini-btn" type="submit" style="margin-top:14px;padding:12px 22px">⬆️ 上傳相片 Upload</button>
    </form>
  </div>

  <!-- ---------- Manage ---------- -->
  <div class="panel">
    <h2>相片管理 <span class="en">Arrange photos</span></h2>

    <?php if (!$photos): ?>
      <p class="help">尚未上傳任何相片。No photos yet.</p>
    <?php else: ?>
      <p class="help" style="margin-bottom:16px">
        🖐️ <strong>按住相片拖到新位置</strong>，放開即自動儲存。排列順序即為前台顯示順序。也可用 ↑ ↓ 按鈕。說明文字可留空。<br>
        <span class="en"><strong>Drag a photo to a new place</strong> — the order saves as soon as you let go, and is the order visitors see. The ↑ ↓ buttons still work.</span>
      </p>

      <div class="admin-photo-grid" data-sortable="<?= url('/admin/photos/reorder') ?>" data-extra='<?= h(json_encode(['event_id' => (int) $event['id']])) ?>'>
        <?php foreach ($photos as $i => $photo): ?>
          <div class="admin-photo" data-id="<?= (int) $photo['id'] ?>">
            <div class="drag-area" data-drag-handle title="拖曳排序 Drag to reorder">
              <img src="<?= h(BASE_URL . '/' . $photo['thumb_path']) ?>" alt="相片 Photo <?= $i + 1 ?>" draggable="false">
              <span class="drag-badge" aria-hidden="true">⠿ <b data-position><?= $i + 1 ?></b></span>
            </div>

            <form method="POST" action="<?= url('/admin/photos/caption') ?>" class="caption-form">
              <?= csrf_field() ?>
              <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
              <input type="text" name="caption" maxlength="255"
                     value="<?= h($photo['caption'] ?? '') ?>" placeholder="相片說明 Caption（選填 optional）">
              <button class="mini-btn ghost" type="submit">儲存 Save</button>
            </form>

            <div class="admin-photo-actions">
              <form method="POST" action="<?= url('/admin/photos/move') ?>" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                <input type="hidden" name="direction" value="up">
                <button class="mini-btn ghost" type="submit" title="往前 Move earlier" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
              </form>

              <form method="POST" action="<?= url('/admin/photos/move') ?>" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                <input type="hidden" name="direction" value="down">
                <button class="mini-btn ghost" type="submit" title="往後 Move later" <?= $i === count($photos) - 1 ? 'disabled' : '' ?>>↓</button>
              </form>

              <form method="POST" action="<?= url('/admin/photos/delete') ?>" style="margin:0"
                    data-confirm="確定要刪除這張相片嗎？此操作無法復原。&#10;Delete this photo? This cannot be undone." data-danger>
                <?= csrf_field() ?>
                <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                <button class="mini-btn danger" type="submit" title="刪除 Delete">🗑</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>
<script src="<?= asset('js/sortable.js') ?>"></script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
