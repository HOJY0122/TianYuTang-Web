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
    <div class="flash test">🧪 測試活動 — 此處登記不列入正式統計。Test event — not counted in the real totals.</div>
  <?php endif; ?>

  <!-- ---------- Money in, by source ---------- -->
  <div class="checkin-stats">
    <div><small>現場現金 Counter</small><strong><?= rm($totals['counter']) ?></strong></div>
    <div><small>線上 Online</small><strong><?= rm($totals['online']) ?></strong></div>
    <div><small>現場筆數 Records</small><strong><?= (int) $totals['counter_count'] ?></strong></div>
  </div>

  <div class="page-grid counter-grid">
  <div class="page-col">
  <!-- ---------- ① Online pledge: donor shows their donation QR ---------- -->
  <div class="panel">
    <h2 style="margin-top:0">① 線上布施付款 <span class="en">Paying an online pledge</span></h2>
    <p class="help" style="margin-top:0">布施者出示「布施編號」QR Code（提交後的頁面或截圖），掃描或輸入編號，核對金額後按「確認收款」。<br>
      <span class="en">The donor shows the donation QR from their confirmation page. Scan it or type the reference, check the amount, then confirm.</span></p>
    <form method="GET" action="<?= url('/admin/counter') ?>" class="lookup-form">
      <input type="hidden" name="event" value="<?= (int) $event['id'] ?>">
      <input type="text" name="ref" value="<?= h($ref) ?>" aria-label="布施編號 Donation reference"
             autocomplete="off" autocapitalize="characters" spellcheck="false"
             placeholder="布施編號 Reference，例 e.g. DON-0012">
      <button class="mini-btn btn-lg" type="submit">查詢 Find</button>
      <button class="mini-btn ghost btn-lg" type="button" data-scan>📷 掃描 Scan QR</button>
    </form>
    <div id="scanPanel" class="scan-panel" hidden>
      <video playsinline muted></video>
      <canvas class="hidden"></canvas>
      <div class="scan-row">
        <span data-scan-status class="help"></span>
        <button class="mini-btn ghost" type="button" data-scan-stop>停止 Stop</button>
      </div>
    </div>

    <?php if ($ref !== '' && !$found): ?>
      <div class="result-miss" style="margin-top:14px;padding:14px;border-radius:12px">
        <strong>查無此編號 Not found：<?= h($ref) ?></strong>
        <p class="help" style="margin:.3rem 0 0">請確認編號，或該布施是否屬於其他年度。Check the number, or whether it belongs to another year.
          也可在下方「② 現場布施」直接登記。You can also record it below as a new counter donation.</p>
      </div>
    <?php elseif ($found): ?>
      <?php $paid = $found['status'] === 'paid'; ?>
      <div class="pledge-card<?= $paid ? ' is-paid' : '' ?>">
        <div class="pledge-head">
          <div>
            <div class="pledge-ref"><?= h($found['ref_code']) ?></div>
            <div class="pledge-name"><?= h($found['name']) ?> <span class="help">📞 <?= h($found['contact_no']) ?></span></div>
          </div>
          <span class="pledge-status"><?= $paid ? '✅ 已付款 Paid' : '⏳ 待付款 Pending' ?></span>
        </div>
        <dl class="pledge-lines">
          <div><dt>布施方式 Method</dt><dd><?= h(App\Models\Donation::describe($found)) ?></dd></div>
          <div><dt>應收金額 Amount due</dt><dd class="pledge-amount"><?= rm((float) $found['amount']) ?></dd></div>
          <div><dt>提交時間 Submitted</dt><dd><?= h(date('d/m/Y H:i', strtotime($found['created_at']))) ?></dd></div>
          <?php if ($paid && !empty($found['paid_at'])): ?>
            <div><dt>收款 Received</dt><dd><?= h(date('d/m/Y H:i', strtotime($found['paid_at']))) ?><?= !empty($found['paid_by']) ? ' · ' . h($found['paid_by']) : '' ?></dd></div>
          <?php endif; ?>
          <?php if (!empty($found['notes'])): ?><div><dt>備註 Notes</dt><dd><?= h($found['notes']) ?></dd></div><?php endif; ?>
        </dl>
        <?php if (!$paid): ?>
          <form method="POST" action="<?= url('/admin/counter/receive') ?>" enctype="multipart/form-data" class="pledge-form">
            <?= csrf_field() ?>
            <input type="hidden" name="donation_id" value="<?= (int) $found['id'] ?>">
            <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
            <div class="form-row">
              <div><label for="rNotes">收據號碼 / 備註 <span class="en">Receipt no. / note (optional)</span></label>
                <input id="rNotes" name="notes" maxlength="100" placeholder="例 e.g. 收據簿 #042 / 現金 Cash"></div>
              <div><label for="rPhoto">收據相片 <span class="en">Receipt photo (optional)</span></label>
                <input id="rPhoto" name="receipt" type="file" accept="image/jpeg,image/png,image/gif,image/webp" capture="environment"
                       data-aspects="original,3:4,4:3" data-max-width="1600"></div>
            </div>
            <button class="big-btn" type="submit"
                    data-confirm="確認已收到 <?= h(rm((float) $found['amount'])) ?>？&#10;Confirm <?= h(rm((float) $found['amount'])) ?> received?">
              💵 確認收款 <?= rm((float) $found['amount']) ?> <span class="en">Confirm payment</span></button>
          </form>
        <?php elseif (!empty($found['receipt_path'])): ?>
          <a class="receipt-link" href="<?= url('/admin/receipt?id=' . (int) $found['id']) ?>" target="_blank">📄 查看收據 View receipt</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="panel guide">
    <h2 style="margin-top:0">🧭 用哪一個？ <span class="en">Which one do I use?</span></h2>
    <ul>
      <li><strong>① 線上布施付款</strong>：善信已在網上登記布施，現在來付款 → 掃描他的布施 QR。
        <span class="en">They pledged online and are paying now → scan their donation QR.</span></li>
      <li><strong>② 現場布施</strong>：當場布施、之前沒有登記 → 在右邊填寫。
        <span class="en">Giving on the spot with no online pledge → fill in the form on the right.</span></li>
      <li>記得拍下紙本收據，日後可核對。<span class="en">Photograph the paper receipt so amounts can be checked later.</span></li>
      <li>掃到報名 QR 會自動轉到「現場報到」。<span class="en">A registration QR opens Check-in automatically.</span></li>
    </ul>
  </div>
  </div>
  <div class="page-col">
  <!-- ---------- Entry form ---------- -->
  <div class="panel form-panel">
    <h2 style="margin-top:0">② 現場布施 <span class="en">New counter donation (walk-up, cash in hand)</span></h2>
    <p class="help" style="margin-top:0"><?= h($event['year']) ?> · <?= h($event['name']) ?></p>

    <?php if (!empty($old['errors'])): ?>
      <div class="error">
        <strong>請修正以下問題 Please fix:</strong>
        <ul style="margin:8px 0 0;padding-left:20px">
          <?php foreach ($old['errors'] as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <p class="help" style="margin-top:14px">
      記錄現場收到的現金布施。儲存後會<strong>直接標記為「已付」</strong>，並記下是由誰登記的。<br>
      <span class="en">For money received here and now. It is saved as <strong>paid</strong>, with your name on it.</span>
    </p>

    <form method="POST" action="<?= url('/admin/counter/save') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">

      <label for="name">姓名 <span class="en">Name *</span></label>
      <input id="name" name="name" required maxlength="100"
             value="<?= h($v('name')) ?>" placeholder="布施者姓名 Donor's name">

      <label for="contact">聯絡號碼 <span class="en">Contact (optional)</span></label>
      <input id="contact" name="contact" maxlength="30"
             value="<?= h($v('contact')) ?>" placeholder="例 e.g. 012 345 6789">
      <p class="help">現場布施者未必願意留電話，留空即可。Leave blank if the donor prefers not to give one.</p>

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

      <label for="receipt">收據相片 <span class="en">Receipt photo (recommended)</span></label>
      <input id="receipt" name="receipt" type="file" class="file-input" data-aspects="original,3:4,4:3" data-max-width="1600"
             accept="image/jpeg,image/png,image/gif,image/webp" capture="environment">
      <p class="help">用手機直接拍下紙本收據即可，日後如有疑問可核對。Snap the paper receipt with your phone — handy if an amount is ever questioned.</p>

      <label for="notes">備註 <span class="en">Notes (optional)</span></label>
      <input id="notes" name="notes" maxlength="255"
             value="<?= h($v('notes')) ?>" placeholder="例 e.g. 收據簿 Receipt book #042">

      <div class="form-actions">
        <button class="primary" type="submit">💰 登記布施 Record donation</button>
      </div>
    </form>
  </div>

  </div>
  </div>

  <!-- ---------- Recently recorded ---------- -->
  <?php if ($recent): ?>
    <div class="panel">
      <h2>最近登記 <span class="en">Recently recorded</span></h2>
      <div class="scroll">
        <table>
          <thead>
            <tr><th>編號 Ref</th><th>姓名 Name</th><th>方式 Method</th><th class="amount">金額 Amount</th><th>收據 Receipt</th><th>登記 By</th><th>時間 Time</th></tr>
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
                    <a href="<?= url('/admin/receipt?id=' . (int) $d['id']) ?>" target="_blank" class="receipt-link">📄 查看 View</a>
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

<script src="<?= asset('js/jsqr.min.js') ?>"></script>
<script src="<?= asset('js/scanner.js') ?>"></script>
<script>
TYTScanner({
  form: document.querySelector('.lookup-form'), here: 'don', eventId: <?= (int) $event['id'] ?>,
  urls: { rsvp: <?= json_encode(url('/admin/checkin')) ?>, don: <?= json_encode(url('/admin/counter')) ?> }
});
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
