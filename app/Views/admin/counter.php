<?php
$seatPrice = (float) $event['merit_table_price'];
$v = static fn(string $k, $d = '') => $old['values'][$k] ?? $d;
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>現場布施登記｜天玉堂</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@700;900&family=Noto+Sans+TC:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body>

<div class="adminbar">
  <h1>💰 現場布施登記</h1>
  <div style="display:flex;align-items:center;gap:14px">
    <span class="who"><?= h($_SESSION['admin_username']) ?></span>
    <a class="logout" href="<?= url('/admin/dashboard') ?>">← 後台</a>
  </div>
</div>

<div class="wrap checkin-wrap">

  <?php if (!empty($flash)): ?>
    <div class="flash <?= h($flash['type']) ?>">
      <strong><?= h($flash['title']) ?></strong> — <?= h($flash['message']) ?>
    </div>
  <?php endif; ?>

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

      <label for="method">布施方式｜Method *</label>
      <?php $m = $v('method', 'free'); ?>
      <select id="method" name="method" onchange="updateCounter()">
        <option value="free"<?= $m === 'free' ? ' selected' : '' ?>>隨喜布施 Freewill</option>
        <option value="table"<?= $m === 'table' ? ' selected' : '' ?>>功德席 RM<?= number_format($seatPrice, 0) ?> / 席</option>
      </select>

      <div id="freeWrap">
        <label for="free_amount">金額 (RM)｜Amount *</label>
        <input id="free_amount" name="free_amount" type="number" min="1" step="0.01"
               inputmode="decimal" value="<?= h((string) $v('free_amount')) ?>"
               oninput="updateCounter()" placeholder="收到的金額">
      </div>

      <div id="tableWrap" class="hidden">
        <label for="table_count">功德席數量｜Seats *</label>
        <input id="table_count" name="table_count" type="number" min="1" max="200"
               inputmode="numeric" value="<?= h((string) $v('table_count', '1')) ?>"
               oninput="updateCounter()">
      </div>

      <div class="total counter-total"><span>合計</span><strong id="counterTotal">RM 0</strong></div>

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
                <td><?= $d['method'] === 'table' ? ((int) $d['table_count'] . ' 席') : '隨喜' ?></td>
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
  const method = document.getElementById('method').value;
  const free   = document.getElementById('free_amount');
  const seats  = document.getElementById('table_count');

  document.getElementById('freeWrap').classList.toggle('hidden', method !== 'free');
  document.getElementById('tableWrap').classList.toggle('hidden', method !== 'table');

  // Only the field in use is submitted, so the unused one cannot fail
  // validation or send a stray value.
  free.disabled  = (method !== 'free');
  seats.disabled = (method !== 'table');

  const total = (method === 'free')
    ? (Number(free.value) || 0)
    : (Number(seats.value) || 0) * SEAT_PRICE;

  document.getElementById('counterTotal').textContent = 'RM ' + total.toLocaleString('en-MY');
}
updateCounter();
</script>
</body>
</html>
