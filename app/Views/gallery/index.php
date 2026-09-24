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
    <h2><?= tb('gallery.title') ?></h2>
    <p><?= tb('gallery.hint') ?></p>
  </div>

  <?php if (!$albums): ?>
    <div class="card closed-card">
      <div class="closed-icon">📷</div>
      <h3><?= tb('gallery.empty_title') ?></h3>
      <p><?= h(t('gallery.empty_text') . ' ' . t('gallery.empty_text', 'en')) ?></p>
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
