<?php
/**
 * 收據紀錄 Receipts — the paper receipt book, searchable and sortable.
 */
use App\Core\ReceiptReader;

require BASE_PATH . '/app/Views/layouts/admin_header.php';
$pagerBase  = '/admin/receipts';
$pagerQuery = array_filter($filters, static fn($v) => $v !== '' && $v !== null);
$payLabel   = ['cash' => '現金 Cash', 'bank' => '轉帳 Bank-in', '' => '—'];
$holderOf   = [];
foreach ($books as $b) {
    if ($b['book_no'] !== null && $b['holder']) {
        $holderOf[$b['book_no']] = $b['holder'];
    }
}
$filtered   = array_diff_key($pagerQuery, ['sort' => 1, 'dir' => 1]) !== [];
$bookUrl    = static fn(?string $b): string => url('/admin/receipts') . '?' . http_build_query(
    ['book' => $b ?? '-', 'sort' => 'no', 'dir' => 'asc'] + array_intersect_key($pagerQuery, ['check' => 1]));
?>
<div class="kpis" id="liveKpis" data-live="receipts">
  <div class="kpi"><div class="k-label">收據張數<span class="en">Receipts</span></div><div class="k-value"><?= number_format((int) $sum['n']) ?></div></div>
  <div class="kpi"><div class="k-label">總額<span class="en">Total</span></div><div class="k-value"><?= rm_compact((float) $sum['total']) ?></div></div>
  <?php if ((int) $sum['to_check'] > 0): ?>
    <a class="kpi" href="<?= url('/admin/receipts') . '?' . h(http_build_query(['check' => '1', 'sort' => 'book', 'dir' => 'asc'] + array_intersect_key($pagerQuery, ['book' => 1]))) ?>">
      <div class="k-label">待核對<span class="en">To check</span></div><div class="k-value warn-text"><?= number_format((int) $sum['to_check']) ?></div></a>
  <?php endif; ?>
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
    <label class="field">簿號 Book
      <select name="book">
        <option value="">全部 All</option>
        <?php foreach ($books as $b): ?>
          <?php $bv = $b['book_no'] ?? '-'; ?>
          <option value="<?= h($bv) ?>"<?= $filters['book'] === $bv ? ' selected' : '' ?>><?= $b['book_no'] !== null ? h($b['book_no']) : '— 未填 None' ?> (<?= (int) $b['n'] ?>)</option>
        <?php endforeach; ?>
        <?php if ($filters['book'] !== '' && !in_array($filters['book'], array_map(static fn($b) => $b['book_no'] ?? '-', $books), true)): ?>
          <option value="<?= h($filters['book']) ?>" selected><?= h($filters['book']) ?> (0)</option>
        <?php endif; ?>
      </select>
    </label>
    <?php if ($holders): ?>
    <label class="field">負責人 In charge
      <select name="holder">
        <option value="">全部 All</option>
        <?php foreach ($holders as $hn): ?>
          <option value="<?= h($hn) ?>"<?= $filters['holder'] === $hn ? ' selected' : '' ?>><?= h($hn) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php endif; ?>
    <label class="field">號碼由 No. from <input name="no_from" value="<?= h($filters['no_from']) ?>" inputmode="numeric" pattern="[0-9]*" maxlength="12" size="7" placeholder="26400"></label>
    <label class="field">至 to <input name="no_to" value="<?= h($filters['no_to']) ?>" inputmode="numeric" pattern="[0-9]*" maxlength="12" size="7" placeholder="26450"></label>
    <label class="field">狀態 Status
      <select name="check">
        <option value="">全部 All</option>
        <option value="1"<?= $filters['check'] === '1' ? ' selected' : '' ?>>待核對 To check</option>
        <option value="0"<?= $filters['check'] === '0' ? ' selected' : '' ?>>已核對 Checked</option>
      </select>
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
    <button class="mini-btn btn-lg" type="submit"><?= icon('search') ?> 搜尋 Search</button>
    <?php if ($filtered): ?>
      <a class="mini-btn ghost btn-lg" href="<?= url('/admin/receipts') ?>">清除 Clear</a>
    <?php endif; ?>
    <span class="spacer"></span>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/receipts/excel') . '?' . h(http_build_query($pagerQuery)) ?>"><?= icon('chart') ?> Excel</a>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/receipts/bulk') . ($filters['book'] !== '' && $filters['book'] !== '-' ? '?book=' . h(rawurlencode($filters['book'])) : '') ?>"><?= icon('list') ?> 整本上傳 Upload a book</a>
    <a class="mini-btn btn-lg" href="<?= url('/admin/receipts/new') ?>"><?= $aiReady ? 'AI 掃描收據 AI scan receipt' : '新增收據 Add receipt' ?></a>
  </form>
</div>

