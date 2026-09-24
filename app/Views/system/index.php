<?php
require BASE_PATH . '/app/Views/layouts/admin_header.php';
$img = static fn(?string $p): string => $p ? BASE_URL . '/' . $p : '';
?>
<?php if (!empty($activeEvent)): ?>
  <div class="panel eventbar">
    <div>
      <h2 style="margin:0">目前公開的活動 <span class="en">Live event</span></h2>
      <p style="margin:4px 0 0"><strong><?= h($activeEvent['year']) ?> · <?= h($activeEvent['name']) ?></strong>
        <?= !empty($activeEvent['is_test']) ? ' <span class="badge cancelled">測試 Test</span>' : '' ?></p>
    </div>
    <div class="eventbar-actions">
      <a class="mini-btn btn-lg" href="<?= url('/admin/event/edit') ?>">📅 活動資料 Event details</a>
      <a class="mini-btn ghost btn-lg" href="<?= url('/') ?>" target="_blank">👀 查看網站 View site</a>
    </div>
  </div>
<?php endif; ?>

<!-- enctype is required: without it the browser sends only the file
     NAME, never the file itself, and $_FILES arrives empty. -->
<form method="POST" action="<?= url('/system/settings') ?>" enctype="multipart/form-data" class="panel form-panel" style="max-width:900px">
  <?= csrf_field() ?>
  <p class="help" style="margin-top:0">這些設定屬於網站本身，換年度也不必重新設定。
    <span class="en">These belong to the site itself and carry over from year to year.</span></p>

  <h3>① 網站名稱 <span class="en">Site name &amp; header</span></h3>
  <div class="form-grid">
    <div><label for="site_name">網站名稱（中文）<span class="en">Site name *</span></label>
      <input id="site_name" name="site_name" required maxlength="80" value="<?= h($settings['site_name']) ?>">
      <p class="help">顯示在網站左上角標誌旁邊。Shown beside the logo, top left.</p></div>
    <div><label for="site_name_en">英文名稱 <span class="en">English name</span></label>
      <input id="site_name_en" name="site_name_en" maxlength="120" value="<?= h($settings['site_name_en']) ?>">
      <p class="help">顯示在中文名稱下方。Shown under the Chinese name.</p></div>
  </div>
  <label for="site_tagline">頂部標語 <span class="en">Top bar text</span></label>
  <input id="site_tagline" name="site_tagline" maxlength="255" value="<?= h($settings['site_tagline']) ?>">
  <p class="help">網站最上方的深紅色橫條，留空即不顯示。The dark red strip at the very top. Leave empty to hide it.</p>

  <h3>② 圖片 <span class="en">Images</span></h3>
  <p class="help" style="margin-top:0">JPG、PNG、GIF、WebP，單檔 5MB 以內。上傳後系統會自動重新產生圖片。JPG / PNG / GIF / WebP up to 5 MB.</p>

  <?php foreach ([
      ['logo', 'site_logo_path', '網站標誌', 'Logo', '顯示在網站名稱左邊、後台與消息貼文。建議正方形透明背景 PNG。Shown left of the site name, in the admin area and on news posts. A square PNG with transparent background works best.', 'favicon'],
      ['hero_banner', 'site_banner_path', '首頁橫幅', 'Home page banner', '完整顯示在首頁頂部，不會被裁切或淡化。建議寬 1920px。Shown whole at the top of the home page — never cropped or faded. 1920px wide is ideal.', ''],
      ['favicon', 'site_favicon_path', '網站小圖示', 'Favicon', '瀏覽器分頁上的小圖示（前台與後台都會用到）。建議正方形 180×180px。更換後可能要按 Ctrl+F5 才看得到。The small icon on browser tabs (public site and admin). Square, 180×180px. Press Ctrl+F5 to see a change.', 'favicon'],
  ] as [$input, $key, $zh, $en, $help, $cls]): ?>
    <label for="<?= $input ?>"><?= $zh ?> <span class="en"><?= $en ?></span></label>
    <?php if (!empty($settings[$key])): ?>
      <div class="image-preview <?= $cls ?>">
        <img src="<?= h($img($settings[$key])) ?>" alt="<?= h($zh) ?>">
        <label class="remove-check"><input type="checkbox" name="remove_<?= $input ?>" value="1"> 移除 Remove</label>
      </div>
    <?php endif; ?>
    <input id="<?= $input ?>" name="<?= $input ?>" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
    <p class="help"><?= h($help) ?></p>
  <?php endforeach; ?>

  <h3>③ 頁尾 <span class="en">Footer</span></h3>
  <p class="help" style="margin-top:0">留空的項目不會顯示。Empty lines are not shown.</p>
  <label for="footer_org">機構名稱 <span class="en">Organisation name</span></label>
  <input id="footer_org" name="footer_org" maxlength="150" value="<?= h($settings['footer_org']) ?>">
  <div class="form-grid">
    <div><label for="footer_address">地址 <span class="en">Address</span></label>
      <input id="footer_address" name="footer_address" maxlength="255" value="<?= h($settings['footer_address']) ?>"></div>
    <div><label for="footer_contact">聯絡方式 <span class="en">Contact</span></label>
      <input id="footer_contact" name="footer_contact" maxlength="150" value="<?= h($settings['footer_contact']) ?>" placeholder="012-345 6789 (WhatsApp)"></div>
  </div>
  <label for="footer_note_zh">頁尾說明（中文）<span class="en">Footer note (Chinese)</span></label>
  <textarea id="footer_note_zh" name="footer_note_zh" rows="2" maxlength="500"><?= h($settings['footer_note_zh']) ?></textarea>
  <label for="footer_note_en">頁尾說明（英文）<span class="en">Footer note (English)</span></label>
  <textarea id="footer_note_en" name="footer_note_en" rows="2" maxlength="500"><?= h($settings['footer_note_en']) ?></textarea>

  <div class="form-actions">
    <button class="primary" type="submit">💾 儲存設定 Save settings</button>
    <a class="mini-btn ghost" href="<?= url('/') ?>" target="_blank">👀 查看網站 View site</a>
  </div>
</form>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
