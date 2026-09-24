<?php
/**
 * 收據紀錄 Receipts — the paper receipt book, searchable and sortable.
 */
use App\Core\ReceiptReader;

require BASE_PATH . '/app/Views/layouts/admin_header.php';
$pagerBase  = '/admin/receipts';
$pagerQuery = array_filter($filters, static fn($v) => $v !== '' && $v !== null);
$payLabel   = ['cash' => '💵 現金 Cash', 'bank' => '🏦 轉帳 Bank-in', '' => '—'];
?>
<div class="kpis">
  <div class="kpi"><div class="k-label">收據張數<span class="en">Receipts</span></div><div class="k-value"><?= number_format((int) $sum['n']) ?></div></div>
  <div class="kpi"><div class="k-label">總額<span class="en">Total</span></div><div class="k-value"><?= rm_compact((float) $sum['total']) ?></div></div>
  <?php
  // The three biggest boxes for the current filter, so the committee
  // sees at a glance what the money was for.
  $byCat = [];
  foreach (ReceiptReader::CATEGORIES as $k => [$zh, $en]) {
      $byCat[$k] = (float) ($sum['amt_' . $k] ?? 0);
  }
  arsort($byCat);
  foreach (array_slice(array_filter($byCat), 0, 3, true) as $k => $amt): ?>
    <div class="kpi"><div class="k-label"><?= h(ReceiptReader::CATEGORIES[$k][0]) ?><span class="en"><?= h(ReceiptReader::CATEGORIES[$k][1]) ?></span></div>
      <div class="k-value"><?= rm_compact($amt) ?></div></div>
  <?php endforeach; ?>
</div>

<div class="panel">
  <form method="GET" action="<?= url('/admin/receipts') ?>" class="toolbar">
    <input type="hidden" name="sort" value="<?= h($filters['sort']) ?>">
    <input type="hidden" name="dir" value="<?= h($filters['dir']) ?>">
    <label class="field">搜尋 Search
      <input type="search" name="q" value="<?= h($filters['q']) ?>" placeholder="號碼、姓名、項目 No., name, item">
    </label>
    <label class="field">由 From <input type="date" name="from" value="<?= h($filters['from']) ?>"></label>
    <label class="field">至 To <input type="date" name="to" value="<?= h($filters['to']) ?>"></label>
    <label class="field">付款 Paid by
      <select name="payment">
        <option value="">全部 All</option>
        <option value="cash"<?= $filters['payment'] === 'cash' ? ' selected' : '' ?>>現金 Cash</option>
        <option value="bank"<?= $filters['payment'] === 'bank' ? ' selected' : '' ?>>轉帳 Bank-in</option>
      </select>
    </label>
    <button class="mini-btn btn-lg" type="submit">🔍 搜尋 Search</button>
    <?php if ($filters['q'] !== '' || $filters['from'] !== '' || $filters['to'] !== '' || $filters['payment'] !== ''): ?>
      <a class="mini-btn ghost btn-lg" href="<?= url('/admin/receipts') ?>">清除 Clear</a>
    <?php endif; ?>
    <span class="spacer"></span>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/receipts/excel') . '?' . h(http_build_query($pagerQuery)) ?>">📊 Excel</a>
    <a class="mini-btn btn-lg" href="<?= url('/admin/receipts/new') ?>">📷 掃描收據 Scan receipt</a>
  </form>

  <?php if (!$aiReady): ?>
    <p class="flash info" style="margin:0 0 12px">🤖 AI 讀取尚未啟用，可以照常拍照並手動輸入。AI reading is off — you can still attach photos and type receipts in.<br>
      <?php if (!empty($isSystem)): ?>
        👉 <a href="<?= url('/system') ?>#ai">到「網站設定 → ⑤ AI」貼上金鑰並測試連線 Set the key in Site settings → ⑤ AI</a>
      <?php else: ?>
        請系統管理員在「網站設定 → ⑤ AI」設定金鑰。Ask a system admin to set the key in Site settings → ⑤ AI.
      <?php endif; ?></p>
  <?php endif; ?>

  <p class="help" style="margin:0 0 8px">共 <?= number_format((int) $sum['n']) ?> 張 · <?= number_format((int) $sum['n']) ?> receipt<?= (int) $sum['n'] === 1 ? '' : 's' ?>
    · 點欄位標題可排序 Tap a column heading to sort</p>

  <?php if (!$rows): ?>
    <p class="empty">還沒有收據。按「掃描收據」加入第一張。No receipts yet — tap “Scan receipt” to add one.</p>
  <?php else: ?>
  <table class="records">
    <thead><tr>
      <th class="thumb-col">相片 Photo</th>
      <?= sort_th('號碼 No.', 'no', $pagerQuery + $filters, $pagerBase) ?>
      <?= sort_th('日期 Date', 'date', $pagerQuery + $filters, $pagerBase) ?>
      <?= sort_th('姓名 Name', 'name', $pagerQuery + $filters, $pagerBase, 'asc') ?>
      <th>項目 Details</th>
      <?= sort_th('總數 Total', 'total', $pagerQuery + $filters, $pagerBase, 'desc', 'num') ?>
      <th>付款 Paid by</th>
      <?= sort_th('加入 Added', 'added', $pagerQuery + $filters, $pagerBase) ?>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <?php
        $parts = [];
        foreach (ReceiptReader::CATEGORIES as $k => [$zh, $en]) {
            if ((float) $r['amt_' . $k] > 0) {
                $parts[] = $zh . ($k === 'other' && $r['other_label'] ? '（' . $r['other_label'] . '）' : '') . ' ' . rm((float) $r['amt_' . $k]);
            }
        }
        $edit = url('/admin/receipts/edit') . '?id=' . (int) $r['id'];
      ?>
      <tr>
        <td data-label="相片 Photo" class="thumb-col">
          <?php if ($r['image_path']): ?>
            <a href="<?= $edit ?>"><img class="receipt-thumb" src="<?= url('/admin/receipts/image') ?>?id=<?= (int) $r['id'] ?>" alt="收據相片 Receipt photo" loading="lazy"></a>
          <?php else: ?><span class="help">—</span><?php endif; ?>
        </td>
        <td data-label="號碼 No."><a class="rowlink" href="<?= $edit ?>"><?= $r['receipt_no'] !== null && $r['receipt_no'] !== '' ? 'No. ' . h($r['receipt_no']) : '#' . (int) $r['id'] ?></a>
          <?= $r['source'] === 'ai' ? '<span class="badge ai">🤖 AI</span>' : '' ?></td>
        <td data-label="日期 Date"><?= $r['receipt_date'] ? h(date('d/m/Y', strtotime($r['receipt_date']))) : '—' ?></td>
        <td data-label="姓名 Name"><?= h($r['name'] ?? '—') ?></td>
        <td data-label="項目 Details"><?= h($r['item'] ?? '') ?>
          <?php if ($parts): ?><span class="sub-line"><?= h(implode(' · ', $parts)) ?></span><?php endif; ?></td>
        <td data-label="總數 Total" class="num"><strong><?= rm((float) $r['total']) ?></strong></td>
        <td data-label="付款 Paid by"><?= $payLabel[$r['payment']] ?? '—' ?></td>
        <td data-label="加入 Added"><?= h(date('d/m H:i', strtotime($r['created_at']))) ?>
          <span class="sub-line"><?= h($r['created_by'] ?? '') ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php require BASE_PATH . '/app/Views/partials/pager.php'; ?>
  <?php endif; ?>
</div>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
