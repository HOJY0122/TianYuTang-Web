<?php
/** Printable donation record for the committee's own accounting. */
$seatPrice = (float) $event['merit_table_price'];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>布施名單｜<?= h($event['year']) ?> <?= h($event['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=LXGW+WenKai+TC:wght@700&family=Noto+Sans+TC:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/print.css') ?>">
<style>
/* More columns than the check-in sheet: landscape keeps names on one line. */
@page { size: A4 landscape; }
</style>
</head>
<body>

<div class="toolbar no-print">
  <button class="primary" onclick="window.print()">🖨️ 列印 / 存成 PDF　Print / Save as PDF</button>
  <a href="<?= url('/admin/print/attendees') ?>?event=<?= (int) $event['id'] ?>">報名名單 →</a>
  <a href="<?= url('/admin/dashboard') ?>?event=<?= (int) $event['id'] ?>">← 返回後台</a>
  <span class="hint">在列印視窗選擇「另存為 PDF」。Choose “Save as PDF” in the print window.</span>
</div>

<?php
$docTitleZh = '布施名單 · 功德紀錄';
$docTitleEn = 'Donation record';
require BASE_PATH . '/app/Views/print/_letterhead.php';
?>

<div class="summary">
  <span>布施總額 Pledged <b><?= rm($totals['pledged']) ?></b></span>
  <span>已收 Received <b><?= rm($totals['paid']) ?></b></span>
  <span>功德席 Seats <b><?= (int) $totals['seats'] ?></b> 席</span>
  <span>筆數 Count <b><?= count($donations) ?></b></span>
  <span class="muted">線上 <?= rm($totals['online']) ?> · 現場 <?= rm($totals['counter']) ?></span>
</div>

<table>
  <thead>
    <tr>
      <th class="tick">核對<br><small>Check</small></th>
      <th class="num">#</th>
      <th class="ref">編號 <small>Ref</small></th>
      <th>來源 <small>Source</small></th>
      <th>姓名 <small>Name</small></th>
      <th>聯絡號碼 <small>Contact</small></th>
      <th>方式 <small>Method</small></th>
      <th>詳情 <small>Details</small></th>
      <th class="amount">金額 <small>RM</small></th>
      <th>狀態 <small>Status</small></th>
    </tr>
  </thead>
  <tbody>
    <?php if (!$donations): ?>
      <tr><td colspan="10" class="empty">尚無布施資料。</td></tr>
    <?php else: ?>
      <?php foreach ($donations as $i => $d): ?>
        <tr>
          <td class="tick"><span class="tick-box"></span></td>
          <td class="num"><?= $i + 1 ?></td>
          <td class="ref"><?= h($d['ref_code']) ?></td>
          <td><?= ($d['source'] ?? 'online') === 'counter' ? '現場' : '線上' ?></td>
          <td><strong><?= h($d['name']) ?></strong></td>
          <td><?= h($d['contact_no']) ?></td>
          <td><?= ['table' => '功德席', 'free' => '隨喜布施', 'mixed' => '功德席+隨喜'][$d['method']] ?? h($d['method']) ?></td>
          <td><?= h(App\Models\Donation::describe($d)) ?></td>
          <td class="amount"><?= number_format((float) $d['amount'], 2) ?></td>
          <td><?= $d['status'] === 'paid' ? '已付' : '待付' ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
  <?php if ($donations): ?>
  <tfoot>
    <tr>
      <th colspan="8" style="text-align:right">總計 Total</th>
      <th class="amount"><?= number_format($totals['pledged'], 2) ?></th>
      <th></th>
    </tr>
  </tfoot>
  <?php endif; ?>
</table>

<div class="signoff">
  <div><div class="sign-line">財政簽名 Treasurer</div></div>
  <div><div class="sign-line">覆核簽名 Verified by</div></div>
  <div><div class="sign-line">日期 Date</div></div>
</div>

<p class="confidential">🔒 機密文件：內含個人資料，僅供本會內部使用，用後請妥善銷毀。Confidential — contains personal data (PDPA). For internal use only; dispose of securely.</p>

</body>
</html>
