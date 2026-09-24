<?php
/**
 * Step 2 (review a new scan) and editing a saved receipt — the same form.
 * Laid out like the paper receipt itself, with the photo alongside, so
 * checking is a matter of looking left, then right.
 *
 * $row = saved receipt or null; $v = values; $draft = unsaved scan or null.
 */
use App\Core\ReceiptReader;

require BASE_PATH . '/app/Views/layouts/admin_header.php';
$isNew   = $row === null;
$unsure  = array_map('mb_strtolower', $draft['unsure'] ?? []);
$flag    = static function (string ...$names) use ($unsure): string {
    foreach ($names as $n) {
        foreach ($unsure as $u) {
            if ($u !== '' && (str_contains($u, $n) || str_contains($n, $u))) {
                return ' is-unsure';
            }
        }
    }
    return '';
};
$val     = static fn(string $k): string => h((string) ($v[$k] ?? ''));
$money   = static fn($x): string => ($x === '' || $x === null || (float) $x == 0) ? '' : h(number_format((float) $x, 2, '.', ''));
$imgUrl  = $isNew
    ? (!empty($draft['image_path']) ? url('/admin/receipts/image') . '?draft=1&t=' . time() : null)
    : (!empty($row['image_path']) ? url('/admin/receipts/image') . '?id=' . (int) $row['id'] : null);
$aiNotes = $isNew ? ($draft['ai_notes'] ?? null) : ($row['ai_notes'] ?? null);
?>
<?php if ($isNew && ($draft['source'] ?? '') === 'ai'): ?>
  <div class="flash info">🤖 <strong>已自動讀取並填好草稿，請對照相片核對後再儲存。</strong> A draft has been filled in automatically — please check it against the photo, then save.
    <?php if ($unsure): ?><br>🟨 黃色欄位最需要再看一眼。Yellow fields need a second look most.<?php endif; ?></div>
<?php endif; ?>
<?php if ($duplicate): ?>
  <div class="flash error">⚠️ 已有另一張收據使用 No. <?= h($duplicate['receipt_no']) ?>（<?= h($duplicate['name'] ?? '—') ?>，<?= rm((float) $duplicate['total']) ?>）。
    <a href="<?= url('/admin/receipts/edit') ?>?id=<?= (int) $duplicate['id'] ?>" target="_blank">查看 View</a>
    <br>Another receipt already has this number — check it is not the same receipt twice.</div>
<?php endif; ?>

