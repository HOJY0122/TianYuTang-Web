<?php
/**
 * One batch of 布施憑單 Donation vouchers: import the centre list, check
 * and change each voucher, mark them paid, print.
 */
use App\Models\Voucher;

require BASE_PATH . '/app/Views/layouts/admin_header.php';
$bid    = (int) $batch['id'];
$total  = 0.0;
$paidN  = 0;
$noBank = 0;
foreach ($vouchers as $v) {
    $total += Voucher::amount($batch, $v);
    $paidN += $v['paid_at'] ? 1 : 0;
    $noBank += $v['bank_account'] === null ? 1 : 0;
}
$printUrl = url('/admin/vouchers/print') . '?batch=' . $bid;
?>
<p style="margin:0 0 12px"><a href="<?= url('/admin/vouchers') ?>"><?= icon('arrow-left') ?> 全部批次 All batches</a></p>

<div class="kpis">
  <div class="kpi"><div class="k-label">憑單<span class="en">Vouchers</span></div><div class="k-value"><?= count($vouchers) ?></div></div>
  <div class="kpi"><div class="k-label">總額<span class="en">Total</span></div><div class="k-value"><?= rm_compact($total) ?></div></div>
  <div class="kpi"><div class="k-label">已付<span class="en">Paid</span></div><div class="k-value"><?= $paidN ?> / <?= count($vouchers) ?></div></div>
  <?php if ($noBank): ?>
    <div class="kpi"><div class="k-label">沒有銀行戶口<span class="en">No bank account</span></div><div class="k-value warn-text"><?= $noBank ?></div></div>
  <?php endif; ?>
</div>

<div class="panel">
  <form method="GET" action="<?= url('/admin/vouchers/print') ?>" target="_blank" id="voucherPrint">
    <input type="hidden" name="batch" value="<?= $bid ?>">
    <div class="toolbar">
      <div>
        <h2 style="margin:0"><?= h($batch['title']) ?></h2>
        <p class="help" style="margin:2px 0 0"><?= h($batch['description']) ?> · <?= $batch['voucher_date'] ? h(date('j M Y', strtotime($batch['voucher_date']))) : '—' ?>
          · <?= rm((float) $batch['amount']) ?> · <?= h($batch['method']) ?></p>
      </div>
      <span class="spacer"></span>
      <?php if ($vouchers): ?>
        <a class="mini-btn btn-lg" href="<?= $printUrl ?>" target="_blank"><?= icon('printer') ?> 列印全部 Print all (<?= count($vouchers) ?>)</a>
        <button class="mini-btn" type="submit" id="printTicked" disabled><?= icon('printer') ?> 列印已選 Print ticked (<span id="tickedN">0</span>)</button>
        <a class="mini-btn" href="<?= url('/admin/vouchers/excel') ?>?batch=<?= $bid ?>"><?= icon('download') ?> Excel</a>
      <?php endif; ?>
    </div>

    <?php if (!$vouchers): ?>
      <p class="empty">這個批次還沒有憑單。用下面的「匯入名單」加入中心。No vouchers yet — bring in the centre list below.</p>
    <?php else: ?>
    <p class="help" style="margin:0 0 8px">列印頁面按「列印 / 存成 PDF」即可得到一個合併的 PDF，每頁一張憑單。<span class="en">On the print page choose “Save as PDF” for one combined PDF, a voucher per page.</span></p>
    <div class="table-scroll">
    <table class="records voucher-table">
      <thead><tr>
        <th class="tick-col"><input type="checkbox" id="tickAll" aria-label="全選 Tick all"></th>
        <th>憑單號 Voucher</th><th>受款中心 Pay to</th><th>銀行戶口 Bank account</th><th class="num">金額 Amount</th><th>已付 Paid</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($vouchers as $v): ?>
        <?php $own = $v['amount'] !== null; ?>
        <tr id="v<?= (int) $v['id'] ?>">
          <td class="tick-col"><input type="checkbox" name="ids[]" value="<?= (int) $v['id'] ?>" class="v-tick" aria-label="選取 Tick"></td>
          <td data-label="憑單號 Voucher"><strong><?= h(Voucher::number($batch, $v)) ?></strong>
            <?php if ($v['list_no'] !== null): ?><span class="sub-line">No. <?= h($v['list_no']) ?></span><?php endif; ?></td>
          <td data-label="受款中心 Pay to" class="wrap">
            <?php if ($v['name_zh'] !== null): ?><span class="v-zh"><?= h($v['name_zh']) ?></span><?php endif; ?>
            <?php if ($v['name_en'] !== null): ?><span class="sub-line v-en"><?= h($v['name_en']) ?></span><?php endif; ?></td>
          <td data-label="銀行戶口 Bank account" class="wrap"><?= $v['bank_account'] !== null ? h($v['bank_account']) : '<span class="warn-text">未填 None</span>' ?></td>
          <td data-label="金額 Amount" class="num"><strong><?= rm(Voucher::amount($batch, $v)) ?></strong>
            <?php if ($own): ?><span class="sub-line">另設 Own amount</span><?php endif; ?></td>
          <td data-label="已付 Paid">
            <label class="check-inline paid-toggle"><input type="checkbox" data-paid="<?= (int) $v['id'] ?>"<?= $v['paid_at'] ? ' checked' : '' ?>>
              <span><?= $v['paid_at'] ? h(date('d/m', strtotime($v['paid_at']))) : '未付 No' ?></span></label></td>
          <td><div class="actions-cell">
            <a class="mini-btn" href="<?= $printUrl ?>&amp;ids=<?= (int) $v['id'] ?>" target="_blank" title="列印這張 Print this one"><?= icon('printer') ?></a>
            <a class="mini-btn" href="<?= url('/admin/vouchers/edit') ?>?id=<?= (int) $v['id'] ?>"><?= icon('pencil') ?> <span class="hide-sm">修改 Edit</span></a>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </form>
  <span id="end"></span>
