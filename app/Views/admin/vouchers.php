<?php
/**
 * 布施憑單 Donation vouchers — the batches, and a form for a new one.
 */
require BASE_PATH . '/app/Views/layouts/admin_header.php';
?>
<div class="panel">
  <div class="toolbar" style="margin-bottom:6px">
    <p class="help" style="margin:0">本會布施給其他慈善機構時，每個中心一張憑單。先建立批次，再匯入中心名單（Excel 或直接貼上），然後一次列印全部。<br>
      <span class="en">When the temple gives to other charities, each centre gets a voucher. Make a batch, bring in the centre list (Excel or paste), then print them all at once.</span></p>
    <span class="spacer"></span>
    <a class="mini-btn" href="<?= url('/admin/vouchers/template') ?>"><?= icon('download') ?> 名單範本 List template</a>
  </div>

  <?php if (!$batches): ?>
    <p class="empty">還沒有批次。用下面的表格建立第一個。No batches yet — create the first one below.</p>
  <?php else: ?>
  <div class="table-scroll">
  <table class="records">
    <thead><tr>
      <th>批次 Batch</th><th>日期 Date</th><th class="num">張數 Vouchers</th><th class="num">總額 Total</th><th>已付 Paid</th><th>付款方式 Method</th>
    </tr></thead>
    <tbody>
    <?php foreach ($batches as $b): ?>
      <?php $open = url('/admin/vouchers/batch') . '?id=' . (int) $b['id']; ?>
      <tr>
        <td data-label="批次 Batch"><a class="rowlink" href="<?= $open ?>"><?= h($b['title']) ?></a>
          <span class="sub-line"><?= h($b['description']) ?></span></td>
        <td data-label="日期 Date"><?= $b['voucher_date'] ? h(date('d/m/Y', strtotime($b['voucher_date']))) : '—' ?></td>
        <td data-label="張數 Vouchers" class="num"><?= (int) $b['n'] ?></td>
        <td data-label="總額 Total" class="num"><strong><?= rm((float) $b['total']) ?></strong></td>
        <td data-label="已付 Paid"><?php if ((int) $b['n'] === 0): ?><span class="help">—</span>
          <?php elseif ((int) $b['paid'] === (int) $b['n']): ?><span class="badge ok">全部已付 All paid</span>
          <?php else: ?><span class="badge pending"><?= (int) $b['paid'] ?> / <?= (int) $b['n'] ?></span><?php endif; ?></td>
        <td data-label="付款方式 Method"><?= h($b['method']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<div class="panel form-panel">
  <h2><?= icon('plus') ?> 新批次 <span class="en">New batch</span></h2>
  <form method="POST" action="<?= url('/admin/vouchers/batch/save') ?>">
    <?= csrf_field() ?>
    <?php $b = []; require BASE_PATH . '/app/Views/admin/_voucher_batch_fields.php'; ?>
    <div class="form-actions">
      <button class="primary" type="submit"><?= icon('save') ?> 建立批次 Create batch</button>
    </div>
  </form>
</div>
<?php $defaultHead = implode("\n", App\Models\Voucher::headerLines(['voucher_header' => null] + $site)); ?>
<?php if ($isSystem): ?>
<details class="panel form-panel" id="layout" style="max-width:none">
  <summary><strong><?= icon('palette') ?> 憑單版面 Voucher layout</strong> <span class="help">抬頭、標語、批准人簽名 Header, motto, approver's signature</span></summary>
  <form method="POST" action="<?= url('/admin/vouchers/layout') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label for="vlHead">抬頭（每行一句）<span class="en">Header lines, one per line</span></label>
    <textarea id="vlHead" name="voucher_header" rows="6" maxlength="1200" placeholder="<?= h($defaultHead) ?>"><?= h((string) $site['voucher_header']) ?></textarea>
    <p class="help">第一行印成大字中文名稱；全大寫的英文行印粗體；其他（地址、電郵、電話）照常。留空 = 用「網站設定 → 列印」的抬頭。<br>
      <span class="en">Line 1 is the big Chinese name; English lines in CAPITALS print bold; the rest (address, email, phone) plain. Blank = the printout letterhead from Site settings.</span></p>
    <label class="check-inline" style="margin-top:10px"><input type="checkbox" name="voucher_show_logo" value="1"<?= $site['voucher_show_logo'] === '1' ? ' checked' : '' ?>> 左邊印上網站標誌 <span class="en">Print the site logo on the left</span></label>
    <div class="form-row">
      <div><label for="vlTitle">標題 <span class="en">Title</span></label>
        <input id="vlTitle" name="voucher_title" maxlength="80" value="<?= h((string) $site['voucher_title']) ?>"></div>
      <div></div>
    </div>
    <div class="form-row">
      <div><label for="vlMottoEn">標語（英文）<span class="en">Motto (English)</span></label>
        <input id="vlMottoEn" name="voucher_motto_en" maxlength="120" value="<?= h((string) $site['voucher_motto_en']) ?>"></div>
      <div><label for="vlMottoZh">標語（中文）<span class="en">Motto (Chinese)</span></label>
        <input id="vlMottoZh" name="voucher_motto_zh" maxlength="60" value="<?= h((string) $site['voucher_motto_zh']) ?>"></div>
    </div>
    <p class="help">兩句都留空就不印標語。<span class="en">Leave both blank to print no motto.</span></p>
    <div class="form-row">
      <div><label for="vlAppr">批准人姓名 <span class="en">Approved by — name</span></label>
        <input id="vlAppr" name="voucher_approver" maxlength="80" value="<?= h((string) $site['voucher_approver']) ?>"></div>
      <div><label for="vlApprT">職位 <span class="en">Position</span></label>
        <input id="vlApprT" name="voucher_approver_title" maxlength="80" value="<?= h((string) $site['voucher_approver_title']) ?>"></div>
    </div>
    <label for="vlSign">批准人簽名圖片（可選）<span class="en">Approver's signature picture (optional)</span></label>
    <?php if ($site['voucher_signature']): ?>
      <div class="image-preview"><img src="<?= url('/admin/vouchers/signature') ?>?v=<?= substr(md5((string) $site['voucher_signature']), 0, 8) ?>" alt="簽名 Signature">
        <label class="remove-check"><input type="checkbox" name="remove_signature" value="1"> 移除簽名 Remove the signature</label></div>
    <?php endif; ?>
    <input id="vlSign" type="file" name="signature" accept="image/png,image/jpeg,image/webp">
    <p class="help">用透明背景的 PNG 最好看。簽名只存在伺服器的私人資料夾，只有已登入的憑單管理員看得到；不想預先印上簽名就不要上傳，改為親筆簽。<br>
      <span class="en">A PNG with a transparent background looks best. It is kept in a private folder and shown only to signed-in voucher staff. Skip it to sign each voucher by hand.</span></p>
    <div class="form-actions"><button class="primary" type="submit"><?= icon('save') ?> 儲存版面 Save layout</button>
      <?php if ($batches): ?><a class="mini-btn ghost" target="_blank" href="<?= url('/admin/vouchers/print') ?>?batch=<?= (int) $batches[0]['id'] ?>"><?= icon('eye') ?> 預覽 Preview</a><?php endif; ?></div>
  </form>
</details>
<?php endif; ?>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
