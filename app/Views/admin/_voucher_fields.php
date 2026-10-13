<?php
/**
 * One voucher's fields — shared by "add one" and "edit".
 * Expects $voucher (row or []) and $batch.
 */
$vv = static fn(string $k): string => h((string) ($voucher[$k] ?? ''));
?>
<label for="vfZh">中文名稱 <span class="en">Chinese name</span></label>
<input id="vfZh" name="name_zh" maxlength="120" value="<?= $vv('name_zh') ?>">
<label for="vfEn">英文名稱 <span class="en">Centre name (English)</span></label>
<input id="vfEn" name="name_en" maxlength="200" value="<?= $vv('name_en') ?>">
<label for="vfBank">銀行戶口 <span class="en">Bank account</span></label>
<input id="vfBank" name="bank_account" maxlength="160" value="<?= $vv('bank_account') ?>" placeholder="例 e.g. Maybank 5123 4567 8901">
<div class="form-row">
  <div>
    <label for="vfNo">名單編號 <span class="en">List No.</span></label>
    <input id="vfNo" name="list_no" maxlength="20" value="<?= $vv('list_no') ?>">
  </div>
  <div>
    <label for="vfAmt">金額 <span class="en">Amount (RM)</span></label>
    <input id="vfAmt" name="amount" inputmode="decimal" maxlength="16" value="<?= isset($voucher['amount']) ? h(number_format((float) $voucher['amount'], 2, '.', '')) : '' ?>"
           placeholder="<?= h(number_format((float) $batch['amount'], 2, '.', '')) ?>">
    <p class="help">留空 = 批次金額。<span class="en">Blank = the batch amount.</span></p>
  </div>
</div>
