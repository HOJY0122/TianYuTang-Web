<?php
/**
 * System → 關於我們 About page. The page is a column of blocks: big
 * centred pictures (the main deity's poster…) and headings with
 * paragraphs. Add, edit, reorder (drag or ↑ ↓) and delete them here.
 */
require BASE_PATH . '/app/Views/layouts/admin_header.php';
$aboutImg = static fn(array $b): string => media_url($b['image_path']);
use App\Models\AboutBlock;
// 版面 Layout: width, text size and alignment of one block.
$layout = static function (?array $b): string {
    $sel = static function (string $name, array $choices, ?string $cur): string {
        $o = '';
        foreach ($choices as $v => [$zh, $en]) {
            $o .= '<option value="' . h($v) . '"' . ($cur === $v ? ' selected' : '') . '>' . h($zh . ' ' . $en) . '</option>';
        }
        return '<select name="' . $name . '">' . $o . '</select>';
    };
    return '<fieldset class="about-layout"><legend>' . icon('ruler') . ' 版面 <span class="en">Layout</span></legend>'
        . '<label>寬度 <span class="en">Width</span>' . $sel('width', AboutBlock::WIDTHS, $b['width'] ?? null) . '</label>'
        . '<label>文字大小 <span class="en">Text size</span>' . $sel('text_size', AboutBlock::TEXT_SIZES, $b['text_size'] ?? null) . '</label>'
        . '<label>對齊 <span class="en">Alignment</span>' . $sel('align', AboutBlock::ALIGNS, $b['align'] ?? null) . '</label>'
        . '</fieldset>';
};
?>
<div class="panel eventbar">
  <div>
    <h2 style="margin:0 0 4px"><?= icon('users') ?> 關於我們頁面 <span class="en">About page</span></h2>
    <div class="help"><?= count($blocks) ?> 段內容 blocks ·
      <?php if (!$blocks): ?><span class="badge pending">未有內容，網站不會顯示 Empty — not shown on the website</span>
      <?php elseif ($enabled): ?><span class="badge ok">已在網站選單顯示 Shown in the website menu</span>
      <?php else: ?><span class="badge cancelled">已隱藏 Hidden</span><?php endif; ?></div>
  </div>
  <div class="eventbar-actions">
    <form method="POST" action="<?= url('/system/about/toggle') ?>" style="margin:0">
      <?= csrf_field() ?><input type="hidden" name="to" value="<?= $enabled ? 'off' : 'on' ?>">
      <button class="mini-btn ghost btn-lg" type="submit"><?= $enabled ? icon('eye-off') . ' 隱藏頁面 Hide page' : icon('eye') . ' 顯示頁面 Show page' ?></button>
    </form>
    <?php if ($blocks): ?><a class="mini-btn btn-lg" href="<?= url('/about') ?>" target="_blank"><?= icon('external') ?> 查看頁面 View page</a><?php endif; ?>
  </div>
</div>

