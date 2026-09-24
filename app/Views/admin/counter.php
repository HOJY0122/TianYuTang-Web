<?php
$seatPrice = (float) $event['merit_table_price'];
$v = static fn(string $k, $d = '') => $old['values'][$k] ?? $d;
?>
<?php
$pageTitle = '現場布施 Counter Donation';
$nav = 'counter';
require BASE_PATH . '/app/Views/layouts/admin_header.php';
?>
<div class="wrap checkin-wrap">


  <?php if ($event['is_test']): ?>
    <div class="flash test">🧪 測試活動 — 此處登記不列入正式統計。</div>
  <?php endif; ?>

  <!-- ---------- Money in, by source ---------- -->
  <div class="checkin-stats">
    <div><small>現場現金 Counter</small><strong><?= rm($totals['counter']) ?></strong></div>
    <div><small>線上 Online</small><strong><?= rm($totals['online']) ?></strong></div>
    <div><small>現場筆數 Records</small><strong><?= (int) $totals['counter_count'] ?></strong></div>
  </div>

  <!-- ---------- Entry form ---------- -->
  <div class="panel form-panel">
    <h2 style="margin-top:0"><?= h($event['year']) ?> · <?= h($event['name']) ?></h2>

    <?php if (!empty($old['errors'])): ?>
      <div class="error">
        <strong>請修正以下問題：</strong>
        <ul style="margin:8px 0 0;padding-left:20px">
          <?php foreach ($old['errors'] as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <p class="help" style="margin-top:14px">
      記錄現場收到的現金布施。儲存後會<strong>直接標記為「已付」</strong>，
      並記下是由誰登記的。
    </p>

    <form method="POST" action="<?= url('/admin/counter/save') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">

      <label for="name">姓名｜Name *</label>
      <input id="name" name="name" required maxlength="100" autofocus
             value="<?= h($v('name')) ?>" placeholder="布施者姓名">

      <label for="contact">聯絡號碼｜Contact（選填）</label>
      <input id="contact" name="contact" maxlength="30"
             value="<?= h($v('contact')) ?>" placeholder="例如：012 345 6789">
      <p class="help">現場布施者未必願意留電話，留空即可。</p>

      <p class="help" style="margin:16px 0 0">填寫功德席、隨喜金額，或兩者皆填。Fill in seats, freewill, or both.</p>
      <div class="form-row">
        <div>
          <label for="table_count">功德席數量 <span class="en">Merit seats (RM<?= number_format($seatPrice, 0) ?> each)</span></label>
          <input id="table_count" name="table_count" type="number" min="0" max="200"
                 inputmode="numeric" value="<?= h((string) $v('table_count', '0')) ?>" oninput="updateCounter()">
        </div>
        <div>
          <label for="free_amount">隨喜金額 <span class="en">Freewill (RM)</span></label>
          <input id="free_amount" name="free_amount" type="number" min="0" step="0.01"
                 inputmode="decimal" value="<?= h((string) $v('free_amount')) ?>" oninput="updateCounter()" placeholder="0">
        </div>
      </div>

      <div class="total counter-total"><span>合計 Total</span><strong id="counterTotal">RM 0</strong></div>

      <label for="receipt">收據相片｜Receipt Photo（建議）</label>
      <input id="receipt" name="receipt" type="file" class="file-input"
             accept="image/jpeg,image/png,image/gif,image/webp" capture="environment">
      <p class="help">
        用手機直接拍下紙本收據即可。金額若日後有爭議，可調出原始收據核對。
      </p>

      <label for="notes">備註｜Notes（選填）</label>
      <input id="notes" name="notes" maxlength="255"
             value="<?= h($v('notes')) ?>" placeholder="例如：收據簿 #042">

      <div class="form-actions">
        <button class="primary" type="submit">💰 登記布施</button>
      </div>
    </form>
  </div>

  <!-- ---------- Recently recorded ---------- -->
  <?php if ($recent): ?>
    <div class="panel">
      <h2>最近登記</h2>
      <div class="scroll">
        <table>
          <thead>
            <tr><th>編號</th><th>姓名</th><th>方式</th><th class="amount">金額</th><th>收據</th><th>登記者</th><th>時間</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $d): ?>
              <tr>
                <td><?= h($d['ref_code']) ?></td>
                <td><?= h($d['name']) ?></td>
                <td><?= h(App\Models\Donation::describe($d)) ?></td>
                <td><?= rm((float) $d['amount']) ?></td>
                <td>
                  <?php if (!empty($d['receipt_path'])): ?>
                    <a href="<?= url('/admin/receipt?id=' . (int) $d['id']) ?>" target="_blank" class="receipt-link">📄 查看</a>
                  <?php else: ?>
                    <span class="help">—</span>
                  <?php endif; ?>
                </td>
                <td><?= h($d['recorded_by'] ?? '—') ?></td>
                <td><?= h(date('m-d H:i', strtotime($d['created_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

</div>

<script>
const SEAT_PRICE = <?= (float) $seatPrice ?>;

function updateCounter() {
  const seats = Math.max(0, parseInt(document.getElementById('table_count').value, 10) || 0);
  const free  = Math.max(0, parseFloat(document.getElementById('free_amount').value) || 0);
  const total = seats * SEAT_PRICE + free;
  document.getElementById('counterTotal').textContent =
    'RM ' + total.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
updateCounter();
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
