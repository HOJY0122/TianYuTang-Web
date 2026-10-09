<?php
/**
 * Public gallery, one layer at a time (GalleryController):
 *   年份 years  →  類別 albums of that year  →  相片 photos (tap to enlarge)
 * with a breadcrumb back up. Quiet cards: one cover photo, a name, a count.
 */
$pageTitle = '相簿 Gallery';
require BASE_PATH . '/app/Views/layouts/header.php';
$galleryUrl = url('/gallery');
$countText  = static fn(string $key, int $n): string => trim(t($key, 'zh', ['n' => $n]) . ' · ' . t($key, 'en', ['n' => $n]), ' ·');
$albumName  = static fn(array $a): array => (int) $a['cat_id'] === 0
    ? [t('gallery.others'), t('gallery.others', 'en')] : [(string) $a['name_zh'], (string) ($a['name_en'] ?? '')];
?>

<main id="main">
<section class="section gallery-page" id="liveGallery" data-live="event_photos events settings photo_categories">
  <div class="section-title">
    <h2><?= tb('gallery.title') ?></h2>
    <p><?= $album ? tb('gallery.album_hint') : ($year !== '' ? tb('gallery.year_hint') : tb('gallery.hint')) ?></p>
  </div>

  <?php if (!$years): ?>
    <div class="card closed-card">
      <div class="closed-icon"><?= icon('camera') ?></div>
      <h3><?= tb('gallery.empty_title') ?></h3>
      <p><?= h(t('gallery.empty_text') . ' ' . t('gallery.empty_text', 'en')) ?></p>
    </div>
  <?php else: ?>

    <?php if ($year !== ''): ?>
      <nav class="g-crumbs" aria-label="位置 You are here">
        <a href="<?= $galleryUrl ?>"><?= icon('chevron-left') ?> 全部年份 <span class="en">All years</span></a>
        <span aria-hidden="true">›</span>
        <?php if ($album): ?>
          <a href="<?= $galleryUrl ?>?year=<?= h(urlencode($year)) ?>&amp;cat="><?= h($year) ?></a>
          <span aria-hidden="true">›</span>
          <?php [$_az, $_ae] = $albumName($album); ?>
          <strong aria-current="page"><?= h($_az) ?><?php if ($_ae !== ''): ?> <span class="en"><?= h($_ae) ?></span><?php endif; ?></strong>
        <?php else: ?>
          <strong aria-current="page"><?= h($year) ?></strong>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

    <?php if ($year === ''): ?>
      <?php // ① Years ?>
      <div class="g-grid">
        <?php foreach ($years as $y): ?>
          <a class="g-card" href="<?= $galleryUrl ?>?year=<?= h(urlencode((string) $y['year'])) ?>">
            <span class="g-cover"><?php if ($y['cover']): ?><?= photo_img($y['cover']['thumb_path'], 'albums', ['alt' => '', 'loading' => 'lazy']) ?><?php endif; ?></span>
            <span class="g-text">
              <strong class="g-year"><?= h($y['year']) ?><?php if (!empty($y['year_label'])): ?> <small><?= h($y['year_label']) ?></small><?php endif; ?></strong>
              <span class="g-count"><?= h($countText('gallery.albums', (int) $y['album_count'])) ?> · <?= h($countText('gallery.count', (int) $y['photo_count'])) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>

    <?php elseif (!$album): ?>
      <?php // ② The year's albums (categories) ?>
      <div class="g-grid">
        <?php foreach ($albums as $a): [$_az, $_ae] = $albumName($a); ?>
          <a class="g-card" href="<?= $galleryUrl ?>?year=<?= h(urlencode($year)) ?>&amp;cat=<?= (int) $a['cat_id'] ?>">
            <span class="g-cover"><?php if ($a['cover']): ?><?= photo_img($a['cover']['thumb_path'], 'albums', ['alt' => '', 'loading' => 'lazy']) ?><?php endif; ?></span>
            <span class="g-text">
              <strong class="g-name"><?= h($_az) ?></strong>
              <?php if ($_ae !== ''): ?><span class="en"><?= h($_ae) ?></span><?php endif; ?>
              <span class="g-count"><?= h($countText('gallery.count', (int) $a['photo_count'])) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>

    <?php else: ?>
      <?php // ③ The album's photos ?>
      <div class="album album-open">
        <div class="album-strip album-grid" aria-label="<?= h($year . ' ' . $albumName($album)[0]) ?>">
          <?php foreach ($photos as $photo): ?>
            <figure <?= photo_full_attrs($photo['file_path'], 'albums') ?> data-caption="<?= h($photo['caption'] ?? '') ?>">
              <?= photo_img($photo['thumb_path'], 'albums', ['alt' => $photo['caption'] ?: ($year . ' ' . $albumName($album)[0]), 'loading' => 'lazy']) ?>
              <?php if (!empty($photo['caption'])): ?><figcaption><?= h($photo['caption']) ?></figcaption><?php endif; ?>
            </figure>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
</main>

<?php require BASE_PATH . '/app/Views/partials/lightbox.php'; ?>
<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
