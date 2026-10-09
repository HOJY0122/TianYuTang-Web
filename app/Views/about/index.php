<?php
/**
 * 關於我們 About us — the blocks written in System → About page, top to
 * bottom: big centred pictures (tap to zoom) and headings with
 * paragraphs. Pictures follow the About photo-protection switch (Forms & fonts).
 */
$pageTitle = t('about.title') . ' ' . t('about.title', 'en');
require BASE_PATH . '/app/Views/layouts/header.php';
// Paragraphs: a blank line starts a new one; single line breaks are kept.
$paras = static function (?string $text, string $class = ''): string {
    $out = '';
    foreach (preg_split('/\n\s*\n/', trim((string) $text)) as $p) {
        if (trim($p) !== '') {
            $out .= '<p' . ($class !== '' ? ' class="' . $class . '"' : '') . '>' . nl2br(h(trim($p))) . '</p>';
        }
    }
    return $out;
};
?>
<main id="main">
<section class="section about-page" id="liveAbout" data-live="about_blocks settings">
  <div class="section-title">
    <h2><?= tb('about.title') ?></h2>
    <?php if (t('about.sub') !== '' || t('about.sub', 'en') !== ''): ?><p><?= tb('about.sub') ?></p><?php endif; ?>
  </div>

  <?php foreach ($blocks as $b):
      $cls = 'about-block w-' . $b['width'] . ' ts-' . $b['text_size'] . ' al-' . $b['align'];
      $hasText = $b['body_zh'] || $b['body_en']; ?>
    <?php if ($b['kind'] === 'image' && $b['image_path']): ?>
      <?php // Picture, caption and its text: one card, one width. ?>
      <article class="<?= $cls ?> about-card about-card-img">
        <div class="album-strip about-strip">
          <figure class="about-figure" <?= photo_full_attrs($b['image_path'], 'about') ?>
                  data-caption="<?= h(trim(($b['heading_zh'] ?? '') . ' ' . ($b['heading_en'] ?? ''))) ?>">
            <?= photo_img($b['image_path'], 'about', ['alt' => $b['heading_zh'] ?: ($b['heading_en'] ?: t('about.title')), 'loading' => 'lazy', 'class' => 'about-img']) ?>
          </figure>
        </div>
        <?php if ($b['heading_zh'] || $b['heading_en'] || $hasText): ?>
          <div class="about-card-text">
            <?php if ($b['heading_zh'] || $b['heading_en']): ?>
              <h3 class="about-caption"><?= h($b['heading_zh'] ?? '') ?><?php if ($b['heading_en']): ?><span class="en"><?= h($b['heading_en']) ?></span><?php endif; ?></h3>
            <?php endif; ?>
            <?php if ($hasText): ?><div class="about-body"><?= $paras($b['body_zh']) ?><?= $paras($b['body_en'], 'en') ?></div><?php endif; ?>
          </div>
        <?php endif; ?>
      </article>
    <?php else: ?>
      <article class="<?= $cls ?> about-card">
        <?php if ($b['heading_zh'] || $b['heading_en']): ?>
          <h3 class="about-caption"><?= h($b['heading_zh'] ?? '') ?><?php if ($b['heading_en']): ?><span class="en"><?= h($b['heading_en']) ?></span><?php endif; ?></h3>
        <?php endif; ?>
        <div class="about-body"><?= $paras($b['body_zh']) ?><?= $paras($b['body_en'], 'en') ?></div>
      </article>
    <?php endif; ?>
  <?php endforeach; ?>
</section>
</main>
<?php require BASE_PATH . '/app/Views/partials/lightbox.php'; ?>
<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
