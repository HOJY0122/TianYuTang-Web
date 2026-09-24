<?php
require BASE_PATH . '/app/Views/layouts/admin_header.php';
require BASE_PATH . '/app/Views/partials/event_bar.php';

$eid        = (int) $event['id'];
$pagerBase  = '/admin/registrations';
$pagerQuery = array_filter(['event' => $eid] + $filters, static fn($v) => $v !== '' && $v !== null);
$here       = $pagerBase . '?' . http_build_query($pagerQuery + ['page' => $page]);
$statusText = ['pending' => '待確認 Pending', 'confirmed' => '已確認 Confirmed', 'cancelled' => '已取消 Cancelled'];
?>
<div class="panel">
  <form method="GET" action="<?= url('/admin/registrations') ?>" class="toolbar">
    <input type="hidden" name="event" value="<?= $eid ?>">
    <input type="hidden" name="sort" value="<?= h($filters['sort']) ?>"><input type="hidden" name="dir" value="<?= h($filters['dir']) ?>">
    <label class="field">搜尋 Search
      <input type="search" name="q" value="<?= h($filters['q']) ?>" placeholder="姓名、電話、證件或編號 Name, phone, IC, ref">
    </label>
    <label class="field">狀態 Status
      <select name="status">
        <option value="">全部 All</option>
        <?php foreach ($statusText as $k => $label): ?>
          <option value="<?= $k ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field">來源 Source
      <select name="source">
        <option value="">全部 All</option>
        <option value="online"<?= $filters['source'] === 'online' ? ' selected' : '' ?>>線上 Online</option>
        <option value="walkin"<?= $filters['source'] === 'walkin' ? ' selected' : '' ?>>現場 Walk-in</option>
      </select>
    </label>
    <button class="mini-btn btn-lg" type="submit">🔍 搜尋 Search</button>
    <?php if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['source'] !== ''): ?>
      <a class="mini-btn ghost btn-lg" href="<?= url('/admin/registrations') ?>?event=<?= $eid ?>">清除 Clear</a>
    <?php endif; ?>
    <span class="spacer"></span>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/export/attendees-excel') ?>?event=<?= $eid ?>">📊 Excel</a>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/export/attendees') ?>?event=<?= $eid ?>">⬇️ CSV</a>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/walkin') ?>?event=<?= $eid ?>">＋ 現場報名 Walk-in</a>
  </form>

  <p class="help" style="margin:0 0 8px">共 <?= number_format($total) ?> 筆 · <?= number_format($total) ?> registration<?= $total === 1 ? '' : 's' ?></p>

  <?php if (!$groups): ?>
    <p class="empty">沒有符合的報名。No matching registrations.</p>
  <?php else: ?>
  <table class="records">
    <thead><tr>
      <?= sort_th('編號 Ref', 'ref', $pagerQuery, $pagerBase) ?><?= sort_th('聯絡人 Contact', 'name', $pagerQuery, $pagerBase, 'asc') ?>
      <?= sort_th('人數 People', 'people', $pagerQuery, $pagerBase) ?><?= sort_th('狀態 Status', 'status', $pagerQuery, $pagerBase, 'asc') ?>
      <th>報到 Arrived</th><th>操作 Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($groups as $g): ?>
      <tr>
        <td data-label="編號 Ref"><a class="rowlink" href="<?= url('/admin/registrations/edit') ?>?id=<?= (int) $g['id'] ?>"><?= h($g['ref_code']) ?></a>
          <span class="sub-line"><?= h(date('Y-m-d H:i', strtotime($g['created_at']))) ?></span></td>
        <td data-label="聯絡人 Contact"><?= h($g['lead_name']) ?><span class="sub-line"><?= h($g['lead_contact']) ?></span></td>
        <td data-label="人數 People"><?= (int) $g['attendee_count'] ?>
          <?= $g['source'] === 'walkin' ? '<span class="badge walkin">現場 Walk-in</span>' : '' ?></td>
        <td data-label="狀態 Status"><span class="badge <?= $g['status'] === 'confirmed' ? 'ok' : h($g['status']) ?>"><?= h($statusText[$g['status']]) ?></span></td>
        <td data-label="報到 Arrived"><?= (int) $g['arrived'] ?> / <?= (int) $g['attendee_count'] ?></td>
        <td data-label="操作 Actions">
          <div class="actions-cell">
            <a class="mini-btn" href="<?= url('/admin/registrations/edit') ?>?id=<?= (int) $g['id'] ?>">✏️ 編輯 Edit</a>
            <?php if ($g['status'] !== 'confirmed'): ?>
              <form method="POST" action="<?= url('/admin/registrations/status') ?>" style="margin:0">
                <?= csrf_field() ?><input type="hidden" name="group_id" value="<?= (int) $g['id'] ?>">
                <input type="hidden" name="status" value="confirmed"><input type="hidden" name="return" value="<?= h($here) ?>">
                <button class="mini-btn ghost" type="submit">✓ 確認 Confirm</button>
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
