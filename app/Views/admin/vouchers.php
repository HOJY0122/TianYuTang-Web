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
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
