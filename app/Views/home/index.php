<?php
/**
 * Public home page — information, news and photos. Every value comes
 * from the active event (admin) or site settings (system admin).
 */
$pageTitle = $event['year'] . ' ' . $event['name'];
require BASE_PATH . '/app/Views/layouts/header.php';

$hasQrImage = !empty($event['waze_qr_path']);
$wazeUrl    = trim((string) ($event['waze_url'] ?? ''));
$mapsUrl    = trim((string) ($event['maps_url'] ?? ''));
$showMap    = $hasQrImage || $wazeUrl !== '' || $mapsUrl !== '' || !empty($event['maps_qr_path']);
// One QR per service: the uploaded image, or one drawn from the link (qr-auto).
$qrCodes = [];
foreach (['waze' => ['waze', 'Waze', $wazeUrl], 'maps' => ['gmaps', 'Google 地圖 Maps', $mapsUrl]] as $qk => [$qIcon, $qName, $qUrl]) {
    $qImg = $event[$qk . '_qr_path'] ?? null;
    if ($qImg || $qUrl !== '') {
        $qrCodes[] = ['key' => $qk, 'icon' => $qIcon, 'name' => $qName, 'img' => $qImg, 'url' => $qUrl];
    }
}
$autoQr = (bool) array_filter($qrCodes, static fn($q) => !$q['img']);
?>

<main id="main">

<?php
// Banner slideshow (System → 首頁橫幅). With no slides yet, an old event
// banner is shown as a single picture.
$bannerSlides = (new App\Models\Banner())->active();
if (!$bannerSlides && $siteHeroBanner) {
    $bannerSlides = (new App\Models\Banner())->withSizes([['id' => 0, 'image_path' => $siteHeroBanner, 'img_w' => null, 'img_h' => null,
        'pos_x' => 50, 'pos_y' => 50, 'zoom' => 100, 'caption_zh' => null, 'caption_en' => null, 'link_url' => null]]);
}
?>
<div id="liveBanner" data-live="site_banners settings">
<?php if ($bannerSlides): ?>
  <?php $bannerSite = $site; $bannerAlt = $siteName; require BASE_PATH . '/app/Views/partials/banner_show.php'; ?>
<?php else: ?>
  <div class="hero-fallback">
    <h1><?= h($siteName) ?></h1>
    <?php if (($site['site_name_en'] ?? '') !== ''): ?><p><?= h($site['site_name_en']) ?></p><?php endif; ?>
  </div>
<?php endif; ?>
</div>


