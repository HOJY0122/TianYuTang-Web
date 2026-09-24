<?php
require BASE_PATH . '/app/Views/layouts/admin_header.php';
require BASE_PATH . '/app/Views/partials/event_bar.php';

$eid        = (int) $event['id'];
$pagerBase  = '/admin/donations';
$pagerQuery = array_filter(['event' => $eid] + $filters, static fn($v) => $v !== '' && $v !== null);
$here       = $pagerBase . '?' . http_build_query($pagerQuery + ['page' => $page]);
?>
<div class="kpis">
  <div class="kpi"><div class="k-label">布施總額<span class="en">Total pledged</span></div><div class="k-value"><?= rm_compact($sum) ?></div></div>
  <div class="kpi"><div class="k-label">已收款<span class="en">Received</span></div><div class="k-value"><?= rm_compact($paid) ?></div></div>
  <div class="kpi"><div class="k-label">未收款<span class="en">Outstanding</span></div><div class="k-value"><?= rm_compact(max(0, $sum - $paid)) ?></div></div>
</div>

<div class="panel">
  <form method="GET" action="<?= url('/admin/donations') ?>" class="toolbar">
    <input type="hidden" name="event" value="<?= $eid ?>">
    <input type="hidden" name="sort" value="<?= h($filters['sort']) ?>"><input type="hidden" name="dir" value="<?= h($filters['dir']) ?>">
    <label class="field">搜尋 Search
      <input type="search" name="q" value="<?= h($filters['q']) ?>" placeholder="姓名、電話或編號 Name, phone, ref">
    </label>
    <label class="field">付款 Payment
      <select name="status">
        <option value="">全部 All</option>
        <option value="pending"<?= $filters['status'] === 'pending' ? ' selected' : '' ?>>待付 Pending</option>
        <option value="paid"<?= $filters['status'] === 'paid' ? ' selected' : '' ?>>已付 Paid</option>
      </select>
    </label>
    <label class="field">來源 Source
      <select name="source">
        <option value="">全部 All</option>
        <option value="online"<?= $filters['source'] === 'online' ? ' selected' : '' ?>>線上 Online</option>
        <option value="counter"<?= $filters['source'] === 'counter' ? ' selected' : '' ?>>現場 Counter</option>
      </select>
    </label>
    <button class="mini-btn btn-lg" type="submit">🔍 搜尋 Search</button>
    <?php if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['source'] !== ''): ?>
      <a class="mini-btn ghost btn-lg" href="<?= url('/admin/donations') ?>?event=<?= $eid ?>">清除 Clear</a>
    <?php endif; ?>
    <span class="spacer"></span>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/export/donations-excel') ?>?event=<?= $eid ?>">📊 Excel</a>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/export/donations') ?>?event=<?= $eid ?>">⬇️ CSV</a>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/counter') ?>?event=<?= $eid ?>">＋ 現場布施 Counter</a>
  </form>

  <p class="help" style="margin:0 0 8px">共 <?= number_format($total) ?> 筆 · <?= number_format($total) ?> donation<?= $total === 1 ? '' : 's' ?></p>

  <?php if (!$rows): ?>
    <p class="empty">沒有符合的布施。No matching donations.</p>
  <?php else: ?>
  <table class="records">
    <thead><tr><?= sort_th('編號 Ref', 'ref', $pagerQuery, $pagerBase) ?><?= sort_th('姓名 Name', 'name', $pagerQuery, $pagerBase, 'asc') ?><th>內容 Details</th>
      <?= sort_th('金額 Amount', 'amount', $pagerQuery, $pagerBase, 'desc', 'num') ?><?= sort_th('付款 Payment', 'status', $pagerQuery, $pagerBase, 'asc') ?><th>操作 Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $d): ?>
      <tr>
        <td data-label="編號 Ref"><a class="rowlink" href="<?= url('/admin/donations/edit') ?>?id=<?= (int) $d['id'] ?>"><?= h($d['ref_code']) ?></a>
          <span class="sub-line"><?= h(date('Y-m-d H:i', strtotime($d['created_at']))) ?></span></td>
        <td data-label="姓名 Name"><?= h($d['name']) ?><span class="sub-line"><?= h($d['contact_no']) ?></span></td>
        <td data-label="內容 Details"><?= h(App\Models\Donation::describe($d)) ?>
          <?= $d['source'] === 'counter' ? '<span class="badge counter">現場 Counter</span>' : '' ?>
          <?php if (!empty($d['receipt_path'])): ?><a href="<?= url('/admin/receipt') ?>?id=<?= (int) $d['id'] ?>" target="_blank">📄 收據 Receipt</a><?php endif; ?></td>
        <td data-label="金額 Amount" class="num"><strong><?= rm((float) $d['amount']) ?></strong></td>
        <td data-label="付款 Payment"><?= $d['status'] === 'paid' ? '<span class="badge ok">✓ 已付 Paid</span>' : '<span class="badge pending">待付 Pending</span>' ?></td>
        <td data-label="操作 Actions">
          <div class="actions-cell">
            <a class="mini-btn" href="<?= url('/admin/donations/edit') ?>?id=<?= (int) $d['id'] ?>">✏️ 編輯 Edit</a>
            <?php if ($d['status'] !== 'paid'): ?>
              <form method="POST" action="<?= url('/admin/donations/paid') ?>" style="margin:0"
                    data-confirm="確認已收到 <?= h(rm((float) $d['amount'])) ?>？&#10;Confirm payment received?">
                <?= csrf_field() ?><input type="hidden" name="donation_id" value="<?= (int) $d['id'] ?>">
                <input type="hidden" name="return" value="<?= h($here) ?>">
                <button class="mini-btn ghost" type="submit">💵 已收款 Mark paid</button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php require BASE_PATH . '/app/Views/partials/pager.php'; ?>
  <?php endif; ?>
</div>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
