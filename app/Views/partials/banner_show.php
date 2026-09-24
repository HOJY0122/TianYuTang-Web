<?php
/**
 * The home page banner: one picture, or several taking turns.
 * Used by the home page and, as a live preview, by 首頁橫幅 Home banner.
 *
 * Expects:
 *   $bannerSlides  rows from Banner (image_path, img_w, img_h, pos_x, pos_y, zoom, caption_*, link_url)
 *   $bannerSite    site settings (banner_* keys)
 *   $bannerAlt     text for screen readers when a slide has no caption
 *   $bannerPreview true on the admin page: every slide is rendered, links are not followed
 *
 * Height: "whole picture" uses the first picture's own shape; otherwise
 * the height is a share of the width, set separately for computers and
 * phones. Each picture fills the frame and is anchored at pos_x / pos_y
 * and zoomed around that point, so the part that matters stays in view.
 */
$bannerPreview = $bannerPreview ?? false;
$first = $bannerSlides[0] ?? null;
$shape = static function (string $h) use ($first): string {
    $h = (int) $h;
    if ($h > 0) {
        return '100 / ' . $h;
    }
    return max(1, (int) ($first['img_w'] ?? 1920)) . ' / ' . max(1, (int) ($first['img_h'] ?? 640));
};
$count = count($bannerSlides);
?>
<div class="banner-show fx-<?= $bannerSite['banner_effect'] === 'slide' ? 'slide' : 'fade' ?><?= $bannerSite['banner_controls'] === '0' ? ' no-controls' : '' ?>"
     style="--ar-d:<?= h($shape((string) $bannerSite['banner_height_desktop'])) ?>;--ar-m:<?= h($shape((string) $bannerSite['banner_height_mobile'])) ?>"
     data-interval="<?= (int) $bannerSite['banner_interval'] ?>" data-count="<?= $count ?>"
     role="region" aria-roledescription="carousel" aria-label="<?= h($bannerAlt) ?>"<?= $bannerPreview ? ' data-preview' : '' ?>>
  <div class="banner-track">
    <?php foreach ($bannerSlides as $i => $s):
      $x = (int) $s['pos_x']; $y = (int) $s['pos_y']; $z = max(100, (int) $s['zoom']) / 100;
      $cap = trim((string) ($s['caption_zh'] ?? '')) !== '' || trim((string) ($s['caption_en'] ?? '')) !== '';
      $link = !$bannerPreview ? trim((string) ($s['link_url'] ?? '')) : '';
      $tag = $link !== '' ? 'a' : 'div';
    ?>
      <figure class="banner-slide<?= $i === 0 ? ' is-active' : '' ?>" data-slide="<?= (int) $s['id'] ?>"
              role="group" aria-roledescription="slide" aria-label="<?= $i + 1 ?> / <?= $count ?>"<?= $i === 0 ? '' : ' aria-hidden="true"' ?>>
        <<?= $tag ?> class="banner-frame"<?= $link !== '' ? ' href="' . h(str_starts_with($link, '/') ? url($link) : $link) . '"' : '' ?>>
          <img src="<?= h(BASE_URL . '/' . $s['image_path'] . '?v=' . substr(md5($s['image_path']), 0, 8)) ?>"
               alt="<?= h($cap ? trim(($s['caption_zh'] ?? '') . ' ' . ($s['caption_en'] ?? '')) : $bannerAlt) ?>"
               width="<?= (int) $s['img_w'] ?>" height="<?= (int) $s['img_h'] ?>"
               <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> draggable="false"
               style="object-position:<?= $x ?>% <?= $y ?>%;transform:scale(<?= $z ?>);transform-origin:<?= $x ?>% <?= $y ?>%">
          <?php if ($cap || $bannerPreview): ?>
            <figcaption<?= $cap ? '' : ' hidden' ?>>
              <strong><?= h($s['caption_zh'] ?? '') ?></strong>
              <span><?= h($s['caption_en'] ?? '') ?></span>
            </figcaption>
          <?php endif; ?>
        </<?= $tag ?>>
      </figure>
    <?php endforeach; ?>
  </div>
  <?php if ($count > 1 || $bannerPreview): ?>
    <button type="button" class="banner-nav prev" aria-label="上一張 Previous">‹</button>
    <button type="button" class="banner-nav next" aria-label="下一張 Next">›</button>
    <div class="banner-dots" role="tablist" aria-label="選擇圖片 Choose picture">
      <?php for ($i = 0; $i < $count; $i++): ?>
        <button type="button" role="tab" aria-label="第 <?= $i + 1 ?> 張 Picture <?= $i + 1 ?>"<?= $i === 0 ? ' aria-selected="true"' : '' ?>></button>
      <?php endfor; ?>
    </div>
    <button type="button" class="banner-pause" aria-label="暫停 Pause" aria-pressed="false">⏸</button>
  <?php endif; ?>
</div>
