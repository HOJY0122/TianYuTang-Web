<?php
/**
 * Public gallery — every year's album, newest first. Each album is a
 * row of four photos that swipes (phone) or pages with arrows.
 */
$pageTitle = '相簿 Gallery';
require BASE_PATH . '/app/Views/layouts/header.php';
?>

<main id="main">
<section class="section">
  <div class="section-title">
    <h2>相簿回顧<span class="en">Photo Albums</span></h2>
    <p>歷年活動留影，左右滑動看更多，點一下放大。<span class="en">Photos from every year. Swipe for more, tap to enlarge.</span></p>
  </div>

  <?php if (!$albums): ?>
    <div class="card closed-card">
      <div class="closed-icon">📷</div>
      <h3>相簿準備中<span class="en">Photos coming soon</span></h3>
      <p>活動後將上傳精彩留影，敬請期待。Photos will be added after the event.</p>
    </div>
  <?php else: ?>
    <?php if (count($albums) > 1): ?>
      <p style="text-align:center">
        <?php foreach ($albums as $album): ?>
          <a class="btn ghost" style="margin:.2rem" href="#album-<?= (int) $album['id'] ?>"><?= h($album['year']) ?></a>
        <?php endforeach; ?>
      </p>
    <?php endif; ?>
    <?php foreach ($albums as $album): ?>
      <?php $albumLink = null; require BASE_PATH . '/app/Views/partials/album.php'; ?>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
</main>

<?php require BASE_PATH . '/app/Views/partials/lightbox.php'; ?>
<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