<div class="receipt-layout<?= $imgUrl ? '' : ' no-photo' ?>">
  <?php if ($imgUrl): ?>
    <figure class="panel receipt-photo">
      <div class="photo-tools">
        <button type="button" class="mini-btn ghost" data-zoom="-1" aria-label="縮小 Zoom out">－</button>
        <button type="button" class="mini-btn ghost" data-zoom="0">適合 Fit</button>
        <button type="button" class="mini-btn ghost" data-zoom="1" aria-label="放大 Zoom in">＋</button>
        <button type="button" class="mini-btn ghost" data-rotate>↻ 旋轉 Rotate</button>
        <a class="mini-btn ghost" href="<?= h($imgUrl) ?>" target="_blank">↗ 原圖 Full size</a>
      </div>
      <div class="photo-view" id="photoView"><img src="<?= h($imgUrl) ?>" alt="收據相片 Receipt photo" id="photoImg"></div>
      <?php if ($aiNotes): ?><figcaption class="help">🤖 <?= h($aiNotes) ?></figcaption><?php endif; ?>
      <?php if ($isNew && !empty($draft['ocr_text'])): ?>
        <details class="ocr-text" open>
          <summary>🔤 掃描到的文字 <span class="en">Text found in the photo</span></summary>
          <pre><?= h($draft['ocr_text']) ?></pre>
          <p class="help">可以從這裡複製貼上到右邊的欄位。You can copy from here into the fields.</p>
        </details>
      <?php endif; ?>
    </figure>
  <?php endif; ?>

  <form method="POST" action="<?= url('/admin/receipts/save') ?>" enctype="multipart/form-data" class="panel form-panel paper" id="receiptForm">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

    <div class="paper-head">
      <div><strong class="paper-title">收據 <span class="en">Receipt</span></strong></div>
      <label class="paper-no<?= $flag('receipt_no', 'number', 'no') ?>">No.
        <input name="receipt_no" value="<?= $val('receipt_no') ?>" inputmode="numeric" maxlength="30" placeholder="例 e.g. 26432"></label>
    </div>

    <div class="form-grid">
      <div class="<?= trim($flag('date')) ?>"><label for="rDate">日期 <span class="en">Date</span></label>
        <input id="rDate" name="receipt_date" type="date" value="<?= $val('receipt_date') ?>"></div>
      <div class="<?= trim($flag('name')) ?>"><label for="rName">姓名 <span class="en">Name</span></label>
        <input id="rName" name="name" value="<?= $val('name') ?>" maxlength="150"></div>
    </div>
    <div class="<?= trim($flag('item')) ?>"><label for="rItem">項目 <span class="en">Item / purpose</span></label>
      <input id="rItem" name="item" value="<?= $val('item') ?>" maxlength="255"></div>

    <div class="amount-grid">
      <?php foreach (ReceiptReader::CATEGORIES as $k => [$zh, $en]): ?>
        <label class="amount-box<?= $flag($k, 'amounts.' . $k, $zh) ?>">
          <span class="amount-label"><?= h($zh) ?> <small><?= h($en) ?></small></span>
          <span class="rm-input"><span aria-hidden="true">RM</span>
            <input name="amt_<?= $k ?>" value="<?= $money($v['amt_' . $k] ?? '') ?>" inputmode="decimal" placeholder="0.00" data-amt></span>
        </label>
      <?php endforeach; ?>
    </div>
    <div class="<?= trim($flag('other_label')) ?>"><label for="rOther">「其他」是甚麼 <span class="en">What “Other” was for</span></label>
      <input id="rOther" name="other_label" value="<?= $val('other_label') ?>" maxlength="100"></div>

    <div class="form-grid">
      <fieldset class="pay-choice<?= $flag('payment') ?>">
        <legend>付款方式 <span class="en">Paid by</span></legend>
        <?php foreach (['cash' => '💵 現金 Cash', 'bank' => '🏦 轉帳 Bank-In', '' => '— 未註明 Not marked'] as $pv => $pl): ?>
          <label><input type="radio" name="payment" value="<?= $pv ?>"<?= (string) ($v['payment'] ?? '') === $pv ? ' checked' : '' ?>> <?= $pl ?></label>
        <?php endforeach; ?>
      </fieldset>
      <div class="<?= trim($flag('total')) ?>">
        <label for="rTotal">總數 <span class="en">Total (RM)</span></label>
        <input id="rTotal" name="total" value="<?= $money($v['total'] ?? '') ?>" inputmode="decimal" placeholder="0.00" class="total-input">
        <p class="help" id="sumHint"></p>
      </div>
    </div>

    <div class="<?= trim($flag('issued_by', 'issued')) ?>"><label for="rIssued">發據人 <span class="en">Issued by</span></label>
      <input id="rIssued" name="issued_by" value="<?= $val('issued_by') ?>" maxlength="100"></div>
    <label for="rNotes">備註 <span class="en">Notes</span></label>
    <textarea id="rNotes" name="notes" rows="2" maxlength="2000"><?= $val('notes') ?></textarea>

    <?php if (!$isNew): ?>
      <p class="help" style="margin-top:14px">
        <?= $row['source'] === 'ai' ? '🤖 由 AI 讀取 Read by AI' : '✍️ 手動輸入 Typed in' ?> ·
        加入 Added <?= h(date('d/m/Y H:i', strtotime($row['created_at']))) ?> <?= h($row['created_by'] ?? '') ?>
        <?php if ($row['updated_at']): ?> · 修改 Edited <?= h(date('d/m/Y H:i', strtotime($row['updated_at']))) ?> <?= h($row['updated_by'] ?? '') ?><?php endif; ?>
      </p>
      <label for="newPhoto"><?= $row['image_path'] ? '更換相片' : '加上相片' ?> <span class="en"><?= $row['image_path'] ? 'Replace photo' : 'Add a photo' ?> (optional)</span></label>
      <input id="newPhoto" name="photo" type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-aspects="original,3:2,4:3" data-max-width="2000">
    <?php endif; ?>

    <div class="form-actions">
      <button class="primary" type="submit">💾 儲存 <span class="en">Save</span></button>
      <?php if ($isNew): ?>
        <button class="mini-btn ghost btn-lg" type="submit" name="next" value="1">💾 儲存並掃描下一張 <span class="en">Save &amp; scan next</span></button>
        <button class="mini-btn ghost btn-lg" type="submit" form="cancelForm">✖ 取消 Cancel</button>
      <?php else: ?>
        <a class="mini-btn ghost btn-lg" href="<?= url('/admin/receipts') ?>">← 返回列表 Back to list</a>
        <button class="mini-btn danger btn-lg" type="submit" form="deleteForm">🗑 刪除 Delete</button>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php if ($isNew): ?>
  <form id="cancelForm" method="POST" action="<?= url('/admin/receipts/cancel') ?>"
        data-confirm="放棄這張收據？相片也會刪除。&#10;Discard this receipt? The photo will be deleted too." data-danger><?= csrf_field() ?></form>