<!-- ============ Welcome ============ -->
<section class="section narrow welcome" id="liveWelcome" data-live="events settings">
  <div class="year"><?= h($event['year']) ?><?= !empty($event['year_label']) ? ' ' . h($event['year_label']) : '' ?></div>
  <h1><?= h($event['name']) ?></h1>
  <?php if (!empty($event['subtitle'])): ?>
    <p class="subtitle"><?= h($event['subtitle']) ?></p>
  <?php endif; ?>
  <?php
  // The date line is written for you from the event's dates; the admin
  // can replace either language in Event details (e.g. "農曆九月初七至初九").
  $dateZh = trim((string) ($event['date_text_zh'] ?? ''));
  $dateEn = trim((string) ($event['date_text_en'] ?? ''));
  if ($dateZh === '' && $dateLines) {
      $dateZh = $dateLines[0] . (count($dateLines) > 1 ? ' ' . t('home.days', 'zh', ['n' => count($dateLines)]) : '');
  }
  if ($dateEn === '' && $dateRange) {
      $dateEn = $dateRange . ' ' . $event['year'];
  }
  ?>
  <?php if ($dateZh !== '' || $dateEn !== ''): ?>
    <div class="dates"><?= icon('calendar') ?> <?= h($dateZh) ?>
      <?php if ($dateEn !== ''): ?><span class="en"><?= h($dateEn) ?></span><?php endif; ?></div>
  <?php endif; ?>

  <?php if (!empty($event['welcome_zh']) || !empty($event['welcome_en'])): ?>
    <div class="welcome-text">
      <?= h($event['welcome_zh'] ?? '') ?>
      <?php if (!empty($event['welcome_en'])): ?><span class="en"><?= h($event['welcome_en']) ?></span><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="welcome-text"><?= tb('home.welcome') ?></div>
  <?php endif; ?>

  <div class="cta-grid">
    <a class="cta" href="<?= url('/register') ?>">
      <span class="icon"><?= icon('register') ?></span>
      <strong><?= h(t('home.cta_register')) ?></strong>
      <span><?= h($rsvpWindow['open'] ? t('home.cta_register', 'en') : ($rsvpWindow['reason'] === 'not_yet' ? t('home.opening_soon') . ' ' . t('home.opening_soon', 'en') : t('home.closed') . ' ' . t('home.closed', 'en'))) ?></span>
    </a>
    <a class="cta" href="<?= url('/donate') ?>">
      <span class="icon"><?= icon('donate') ?></span>
      <strong><?= h(t('home.cta_donate')) ?></strong>
      <span><?= h($donationWindow['open'] ? t('home.cta_donate', 'en') : ($donationWindow['reason'] === 'not_yet' ? t('home.opening_soon') . ' ' . t('home.opening_soon', 'en') : t('home.closed') . ' ' . t('home.closed', 'en'))) ?></span>
    </a>
  </div>
</section>

<!-- ============ Event information ============ -->
<section class="section" id="info" data-live="events settings">
  <div class="section-title">
    <h2><?= tb('home.info') ?></h2>
  </div>

  <div class="info-grid">
    <div class="card info-card">
      <h3><?= icon('calendar') ?> <?= tb('home.date') ?></h3>
      <?php if (trim((string) ($event['date_text_zh'] ?? '')) !== ''): ?>
        <p><?= h($event['date_text_zh']) ?></p>
      <?php else: ?>
        <p><?php foreach ($dateLines as $i => $line): ?><?= $i ? "\n" : '' ?><?= h($line) ?><?php endforeach; ?></p>
      <?php endif; ?>
      <?php if ($dateEn !== ''): ?><p class="help"><?= h($dateEn) ?></p><?php endif; ?>
    </div>
    <div class="card info-card">
      <h3><?= icon('map-pin') ?> <?= tb('home.venue') ?></h3>
      <p><?= h($event['location']) ?></p>
    </div>
    <div class="card info-card">
      <h3><?= icon('phone') ?> <?= tb('home.enquiry') ?></h3>
      <p><?= h(!empty($event['counter_note']) ? $event['counter_note'] : t('home.enquiry_text')) ?></p>
      <?php if (!empty($event['contact_info'])): ?>
        <p class="help"><?= icon('phone') ?> <?= h($event['contact_info']) ?></p>
      <?php endif; ?>
      <?php if (t('home.enquiry_text', 'en') !== ''): ?><p class="help"><?= h(t('home.enquiry_text', 'en')) ?></p><?php endif; ?>
    </div>
  </div>

  <?php if ($showMap): ?>
    <div class="card location-card">
      <div>
        <h3 class="loc-title"><?= icon('car') ?> <?= tb('home.directions') ?></h3>
        <p style="margin:.5rem 0 0"><?= tb('home.directions_text') ?></p>
        <div class="map-buttons">
          <?php if ($wazeUrl !== ''): ?>
            <a class="btn waze" href="<?= h($wazeUrl) ?>" target="_blank" rel="noopener"><span class="brand-ic"><?= icon('waze') ?></span> <?= h(t('home.waze')) ?></a>
          <?php endif; ?>
          <?php if ($mapsUrl !== ''): ?>
            <a class="btn ghost" href="<?= h($mapsUrl) ?>" target="_blank" rel="noopener"><span class="brand-ic"><?= icon('gmaps') ?></span> <?= h(t('home.maps') . ' ' . t('home.maps', 'en')) ?></a>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($qrCodes): ?>
        <div class="qr-pair<?= count($qrCodes) > 1 ? ' is-two' : '' ?>">
          <?php foreach ($qrCodes as $q): ?>
            <div class="qr-frame qr-<?= $q['key'] ?>">
              <?php if ($q['img']): ?>
                <img src="<?= h(BASE_URL . '/' . $q['img']) ?>" alt="<?= h($q['name']) ?> QR Code">
              <?php else: ?>
                <canvas class="qr-auto" data-url="<?= h($q['url']) ?>" aria-label="<?= h($q['name']) ?> QR Code"></canvas>
              <?php endif; ?>
              <span class="qr-name"><span class="brand-mini"><?= icon($q['icon']) ?></span> <?= h($q['name']) ?></span>
              <small><?= $q['key'] === 'waze' ? h(t('home.scan') . ' ' . t('home.scan', 'en')) : '掃描開啟地圖 Scan to open the map' ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<div id="liveNews" data-live="posts settings">
