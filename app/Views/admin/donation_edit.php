<?php
require BASE_PATH . '/app/Views/layouts/admin_header.php';
$price = (float) $event['merit_table_price'];
$free  = $d['free_amount'] !== null ? (float) $d['free_amount'] : ($d['method'] === 'free' ? (float) $d['amount'] : 0.0);
?>
<p style="margin:0 0 12px"><a class="mini-btn ghost" href="<?= url('/admin/donations') ?>?event=<?= (int) $d['event_id'] ?>">← 返回列表 Back to list</a></p>

<?php foreach ($errors as $e): ?><div class="flash error"><?= h($e) ?></div><?php endforeach; ?>

<form method="POST" action="<?= url('/admin/donations/save') ?>" class="panel form-panel">
  <?= csrf_field() ?>
  <input type="hidden" name="donation_id" value="<?= (int) $d['id'] ?>">

  <h2 style="margin:0"><?= h($d['ref_code']) ?>
    <?= $d['source'] === 'counter' ? '<span class="badge counter">現場 Counter</span>' : '<span class="badge">線上 Online</span>' ?></h2>
  <p class="help" style="margin-top:4px">
    <?= h($event['year'] . ' ' . $event['name']) ?> · 登記於 Recorded <?= h($d['created_at']) ?>
    <?= !empty($d['recorded_by']) ? ' · 登記者 by ' . h($d['recorded_by']) : '' ?>
    <?php if (!empty($d['receipt_path'])): ?> · <a href="<?= url('/admin/receipt') ?>?id=<?= (int) $d['id'] ?>" target="_blank">📄 查看收據 View receipt</a><?php endif; ?>
  </p>

  <div class="form-grid">
    <div><label for="name">姓名 <span class="en">Name</span></label>
      <input id="name" name="name" required maxlength="100" value="<?= h($d['name']) ?>"></div>
    <div><label for="contact">聯絡號碼 <span class="en">Contact</span></label>
      <input id="contact" name="contact" maxlength="30" value="<?= h($d['contact_no']) ?>"></div>
    <div><label for="seats">功德席數量 <span class="en">Merit seats (× <?= rm($price) ?>)</span></label>
      <input id="seats" name="seats" type="number" min="0" max="<?= App\Models\Donation::MAX_SEATS ?>" value="<?= (int) $d['table_count'] ?>"></div>
    <div><label for="free_amount">隨喜金額 <span class="en">Freewill (RM)</span></label>
      <input id="free_amount" name="free_amount" type="number" min="0" step="0.01" value="<?= h(number_format($free, 2, '.', '')) ?>"></div>
    <div><label for="status">付款狀態 <span class="en">Payment</span></label>
      <select id="status" name="status">
        <option value="pending"<?= $d['status'] === 'pending' ? ' selected' : '' ?>>待付 Pending</option>
        <option value="paid"<?= $d['status'] === 'paid' ? ' selected' : '' ?>>已付 Paid</option>
      </select></div>
    <div><label for="notes">備註 <span class="en">Notes</span></label>
      <input id="notes" name="notes" maxlength="255" value="<?= h($d['notes'] ?? '') ?>" placeholder="例 e.g. 收據簿 #042"></div>
  </div>

  <div class="note-box">新總額 New total: <strong id="newTotal"></strong>
    <span class="help" style="display:block">總額由系統按每席 <?= rm($price) ?> 重新計算。The total is recalculated at <?= rm($price) ?> per seat.</span></div>

  <div class="form-actions">
    <button class="primary" type="submit">💾 儲存 Save changes</button>
  </div>
</form>
<script>
(function () {
  var price = <?= json_encode($price) ?>;
  var seats = document.getElementById('seats'), free = document.getElementById('free_amount');
  function update() {
    var t = (parseInt(seats.value, 10) || 0) * price + (parseFloat(free.value) || 0);
    document.getElementById('newTotal').textContent = 'RM ' + t.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  seats.addEventListener('input', update); free.addEventListener('input', update); update();
})();
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
