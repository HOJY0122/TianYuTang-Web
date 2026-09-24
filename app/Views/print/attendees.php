<?php
/** Printable check-in sheet for the on-site counter. */
$dateLines = App\Models\Event::formatDateLines($event);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>報名名單｜<?= h($event['year']) ?> <?= h($event['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@700;800&family=Noto+Sans+TC:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/print.css') ?>">
</head>
<body>

<div class="toolbar no-print">
  <button class="primary" onclick="window.print()">🖨️ 列印 / 存成 PDF</button>
  <a href="<?= url('/admin/print/donations') ?>?event=<?= (int) $event['id'] ?>">布施名單 →</a>
  <a href="<?= url('/admin/dashboard') ?>?event=<?= (int) $event['id'] ?>">← 返回後台</a>
  <span class="hint">在列印視窗選擇「另存為 PDF」即可存檔，不需額外軟體。</span>
</div>

<div class="sheet-head">
  <h1>報名名單 · 現場報到表</h1>
  <div class="meta">
    <span><strong><?= h($event['year']) ?> <?= h($event['name']) ?></strong>
      <?= $dateLines ? '　|　' . h($dateLines[0]) . (count($dateLines) > 1 ? ' 起' : '') : '' ?></span>
    <span>列印時間：<?= h($printedAt) ?></span>
  </div>
</div>

<div class="summary">
  <span>參加人數 <b><?= (int) $totals['attendees'] ?></b> 位</span>
  <span>報名組數 <b><?= (int) $totals['groups'] ?></b> 組</span>
  <span class="muted">（已取消的報名不列入本表）</span>
</div>

<table>
  <thead>
    <tr>
      <th class="tick">報到</th>
      <th class="num">#</th>
      <th>姓名</th>
      <th>身份證號碼</th>
      <th>聯絡號碼</th>
      <th class="ref">報名編號</th>
      <th>狀態</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!$attendees): ?>
      <tr><td colspan="7" class="empty">尚無報名資料。</td></tr>
    <?php else: ?>
      <?php foreach ($attendees as $i => $a): ?>
        <?php $in = !empty($a['checked_in_at']); ?>
        <tr>
          <td class="tick">
            <?php if ($in): ?>
              <span class="tick-box ticked">✓</span>
            <?php else: ?>
              <span class="tick-box"></span>
            <?php endif; ?>
          </td>
          <td class="num"><?= $i + 1 ?></td>
          <td><strong><?= h($a['name']) ?></strong></td>
          <td><?= h($a['ic_no']) ?></td>
          <td><?= h($a['contact_no']) ?></td>
          <td class="ref"><?= h($a['ref_code']) ?></td>
          <td><?= $in ? '已報到 ' . h(date('H:i', strtotime($a['checked_in_at']))) : ($a['status'] === 'confirmed' ? '已確認' : '待確認') ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

<!--
  No signature block here on purpose. This is a working checklist the
  counter ticks through, not a financial record — and with a long list
  the block was being pushed onto a page of its own, wasting a sheet
  every time the sheet is reprinted during the event. The donation
  sheet, which IS an accounting record, keeps its sign-off.
-->

</body>
</html>
