<?php
/**
 * System → 表單與字體 Forms & fonts. Settings on the left, the real public
 * pages on the right showing every change before it is saved
 * (FormsController::draft + ?draft=1).
 */
use App\Core\FormRules;
use App\Models\Setting;

require BASE_PATH . '/app/Views/layouts/admin_header.php';

$radio = static function (string $name, array $choices, string $current): void {
    foreach ($choices as $value => [$zh, $en]) {
        ?>
        <label class="pick">
          <input type="radio" name="<?= h($name) ?>" value="<?= h($value) ?>"<?= $current === (string) $value ? ' checked' : '' ?>>
          <span><strong><?= h($zh) ?></strong><small><?= h($en) ?></small></span>
        </label>
        <?php
    }
};
// Only the characters each sample shows are downloaded (&text=), so
// offering eight typefaces costs a few kilobytes, not megabytes.
$sampleHead = $site['site_name'] . '千秋寶誕';
$sampleBody = '報名參加 歡迎蒞臨 功德布施 身份證號碼 Register 123';
$fontLinks  = [];
foreach (Setting::HEADING_FONTS as [, $fam]) {
    $fontLinks[] = 'family=' . str_replace(' ', '+', $fam) . '&text=' . rawurlencode($sampleHead);
}
foreach (FormRules::BODY_FONTS as [, $fam]) {
    $fontLinks[] = 'family=' . str_replace(' ', '+', $fam) . '&text=' . rawurlencode($sampleBody);
}
?>
<?php foreach ($fontLinks as $q): ?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?<?= h($q) ?>&display=swap">
<?php endforeach; ?>