</div>

<div class="grid-2">
  <div class="panel form-panel" style="max-width:none">
    <h2><?= icon('upload') ?> 匯入名單 <span class="en">Bring in the centre list</span></h2>
    <form method="POST" action="<?= url('/admin/vouchers/import') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="batch_id" value="<?= $bid ?>">
      <label for="vSheet">Excel / CSV 檔案 <span class="en">Excel or CSV file</span></label>
      <input id="vSheet" class="file-input" type="file" name="sheet" accept=".xlsx,.xlsm,.csv,.txt">
      <p class="help">讀取第一個工作表：「No.」、「Centre Name」、中文名稱、「Bank Account」，可另加「Amount」。<a href="<?= url('/admin/vouchers/template') ?>">下載範本</a><br>
        <span class="en">Reads the first sheet: No., Centre Name, Chinese name, Bank Account, and optionally Amount. <a href="<?= url('/admin/vouchers/template') ?>">Download a template</a></span></p>
      <label for="vPaste">或從 Excel 複製貼上 <span class="en">…or copy and paste from Excel</span></label>
      <textarea id="vPaste" name="paste" rows="5" placeholder="No.&#9;Centre Name&#9;中文名稱&#9;Bank Account"></textarea>
      <p class="help">新的憑單會接在現有的後面。<span class="en">New vouchers are added after the existing ones.</span></p>
      <div class="form-actions"><button class="primary" type="submit"><?= icon('upload') ?> 匯入 Import</button></div>
    </form>
  </div>

  <div class="panel form-panel" style="max-width:none">
    <h2><?= icon('plus') ?> 加一張 <span class="en">Add one voucher</span></h2>
    <form method="POST" action="<?= url('/admin/vouchers/save') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="batch_id" value="<?= $bid ?>">
      <?php $voucher = []; require BASE_PATH . '/app/Views/admin/_voucher_fields.php'; ?>
      <div class="form-actions"><button class="primary" type="submit"><?= icon('plus') ?> 加入 Add</button></div>
    </form>
  </div>
</div>

<details class="panel form-panel" style="max-width:none">
  <summary><strong><?= icon('settings') ?> 批次資料 Batch details</strong> <span class="help">日期、金額、付款方式、紙張 Date, amount, method, paper</span></summary>
  <form method="POST" action="<?= url('/admin/vouchers/batch/save') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $bid ?>">
    <?php $b = $batch; require BASE_PATH . '/app/Views/admin/_voucher_batch_fields.php'; ?>
    <div class="form-actions">
      <button class="primary" type="submit"><?= icon('save') ?> 儲存 Save</button>
    </div>
  </form>
  <form method="POST" action="<?= url('/admin/vouchers/batch/delete') ?>" style="margin-top:14px"
        data-confirm="刪除整個批次和 <?= count($vouchers) ?> 張憑單？&#10;Delete this batch and its <?= count($vouchers) ?> voucher(s)?" data-danger>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $bid ?>">
    <button class="mini-btn danger" type="submit"><?= icon('trash') ?> 刪除批次 Delete batch</button>
  </form>
</details>

<script src="<?= asset('js/vouchers.js') ?>"></script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
