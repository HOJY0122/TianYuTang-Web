<?php
/**
 * 施齋 Day 3 — 團體 groups: organisation registrations (added
 * automatically) and the PM's own list (imported). On arrival: mark
 * arrived, give the table(s); then a reminder to take the cheque photo (on the staff phone, not uploaded).
 */
require BASE_PATH . '/app/Views/layouts/admin_header.php';
require BASE_PATH . '/app/Views/admin/_zai_head.php';
$empty = [];
foreach ($seating as $no => $t) {
    if ($t['seats'] > 0 && $t['used'] === 0) {
        $empty[] = (int) $no;
    }
}
$arrived = count(array_filter($groups, static fn($g) => $g['status'] === 'arrived'));
?>
<div class="panel zai-search">
  <form method="GET" action="<?= url('/admin/zai/groups') ?>" class="lookup-form">
    <input type="hidden" name="event" value="<?= (int) $event['id'] ?>">
    <input type="search" name="q" value="<?= h($q) ?>" autocomplete="off" placeholder="團體名稱 / 電話 / 編號 / 桌號　Group, phone, no. or table" aria-label="搜尋 Search">
    <button class="mini-btn btn-lg" type="submit"><?= icon('search') ?> 查詢 Find</button>
  </form>
  <p class="help" style="margin:6px 0 0"><?= count($groups) ?> 個團體 groups · 已到 arrived <?= $arrived ?>
    <?php if ($empty): ?> · 空桌 Empty tables: <b><?= h(implode(', ', array_slice($empty, 0, 30))) ?><?= count($empty) > 30 ? ' …' : '' ?></b><?php endif; ?></p>
</div>

<?php foreach ($groups as $g): $zid = (int) $g['id']; $done = $g['status'] === 'arrived'; ?>
  <form method="POST" action="<?= url('/admin/zai/group') ?>" class="panel zai-group<?= $done ? ' is-done' : '' ?>" id="zg<?= $zid ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $zid ?>"><input type="hidden" name="q" value="<?= h($q) ?>">
    <div class="zai-reg-head">
      <strong><?= h($g['name']) ?></strong>
      <?php if ($g['source'] === 'rsvp'): ?><span class="badge"><?= icon('form') ?> 網上團體 <?= h($g['ref_code'] ?? '') ?></span>
      <?php else: ?><span class="badge"><?= icon('list') ?> 名單 List<?= $g['list_no'] ? ' #' . h($g['list_no']) : '' ?></span><?php endif; ?>
      <span class="badge <?= $done ? 'ok' : 'pending' ?>"><?= $done ? '✓ 已到 Arrived ' . h(date('H:i', strtotime((string) $g['arrived_at']))) : '未到 Expected' ?></span>
      <?php if ($g['contact']): ?><span class="help"><?= icon('phone') ?> <?= h($g['contact']) ?></span><?php endif; ?>
    </div>
    <div class="zai-fields">
      <label>人數 <span class="en">People</span><input type="number" name="people" min="0" max="5000" value="<?= (int) $g['people'] ?>"></label>
      <label>桌號 <span class="en">Table(s)</span><input name="tables" value="<?= h($g['tables'] ?? '') ?>" placeholder="例 e.g. 5, 6" inputmode="numeric"></label>
      <label>支票號碼 <span class="en">Cheque no.</span><input name="cheque_no" value="<?= h($g['cheque_no'] ?? '') ?>" maxlength="40"></label>
      <label class="wide">備註 <span class="en">Notes</span><input name="notes" value="<?= h($g['notes'] ?? '') ?>" maxlength="500"></label>
    </div>
    <p class="help zai-cheque-note"><?= icon('camera') ?> 按「已到」後，請用手機與支票合照（不用上傳）。<span class="en">After “Arrived”, take the cheque photo on your phone — no upload.</span></p>
    <div class="zai-act">
      <?php if (!$done): ?>
        <button class="mini-btn btn-lg" name="status" value="arrived" type="submit">✓ 已到，儲存 <span class="en">Arrived — save</span></button>
        <button class="mini-btn ghost" name="status" value="expected" type="submit"><?= icon('save') ?> 只儲存 <span class="en">Save only</span></button>
      <?php else: ?>
        <button class="mini-btn btn-lg" name="status" value="arrived" type="submit"><?= icon('save') ?> 儲存 <span class="en">Save</span></button>
        <button class="mini-btn ghost" name="status" value="expected" type="submit" title="改回未到 Mark not arrived">↺ 未到 <span class="en">Not arrived</span></button>
      <?php endif; ?>
      <?php if ($g['source'] === 'list'): ?>
        <button class="mini-btn danger" type="submit" form="del-<?= $zid ?>" title="刪除 Delete"><?= icon('trash') ?></button>
      <?php endif; ?>
    </div>
  </form>
  <?php if ($g['source'] === 'list'): ?>
    <form method="POST" action="<?= url('/admin/zai/groups/delete') ?>" id="del-<?= $zid ?>" hidden
          data-confirm="從名單刪除「<?= h($g['name']) ?>」？&#10;Delete this group from the list?" data-danger><?= csrf_field() ?><input type="hidden" name="id" value="<?= $zid ?>"></form>
  <?php endif; ?>
<?php endforeach; ?>
<?php if (!$groups): ?>
  <div class="panel"><p class="empty" style="margin:0"><?= $q !== '' ? '找不到。Nothing found.' : '還沒有團體。網上的團體登記會自動出現；PM 的名單可在下面匯入。No groups yet — organisation registrations appear here automatically; import the PM\'s list below.' ?></p></div>
<?php endif; ?>

<details class="panel form-panel" style="max-width:none"<?= !$groups ? ' open' : '' ?>>
  <summary><strong><?= icon('upload') ?> 匯入 PM 的團體名單 <span class="en">Import the PM's group list</span></strong></summary>
  <form method="POST" action="<?= url('/admin/zai/groups/import') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
    <p class="help">欄位：團體名稱、人數、聯絡電話、編號、備註（有標題列最好；沒有的話依次為 名稱、人數、電話、備註）。<br>
      <span class="en">Columns: group name, people, contact, no., notes (with a heading row; without one: name, people, phone, notes).</span></p>
    <input type="file" name="sheet" accept=".xlsx,.xlsm,.csv,.txt">
    <label for="zPaste">或從 Excel 複製貼上 <span class="en">…or paste from Excel</span></label>
    <textarea id="zPaste" name="paste" rows="4" placeholder="團體名稱&#9;人數&#9;聯絡電話"></textarea>
    <div class="form-actions"><button class="primary" type="submit"><?= icon('upload') ?> 匯入 Import</button></div>
  </form>
</details>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
