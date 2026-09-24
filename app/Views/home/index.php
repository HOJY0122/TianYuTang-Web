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
  <?php if ($dateLines): ?>
    <div class="dates">📅 <?= h($dateLines[0]) ?><?= count($dateLines) > 1 ? ' <span class="nw">起 · ' . count($dateLines) . ' 天</span>' : '' ?>
      <?php if ($dateRange): ?><span class="en"><?= h($dateRange . ' ' . $event['year']) ?></span><?php endif; ?></div>
  <?php endif; ?>

  <?php if (!empty($event['welcome_zh']) || !empty($event['welcome_en'])): ?>
    <div class="welcome-text">
      <?= h($event['welcome_zh'] ?? '') ?>
      <?php if (!empty($event['welcome_en'])): ?><span class="en"><?= h($event['welcome_en']) ?></span><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="welcome-text">
      誠邀十方善信共襄盛舉，同結善緣，共種福田。
      <span class="en">All are warmly welcome to join us in this celebration.</span>
    </div>
  <?php endif; ?>

  <div class="cta-grid">
    <a class="cta" href="<?= url('/register') ?>">
      <span class="icon">📝</span>
      <strong>報名參加</strong>
      <span><?= $rsvpWindow['open'] ? 'Register to Attend' : ($rsvpWindow['reason'] === 'not_yet' ? '尚未開放 Opening soon' : '已截止 Closed') ?></span>
    </a>
    <a class="cta" href="<?= url('/donate') ?>">
      <span class="icon">🙏</span>
      <strong>功德布施</strong>
      <span><?= $donationWindow['open'] ? 'Make a Donation' : ($donationWindow['reason'] === 'not_yet' ? '尚未開放 Opening soon' : '已截止 Closed') ?></span>
    </a>
  </div>
</section>

<!-- ============ Event information ============ -->
<section class="section" id="info">
  <div class="section-title">
    <h2>活動資料<span class="en">Event Information</span></h2>
  </div>

  <div class="info-grid">
    <div class="card info-card">
      <h3>📅 日期<span class="en">Date</span></h3>
      <p><?php foreach ($dateLines as $i => $line): ?><?= $i ? "\n" : '' ?><?= h($line) ?><?php endforeach; ?></p>
      <?php if ($dateRange): ?><p class="help"><?= h($dateRange) ?></p><?php endif; ?>
    </div>
    <div class="card info-card">
      <h3>📍 地點<span class="en">Venue</span></h3>
      <p><?= h($event['location']) ?></p>
    </div>
    <div class="card info-card">
      <h3>🙏 現場詢問<span class="en">Enquiries</span></h3>
      <p><?= !empty($event['counter_note']) ? h($event['counter_note']) : '歡迎於活動當日親臨櫃台詢問。' ?></p>
      <?php if (!empty($event['contact_info'])): ?>
        <p class="help">📞 <?= h($event['contact_info']) ?></p>
      <?php endif; ?>
      <p class="help">Walk-in registration is available at the counter on the day.</p>
    </div>
  </div>

  <?php if ($showMap): ?>
    <div class="card location-card">
      <div>
        <h3 class="kai" style="margin:0;color:var(--red);font-size:1.4rem">🚗 如何前往<?= info_tip('用手機相機對準 QR Code，點出現的連結，就會打開 Waze 導航到會場。', 'Point your phone camera at the QR code and tap the link that appears — Waze will open with directions to the venue.') ?><span class="en">Getting there</span></h3>
        <p style="margin:.5rem 0 0">
          用手機掃描右邊的 QR Code，或按下面的按鈕開啟導航。
          <span class="en">Scan the QR code with your phone, or tap a button below to open navigation.</span>
        </p>
        <div class="map-buttons">
          <?php if ($wazeUrl !== ''): ?>
            <a class="btn waze" href="<?= h($wazeUrl) ?>" target="_blank" rel="noopener">🚙 Waze 導航</a>
          <?php endif; ?>
          <?php if ($mapsUrl !== ''): ?>
            <a class="btn ghost" href="<?= h($mapsUrl) ?>" target="_blank" rel="noopener">🗺️ Google 地圖 Maps</a>
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
          <small>掃描導航 Scan for Waze</small>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($posts): ?>
<!-- ============ News feed ============ -->
<section class="section" id="news">
  <div class="section-title">
    <h2>最新消息<span class="en">News &amp; Announcements</span></h2>
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
          <?php if ($post['is_pinned']): ?><span class="pin">📌 置頂 Pinned</span><?php endif; ?>
        </div>
        <div class="post-body">
          <h3><?= h($post['title_zh']) ?><?php if (!empty($post['title_en'])): ?><span class="en"><?= h($post['title_en']) ?></span><?php endif; ?></h3>
          <?php if (!empty($post['body_zh'])): ?><p><?= h($post['body_zh']) ?></p><?php endif; ?>
          <?php if (!empty($post['body_en'])): ?><p class="en"><?= h($post['body_en']) ?></p><?php endif; ?>
        </div>
        <?php if (!empty($post['image_path'])): ?>
          <img class="post-image" src="<?= h(BASE_URL . '/' . $post['image_path']) ?>"
               data-lightbox="<?= h(BASE_URL . '/' . $post['image_path']) ?>"
               alt="<?= h($post['title_zh']) ?>" loading="lazy">
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
    <h2>活動留影<span class="en">Photo Albums</span></h2>
    <p>左右滑動看更多相片，點一下放大。<span class="en">Swipe for more photos. Tap a photo to enlarge.</span></p>
  </div>
  <?php foreach ($albums as $album): ?>
    <?php $albumLink = url('/gallery') . '#album-' . (int) $album['id']; ?>
    <?php require BASE_PATH . '/app/Views/partials/album.php'; ?>
  <?php endforeach; ?>
  <div style="text-align:center">
    <a class="btn ghost" href="<?= url('/gallery') ?>">📸 瀏覽全部相簿 View all albums</a>
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
