<?php
$pageTitle = '相簿管理 Photos';
$nav = 'photos';
require BASE_PATH . '/app/Views/layouts/admin_header.php';
?>
<div class="wrap">


  <?php if ($event['is_test']): ?>
    <div class="flash test"><?= icon('flask') ?> 這是測試活動的相簿，不會出現在正式網站上。This is a test event's album — it is not shown on the live site.</div>
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
    <form method="POST" action="<?= url('/admin/photos/upload') ?>" enctype="multipart/form-data" id="photoUpload">
      <?= csrf_field() ?>
      <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">

      <input type="file" name="photos[]" multiple required data-no-editor
             accept="image/jpeg,image/png,image/gif,image/webp"
             class="file-input" id="photoFiles">

      <p class="help" style="margin-top:10px">
        <?= icon('image') ?> <strong>一次可選 50、100 張或更多</strong>（按住 Ctrl / Shift 多選，手機可在相簿中多選）。
        系統會逐張上傳並顯示進度；上傳中請不要關閉此頁。完成後可再選下一批。單張 20MB 以內，系統會自動縮小並產生縮圖。<br>
        <span class="en"><strong>Pick 50, 100 or more at once</strong> (Ctrl / Shift, or multi-select on a phone). They go up one by one with a progress bar — keep this page open. Then choose the next batch. Up to 20 MB each; pictures are resized and thumbnails made automatically.</span>
      </p>

      <div class="form-actions">
        <button class="primary" type="submit" id="photoUploadBtn"><?= icon('upload') ?> 上傳相片 <span class="en">Upload</span></button>
        <span class="help" id="photoPicked" aria-live="polite"></span>
      </div>
      <div class="bulk-panel" id="photoProgress" hidden aria-live="polite"></div>
    </form>
  </div>

  <!-- ---------- Manage ---------- -->
  <div class="panel">
    <h2>相片管理 <span class="en">Arrange photos</span></h2>

    <?php if (!$photos): ?>
      <p class="help">尚未上傳任何相片。No photos yet.</p>
    <?php else: ?>
      <p class="help" style="margin-bottom:16px">
        <?= icon('hand') ?> <strong>按住相片拖到新位置</strong>，放開即自動儲存。排列順序即為前台顯示順序。也可用 ↑ ↓ 按鈕。說明文字可留空。<br>
        <span class="en"><strong>Drag a photo to a new place</strong> — the order saves as soon as you let go, and is the order visitors see. The ↑ ↓ buttons still work.</span>
      </p>

      <div class="admin-photo-grid" id="livePhotoGrid" data-live="event_photos" data-sortable="<?= url('/admin/photos/reorder') ?>" data-extra='<?= h(json_encode(['event_id' => (int) $event['id']])) ?>'>
        <?php foreach ($photos as $i => $photo): ?>
          <div class="admin-photo" data-id="<?= (int) $photo['id'] ?>">
            <div class="drag-area" data-drag-handle title="拖曳排序 Drag to reorder">
              <img src="<?= h(media_url($photo['thumb_path'])) ?>" alt="相片 Photo <?= $i + 1 ?>" draggable="false">
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
                <button class="mini-btn danger" type="submit" title="刪除 Delete"><?= icon('trash') ?></button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>
<script src="<?= asset('js/sortable.js') ?>"></script>
<script src="<?= asset('js/bulk-upload.js') ?>"></script>
<script>
(function () {
  var form = document.getElementById('photoUpload'), input = document.getElementById('photoFiles'),
      btn = document.getElementById('photoUploadBtn'), picked = document.getElementById('photoPicked'),
      box = document.getElementById('photoProgress');
  if (!window.fetch || !window.TYTBulk) return;           // old browser: the plain form still works
  input.addEventListener('change', function () {
    var n = input.files.length;
    picked.textContent = n ? '已選 ' + n + ' 張 · ' + n + ' selected' : '';
  });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var files = Array.prototype.slice.call(input.files);
    if (!files.length) return;
    btn.disabled = true; input.disabled = true;
    var p = TYTBulk.panel(box, files.length), base = new FormData(form), failed = [];
    TYTBulk.queue(files, function (file) {
      var row = p.row(file.name);
      row.set('is-busy', '縮小中 Preparing…');
      return TYTBulk.shrink(file, 2400).then(function (small) {
        row.set('is-busy', '上傳中 Uploading…');
        var fd = new FormData();
        fd.append('csrf_token', base.get('csrf_token'));
        fd.append('event_id', base.get('event_id'));
        fd.append('photos[]', small, small.name);
        return TYTBulk.send(form.action, fd);
      }).then(function (j) {
        if (j.ok) { row.set('is-ok', '完成 Done'); p.step(true); }
        else { throw new Error((j.failures || []).join('；') || '未成功 Failed'); }
      }).catch(function (err) {
        row.set('is-bad', TYTBulk.esc(err.message)); p.step(false); failed.push(file);
      });
    }, 3).then(function () {
      var r = p.finish();
      btn.disabled = false; input.disabled = false; input.value = ''; picked.textContent = '';
      var msg = '成功上傳 ' + (r.done - r.bad) + ' 張' + (r.bad ? '，' + r.bad + ' 張未成功（見清單）' : '') + '。\n'
              + (r.done - r.bad) + ' uploaded' + (r.bad ? ', ' + r.bad + ' failed (see the list)' : '') + '.';
      if (!r.bad) { location.reload(); return; }
      TYTDialog.alert(msg, { type: r.done > r.bad ? 'info' : 'error', title: '上傳結果 Upload result' }).then(function () {
        if (r.done > r.bad) location.reload();
      });
    });
  });
})();
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
