<?php
/**
 * One photo album: a year heading, then a row showing four photos at a
 * time that swipes (phone) or scrolls with big arrow buttons (computer).
 *
 * Expects: $album — a row from Photo::albums(), with ['photos'].
 * Optional: $albumLink — "view all" URL shown in the heading.
 * Clicking a photo opens the shared lightbox (partials/lightbox.php).
 */
$albumLink = $albumLink ?? null;
?>
<div class="album" id="album-<?= (int) $album['id'] ?>">
  <div class="album-head">
    <h3>
      <?php // Digits in a plain, even font — brush fonts draw numbers unevenly. ?>
      <span class="album-year"><?= h($album['year']) ?></span>
      <?php if (!empty($album['year_label'])): ?><span class="album-ganzhi"><?= h($album['year_label']) ?></span><?php endif; ?>
      <small><?= h($album['name']) ?></small>
    </h3>
    <?php if ($albumLink): ?>
      <?php // Wording: 網站文字 → 相簿 Gallery (gallery.view_all / gallery.count). ?>
      <?php $albumN = ['n' => (int) $album['photo_count']]; ?>
      <a class="album-all" href="<?= h($albumLink) ?>"><?= h(trim(t('gallery.view_all', 'zh', $albumN) . ' · ' . t('gallery.view_all', 'en', $albumN), ' ·')) ?></a>
    <?php else: ?>
      <span class="help"><?= h(trim(t('gallery.count', 'zh', ['n' => (int) $album['photo_count']]) . ' ' . t('gallery.count', 'en', ['n' => (int) $album['photo_count']]))) ?></span>
    <?php endif; ?>
  </div>

  <div class="album-row">
    <button type="button" class="album-nav prev" aria-label="上一組 Previous" data-dir="-1">&#10094;</button>
    <div class="album-strip" tabindex="0" aria-label="<?= h($album['year']) ?> 相片 photos">
      <?php foreach ($album['photos'] as $photo): ?>
        <figure data-full="<?= h(BASE_URL . '/' . $photo['file_path']) ?>"
                data-caption="<?= h($photo['caption'] ?? '') ?>">
          <img src="<?= h(BASE_URL . '/' . $photo['thumb_path']) ?>"
               alt="<?= h($photo['caption'] ?: ($album['year'] . ' 活動留影')) ?>" loading="lazy">
          <?php if (!empty($photo['caption'])): ?>
            <figcaption><?= h($photo['caption']) ?></figcaption>
          <?php endif; ?>
        </figure>
      <?php endforeach; ?>
    </div>
    <button type="button" class="album-nav next" aria-label="下一組 Next" data-dir="1">&#10095;</button>
  </div>
</div>