<?php if ($posts): ?>
<!-- ============ News: one row of cards, swipe or use the arrows — like the albums ============ -->
<section class="section" id="news">
  <div class="section-title">
    <h2><?= tb('home.news') ?></h2>
  </div>
  <div class="album-row news-row">
    <button type="button" class="album-nav prev" aria-label="上一則 Previous" data-dir="-1"><?= icon('chevron-left') ?></button>
    <div class="album-strip news-strip" tabindex="0" aria-label="<?= h(t('home.news') . ' ' . t('home.news', 'en')) ?>">
      <?php foreach ($posts as $post): ?>
        <article class="card post news-card">
          <?php if (!empty($post['image_path'])): ?>
            <div class="post-media">
              <?= photo_img($post['image_path'], 'news', array_merge(['class' => 'post-image', 'alt' => $post['title_zh'], 'loading' => 'lazy'],
                  App\Core\Media::guarded('news')
                      ? ['data-lightbox' => App\Core\Media::url($post['image_path'], true), 'data-seed' => (string) App\Core\Media::seed($post['image_path'])]
                      : ['data-lightbox' => media_url($post['image_path'])])) ?>
              <span class="zoom-hint" aria-hidden="true"><?= icon('search') ?></span>
            </div>
          <?php endif; ?>
          <div class="post-head">
            <div class="post-avatar">
              <?php if ($siteLogo): ?><img src="<?= h($uploadUrl($siteLogo)) ?>" alt=""><?php else: ?><?= h(mb_substr($siteName, 0, 1)) ?><?php endif; ?>
            </div>
            <div class="post-meta">
              <strong><?= h($siteName) ?></strong>
              <span><?= h(date('Y-m-d', strtotime($post['created_at']))) ?></span>
            </div>
            <?php if ($post['is_pinned']): ?><span class="pin"><?= icon('pin') ?> <?= h(t('home.pinned') . ' ' . t('home.pinned', 'en')) ?></span><?php endif; ?>
          </div>
          <div class="post-body">
            <h3><?= h($post['title_zh']) ?><?php if (!empty($post['title_en'])): ?><span class="en"><?= h($post['title_en']) ?></span><?php endif; ?></h3>
            <div class="post-text">
              <?php if (!empty($post['body_zh'])): ?><p><?= h($post['body_zh']) ?></p><?php endif; ?>
              <?php if (!empty($post['body_en'])): ?><p class="en"><?= h($post['body_en']) ?></p><?php endif; ?>
            </div>
            <button type="button" class="read-more" hidden><?= h(t('home.read_more') . ' ' . t('home.read_more', 'en')) ?></button>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <button type="button" class="album-nav next" aria-label="下一則 Next" data-dir="1"><?= icon('chevron-right') ?></button>
  </div>
</section>
<?php endif; ?>
</div>