<?php if ($blocks): ?>
<div class="panel">
  <h2 style="margin-top:0">頁面內容 <span class="en">Page content</span></h2>
  <p class="help" style="margin-top:0"><?= icon('hand') ?> 按住 ⠿ 拖到新位置可重新排列，或用 ↑ ↓。由上而下就是網站顯示的次序。
    <span class="en">Drag ⠿ to reorder, or use ↑ ↓. Top to bottom is the order visitors see.</span></p>
  <div class="about-admin" data-sortable="<?= url('/system/about/reorder') ?>" data-live="about_blocks">
    <?php foreach ($blocks as $i => $b): $bid = (int) $b['id']; ?>
      <div class="about-block panel" data-id="<?= $bid ?>" id="block<?= $bid ?>">
        <div class="about-block-head">
          <span class="drag-badge" data-drag-handle title="拖曳排序 Drag to reorder">⠿ <b data-position><?= $i + 1 ?></b></span>
          <strong><?= $b['kind'] === 'image' ? icon('image') . ' 圖片 Picture' : icon('type') . ' 文字 Text' ?></strong>
          <span class="help"><?= h(AboutBlock::WIDTHS[$b['width']][0] ?? '') ?>寬 · <?= h(AboutBlock::TEXT_SIZES[$b['text_size']][0] ?? '') ?>字 · <?= h(AboutBlock::ALIGNS[$b['align']][0] ?? '') ?></span>
          <span class="spacer"></span>
          <form method="POST" action="<?= url('/system/about/move') ?>" style="margin:0"><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $bid ?>"><input type="hidden" name="dir" value="up">
            <button class="mini-btn ghost" type="submit" title="上移 Move up"<?= $i === 0 ? ' disabled' : '' ?>>↑</button></form>
          <form method="POST" action="<?= url('/system/about/move') ?>" style="margin:0"><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $bid ?>"><input type="hidden" name="dir" value="down">
            <button class="mini-btn ghost" type="submit" title="下移 Move down"<?= $i === count($blocks) - 1 ? ' disabled' : '' ?>>↓</button></form>
          <form method="POST" action="<?= url('/system/about/delete') ?>" style="margin:0"
                data-confirm="刪除這一段？此動作無法復原。&#10;Delete this block? This cannot be undone." data-danger><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $bid ?>">
            <button class="mini-btn danger" type="submit"><?= icon('trash') ?> 刪除 Delete</button></form>
        </div>
        <form method="POST" action="<?= url('/system/about/save') ?>" enctype="multipart/form-data" class="form-panel about-edit">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $bid ?>">
          <?php if ($b['kind'] === 'image'): ?>
            <div class="about-edit-grid">
              <img class="about-admin-img" src="<?= h($aboutImg($b)) ?>" alt="">
              <div>
                <label>更換圖片 <span class="en">Replace picture (optional)</span></label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-aspects="original,3:4,2:3,1:1" data-max-width="2400">
                <label>圖片標題（中文）<span class="en">Caption (Chinese)</span></label>
                <input name="heading_zh" maxlength="120" value="<?= h($b['heading_zh'] ?? '') ?>" placeholder="例 中壇元帥">
                <label>圖片標題（英文）<span class="en">Caption (English)</span></label>
                <input name="heading_en" maxlength="160" value="<?= h($b['heading_en'] ?? '') ?>" placeholder="e.g. Marshal Zhongtan">
              </div>
            </div>
            <label>圖片下的文字（中文，選填）<span class="en">Text below the picture (Chinese, optional)</span></label>
          <?php else: ?>
            <div class="form-grid">
              <div><label>標題（中文）<span class="en">Heading (Chinese)</span></label>
                <input name="heading_zh" maxlength="120" value="<?= h($b['heading_zh'] ?? '') ?>"></div>
              <div><label>標題（英文）<span class="en">Heading (English)</span></label>
                <input name="heading_en" maxlength="160" value="<?= h($b['heading_en'] ?? '') ?>"></div>
            </div>
            <label>內容（中文）<span class="en">Paragraphs (Chinese)</span></label>
          <?php endif; ?>
          <textarea name="body_zh" rows="5" maxlength="20000" data-trad-check><?= h($b['body_zh'] ?? '') ?></textarea>
          <label>內容（英文）<span class="en">Paragraphs (English)</span></label>
          <textarea name="body_en" rows="4" maxlength="30000"><?= h($b['body_en'] ?? '') ?></textarea>
          <p class="help">空一行 = 新段落。<span class="en">Leave a blank line to start a new paragraph.</span></p>
          <?= $layout($b) ?>
          <button class="mini-btn" type="submit"><?= icon('save') ?> 儲存 Save</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="page-grid">
  <form method="POST" action="<?= url('/system/about/save') ?>" enctype="multipart/form-data" class="panel form-panel">
    <?= csrf_field() ?><input type="hidden" name="kind" value="image">
    <h2 style="margin-top:0"><?= icon('image') ?> 加圖片 <span class="en">Add a picture</span></h2>
    <p class="help" style="margin-top:0">例如主神海報：會放大置中顯示，訪客可點擊放大。建議直向、寬 1200px 以上。
      <span class="en">E.g. the main deity's poster — shown large and centred; visitors can tap to zoom. Portrait, 1200px+ wide is best.</span></p>
    <label for="newImage">圖片 <span class="en">Picture</span> <span class="req">*</span></label>
    <input id="newImage" type="file" name="image" required accept="image/jpeg,image/png,image/webp" data-aspects="original,3:4,2:3,1:1" data-max-width="2400">
    <label>圖片標題（中文）<span class="en">Caption (Chinese)</span></label>
    <input name="heading_zh" maxlength="120" placeholder="例 中壇元帥">
    <label>圖片標題（英文）<span class="en">Caption (English)</span></label>
    <input name="heading_en" maxlength="160" placeholder="e.g. Marshal Zhongtan">
    <label>圖片下的文字（中文，選填）<span class="en">Text below (Chinese, optional)</span></label>
    <textarea name="body_zh" rows="4" maxlength="20000" data-trad-check></textarea>
    <label>圖片下的文字（英文，選填）<span class="en">Text below (English, optional)</span></label>
    <textarea name="body_en" rows="3" maxlength="30000"></textarea>
    <?= $layout(null) ?>
    <div class="form-actions"><button class="primary" type="submit"><?= icon('plus') ?> 加入 <span class="en">Add</span></button></div>
  </form>

  <form method="POST" action="<?= url('/system/about/save') ?>" class="panel form-panel">
    <?= csrf_field() ?><input type="hidden" name="kind" value="text">
    <h2 style="margin-top:0"><?= icon('type') ?> 加文字 <span class="en">Add text</span></h2>
    <p class="help" style="margin-top:0">一段標題和文字，例如宮廟簡介、歷史、主神事蹟。<span class="en">A heading with paragraphs — the temple's story, history, the deity…</span></p>
    <div class="form-grid">
      <div><label>標題（中文）<span class="en">Heading (Chinese)</span></label><input name="heading_zh" maxlength="120" placeholder="例 宮廟簡介"></div>
      <div><label>標題（英文）<span class="en">Heading (English)</span></label><input name="heading_en" maxlength="160" placeholder="e.g. Our Temple"></div>
    </div>
    <label>內容（中文）<span class="en">Paragraphs (Chinese)</span></label>
    <textarea name="body_zh" rows="6" maxlength="20000" data-trad-check></textarea>
    <label>內容（英文）<span class="en">Paragraphs (English)</span></label>
    <textarea name="body_en" rows="4" maxlength="30000"></textarea>
    <p class="help">空一行 = 新段落。<span class="en">Leave a blank line to start a new paragraph.</span></p>
    <?= $layout(null) ?>
    <div class="form-actions"><button class="primary" type="submit"><?= icon('plus') ?> 加入 <span class="en">Add</span></button></div>
  </form>
</div>
<script src="<?= asset('js/sortable.js') ?>"></script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