<details class="panel guide book-summary"<?= $filters['book'] !== '' || count($books) > 1 ? ' open' : '' ?>>
  <summary><span><?= icon('list') ?> 按簿號分組 <span class="en">By receipt book</span> · <?= count($books) ?></span><span class="guide-toggle" aria-hidden="true">顯示 Show ▾</span></summary>
  <table class="records book-table">
    <thead><tr><th>簿號 Book</th><th>負責人 In charge</th><th class="num">張數 Receipts</th><th>號碼範圍 Numbers</th><th>欠缺 / 重複 Missing / repeated</th><th class="num">待核對 To check</th><th class="num">總額 Total</th></tr></thead>
    <tbody>
    <?php foreach ($books as $b): ?>
      <?php $current = $filters['book'] === ($b['book_no'] ?? '-'); ?>
      <tr class="book-row<?= $current ? ' is-current' : '' ?>">
        <td data-label="簿號 Book"><a class="rowlink" href="<?= h($bookUrl($b['book_no'])) ?>"><?= $b['book_no'] !== null ? '<span class="book-chip">' . h($b['book_no']) . '</span>' : '— 未填簿號 No book' ?></a></td>
        <td data-label="負責人 In charge"><?php if ($b['book_no'] === null): ?><span class="help">—</span>
          <?php elseif ($b['holder']): ?><strong><?= h($b['holder']) ?></strong><?= $b['holder_phone'] ? '<span class="sub-line"><a href="tel:' . h(preg_replace('/[^0-9+]/', '', $b['holder_phone'])) . '">' . h($b['holder_phone']) . '</a></span>' : '' ?>
          <?php else: ?><a class="warn-text" href="<?= h($bookUrl($b['book_no'])) ?>#bookHolder">未填 Not set</a><?php endif; ?></td>
        <td data-label="張數 Receipts" class="num"><?= number_format((int) $b['n']) ?></td>
        <td data-label="號碼範圍 Numbers"><?= $b['lo'] !== null ? h($b['lo']) . ' – ' . h($b['hi']) : '<span class="help">未有號碼 No numbers yet</span>' ?></td>
        <td data-label="欠缺 / 重複 Missing / repeated">
          <?php if ($b['lo'] === null): ?><span class="help">—</span>
          <?php elseif (!$b['missing'] && !$b['repeats']): ?><span class="ok-text"><?= icon('check') ?> 齊全 Complete</span>
          <?php else: ?>
            <?= $b['missing'] ? '<span class="warn-text">欠 ' . number_format($b['missing']) . ' 張 missing</span>' : '' ?>
            <?= $b['repeats'] ? '<span class="warn-text">重複 ' . (int) $b['repeats'] . ' repeated</span>' : '' ?>
          <?php endif; ?>
        </td>
        <td data-label="待核對 To check" class="num"><?= (int) $b['to_check'] ? '<span class="warn-text">' . (int) $b['to_check'] . '</span>' : '0' ?></td>
        <td data-label="總額 Total" class="num"><?= rm((float) $b['total']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <form method="POST" action="<?= url('/admin/receipts/book') ?>" class="toolbar book-register">
    <?= csrf_field() ?>
    <strong class="book-register-title"><?= icon('plus') ?> 登記收據簿 <span class="en">Hand out a book</span></strong>
    <label class="field">簿號 Book <input name="book_no" maxlength="30" required size="6" placeholder="12" autocomplete="off"></label>
    <label class="field">負責人 In charge <input name="holder" maxlength="100" required placeholder="陳大文" autocomplete="off"></label>
    <label class="field">電話 Phone <input name="holder_phone" maxlength="30" inputmode="tel" size="12" placeholder="012-345 6789" autocomplete="off"></label>
    <button class="mini-btn btn-lg" type="submit"><?= icon('save') ?> 儲存 Save</button>
  </form>
</details>

<?php if ($gaps !== null): ?>
  <?php $bookName = $filters['book'] === '-' ? '未填簿號 No book' : '簿 Book ' . $filters['book']; ?>
  <div class="panel book-gaps">
    <h2 style="margin:0 0 6px"><?= icon('clipboard') ?> <?= h($bookName) ?></h2>
    <?php if ($bookInfo !== null): ?>
      <form method="POST" action="<?= url('/admin/receipts/book') ?>" class="toolbar book-holder" id="bookHolder">
        <?= csrf_field() ?>
        <input type="hidden" name="book_no" value="<?= h($bookInfo['book_no']) ?>">
        <label class="field">負責人 Member in charge <input name="holder" value="<?= h($bookInfo['holder'] ?? '') ?>" maxlength="100" placeholder="未填 Not set" autocomplete="off"></label>
        <label class="field">電話 Phone <input name="holder_phone" value="<?= h($bookInfo['holder_phone'] ?? '') ?>" maxlength="30" inputmode="tel" size="12" autocomplete="off"></label>
        <button class="mini-btn btn-lg" type="submit"><?= icon('save') ?> 儲存 Save</button>
      </form>
    <?php endif; ?>
    <?php if ($gaps['wide']): ?>
      <p class="warn-text">號碼範圍太闊，可能有號碼讀錯，請按號碼排序檢查最大及最小的號碼。<span class="en">The number range is very wide — a number was probably misread. Sort by No. and check the highest and lowest.</span></p>
    <?php elseif ($gaps['missing']): ?>
      <p style="margin:0">欠缺號碼 <span class="en">Missing numbers</span> (<?= count($gaps['missing']) ?>)：
        <span class="gap-list warn-text"><?= h(implode(', ', array_slice($gaps['missing'], 0, 120))) ?><?= count($gaps['missing']) > 120 ? ' …' : '' ?></span></p>
    <?php else: ?>
      <p class="ok-text" style="margin:0"><?= icon('check') ?> 號碼連續，沒有欠缺。<span class="en">No gaps in the numbers.</span></p>
    <?php endif; ?>
    <?php if ($gaps['repeats']): ?>
      <p style="margin:6px 0 0">重複號碼 <span class="en">Numbers used twice</span>：<span class="warn-text"><?= h(implode(', ', $gaps['repeats'])) ?></span></p>
    <?php endif; ?>
    <?php if ($unread && $aiReady && $filters['book'] !== '-'): ?>
      <p style="margin:10px 0 0"><a class="mini-btn btn-lg" href="<?= url('/admin/receipts/bulk') . '?book=' . h(rawurlencode($filters['book'])) . '&amp;resume=1' ?>"><?= icon('bot') ?> AI 讀取未讀的 <?= (int) $unread ?> 張 <span class="en">AI-read the <?= (int) $unread ?> unread</span></a></p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="panel">
  <div id="liveList" data-live="receipts">

  <?php if (!$aiReady): ?>
    <p class="flash info" style="margin:0 0 12px"><?= icon('bot') ?> AI 讀取尚未啟用，可以照常拍照並手動輸入。AI reading is off — you can still attach photos and type receipts in.<br>
      <?php if (!empty($isSystem)): ?>
        <?= nl2br(h(ReceiptReader::whyOff())) ?><br>
        <?= icon('arrow-right') ?> <a href="<?= url('/system') ?>#ai">到「網站設定 → ⑤ AI」貼上金鑰並測試連線 Set the key in Site settings → ⑤ AI</a>
      <?php else: ?>
        請系統管理員在「網站設定 → ⑤ AI」設定金鑰。Ask a system admin to set the key in Site settings → ⑤ AI.
      <?php endif; ?></p>
  <?php endif; ?>

  <p class="help" style="margin:0 0 8px">共 <?= number_format((int) $sum['n']) ?> 張 · <?= number_format((int) $sum['n']) ?> receipt<?= (int) $sum['n'] === 1 ? '' : 's' ?>
    · 點欄位標題可排序 Tap a column heading to sort</p>

  <?php if (!$rows): ?>
    <p class="empty">還沒有收據。按「掃描收據」加入第一張。No receipts yet — tap “Scan receipt” to add one.</p>
  <?php else: ?>
  <div class="table-scroll">
  <table class="records">
    <thead><tr>
      <th class="thumb-col">相片 Photo</th>
      <?= sort_th('簿號 Book', 'book', $pagerQuery + $filters, $pagerBase, 'asc') ?>
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
        <td data-label="簿號 Book"><?= $r['book_no'] !== null ? '<a href="' . h($bookUrl($r['book_no'])) . '" class="book-chip">' . h($r['book_no']) . '</a>'
            . (isset($holderOf[$r['book_no']]) ? '<span class="sub-line">' . h($holderOf[$r['book_no']]) . '</span>' : '') : '<span class="help">—</span>' ?></td>
        <td data-label="號碼 No."><a class="rowlink" href="<?= $edit ?>"><?= $r['receipt_no'] !== null && $r['receipt_no'] !== '' ? 'No. ' . h($r['receipt_no']) : '#' . (int) $r['id'] ?></a>
          <?= $r['source'] === 'ai' ? '<span class="badge ai">AI</span>' : '' ?>
          <?= (int) $r['needs_check'] ? '<span class="sub-line"><span class="badge check">待核對 To check</span></span>' : '' ?></td>
        <td data-label="日期 Date"><?= $r['receipt_date'] ? h(date('d/m/Y', strtotime($r['receipt_date']))) : '—' ?></td>
        <td data-label="姓名 Name"><?= h($r['name'] ?? '—') ?></td>
        <td data-label="項目 Details"><?= h($r['item'] ?? '') ?>
          <?php if ($parts): ?><span class="sub-line"><?= h(implode(' · ', $parts)) ?></span><?php endif; ?></td>
        <td data-label="總數 Total" class="num"><strong><?= rm((float) $r['total']) ?></strong></td>
        <td data-label="付款 Paid by"><?= $payLabel[$r['payment']] ?? '—' ?>
          <?php if (!empty($r['bank_slip_path'])): ?>
            <a class="sub-line" href="<?= url('/admin/receipts/image') ?>?id=<?= (int) $r['id'] ?>&amp;slip=1" target="_blank"><?= icon('paperclip') ?> 單據 Slip</a>
          <?php elseif ($r['payment'] === 'bank'): ?>
            <span class="sub-line warn-text">未附單據 No slip</span>
          <?php endif; ?></td>
        <td data-label="加入 Added"><?= h(date('d/m H:i', strtotime($r['created_at']))) ?>
          <span class="sub-line"><?= h($r['created_by'] ?? '') ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php require BASE_PATH . '/app/Views/partials/pager.php'; ?>
  <?php endif; ?>
  </div>
</div>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
