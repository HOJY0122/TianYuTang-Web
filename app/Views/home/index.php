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
$showMap    = $hasQrImage || $wazeUrl !== '' || $mapsUrl !== '';
?>

<main id="main">

<?php if ($siteHeroBanner): ?>
  <!-- The banner is shown whole: never faded, never cropped. -->
  <div class="banner">
    <img src="<?= h($uploadUrl($siteHeroBanner)) ?>" alt="<?= h($siteName) ?>">
  </div>
<?php else: ?>
  <div class="hero-fallback">
    <h1><?= h($siteName) ?></h1>
    <?php if (($site['site_name_en'] ?? '') !== ''): ?><p><?= h($site['site_name_en']) ?></p><?php endif; ?>
  </div>
<?php endif; ?>

<!-- ============ Welcome ============ -->
<section class="section narrow welcome">
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
    <div class="dates">📅 <?= h($dateZh) ?>
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
      <span class="icon">📝</span>
      <strong><?= h(t('home.cta_register')) ?></strong>
      <span><?= h($rsvpWindow['open'] ? t('home.cta_register', 'en') : ($rsvpWindow['reason'] === 'not_yet' ? t('home.opening_soon') . ' ' . t('home.opening_soon', 'en') : t('home.closed') . ' ' . t('home.closed', 'en'))) ?></span>
    </a>
    <a class="cta" href="<?= url('/donate') ?>">
      <span class="icon">🙏</span>
      <strong><?= h(t('home.cta_donate')) ?></strong>
      <span><?= h($donationWindow['open'] ? t('home.cta_donate', 'en') : ($donationWindow['reason'] === 'not_yet' ? t('home.opening_soon') . ' ' . t('home.opening_soon', 'en') : t('home.closed') . ' ' . t('home.closed', 'en'))) ?></span>
    </a>
  </div>
</section>

<!-- ============ Event information ============ -->
<section class="section" id="info">
  <div class="section-title">
    <h2><?= tb('home.info') ?></h2>
  </div>

  <div class="info-grid">
    <div class="card info-card">
      <h3><?= tb('home.date') ?></h3>
      <?php if (trim((string) ($event['date_text_zh'] ?? '')) !== ''): ?>
        <p><?= h($event['date_text_zh']) ?></p>
      <?php else: ?>
        <p><?php foreach ($dateLines as $i => $line): ?><?= $i ? "\n" : '' ?><?= h($line) ?><?php endforeach; ?></p>
      <?php endif; ?>
      <?php if ($dateEn !== ''): ?><p class="help"><?= h($dateEn) ?></p><?php endif; ?>
    </div>
    <div class="card info-card">
      <h3><?= tb('home.venue') ?></h3>
      <p><?= h($event['location']) ?></p>
    </div>
    <div class="card info-card">
      <h3><?= tb('home.enquiry') ?></h3>
      <p><?= h(!empty($event['counter_note']) ? $event['counter_note'] : t('home.enquiry_text')) ?></p>
      <?php if (!empty($event['contact_info'])): ?>
        <p class="help">📞 <?= h($event['contact_info']) ?></p>
      <?php endif; ?>
      <?php if (t('home.enquiry_text', 'en') !== ''): ?><p class="help"><?= h(t('home.enquiry_text', 'en')) ?></p><?php endif; ?>
    </div>
  </div>

  <?php if ($showMap): ?>
    <div class="card location-card">
      <div>
        <h3 class="loc-title"><?= tb('home.directions') ?></h3>
        <p style="margin:.5rem 0 0"><?= tb('home.directions_text') ?></p>
        <div class="map-buttons">
          <?php if ($wazeUrl !== ''): ?>
            <a class="btn waze" href="<?= h($wazeUrl) ?>" target="_blank" rel="noopener"><?= h(t('home.waze')) ?></a>
          <?php endif; ?>
          <?php if ($mapsUrl !== ''): ?>
            <a class="btn ghost" href="<?= h($mapsUrl) ?>" target="_blank" rel="noopener"><?= h(t('home.maps') . ' ' . t('home.maps', 'en')) ?></a>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($hasQrImage || $wazeUrl !== ''): ?>
        <div class="qr-frame">
          <?php if ($hasQrImage): ?>
            <img src="<?= h(BASE_URL . '/' . $event['waze_qr_path']) ?>" alt="Waze QR Code">
          <?php else: ?>
            <canvas id="wazeQr" aria-label="Waze QR Code"></canvas>
          <?php endif; ?>
          <small><?= h(t('home.scan') . ' ' . t('home.scan', 'en')) ?></small>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($posts): ?>
<!-- ============ News feed ============ -->
<section class="section" id="news">
  <div class="section-title">
    <h2><?= tb('home.news') ?></h2>
  </div>
  <div class="feed">
    <?php foreach ($posts as $post): ?>
      <article class="card post">
        <div class="post-head">
          <div class="post-avatar">
            <?php if ($siteLogo): ?><img src="<?= h($uploadUrl($siteLogo)) ?>" alt=""><?php else: ?><?= h(mb_substr($siteName, 0, 1)) ?><?php endif; ?>
          </div>
          <div class="post-meta">
            <strong><?= h($siteName) ?></strong>
            <span><?= h(date('Y-m-d', strtotime($post['created_at']))) ?></span>
          </div>
          <?php if ($post['is_pinned']): ?><span class="pin"><?= h(t('home.pinned') . ' ' . t('home.pinned', 'en')) ?></span><?php endif; ?>
        </div>
        <div class="post-body">
          <h3><?= h($post['title_zh']) ?><?php if (!empty($post['title_en'])): ?><span class="en"><?= h($post['title_en']) ?></span><?php endif; ?></h3>
          <?php if (!empty($post['body_zh'])): ?><p><?= h($post['body_zh']) ?></p><?php endif; ?>
          <?php if (!empty($post['body_en'])): ?><p class="en"><?= h($post['body_en']) ?></p><?php endif; ?>
        </div>
        <?php if (!empty($post['image_path'])): ?>
          <div class="post-media">
            <img class="post-image" src="<?= h(BASE_URL . '/' . $post['image_path']) ?>"
                 data-lightbox="<?= h(BASE_URL . '/' . $post['image_path']) ?>"
                 alt="<?= h($post['title_zh']) ?>" loading="lazy">
            <span class="zoom-hint" aria-hidden="true">🔍 點擊放大 Tap to enlarge</span>
          </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

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
    <a class="btn ghost" href="<?= url('/gallery') ?>"><?= h(t('home.all_albums') . ' ' . t('home.all_albums', 'en')) ?></a>
  </div>
</section>
<?php endif; ?>

</main>

<?php require BASE_PATH . '/app/Views/partials/modal.php'; ?>
<?php require BASE_PATH . '/app/Views/partials/lightbox.php'; ?>

<?php if (!$hasQrImage && $wazeUrl !== ''): ?>
<script src="<?= asset('js/qrcode.min.js') ?>"></script>
<script>
// No uploaded QR image, so draw one from the Waze link. Same result for
// the visitor, and nothing for the admin to regenerate if the link changes.
(function () {
  var canvas = document.getElementById('wazeQr');
  if (!canvas || !window.TYTQRCode) return;
  window.TYTQRCode.toCanvas(canvas, <?= json_encode($wazeUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>, {
    width: 220, margin: 1, errorCorrectionLevel: 'M'
  }).catch(function () { canvas.parentNode.style.display = 'none'; });
})();
</script>
<?php endif; ?>

<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
