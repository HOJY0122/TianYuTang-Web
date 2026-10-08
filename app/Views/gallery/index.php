<?php
/**
 * Public gallery — every year's album, newest first. Each album is a
 * row of four photos that swipes (phone) or pages with arrows.
 */
$pageTitle = '相簿 Gallery';
require BASE_PATH . '/app/Views/layouts/header.php';
?>

<main id="main">
<section class="section" id="liveGallery" data-live="event_photos events settings">
  <div class="section-title">
    <h2><?= tb('gallery.title') ?></h2>
    <p><?= tb('gallery.hint') ?></p>
  </div>

  <?php if (!$albums): ?>
    <div class="card closed-card">
      <div class="closed-icon"><?= icon('camera') ?></div>
      <h3><?= tb('gallery.empty_title') ?></h3>
      <p><?= h(t('gallery.empty_text') . ' ' . t('gallery.empty_text', 'en')) ?></p>
    </div>
  <?php else: ?>
    <?php if (count($years) > 1): ?>
      <nav class="year-filter" aria-label="選擇年份 Choose a year">
        <a href="<?= url('/gallery') ?>"<?= $year === '' ? ' class="is-on" aria-current="page"' : '' ?>><?= icon('list') ?> 全部 <span>All</span></a>
        <?php foreach ($years as $y): ?>
          <a href="<?= url('/gallery') ?>?year=<?= h(urlencode($y)) ?>"<?= $year === $y ? ' class="is-on" aria-current="page"' : '' ?>><?= h($y) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
    <?php foreach ($albums as $album): ?>
      <?php $albumLink = null; require BASE_PATH . '/app/Views/partials/album.php'; ?>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
</main>

<?php require BASE_PATH . '/app/Views/partials/lightbox.php'; ?>
<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
