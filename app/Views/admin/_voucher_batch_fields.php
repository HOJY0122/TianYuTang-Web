<?php
/**
 * The batch's details — shared by "new batch" and "edit batch".
 * Expects $b: the batch row, or [] for a new one.
 */
use App\Models\Voucher;

$bv = static fn(string $k, string $d = ''): string => h((string) ($b[$k] ?? $d));
?>
<label for="vbTitle">批次名稱 <span class="en">Batch name</span></label>
<input id="vbTitle" name="title" maxlength="120" required value="<?= $bv('title') ?>" placeholder="例：2026 慈善布施 e.g. 2026 Charity giving">

<div class="form-row">
  <div>
    <label for="vbDate">憑單日期 <span class="en">Voucher date</span></label>
    <input id="vbDate" type="date" name="voucher_date" value="<?= $bv('voucher_date', $b ? '' : date('Y-m-d')) ?>">
  </div>
  <div>
    <label for="vbDesc">項目 <span class="en">Description</span></label>
    <input id="vbDesc" name="description" maxlength="120" value="<?= $bv('description', 'CHARITY DONATION') ?>">
  </div>
</div>

<div class="form-row">
  <div>
    <label for="vbAmount">每張金額 <span class="en">Amount per voucher (RM)</span></label>
    <input id="vbAmount" name="amount" inputmode="decimal" maxlength="16" value="<?= isset($b['amount']) ? h(number_format((float) $b['amount'], 2, '.', '')) : '' ?>" placeholder="2000.00">
    <p class="help">個別中心可在名單裡另設金額。<span class="en">A centre can have its own amount in the list.</span></p>
  </div>
  <div>
    <label for="vbMethod">付款方式 <span class="en">Payment method</span></label>
    <input id="vbMethod" name="method" maxlength="60" list="vbMethods" value="<?= $bv('method', 'Bank Transfer') ?>">
    <datalist id="vbMethods"><option value="Bank Transfer"><option value="Cheque"><option value="Cash"><option value="DuitNow"></datalist>
  </div>
</div>

<label for="vbWords">金額（英文大寫）<span class="en">Amount in words</span></label>
<input id="vbWords" name="amount_words" maxlength="200" value="<?= $bv('amount_words') ?>" placeholder="留空自動填寫 Leave blank to write it automatically">
<p class="help">留空時自動寫出，例如「Two Thousand Only」；個別金額不同的中心也會自動寫出。<span class="en">Blank = written out for you, e.g. “Two Thousand Only” — centres with their own amount get their own words.</span></p>

<label>列印紙張 <span class="en">Paper</span></label>
<div class="choice-row">
  <?php foreach (Voucher::PAPERS as $pk => [$pzh, $pen]): ?>
    <label class="check-inline"><input type="radio" name="paper" value="<?= $pk ?>"<?= ($b['paper'] ?? 'a4') === $pk ? ' checked' : '' ?>> <?= h($pzh) ?> <span class="en"><?= h($pen) ?></span></label>
  <?php endforeach; ?>
</div>

<label for="vbNotes">備註（不會印出）<span class="en">Notes (not printed)</span></label>
<textarea id="vbNotes" name="notes" maxlength="500" rows="2"><?= $bv('notes') ?></textarea>
