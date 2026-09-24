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
  <label>標題字體 <span class="en">Heading font</span></label>
  <div class="font-choices">
    <?php foreach (App\Models\Setting::HEADING_FONTS as $fKey => [$fLabel, $fFamily]): ?>
      <label class="font-choice">
        <input type="radio" name="heading_font" value="<?= h($fKey) ?>"<?= ($settings['heading_font'] ?? 'brush') === $fKey ? ' checked' : '' ?>>
        <span class="font-sample" style="font-family:'<?= h($fFamily) ?>',serif"><?= h($settings['site_name']) ?> 千秋寶誕</span>
        <small><?= h($fLabel) ?></small>
      </label>
    <?php endforeach; ?>
  </div>
  <p class="help">用於網站名稱與各段標題，內文維持清晰的黑體。Used for the site name and headings; body text stays in a clear sans-serif.</p>

  <div class="label-row">
    <label for="site_tagline">頂部標語 <span class="en">Top bar text</span></label>
    <label class="switch"><input type="checkbox" name="site_tagline_on" value="1"<?= $settings['site_tagline_on'] === '1' ? ' checked' : '' ?>>
      <span class="switch-ui" aria-hidden="true"></span> 顯示 <span class="en">Show</span></label>
  </div>
  <input id="site_tagline" name="site_tagline" maxlength="255" value="<?= h($settings['site_tagline']) ?>">
  <p class="help">網站最上方的深紅色橫條。關閉開關即可隱藏，文字會保留，下次打開不必重打。
    The dark red strip at the very top. Switch it off to hide it — the text is kept for next time.</p>

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
    <input id="<?= $input ?>" name="<?= $input ?>" type="file" accept="image/jpeg,image/png,image/gif,image/webp"
           data-aspects="<?= ['logo' => '1:1,original', 'hero_banner' => 'original,16:9,3:1', 'favicon' => '1:1'][$input] ?>"
           data-max-width="<?= ['logo' => 600, 'hero_banner' => 1920, 'favicon' => 256][$input] ?>">
    <p class="help"><?= h($help) ?></p>
  <?php endforeach; ?>

  <h3>③ 頁尾 <span class="en">Footer</span></h3>
  <p class="help" style="margin-top:0">留空的項目不會顯示。Empty lines are not shown.</p>
  <div class="form-grid">
    <div><label for="footer_title">頁尾標題 <span class="en">Footer heading</span></label>
      <input id="footer_title" name="footer_title" maxlength="120" value="<?= h($settings['footer_title']) ?>" placeholder="🙏 <?= h($settings['site_name']) ?>">
      <p class="help">留空＝「🙏 網站名稱」。Blank = 🙏 + site name.</p></div>
    <div><label for="footer_copyright">版權行 <span class="en">Copyright line</span></label>
      <input id="footer_copyright" name="footer_copyright" maxlength="200" value="<?= h($settings['footer_copyright']) ?>" placeholder="© {year} <?= h($settings['footer_org'] ?: $settings['site_name']) ?>">
      <p class="help">{year} 會換成活動年份。留空＝自動。{year} becomes the event year. Blank = automatic.</p></div>
  </div>
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

  <h3>④ 列印 / PDF 抬頭 <span class="en">Printout letterhead</span></h3>
  <p class="help" style="margin-top:0">報到名單、布施清單等列印文件頂部的抬頭。留空的行會沿用上面的網站名稱與頁尾資料。
    <span class="en">The heading on printed lists and PDFs. Blank lines reuse the site name and footer details above.
    Document titles are in 網站文字 Wording.</span></p>
  <label class="switch" style="margin:.6rem 0"><input type="checkbox" name="pdf_show_logo" value="1"<?= $settings['pdf_show_logo'] === '1' ? ' checked' : '' ?>>
    <span class="switch-ui" aria-hidden="true"></span> 顯示標誌 <span class="en">Show the logo</span></label>
  <div class="form-grid">
    <div><label for="pdf_name">抬頭名稱 <span class="en">Heading name</span></label>
      <input id="pdf_name" name="pdf_name" maxlength="80" value="<?= h($settings['pdf_name']) ?>" placeholder="<?= h($settings['site_name']) ?>"></div>
    <div><label for="pdf_name_en">英文名稱 <span class="en">English name</span></label>
      <input id="pdf_name_en" name="pdf_name_en" maxlength="120" value="<?= h($settings['pdf_name_en']) ?>" placeholder="<?= h($settings['site_name_en']) ?>"></div>
  </div>
  <label for="pdf_line1">第一行 <span class="en">Line 1</span></label>
  <input id="pdf_line1" name="pdf_line1" maxlength="255" value="<?= h($settings['pdf_line1']) ?>" placeholder="<?= h($settings['footer_org']) ?>">
  <label for="pdf_line2">第二行 <span class="en">Line 2</span></label>
  <input id="pdf_line2" name="pdf_line2" maxlength="255" value="<?= h($settings['pdf_line2']) ?>" placeholder="<?= h($settings['footer_address'] ?: '地址 Address') ?>">
  <label for="pdf_line3">第三行 <span class="en">Line 3</span></label>
  <input id="pdf_line3" name="pdf_line3" maxlength="255" value="<?= h($settings['pdf_line3']) ?>" placeholder="<?= h($settings['footer_contact'] ?: '電話 Phone · 電郵 Email') ?>">

  <div class="form-actions">
    <button class="primary" type="submit">💾 儲存設定 Save settings</button>
    <a class="mini-btn ghost" href="<?= url('/') ?>" target="_blank">👀 查看網站 View site</a>
  </div>
</form>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
