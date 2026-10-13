<?php
/** Change one 布施憑單 Donation voucher. */
use App\Models\Voucher;

require BASE_PATH . '/app/Views/layouts/admin_header.php';
$back = url('/admin/vouchers/batch') . '?id=' . (int) $batch['id'];
?>
<p style="margin:0 0 12px"><a href="<?= $back ?>#v<?= (int) $voucher['id'] ?>"><?= icon('arrow-left') ?> <?= h($batch['title']) ?></a></p>
<div class="panel form-panel">
  <h2><?= icon('pencil') ?> <?= h(Voucher::number($batch, $voucher)) ?></h2>
  <form method="POST" action="<?= url('/admin/vouchers/save') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $voucher['id'] ?>">
    <?php require BASE_PATH . '/app/Views/admin/_voucher_fields.php'; ?>
    <div class="form-actions">
      <button class="primary" type="submit"><?= icon('save') ?> 儲存 Save</button>
      <a class="mini-btn ghost" href="<?= url('/admin/vouchers/print') ?>?batch=<?= (int) $batch['id'] ?>&amp;ids=<?= (int) $voucher['id'] ?>" target="_blank"><?= icon('printer') ?> 列印 Print</a>
    </div>
  </form>
  <form method="POST" action="<?= url('/admin/vouchers/delete') ?>" style="margin-top:14px"
        data-confirm="刪除這張憑單？號碼不會再用。&#10;Delete this voucher? Its number will not be reused." data-danger>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $voucher['id'] ?>">
    <button class="mini-btn danger" type="submit"><?= icon('trash') ?> 刪除 Delete</button>
  </form>
</div>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