<div class="word-layout forms-layout">
  <form method="POST" action="<?= url('/system/forms') ?>" class="forms-form" id="formsForm" data-live="settings" data-live-mode="form">
    <?= csrf_field() ?>

    <!-- ① Registration -->
    <section class="panel form-panel forms-sec" data-preview-page="register">
      <h2><?= icon('form') ?> 報名 <span class="en">Registration</span></h2>

      <label>報名方式 <span class="en">Who can register</span></label>
      <div class="picks"><?php $radio('rsvp_types', FormRules::TYPES, (string) $site['rsvp_types']); ?></div>
      <p class="help">「團體」需填團體 / 機構名稱，每位參加者仍要填姓名、身份證與電話。
        <span class="en">An organisation group adds the organisation's name; every person still gives their name, IC and phone.</span></p>

      <div class="org-only">
        <label for="rsvp_org_max">團體最多人數 <span class="en">Most people in one organisation group</span></label>
        <div class="num-row">
          <input id="rsvp_org_max" name="rsvp_org_max" type="number" min="0" max="200" value="<?= (int) $site['rsvp_org_max'] ?>">
          <span class="help">0 = 跟活動設定一樣（個人 / 家庭的上限）。<span class="en">0 = same as the event's limit for individuals.</span></span>
        </div>
      </div>

      <div class="label-row">
        <label><?= icon('id-card') ?> 年齡限制 <span class="en">Age limit</span></label>
        <div class="seg-toggle" role="radiogroup" aria-label="年齡限制 Age limit">
          <label class="seg-on"><input type="radio" name="rsvp_age_on" value="1"<?= $site['rsvp_age_on'] === '1' ? ' checked' : '' ?>><span>✓ 開啟 On</span></label>
          <label class="seg-off"><input type="radio" name="rsvp_age_on" value="0"<?= $site['rsvp_age_on'] === '1' ? '' : ' checked' ?>><span>✕ 關閉 Off</span></label>
        </div>
      </div>
      <div class="age-only">
        <label for="rsvp_age_min">最低年齡 <span class="en">Minimum age</span></label>
        <div class="num-row">
          <input id="rsvp_age_min" name="rsvp_age_min" type="number" min="<?= FormRules::AGE_RANGE[0] ?>" max="<?= FormRules::AGE_RANGE[1] ?>" value="<?= (int) $site['rsvp_age_min'] ?>">
          <span class="help">歲或以上，從身份證號碼前 6 位（出生日期）計算。<span class="en">and above — worked out from the IC's first six digits (the birth date).</span></span>
        </div>
        <label>怎樣計歲數 <span class="en">How the age is counted</span></label>
        <div class="picks"><?php $radio('rsvp_age_basis', FormRules::AGE_BASIS, (string) $site['rsvp_age_basis']); ?></div>
        <label>誰要符合 <span class="en">Who must meet it</span></label>
        <div class="picks"><?php $radio('rsvp_age_who', FormRules::AGE_WHO, (string) $site['rsvp_age_who']); ?></div>
        <label>護照或非大馬身份證 <span class="en">Passport or non-Malaysian ID</span></label>
        <div class="picks"><?php $radio('rsvp_age_other', FormRules::AGE_OTHER, (string) $site['rsvp_age_other']); ?></div>
        <p class="help age-example" id="ageExample"></p>
      </div>
    </section>

    <!-- ② Donation -->
    <section class="panel form-panel forms-sec" data-preview-page="donate">
      <h2><?= icon('coins') ?> 布施快速金額 <span class="en">Donation quick amounts</span></h2>
      <label for="donate_amounts">網站布施頁 <span class="en">Public donate page</span></label>
      <input id="donate_amounts" name="donate_amounts" value="<?= h($site['donate_amounts']) ?>" inputmode="numeric" autocomplete="off">
      <div class="amount-chips" data-chips-for="donate_amounts"></div>
      <label for="counter_amounts">現場布施（櫃台）<span class="en">Counter donation page</span></label>
      <input id="counter_amounts" name="counter_amounts" value="<?= h($site['counter_amounts']) ?>" inputmode="numeric" autocomplete="off">
      <div class="amount-chips" data-chips-for="counter_amounts"></div>
      <p class="help">用逗號分開的整數（RM），最多 12 個，例如 <code>1, 5, 10, 20, 50, 100</code>。超出活動設定的隨喜上下限的金額不會顯示。
        <span class="en">Whole Ringgit separated by commas, up to 12, e.g. <code>1, 5, 10, 20, 50, 100</code>. Amounts outside the event's freewill limits are not shown.</span></p>
    </section>

    <!-- ③ Fonts -->
    <section class="panel form-panel forms-sec" data-preview-page="home">
      <h2><?= icon('type') ?> 網站字體 <span class="en">Public site fonts</span></h2>
      <label>標題字體 <span class="en">Heading font</span></label>
      <div class="font-grid">
        <?php foreach (Setting::HEADING_FONTS as $fKey => [$fLabel, $fFamily, , $fWeight]): ?>
          <label class="font-card">
            <input type="radio" name="heading_font" value="<?= h($fKey) ?>"<?= $site['heading_font'] === $fKey ? ' checked' : '' ?>>
            <span class="font-sample head" style="font-family:'<?= h($fFamily) ?>',serif;font-weight:<?= (int) $fWeight ?>"><?= h($sampleHead) ?></span>
            <small><?= h($fLabel) ?></small>
          </label>
        <?php endforeach; ?>
      </div>
      <label>內文字體 <span class="en">Body text font</span></label>
      <div class="font-grid">
        <?php foreach (FormRules::BODY_FONTS as $fKey => [$fLabel, $fFamily, , $fNote]): ?>
          <label class="font-card">
            <input type="radio" name="body_font" value="<?= h($fKey) ?>"<?= $site['body_font'] === $fKey ? ' checked' : '' ?>>
            <span class="font-sample" style="font-family:'<?= h($fFamily) ?>',sans-serif"><?= h($sampleBody) ?></span>
            <small><strong><?= h($fLabel) ?></strong> · <?= h($fNote) ?></small>
          </label>
        <?php endforeach; ?>
      </div>
      <label for="body_size">文字大小 <span class="en">Text size</span></label>
      <div class="fl-range">
        <span class="help">小 A</span>
        <input type="range" id="body_size" name="body_size" min="<?= FormRules::BODY_SIZE[0] ?>" max="<?= FormRules::BODY_SIZE[1] ?>" step="5" value="<?= FormRules::bodySize($site) ?>">
        <span class="help" style="font-size:1.3em">大 A</span>
        <output for="body_size"><?= FormRules::bodySize($site) ?>%</output>
      </div>
      <p class="help">字體全部來自 Google Fonts，都有完整繁體中文。訪客仍可用「無障礙」按鈕自行放大。
        <span class="en">All from Google Fonts, each with full Traditional Chinese. Visitors can still enlarge text with the Access button.</span></p>
    </section>

    <!-- ④ Albums -->
    <section class="panel form-panel forms-sec" data-preview-page="home#photos">
      <h2><?= icon('camera') ?> 首頁相簿 <span class="en">Home page albums</span></h2>
      <div class="picks"><?php $radio('home_albums', [
          'previous' => ['上一屆的相片（推薦）', 'Last event\'s photos — in 2026 show 2025 (recommended)'],
          'latest'   => ['最新的一本相簿', 'The newest album, whichever year'],
          'recent'   => ['最近三年', 'The latest three years'],
      ], (string) $site['home_albums']); ?></div>
      <label for="home_album_photos">每本顯示幾張 <span class="en">Photos shown per album</span></label>
      <div class="num-row">
        <input id="home_album_photos" name="home_album_photos" type="number" min="4" max="40" value="<?= (int) $site['home_album_photos'] ?>">
        <span class="help">其餘相片在「相簿」頁，可按年份篩選。<span class="en">The rest are on the Gallery page, which can be filtered by year.</span></span>
      </div>
    </section>

    <!-- ⑤ Photo protection -->
    <section class="panel form-panel forms-sec" data-preview-page="gallery">
      <h2><?= icon('lock') ?> 相片保護 <span class="en">Photo protection</span></h2>
      <div class="label-row">
        <label>防止下載與複製 <span class="en">Stop saving and copying</span></label>
        <div class="seg-toggle" role="radiogroup" aria-label="相片保護 Photo protection">
          <label class="seg-on"><input type="radio" name="protect_photos" value="1"<?= $site['protect_photos'] === '1' ? ' checked' : '' ?>><span>✓ 開啟 On</span></label>
          <label class="seg-off"><input type="radio" name="protect_photos" value="0"<?= $site['protect_photos'] === '1' ? '' : ' checked' ?>><span>✕ 關閉 Off</span></label>
        </div>
      </div>
      <div class="protect-areas">
        <label>保護哪些部分 <span class="en">What to protect</span></label>
        <?php foreach ([
            'albums' => ['image',  '相簿相片與大圖檢視', 'Album photos and the full-size viewer'],
            'news'   => ['newspaper', '最新消息圖片', 'News post pictures'],
            'banner' => ['camera', '首頁橫幅', 'Home page banner'],
            'about'  => ['users',  '關於我們圖片', 'About page pictures (e.g. the main deity poster)'],
            'qr'     => ['qr',     'Waze 導航 QR Code', 'Waze QR code — usually left OFF so visitors can save or screenshot it to navigate'],
            'keys'   => ['lock',   '開發者 / 儲存快捷鍵與其他地方的右鍵', 'Developer-tool and save shortcuts, and right-click elsewhere on the page'],
        ] as $pa => [$paIcon, $paZh, $paEn]): ?>
          <label class="protect-area">
            <input type="hidden" name="protect_<?= $pa ?>" value="0">
            <input type="checkbox" name="protect_<?= $pa ?>" value="1"<?= ($site['protect_' . $pa] ?? '0') === '1' ? ' checked' : '' ?>>
            <span class="pa-icon"><?= icon($paIcon) ?></span>
            <span class="pa-text"><strong><?= h($paZh) ?></strong><small><?= h($paEn) ?></small></span>
            <span class="pa-state" aria-hidden="true"></span>
          </label>
        <?php endforeach; ?>
      </div>
      <ul class="help protect-list">
        <li>受保護的部分：不能右鍵 / 長按「儲存圖片」、不能拖出；按 Print Screen 時會暫時模糊；列印時不印出。<span class="en">Protected parts: no right-click or long-press “Save image”, no dragging; Print Screen blurs them for a moment; left out of printouts.</span></li>
        <li>看大圖時切換到截圖工具，相片會模糊。<span class="en">Switching to a snipping tool while viewing a photo blurs it.</span></li>
        <li><strong>一直生效：</strong>相片只能在網站頁面內顯示，複製或分享相片網址會打不開，幾小時後自動失效。<span class="en"><strong>Always on:</strong> photos only show inside the site's pages — a copied photo link does not open, and stops working after a few hours.</span></li>
      </ul>
      <label for="photo_watermark">浮水印文字 <span class="en">Watermark text (full-size viewer)</span></label>
      <input id="photo_watermark" name="photo_watermark" maxlength="40" value="<?= h($site['photo_watermark']) ?>" placeholder="例 e.g. 天玉堂 Tian Yu Tang">
      <p class="help">留空 = 不加浮水印。<span class="en">Leave empty for none.</span>
        <?= icon('info') ?> 任何網站都無法完全阻止用另一部手機拍螢幕；以上措施能擋住一般的下載與轉發。
        <span class="en">No website can stop someone photographing the screen with another phone; these steps stop ordinary saving and sharing.</span></p>
    </section>

    <div class="form-actions sticky-actions">
      <button class="primary" type="submit"><?= icon('save') ?> 儲存 <span class="en">Save</span></button>
      <span class="help" id="draftNote" hidden>● 未儲存：右邊預覽已顯示你的修改 <span class="en">Unsaved — the preview shows your changes</span></span>
    </div>
  </form>

  <aside class="panel word-preview">
    <div class="word-preview-bar">
      <strong><?= icon('eye') ?> 即時預覽 <span class="en">Live preview</span></strong>
      <span class="seg-mini" id="pagePick">
        <button type="button" data-page="register" class="is-on">報名 Register</button>
        <button type="button" data-page="donate">布施 Donate</button>
        <button type="button" data-page="home">首頁 Home</button>
        <button type="button" data-page="gallery">相簿 Gallery</button>
      </span>
      <span class="seg-mini">
        <button type="button" data-width="100%" title="電腦 Computer"><?= icon('monitor') ?></button>
        <button type="button" data-width="390px" class="is-on" title="手機 Phone"><?= icon('smartphone') ?></button>
      </span>
    </div>
    <div class="word-frame-wrap"><iframe id="preview" src="<?= url('/register') ?>?draft=1" title="預覽 Preview" style="width:390px"></iframe></div>
    <p class="help" style="margin:.5rem 0 0">預覽是真正的網站頁面，套用了你未儲存的設定；預覽中不能提交表格。
      <span class="en">The preview is the real site with your unsaved settings; forms cannot be sent from it.</span></p>
  </aside>
