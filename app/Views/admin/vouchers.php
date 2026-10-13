<?php
/**
 * 布施憑單 Donation vouchers — the batches, and a form for a new one.
 */
require BASE_PATH . '/app/Views/layouts/admin_header.php';
?>
<?php if (!$site['voucher_signature']): ?>
  <p class="flash info"><?= icon('pencil') ?> <strong>尚未上傳批准人簽名</strong>，印出的憑單「Approved by」上面會是空白。
    <?php if ($isSystem): ?><a href="#layout" style="font-weight:800;text-decoration:underline">按這裡上傳 ↓</a><?php else: ?>請系統管理員登入後，在本頁上傳。<?php endif; ?><br>
    <span class="en">The approver's signature is not uploaded yet, so “Approved by” prints blank.
    <?= $isSystem ? '<a href="#layout" style="font-weight:800;text-decoration:underline">Upload it here ↓</a>' : 'Ask a system admin to sign in and upload it on this page.' ?></span></p>
<?php endif; ?>
<div class="panel">
  <div class="toolbar" style="margin-bottom:6px">
    <p class="help" style="margin:0">本會布施給其他慈善機構時，每個中心一張憑單。先建立批次，再匯入中心名單（Excel 或直接貼上），然後一次列印全部。<br>
      <span class="en">When the temple gives to other charities, each centre gets a voucher. Make a batch, bring in the centre list (Excel or paste), then print them all at once.</span></p>
    <span class="spacer"></span>
    <?php if ($isSystem): ?><a class="mini-btn" href="#layout"><?= icon('pencil') ?> 批准人簽名 Signature<?= $site['voucher_signature'] ? ' ✓' : '' ?></a><?php endif; ?>
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

<?php if ($isSystem): ?>
<details class="panel form-panel" id="layout" style="max-width:none"<?= $site['voucher_signature'] ? '' : ' open' ?>>
  <summary><strong><?= icon('pencil') ?> 批准人簽名 Approver's signature</strong>
    <span class="help"><?= $site['voucher_signature'] ? '已上傳 Uploaded' : '尚未上傳 Not uploaded yet' ?></span></summary>
  <p class="help">憑單的抬頭、標語、「Approved by: Leong Chuan Hoe, Chairman」都跟樣本一樣，已固定。只有簽名要上傳一次：程式碼是公開的，簽名不能放在程式裡，只存在伺服器的私人資料夾，只有已登入的憑單管理員看得到。<br>
    <span class="en">The header, motto and “Approved by: Leong Chuan Hoe, Chairman” are fixed, exactly as the sample. Only the signature is uploaded, once: the code is public, so the signature is kept in a private folder on the server, shown only to signed-in voucher staff.</span></p>
  <form method="POST" action="<?= url('/admin/vouchers/layout') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if ($site['voucher_signature']): ?>
      <div class="image-preview"><img src="<?= url('/admin/vouchers/signature') ?>?v=<?= substr(md5((string) $site['voucher_signature']), 0, 8) ?>" alt="簽名 Signature">
        <label class="remove-check"><input type="checkbox" name="remove_signature" value="1"> 移除簽名 Remove the signature</label></div>
    <?php endif; ?>
    <label for="vlSign"><?= $site['voucher_signature'] ? '更換簽名' : '上傳簽名' ?> <span class="en"><?= $site['voucher_signature'] ? 'Replace' : 'Upload' ?> (PNG, transparent background)</span></label>
    <input id="vlSign" type="file" name="signature" accept="image/png,image/jpeg,image/webp">
    <div class="form-actions"><button class="primary" type="submit"><?= icon('save') ?> 儲存 Save</button>
      <?php if ($batches): ?><a class="mini-btn ghost" target="_blank" href="<?= url('/admin/vouchers/print') ?>?batch=<?= (int) $batches[0]['id'] ?>"><?= icon('eye') ?> 預覽 Preview</a><?php endif; ?></div>
  </form>
</details>
<?php endif; ?>
<script>
(function () {
  var d = document.getElementById('layout');
  function open() { if (d && location.hash === '#layout') { d.open = true; d.scrollIntoView({ block: 'start' }); } }
  window.addEventListener('hashchange', open); open();
})();
</script>

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
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