<!-- A whole post, opened from "read more" (outside the live part, so it survives updates) -->
<dialog class="post-dialog" id="postDialog" aria-label="<?= h(t('home.news')) ?>">
  <button type="button" class="post-dialog-close" aria-label="關閉 Close">×</button>
  <div class="post-dialog-body"></div>
</dialog>

<div id="livePhotos" data-live="event_photos events settings">
<?php if ($albums): ?>
<!-- ============ Photo albums, one row per year ============ -->
<section class="section" id="photos">
  <div class="section-title">
    <h2><?= tb('home.photos') ?></h2>
    <p><?= tb('home.photos_hint') ?></p>
  </div>
  <?php foreach ($albums as $album): ?>
    <?php $albumLink = url('/gallery') . '#album-' . (int) $album['id']; ?>
    <?php require BASE_PATH . '/app/Views/partials/album.php'; ?>
  <?php endforeach; ?>
  <div style="text-align:center">
    <a class="btn ghost" href="<?= url('/gallery') ?>"><?= icon('image') ?> <?= h(t('home.all_albums') . ' ' . t('home.all_albums', 'en')) ?></a>
  </div>
</section>
<?php endif; ?>
</div>


</main>

<?php require BASE_PATH . '/app/Views/partials/modal.php'; ?>
<?php require BASE_PATH . '/app/Views/partials/lightbox.php'; ?>

<script>
// News cards show the first few lines; "read more" appears only on the
// cards whose text is actually cut off, and opens the whole post.
// Runs again on cards that arrive with a realtime update (live:swap).
(function () {
  var dlg = document.getElementById('postDialog');
  var body = dlg.querySelector('.post-dialog-body');
  function bind(root) {
    root.querySelectorAll('.news-card:not([data-bound])').forEach(function (card) {
      card.setAttribute('data-bound', '');
      var text = card.querySelector('.post-text'), more = card.querySelector('.read-more');
      if (text.scrollHeight > text.clientHeight + 4) more.hidden = false;
      more.addEventListener('click', function () {
        body.innerHTML = '';
        var copy = card.cloneNode(true);
        copy.classList.add('is-full');
        copy.querySelector('.read-more').remove();
        body.appendChild(copy);
        // A copied <canvas> (protected picture) arrives blank: paint it again.
        var src = card.querySelectorAll('canvas'), dst = copy.querySelectorAll('canvas');
        Array.prototype.forEach.call(dst, function (c, i) {
          if (src[i] && src[i].classList.contains('is-drawn') && window.TYTPhoto) TYTPhoto.copy(src[i], c);
          else if (window.TYTPhoto && c.dataset.src) TYTPhoto.draw(c, c.dataset.src, c.dataset.scr);
        });
        if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', '');
      });
    });
  }
  bind(document);
  document.addEventListener('live:swap', function (e) { bind(e.detail); });
  dlg.querySelector('.post-dialog-close').addEventListener('click', function () { dlg.close ? dlg.close() : dlg.removeAttribute('open'); });
  dlg.addEventListener('click', function (e) { if (e.target === dlg && dlg.close) dlg.close(); });
})();
</script>

<?php if ($autoQr): ?>
<script src="<?= asset('js/qrcode.min.js') ?>"></script>
<script>
// No uploaded QR image for a service, so draw one from its link. Same
// result for the visitor, and nothing to regenerate if the link changes.
(function () {
  function draw() {
    if (!window.TYTQRCode) return;
    document.querySelectorAll('canvas.qr-auto:not([data-drawn])').forEach(function (canvas) {
      canvas.dataset.drawn = '1';
      window.TYTQRCode.toCanvas(canvas, canvas.dataset.url, { width: 220, margin: 1, errorCorrectionLevel: 'M' })
        .catch(function () { canvas.closest('.qr-frame').style.display = 'none'; });
    });
  }
  draw();
  document.addEventListener('live:swap', draw);   // the info part was refreshed
})();
</script>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
