<?php
$pageTitle = '相簿回顧｜天玉堂';
require BASE_PATH . '/app/Views/layouts/header.php';
?>

<main id="gallery">
<section>
  <div class="section-title">
    <div class="eyebrow">PHOTO ARCHIVE</div>
    <h2>📸 相簿回顧</h2>
    <p>歷年法會留影，與十方善信共同回顧。</p>
  </div>

  <?php if (!$years): ?>
    <div class="form-card closed-card">
      <div class="closed-icon">📷</div>
      <h3>相簿準備中</h3>
      <p>目前尚未有相片，敬請期待活動後的精彩留影。</p>
    </div>

  <?php else: ?>

    <?php if (count($years) > 1): ?>
      <div class="year-tabs">
        <?php foreach ($years as $year): ?>
          <a class="year-tab<?= (int) $year['id'] === (int) $selectedYear['id'] ? ' active' : '' ?>"
             href="<?= url('/gallery') ?>?year=<?= (int) $year['id'] ?>">
            <?= h($year['year']) ?>
            <?php if (!empty($year['year_label'])): ?>
              <span><?= h($year['year_label']) ?></span>
            <?php endif; ?>
            <em><?= (int) $year['photo_count'] ?> 張</em>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="gallery-heading">
      <h3><?= h($selectedYear['year']) ?> <?= h($selectedYear['name']) ?></h3>
      <span class="help"><?= count($photos) ?> 張相片</span>
    </div>

    <div class="photo-grid">
      <?php foreach ($photos as $i => $photo): ?>
        <figure class="photo-item"
                data-full="<?= h(BASE_URL . '/' . $photo['file_path']) ?>"
                data-caption="<?= h($photo['caption'] ?? '') ?>"
                data-index="<?= $i ?>">
          <img src="<?= h(BASE_URL . '/' . $photo['thumb_path']) ?>"
               alt="<?= h($photo['caption'] ?? '法會留影') ?>"
               loading="lazy">
          <?php if (!empty($photo['caption'])): ?>
            <figcaption><?= h($photo['caption']) ?></figcaption>
          <?php endif; ?>
        </figure>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>
</section>
</main>

<!-- Lightbox. Hidden until a photo is clicked. -->
<div id="lightbox" class="lightbox hidden" role="dialog" aria-modal="true" aria-label="相片檢視">
  <button class="lb-close" aria-label="關閉">&times;</button>
  <button class="lb-prev" aria-label="上一張">&#10094;</button>
  <img id="lbImage" src="" alt="">
  <button class="lb-next" aria-label="下一張">&#10095;</button>
  <div id="lbCaption" class="lb-caption"></div>
</div>

<script>
(function () {
  const items = Array.from(document.querySelectorAll('.photo-item'));
  if (!items.length) return;

  const box     = document.getElementById('lightbox');
  const image   = document.getElementById('lbImage');
  const caption = document.getElementById('lbCaption');
  let current   = 0;

  function show(index) {
    // Wrap around at both ends so the arrows never dead-end.
    current = (index + items.length) % items.length;
    const item = items[current];
    image.src = item.dataset.full;
    image.alt = item.dataset.caption || '法會留影';
    caption.textContent = item.dataset.caption || '';
    caption.style.display = item.dataset.caption ? 'block' : 'none';
    box.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  function close() {
    box.classList.add('hidden');
    document.body.style.overflow = '';
    image.src = '';               // stop a large image downloading in the background
  }

  items.forEach((item, i) => {
    item.addEventListener('click', () => show(i));
  });

  document.querySelector('.lb-close').addEventListener('click', close);
  document.querySelector('.lb-prev').addEventListener('click', e => { e.stopPropagation(); show(current - 1); });
  document.querySelector('.lb-next').addEventListener('click', e => { e.stopPropagation(); show(current + 1); });

  // Clicking the dark backdrop closes; clicking the photo itself does not.
  box.addEventListener('click', e => { if (e.target === box) close(); });

  document.addEventListener('keydown', e => {
    if (box.classList.contains('hidden')) return;
    if (e.key === 'Escape')     close();
    if (e.key === 'ArrowLeft')  show(current - 1);
    if (e.key === 'ArrowRight') show(current + 1);
  });
})();
</script>

<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
