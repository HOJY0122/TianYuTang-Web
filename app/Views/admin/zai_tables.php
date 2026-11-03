<?php
/** 施齋 Day 3 — 桌子: how many tables, and how many seats at each. */
require BASE_PATH . '/app/Views/layouts/admin_header.php';
require BASE_PATH . '/app/Views/admin/_zai_head.php';
$set = array_filter($seating, static fn($t) => $t['seats'] > 0);
$common = $set ? array_search(max($c = array_count_values(array_column($set, 'seats'))), $c, true) : App\Models\Zai::DEFAULT_SEATS;
?>
<form method="POST" action="<?= url('/admin/zai/tables') ?>" class="panel form-panel" style="max-width:none">
  <?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
  <h2 style="margin-top:0"><?= icon('settings') ?> 桌數與座位 <span class="en">Tables and seats</span></h2>
  <div class="form-row">
    <div><label for="zCount">桌數 <span class="en">Number of tables</span></label>
      <input id="zCount" type="number" name="count" min="0" max="<?= App\Models\Zai::MAX_TABLES ?>" value="<?= count($set) ?>"></div>
    <div><label for="zSeats">每桌人數 <span class="en">Seats per table (max)</span></label>
      <input id="zSeats" type="number" name="seats" min="1" max="50" value="<?= (int) $common ?>"></div>
  </div>
  <label class="check-inline" style="margin-top:12px"><input type="checkbox" name="reset_seats" value="1"<?= $set ? '' : ' checked' ?>>
    所有桌都用這個人數（取消個別設定）<span class="en">Use this for every table (undo per-table changes)</span></label>
  <p class="help">桌號從 1 開始。減少桌數會移除號碼較大的桌；已安排的人保留原桌號，總覽會標示。<br>
    <span class="en">Tables are numbered from 1. Fewer tables removes the highest numbers; people already there keep the number and are flagged in the overview.</span></p>

  <?php if ($set): ?>
    <h3>個別桌子 <span class="en">Each table</span></h3>
    <p class="help">某張桌坐不同人數（例如主桌 12 人）可在這裡改。<span class="en">A table with a different size (e.g. a head table of 12) can be changed here.</span></p>
    <div class="zai-seat-grid">
      <?php foreach ($set as $no => $t): ?>
        <label><b><?= (int) $no ?></b><input type="number" name="table[<?= (int) $no ?>]" min="1" max="50" value="<?= (int) $t['seats'] ?>" aria-label="第 <?= (int) $no ?> 桌座位 Seats at table <?= (int) $no ?>">
          <small><?= (int) $t['used'] ?> 已坐</small></label>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="form-actions"><button class="primary" type="submit"><?= icon('save') ?> 儲存 Save</button></div>
</form>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