</div>

<script>
(function () {
  var form = document.getElementById('formsForm');
  var frame = document.getElementById('preview');
  var BASE = <?= json_encode(BASE_URL) ?>;
  var PAGES = { register: '/register', donate: '/donate', home: '/', gallery: '/gallery' };
  var page = 'register', hash = '', timer = null, dirty = false;

  function show() {
    form.querySelector('.org-only').hidden = (form.querySelector('[name=rsvp_types]:checked') || {}).value === 'individual';
    var ageOn = (form.querySelector('[name=rsvp_age_on]:checked') || {}).value === '1';
    form.querySelector('.age-only').hidden = !ageOn;
    var pa = form.querySelector('.protect-areas');
    if (pa) pa.hidden = (form.querySelector('[name=protect_photos]:checked') || {}).value === '0';
    var min = +form.querySelector('[name=rsvp_age_min]').value || 66, y = new Date().getFullYear();
    var byYear = (form.querySelector('[name=rsvp_age_basis]:checked') || {}).value === 'year';
    document.getElementById('ageExample').textContent = byYear
      ? '例：' + y + ' 年的活動 → ' + (y - min) + ' 年或以前出生可以報名。 e.g. an event in ' + y + ' accepts people born in ' + (y - min) + ' or earlier.'
      : '例：活動首日滿 ' + min + ' 歲（以出生日期計）。 e.g. ' + min + ' full years old on the first event day.';
    var out = form.querySelector('.fl-range output');
    out.textContent = form.querySelector('[name=body_size]').value + '%';
    form.querySelectorAll('[data-chips-for]').forEach(function (c) {
      var nums = form.querySelector('#' + c.dataset.chipsFor).value.split(/[\s,，、;]+/).filter(function (v) { return /^\d+$/.test(v) && +v >= 1 && +v <= 100000; })
        .map(Number).filter(function (v, i, a) { return a.indexOf(v) === i; }).sort(function (a, b) { return a - b; }).slice(0, 12);
      c.innerHTML = nums.length ? nums.map(function (n) { return '<span>RM ' + n.toLocaleString() + '</span>'; }).join('')
        : '<em>請輸入至少一個金額 Enter at least one amount</em>';
    });
  }

  function load() {
    var p = PAGES[page] || '/register';
    frame.src = BASE + p + '?draft=1&t=' + Date.now() + (hash ? '#' + hash : '');
  }
  function pick(p, h) {
    page = p; hash = h || '';
    document.querySelectorAll('#pagePick [data-page]').forEach(function (b) { b.classList.toggle('is-on', b.dataset.page === p); });
  }

  // Send the unsaved values, then reload the preview with them.
  function send() {
    var data = new FormData(form);
    fetch(BASE + '/system/forms/draft', { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function () { load(); })
      .catch(function () {});
  }
  form.addEventListener('input', change);
  form.addEventListener('change', change);
  function change(e) {
    show();
    dirty = true;
    document.getElementById('draftNote').hidden = false;
    // Jump the preview to the page the changed setting belongs to.
    var sec = e.target.closest('[data-preview-page]');
    if (sec) { var pp = sec.dataset.previewPage.split('#'); if (pp[0] !== page || (pp[1] || '') !== hash) pick(pp[0], pp[1]); }
    if (e.target.name === 'counter_amounts') return;              // not on a public page
    clearTimeout(timer);
    timer = setTimeout(send, e.type === 'input' && e.target.type !== 'range' && e.target.type !== 'radio' ? 600 : 150);
  }
  form.addEventListener('submit', function () { dirty = false; });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  document.querySelectorAll('#pagePick [data-page]').forEach(function (b) {
    b.addEventListener('click', function () { pick(b.dataset.page); load(); });
  });
  document.querySelectorAll('[data-width]').forEach(function (b) {
    b.addEventListener('click', function () {
      frame.style.width = b.dataset.width;
      document.querySelectorAll('[data-width]').forEach(function (x) { x.classList.toggle('is-on', x === b); });
    });
  });
  show();
})();
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
