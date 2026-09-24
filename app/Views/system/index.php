<?php
require BASE_PATH . '/app/Views/layouts/admin_header.php';
$img = static fn(?string $p): string => $p ? BASE_URL . '/' . $p : '';
// Two clear buttons — 顯示 Show / 隱藏 Hide — instead of a small switch:
// easier to tap, and the words say exactly what will happen.
$toggle = static function (string $name, bool $on, string $label) {
    ?>
    <div class="seg-toggle" role="radiogroup" aria-label="<?= h($label) ?>">
      <label class="seg-on"><input type="radio" name="<?= h($name) ?>" value="1"<?= $on ? ' checked' : '' ?>><span>✓ 顯示 Show</span></label>
      <label class="seg-off"><input type="radio" name="<?= h($name) ?>" value="0"<?= $on ? '' : ' checked' ?>><span>✕ 隱藏 Hide</span></label>
    </div>
    <?php
};
// A line prefilled with what the site shows now; ↺ puts the automatic text back.
$line = static function (string $key, string $zh, string $en, int $max, string $hint = '') use ($settings) {
    $fallback = App\Models\Setting::fallback($key, $settings);
    ?>
    <div class="field-with-reset">
      <label for="<?= $key ?>"><?= $zh ?> <span class="en"><?= $en ?></span></label>
      <div class="input-reset">
        <input id="<?= $key ?>" name="<?= $key ?>" maxlength="<?= $max ?>" value="<?= h(App\Models\Setting::effective($key, $settings)) ?>">
        <button type="button" class="mini-btn ghost" data-reset="<?= $key ?>" data-value="<?= h($fallback) ?>" title="<?= h($fallback) ?>">↺ 預設 Default</button>
      </div>
      <?php if ($hint): ?><p class="help"><?= $hint ?></p><?php endif; ?>
    </div>
    <?php
};
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
<form method="POST" action="<?= url('/system/settings') ?>" enctype="multipart/form-data" class="form-panel wide-form" data-trad-check>
  <?= csrf_field() ?>
  <p class="help" style="margin-top:0">這些設定屬於網站本身，換年度也不必重新設定。
    <span class="en">These belong to the site itself and carry over from year to year.</span></p>
  <div class="form-sections">
  <section class="panel form-sec">
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
    <?php $toggle('site_tagline_on', $settings['site_tagline_on'] === '1', '頂部標語 Top bar'); ?>
  </div>
  <input id="site_tagline" name="site_tagline" maxlength="255" value="<?= h($settings['site_tagline']) ?>">
  <p class="help">網站最上方的深紅色橫條。選「隱藏」即可不顯示，文字會保留，下次選「顯示」不必重打。
    The dark red strip at the very top. Choose Hide to take it away — the text is kept for next time.</p>
  </section>
  <section class="panel form-sec">
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
  </section>
  <section class="panel form-sec">
  <h3>③ 頁尾 <span class="en">Footer</span></h3>
  <p class="help" style="margin-top:0">每一行都照你輸入的顯示；<strong>留空就不顯示</strong>。按「↺ 預設」可放回原本的文字。
    <span class="en">Every line shows exactly as typed; <strong>leave it empty to hide it</strong>. ↺ Default puts the original text back.</span></p>
  <div class="form-grid">
    <?php $line('footer_title', '頁尾標題', 'Footer heading', 120); ?>
    <?php $line('footer_copyright', '版權行', 'Copyright line', 200, '{year} 會自動換成活動年份。{year} becomes the event year.'); ?>
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
  </section>
  <section class="panel form-sec">
  <h3>④ 列印 / PDF 抬頭 <span class="en">Printout letterhead</span></h3>
  <p class="help" style="margin-top:0">報到名單、布施清單等列印文件頂部的抬頭。照你輸入的顯示，留空的行不印。文件標題在「網站文字」。
    <span class="en">The heading on printed lists and PDFs, exactly as typed — empty lines are left off. Document titles are in 網站文字 Wording.</span></p>
  <div class="label-row" style="margin-top:.6rem">
    <label>標誌 <span class="en">Logo on printouts</span></label>
    <?php $toggle('pdf_show_logo', $settings['pdf_show_logo'] === '1', '列印標誌 Logo on printouts'); ?>
  </div>
  <div class="form-grid">
    <?php $line('pdf_name', '抬頭名稱', 'Heading name', 80); ?>
    <?php $line('pdf_name_en', '英文名稱', 'English name', 120); ?>
  </div>
  <?php $line('pdf_line1', '第一行', 'Line 1', 255); ?>
  <?php $line('pdf_line2', '第二行', 'Line 2', 255); ?>
  <?php $line('pdf_line3', '第三行', 'Line 3', 255); ?>
  </section>
  <section class="panel form-sec" id="ai">
  <h3>⑤ AI 讀取收據 <span class="en">AI receipt reading</span></h3>
  <?php
    $aiProvider = App\Core\ReceiptReader::provider();
    $aiFromText = ['config' => 'config/config.php', 'env' => '伺服器環境變數 server environment', 'settings' => '本頁 this page'];
  ?>
  <label>使用哪個 AI 服務 <span class="en">Which AI service</span></label>
  <div class="ai-choice" role="radiogroup">
    <?php foreach (App\Core\ReceiptReader::PROVIDERS as $pKey => [$pLabel, $pPrefix, $pUrl]): ?>
      <?php [$pWhere, $pKeyVal] = App\Core\ReceiptReader::keySource($pKey); ?>
      <label class="ai-option">
        <input type="radio" name="ai_provider" value="<?= $pKey ?>"<?= $aiProvider === $pKey ? ' checked' : '' ?>>
        <span class="ai-option-body">
          <strong><?= h($pLabel) ?></strong>
          <small><?= $pKey === 'anthropic'
              ? '讀手寫中文最準確，按用量收費。Best at handwritten Chinese; paid per use.'
              : ($pKey === 'nvidia'
                  ? '有免費額度，開源模型，手寫中文較不準。Free credits; open models, weaker on handwritten Chinese.'
                  : '每月首 1,000 張免費，手寫辨識好；只讀文字，欄位由系統按標籤推斷。First 1,000 a month free, good with handwriting; reads text only — fields are worked out from the printed labels.') ?></small>
          <span class="ai-key-state <?= $pKeyVal !== '' ? 'on' : 'off' ?>"><?= $pKeyVal !== ''
              ? '✓ 金鑰 Key ' . h(App\Core\ReceiptReader::mask($pKeyVal)) . ' · ' . h($aiFromText[$pWhere] ?? '')
              : '✕ 未有金鑰 No key' ?></span>
        </span>
      </label>
    <?php endforeach; ?>
  </div>
  <?php foreach (App\Core\ReceiptReader::setupHints() as $hint): ?>
    <p class="flash error" style="white-space:pre-line;margin:10px 0 0">⚠️ <?= h($hint) ?></p>
  <?php endforeach; ?>

  <div class="ai-panel" data-ai-panel="anthropic">
    <label for="anthropic_api_key">Anthropic API 金鑰 <span class="en">API key</span></label>
    <div class="pw-field">
      <input id="anthropic_api_key" name="anthropic_api_key" type="password" autocomplete="off" spellcheck="false"
             placeholder="<?= App\Core\ReceiptReader::keySource('anthropic')[0] === 'settings' ? '已儲存，留空保持不變 Saved — leave empty to keep it' : 'sk-ant-api03-…' ?>">
      <button type="button" class="pw-eye" data-toggle-password="anthropic_api_key" title="顯示 Show">👁</button>
    </div>
    <p class="help">到 <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com</a> 建立金鑰（sk-ant- 開頭）並儲值。模型 Model：<code><?= h(App\Core\ReceiptReader::model('anthropic')) ?></code>
      <span class="en">Create a key at console.anthropic.com (starts with sk-ant-) and add credit.</span></p>
    <?php if (App\Core\ReceiptReader::keySource('anthropic')[0] === 'settings'): ?>
      <label class="check-row" style="font-weight:600"><input type="checkbox" name="remove_anthropic_key" value="1" style="width:auto"> 移除已儲存的 Anthropic 金鑰 <span class="en">Remove the saved key</span></label>
    <?php endif; ?>
  </div>

  <div class="ai-panel" data-ai-panel="nvidia">
    <label for="nvidia_api_key">NVIDIA API 金鑰 <span class="en">API key</span></label>
    <div class="pw-field">
      <input id="nvidia_api_key" name="nvidia_api_key" type="password" autocomplete="off" spellcheck="false"
             placeholder="<?= App\Core\ReceiptReader::keySource('nvidia')[0] === 'settings' ? '已儲存，留空保持不變 Saved — leave empty to keep it' : 'nvapi-…' ?>">
      <button type="button" class="pw-eye" data-toggle-password="nvidia_api_key" title="顯示 Show">👁</button>
    </div>
    <p class="help">到 <a href="https://build.nvidia.com/" target="_blank" rel="noopener">build.nvidia.com</a> 登入，在任何模型頁按「Get API Key」取得金鑰（nvapi- 開頭）。
      <span class="en">Sign in at build.nvidia.com and press “Get API Key” on any model page (starts with nvapi-).</span></p>
    <label for="nvidia_model">模型 <span class="en">Model (must accept images)</span></label>
    <input id="nvidia_model" name="nvidia_model" list="nvidiaModels" maxlength="120" value="<?= h(App\Core\ReceiptReader::model('nvidia')) ?>" spellcheck="false">
    <datalist id="nvidiaModels">
      <?php foreach (App\Core\ReceiptReader::NVIDIA_MODELS as $m): ?><option value="<?= h($m) ?>"><?php endforeach; ?>
    </datalist>
    <p class="help">可直接從清單選，或貼上 build.nvidia.com 上任何「視覺 Vision」模型的名稱。讀不準時可換一個試試。
      <span class="en">Pick from the list, or paste the name of any vision model on build.nvidia.com. If readings are poor, try another.</span></p>
    <?php if (App\Core\ReceiptReader::keySource('nvidia')[0] === 'settings'): ?>
      <label class="check-row" style="font-weight:600"><input type="checkbox" name="remove_nvidia_key" value="1" style="width:auto"> 移除已儲存的 NVIDIA 金鑰 <span class="en">Remove the saved key</span></label>
    <?php endif; ?>
  </div>

  <div class="ai-panel" data-ai-panel="google">
    <label for="google_api_key">Google Cloud API 金鑰 <span class="en">API key</span></label>
    <div class="pw-field">
      <input id="google_api_key" name="google_api_key" type="password" autocomplete="off" spellcheck="false"
             placeholder="<?= App\Core\ReceiptReader::keySource('google')[0] === 'settings' ? '已儲存，留空保持不變 Saved — leave empty to keep it' : 'AIza…' ?>">
      <button type="button" class="pw-eye" data-toggle-password="google_api_key" title="顯示 Show">👁</button>
    </div>
    <ol class="setup-steps">
      <li>在 <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud</a> 選好專案，到「API 和服務 → 程式庫」啟用 <strong>Cloud Vision API</strong>。
        <span class="en">Pick your project, then APIs &amp; Services → Library → enable <strong>Cloud Vision API</strong>.</span></li>
      <li>到「帳單」確認已連結付款方式（每月首 1,000 張免費）。<span class="en">Under Billing, make sure a payment method is linked (the first 1,000 a month are free).</span></li>
      <li>到「API 和服務 → 憑證」→「建立憑證 → API 金鑰」，複製 AIza 開頭的金鑰貼在上面。
        <span class="en">APIs &amp; Services → Credentials → Create credentials → API key; paste the key (starts with AIza) above.</span></li>
      <li>建議：編輯金鑰，「API 限制」選 Cloud Vision API；「應用程式限制」保持「無」（網站限制會擋住伺服器）。
        <span class="en">Recommended: edit the key, restrict it to Cloud Vision API, and leave Application restrictions at None (a website restriction blocks the server).</span></li>
    </ol>
    <?php if (App\Core\ReceiptReader::keySource('google')[0] === 'settings'): ?>
      <label class="check-row" style="font-weight:600"><input type="checkbox" name="remove_google_key" value="1" style="width:auto"> 移除已儲存的 Google 金鑰 <span class="en">Remove the saved key</span></label>
    <?php endif; ?>
  </div>

  <div class="ai-actions">
    <button type="submit" class="mini-btn btn-lg" formaction="<?= url('/system/ai-test') ?>" formnovalidate>🔌 測試連線 <span class="en">Test connection</span></button>
    <span class="help">測試所選的服務：只送出一個極小的要求，不讀取收據。Tests the service chosen above with one tiny request — no receipt is read.</span>
  </div>
  </section>
  </div>

  <div class="form-actions sticky-actions wide-actions">
    <button class="primary" type="submit">💾 儲存設定 Save settings</button>
    <a class="mini-btn ghost" href="<?= url('/') ?>" target="_blank">👀 查看網站 View site</a>
  </div>
</form>
<script>
// Show only the key box for the AI service that is chosen.
(function () {
  function sync() {
    var chosen = (document.querySelector('input[name="ai_provider"]:checked') || {}).value;
    document.querySelectorAll('[data-ai-panel]').forEach(function (p) { p.hidden = p.dataset.aiPanel !== chosen; });
  }
  document.querySelectorAll('input[name="ai_provider"]').forEach(function (r) { r.addEventListener('change', sync); });
  sync();
})();
document.querySelectorAll('[data-reset]').forEach(function (b) {
  b.addEventListener('click', function () {
    var input = document.getElementById(b.dataset.reset);
    input.value = b.dataset.value;
    input.focus();
  });
});
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