<?php else: ?>
  <form id="deleteForm" method="POST" action="<?= url('/admin/receipts/delete') ?>"
        data-confirm="確定刪除這張收據？此動作無法復原。&#10;Delete this receipt? This cannot be undone." data-danger>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"></form>
<?php endif; ?>

<script>
(function () {
  // Live check: do the boxes add up to the total written on the receipt?
  var boxes = document.querySelectorAll('[data-amt]'), total = document.getElementById('rTotal'), hint = document.getElementById('sumHint');
  function num(s) { var n = parseFloat(String(s).replace(/[^0-9.]/g, '')); return isNaN(n) ? 0 : n; }
  function rm(n) { return 'RM ' + n.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function check() {
    var sum = 0; boxes.forEach(function (b) { sum += num(b.value); });
    var t = num(total.value);
    if (!sum) { hint.textContent = ''; hint.className = 'help'; return; }
    if (!t) { hint.textContent = '留空會自動用各項合計 ' + rm(sum) + '。Left blank, the total becomes ' + rm(sum) + '.'; hint.className = 'help'; return; }
    var ok = Math.abs(sum - t) < 0.01;
    hint.textContent = ok ? '✓ 各項合計相符 Boxes add up' : '⚠️ 各項合計 ' + rm(sum) + ' ≠ 總數。Boxes add up to ' + rm(sum) + '.';
    hint.className = ok ? 'help ok-text' : 'help warn-text';
  }
  boxes.forEach(function (b) { b.addEventListener('input', check); });
  total.addEventListener('input', check);
  check();

  // Photo: zoom and rotate to read small handwriting.
  var view = document.getElementById('photoView'), img = document.getElementById('photoImg');
  if (!view) return;
  var zoom = 1, rot = 0;
  function apply() {
    img.style.width = (zoom * 100) + '%';
    img.style.transform = 'rotate(' + rot + 'deg)';
    view.classList.toggle('zoomed', zoom > 1);
  }
  document.querySelectorAll('[data-zoom]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = +b.dataset.zoom;
      zoom = d === 0 ? 1 : Math.min(4, Math.max(1, zoom + d * 0.5));
      apply();
    });
  });
  document.querySelector('[data-rotate]').addEventListener('click', function () { rot = (rot + 90) % 360; apply(); });
})();
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
