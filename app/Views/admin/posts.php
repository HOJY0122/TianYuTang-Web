<?php require BASE_PATH . '/app/Views/layouts/admin_header.php'; ?>
<div class="panel">
  <div class="toolbar" style="margin-bottom:6px">
    <p class="help" style="margin:0">這些消息顯示在網站首頁。🖐️ <strong>按住 ⠿ 拖到新位置</strong>即可排序，放開自動儲存；最上面的最先顯示。勾選「置頂」的新消息會移到最上面。<br>
      <span class="en">These posts appear on the home page. <strong>Drag ⠿ to reorder</strong> — it saves when you let go; the top one shows first. Pinning a post moves it to the top.</span></p>
    <span class="spacer"></span>
    <a class="mini-btn btn-lg" href="<?= url('/admin/posts/new') ?>">＋ 新增消息 New post</a>
  </div>
  <?php if (!$posts): ?>
    <p class="empty">還沒有消息。No posts yet — add the first one.</p>
  <?php else: ?>
  <?= csrf_field() ?>
  <div class="post-sort-list" data-sortable="<?= url('/admin/posts/reorder') ?>">
    <?php foreach ($posts as $i => $p): ?>
      <div class="post-row" data-id="<?= (int) $p['id'] ?>">
        <button type="button" class="drag-handle" data-drag-handle title="拖曳排序 Drag to reorder" aria-label="拖曳排序 Drag to reorder">⠿</button>
        <span class="post-pos" data-position><?= $i + 1 ?></span>
        <?php if ($p['image_path']): ?>
          <img class="post-row-img" src="<?= h(BASE_URL . '/' . $p['image_path']) ?>" alt="" loading="lazy" draggable="false">
        <?php else: ?><span class="post-row-img none" aria-hidden="true">📰</span><?php endif; ?>
        <div class="post-row-main">
          <a class="rowlink" href="<?= url('/admin/posts/edit') ?>?id=<?= (int) $p['id'] ?>"><?= h($p['title_zh']) ?></a>
          <?php if ($p['title_en']): ?><span class="sub-line"><?= h($p['title_en']) ?></span><?php endif; ?>
          <div class="post-row-meta">
            <?= $p['is_published'] ? '<span class="badge ok">已發佈 Published</span>' : '<span class="badge pending">草稿 Draft</span>' ?>
            <?= $p['is_pinned'] ? '<span class="badge">📌 置頂 Pinned</span>' : '' ?>
            <span class="help"><?= h(date('Y-m-d', strtotime($p['created_at']))) ?> · <?= h($p['created_by'] ?? '') ?></span>
          </div>
        </div>
        <div class="actions-cell">
          <a class="mini-btn" href="<?= url('/admin/posts/edit') ?>?id=<?= (int) $p['id'] ?>">✏️ 編輯 Edit</a>
          <form method="POST" action="<?= url('/admin/posts/delete') ?>" style="margin:0"
                data-confirm="刪除這則消息？&#10;Delete this post?" data-danger>
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="mini-btn danger" type="submit">🗑 <span class="hide-sm">刪除 Delete</span></button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<script src="<?= asset('js/sortable.js') ?>"></script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
