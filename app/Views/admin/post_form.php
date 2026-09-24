<?php require BASE_PATH . '/app/Views/layouts/admin_header.php'; ?>
<p style="margin:0 0 12px"><a class="mini-btn ghost" href="<?= url('/admin/posts') ?>">← 返回 Back</a></p>
<?php foreach ($errors as $e): ?><div class="flash error"><?= h($e) ?></div><?php endforeach; ?>

<form method="POST" action="<?= url('/admin/posts/save') ?>" data-trad-check enctype="multipart/form-data" class="panel form-panel">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">

  <label for="title_zh">中文標題 <span class="en">Title (Chinese) *</span></label>
  <input id="title_zh" name="title_zh" required maxlength="200" value="<?= h($post['title_zh']) ?>">
  <label for="title_en">英文標題 <span class="en">Title (English, optional)</span></label>
  <input id="title_en" name="title_en" maxlength="200" value="<?= h($post['title_en']) ?>">

  <label for="body_zh">中文內容 <span class="en">Message (Chinese)</span></label>
  <textarea id="body_zh" name="body_zh" rows="5" maxlength="5000"><?= h($post['body_zh']) ?></textarea>
  <label for="body_en">英文內容 <span class="en">Message (English, optional)</span></label>
  <textarea id="body_en" name="body_en" rows="4" maxlength="5000"><?= h($post['body_en']) ?></textarea>

  <label for="image">圖片 <span class="en">Picture (optional)</span></label>
  <?php if (!empty($post['image_path'])): ?>
    <div class="image-preview">
      <img src="<?= h(BASE_URL . '/' . $post['image_path']) ?>" alt="">
      <label class="remove-check"><input type="checkbox" name="remove_image" value="1"> 移除圖片 Remove picture</label>
    </div>
  <?php endif; ?>
  <input id="image" name="image" type="file" data-aspects="original,4:3,1:1,16:9,3:4" data-max-width="1600" accept="image/jpeg,image/png,image/gif,image/webp">
  <p class="help">JPG / PNG / GIF / WebP，5MB 以內。Up to 5 MB.</p>

  <label class="checkline"><input type="checkbox" name="is_published" value="1"<?= $post['is_published'] ? ' checked' : '' ?>> 發佈到首頁 Publish on the home page</label>
  <label class="checkline"><input type="checkbox" name="is_pinned" value="1"<?= $post['is_pinned'] ? ' checked' : '' ?>> 📌 置頂 Pin to the top</label>

  <div class="form-actions">
    <button class="primary" type="submit">💾 儲存 Save</button>
    <a class="mini-btn ghost" href="<?= url('/') ?>#news" target="_blank">👀 查看首頁 View home page</a>
  </div>
</form>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
