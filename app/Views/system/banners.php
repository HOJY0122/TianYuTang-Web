<?php
/**
 * 首頁橫幅 Home banner — add pictures, put them in order, nudge and zoom
 * each one, and choose how tall the banner is and how it changes.
 * The real slideshow at the top updates as you tune.
 */
use App\Controllers\BannerController as BC;

require BASE_PATH . '/app/Views/layouts/admin_header.php';
$hD = (int) $site['banner_height_desktop'];
$hM = (int) $site['banner_height_mobile'];
?>
<link rel="stylesheet" href="<?= asset('css/banner.css') ?>">

<p class="help" style="margin:0 0 14px">首頁最上方的大圖。可以放多張輪流顯示，每張都能拖動位置、放大，讓重要的部分不會被切掉。
  <span class="en">The big picture at the top of the home page. Add several to take turns; drag and zoom each one so the important part always shows.</span></p>

<?php foreach ($slides as $s): ?>
  <form id="del-<?= (int) $s['id'] ?>" method="POST" action="<?= url('/system/banners/delete') ?>"
        data-confirm="從首頁橫幅刪除這張圖片？&#10;Remove this picture from the banner?" data-danger>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"></form>
<?php endforeach; ?>

<form method="POST" action="<?= url('/system/banners/save') ?>" id="bannerForm" class="banner-admin form-panel">
  <?= csrf_field() ?>

  <!-- ① The real slideshow, as visitors will see it -->
  <section class="panel">
    <div class="bn-head">
      <h2 style="margin:0">① 預覽 <span class="en">Preview</span></h2>
      <div class="seg-toggle bn-device" role="radiogroup" aria-label="預覽裝置 Preview on">
        <label class="seg-on"><input type="radio" name="_device" value="desktop" checked><span>💻 電腦 Computer</span></label>
        <label class="seg-on"><input type="radio" name="_device" value="mobile"><span>📱 手機 Phone</span></label>
      </div>
    </div>
    <?php if ($slides): ?>
      <div class="bn-preview" id="bnPreview">
        <?php $bannerSlides = $slides; $bannerSite = $site; $bannerAlt = $site['site_name']; $bannerPreview = true;
              require BASE_PATH . '/app/Views/partials/banner_show.php'; ?>
      </div>
      <p class="help">預覽會隨下面的設定即時改變；按「儲存」後首頁才會更新。暫停的圖片（未勾「顯示」）在首頁不會出現。
        <span class="en">The preview follows your changes as you make them; the home page changes when you press Save. Pictures switched off are not shown on the home page.</span></p>
    <?php else: ?>
      <p class="empty">還沒有橫幅圖片。在下面 ④ 加入第一張。No banner pictures yet — add the first one in ④ below.</p>
    <?php endif; ?>
  </section>

  <!-- ② How the banner is shown -->
  <section class="panel">
    <h2 style="margin-top:0">② 顯示方式 <span class="en">How it is shown</span></h2>
    <div class="bn-settings">
      <?php foreach (['desktop' => ['💻 電腦 Computer', $hD], 'mobile' => ['📱 手機 Phone', $hM]] as $dev => [$label, $h]): ?>
        <fieldset class="bn-set" data-height="<?= $dev ?>">
          <legend><?= $label ?> — 高度 <span class="en">Height</span></legend>
          <label class="check-inline"><input type="checkbox" name="whole_<?= $dev ?>" value="1"<?= $h === 0 ? ' checked' : '' ?>>
            整張圖片（不裁切）<span class="en">Whole picture (no cropping)</span></label>
          <div class="bn-range"<?= $h === 0 ? ' hidden' : '' ?>>
            <input type="range" name="height_<?= $dev ?>" min="<?= BC::HEIGHT_MIN ?>" max="<?= BC::HEIGHT_MAX ?>" step="1" value="<?= $h ?: 35 ?>">
            <output>高度 = 寬度的 <?= $h ?: 35 ?>% · height <?= $h ?: 35 ?>% of width</output>
          </div>
        </fieldset>
      <?php endforeach; ?>

      <fieldset class="bn-set">
        <legend>⏱ 換圖速度 <span class="en">Time per picture</span></legend>
        <input type="range" name="interval" min="<?= BC::INTERVAL_MIN ?>" max="<?= BC::INTERVAL_MAX ?>" step="1" value="<?= (int) $site['banner_interval'] ?>">
        <output>每 <?= (int) $site['banner_interval'] ?> 秒 · every <?= (int) $site['banner_interval'] ?> s</output>
        <p class="help">只有一張時不會換。滑鼠停在橫幅上會暫停。Nothing changes with one picture; it pauses while the mouse is over it.</p>
      </fieldset>

      <fieldset class="bn-set">
        <legend>✨ 換圖效果 <span class="en">Effect</span></legend>
        <div class="seg-toggle">
          <label class="seg-on"><input type="radio" name="effect" value="fade"<?= $site['banner_effect'] !== 'slide' ? ' checked' : '' ?>><span>淡入 Fade</span></label>
          <label class="seg-on"><input type="radio" name="effect" value="slide"<?= $site['banner_effect'] === 'slide' ? ' checked' : '' ?>><span>滑動 Slide</span></label>
        </div>
        <div class="bn-sub">‹ › ● 箭頭與圓點 <span class="en">Arrows &amp; dots</span></div>
        <div class="seg-toggle">
          <label class="seg-on"><input type="radio" name="controls" value="1"<?= $site['banner_controls'] !== '0' ? ' checked' : '' ?>><span>✓ 顯示 Show</span></label>
          <label class="seg-off"><input type="radio" name="controls" value="0"<?= $site['banner_controls'] === '0' ? ' checked' : '' ?>><span>✕ 隱藏 Hide</span></label>
        </div>
      </fieldset>
    </div>
  </section>

  <!-- ③ Each picture -->
  <?php if ($slides): ?>
  <section class="panel">
    <h2 style="margin-top:0">③ 圖片 <span class="en">Pictures</span></h2>
    <p class="help" style="margin-top:0">⠿ 拖動排序（立即儲存）。在圖片上<strong>拖動</strong>調整位置，用 🔍 放大。十字＋是畫面的中心點。
      <span class="en">Drag ⠿ to reorder (saved at once). <strong>Drag the picture</strong> to move it, 🔍 to zoom. The ＋ marks the point kept in view.</span></p>
    <div class="bn-list" data-sortable="<?= url('/system/banners/reorder') ?>">
      <?php foreach ($slides as $n => $s): $id = (int) $s['id']; ?>
        <div class="bn-card<?= $s['is_active'] ? '' : ' is-off' ?>" data-id="<?= $id ?>" id="slide-<?= $id ?>"
             data-w="<?= (int) $s['img_w'] ?>" data-h="<?= (int) $s['img_h'] ?>">
          <div class="bn-card-head">
            <button type="button" class="drag-handle" data-drag-handle aria-label="拖動排序 Drag to reorder">⠿</button>
            <strong class="bn-no"><?= $n + 1 ?></strong>
            <label class="check-inline"><input type="checkbox" name="slide[<?= $id ?>][is_active]" value="1"<?= $s['is_active'] ? ' checked' : '' ?> data-active>
              顯示 <span class="en">Show</span></label>
            <span class="spacer"></span>
            <button type="submit" form="del-<?= $id ?>" class="mini-btn danger">🗑 刪除 <span class="en">Delete</span></button>
          </div>

          <div class="bn-tuner" tabindex="0" aria-label="拖動或用方向鍵調整位置 Drag or use arrow keys to move the picture">
            <img src="<?= h(BASE_URL . '/' . $s['image_path']) ?>" alt="" draggable="false"
                 style="object-position:<?= (int) $s['pos_x'] ?>% <?= (int) $s['pos_y'] ?>%;transform:scale(<?= max(100, (int) $s['zoom']) / 100 ?>);transform-origin:<?= (int) $s['pos_x'] ?>% <?= (int) $s['pos_y'] ?>%">
            <span class="bn-cross" style="left:<?= (int) $s['pos_x'] ?>%;top:<?= (int) $s['pos_y'] ?>%" aria-hidden="true"></span>
          </div>
          <input type="hidden" name="slide[<?= $id ?>][pos_x]" value="<?= (int) $s['pos_x'] ?>" data-pos="x">
          <input type="hidden" name="slide[<?= $id ?>][pos_y]" value="<?= (int) $s['pos_y'] ?>" data-pos="y">

          <div class="bn-zoom">
            <label>🔍 放大 <span class="en">Zoom</span>
              <input type="range" name="slide[<?= $id ?>][zoom]" min="100" max="<?= BC::ZOOM_MAX ?>" step="5" value="<?= max(100, (int) $s['zoom']) ?>" data-zoom></label>
            <output><?= max(100, (int) $s['zoom']) ?>%</output>
            <button type="button" class="mini-btn ghost" data-center>⊕ 置中 <span class="en">Centre</span></button>
            <button type="button" class="mini-btn ghost" data-reset>↺ 重設 <span class="en">Reset</span></button>
          </div>

          <div class="form-grid">
            <div><label>字幕（中文）<span class="en">Caption (Chinese) — optional</span></label>
              <input name="slide[<?= $id ?>][caption_zh]" value="<?= h($s['caption_zh'] ?? '') ?>" maxlength="120" data-cap="zh"></div>
            <div><label>字幕（英文）<span class="en">Caption (English) — optional</span></label>
              <input name="slide[<?= $id ?>][caption_en]" value="<?= h($s['caption_en'] ?? '') ?>" maxlength="160" data-cap="en"></div>
          </div>
          <label>點擊連結 <span class="en">Link when tapped — optional, e.g. /register</span></label>
          <input name="slide[<?= $id ?>][link_url]" value="<?= h($s['link_url'] ?? '') ?>" maxlength="255" placeholder="/register  或 or  https://…">
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <div class="form-actions sticky-actions">
    <button class="primary" type="submit">💾 儲存 <span class="en">Save</span></button>
    <a class="mini-btn ghost btn-lg" href="<?= url('/') ?>" target="_blank">👀 查看首頁 <span class="en">View home page</span></a>
  </div>
</form>

<!-- ④ Add a picture -->
<form method="POST" action="<?= url('/system/banners/add') ?>" enctype="multipart/form-data" class="panel form-panel" style="margin-top:18px">
  <?= csrf_field() ?>
  <h2 style="margin-top:0">④ 加入圖片 <span class="en">Add a picture</span></h2>
  <input id="bannerFile" name="banner" type="file" accept="image/jpeg,image/png,image/gif,image/webp"
         data-aspects="original,3:1,16:9,21:9" data-max-width="1920" required>
  <p class="help">JPG / PNG / WebP，5MB 以內，建議寬 1920px。選好後可先裁切。<span class="en">Up to 5 MB, 1920px wide is ideal. You can crop it after choosing.</span></p>
  <div class="form-actions"><button class="primary" type="submit">➕ 加入 <span class="en">Add</span></button></div>
</form>

<script src="<?= asset('js/banner.js') ?>"></script>
<script src="<?= asset('js/banner-tuner.js') ?>"></script>
<script src="<?= asset('js/sortable.js') ?>"></script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
