<?php require BASE_PATH . '/app/Views/layouts/admin_header.php'; ?>
<div class="panel">
  <div class="toolbar" style="margin-bottom:6px">
    <p class="help" style="margin:0">這些消息顯示在網站首頁，像社交媒體的貼文。置頂的消息永遠在最上面。<br>
      <span class="en">These posts appear on the home page like a social-media feed. Pinned posts stay on top.</span></p>
    <span class="spacer"></span>
    <a class="mini-btn btn-lg" href="<?= url('/admin/posts/new') ?>">＋ 新增消息 New post</a>
  </div>
  <?php if (!$posts): ?>
    <p class="empty">還沒有消息。No posts yet — add the first one.</p>
  <?php else: ?>
  <table class="records">
    <thead><tr><th>標題 Title</th><th>狀態 Status</th><th>日期 Date</th><th>操作 Actions</th></tr></thead>
    <tbody>
    <?php foreach ($posts as $p): ?>
      <tr>
        <td data-label="標題 Title"><a class="rowlink" href="<?= url('/admin/posts/edit') ?>?id=<?= (int) $p['id'] ?>"><?= h($p['title_zh']) ?></a>
          <?php if ($p['title_en']): ?><span class="sub-line"><?= h($p['title_en']) ?></span><?php endif; ?></td>
        <td data-label="狀態 Status">
          <?= $p['is_published'] ? '<span class="badge ok">已發佈 Published</span>' : '<span class="badge pending">草稿 Draft</span>' ?>
          <?= $p['is_pinned'] ? '<span class="badge">📌 置頂 Pinned</span>' : '' ?>
          <?= $p['image_path'] ? '🖼️' : '' ?></td>
        <td data-label="日期 Date"><?= h(date('Y-m-d', strtotime($p['created_at']))) ?><span class="sub-line"><?= h($p['created_by'] ?? '') ?></span></td>
        <td data-label="操作 Actions"><div class="actions-cell">
          <a class="mini-btn" href="<?= url('/admin/posts/edit') ?>?id=<?= (int) $p['id'] ?>">✏️ 編輯 Edit</a>
          <form method="POST" action="<?= url('/admin/posts/delete') ?>" style="margin:0"
                onsubmit="return confirm('刪除這則消息？\nDelete this post?');">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="mini-btn danger" type="submit">🗑 刪除 Delete</button>
          </form></div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
