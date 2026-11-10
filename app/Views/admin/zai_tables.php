<?php
/** 施齋 Day 3 — 桌子: how many tables, and how many seats at each. */
require BASE_PATH . '/app/Views/layouts/admin_header.php';
require BASE_PATH . '/app/Views/admin/_zai_head.php';
$set = array_filter($seating, static fn($t) => $t['seats'] > 0);
$common = $set ? array_search(max($c = array_count_values(array_column($set, 'seats'))), $c, true) : App\Models\Zai::DEFAULT_SEATS;
?>
<?php if ($copyFrom): ?>
  <?php // A new year starts with no tables: one tap brings last year's hall back. ?>
  <form method="POST" action="<?= url('/admin/zai/tables/copy') ?>" class="panel zai-copy<?= $set ? '' : ' is-empty' ?>">
    <?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
    <div>
      <h2 style="margin:0"><?= icon('refresh') ?> <?= $set ? '用其他活動的桌子' : '新活動：沿用往年的桌子？' ?> <span class="en"><?= $set ? 'Use another event\'s tables' : 'New event: use last year\'s tables?' ?></span></h2>
      <p class="help" style="margin:.3rem 0 0">複製桌號和每桌人數（團體桌標記不複製）。<span class="en">Copies the table numbers and seats (not the group-table marks).</span></p>
    </div>
    <div class="zai-copy-row">
      <select name="from" aria-label="從哪個活動 From which event">
        <?php foreach ($copyFrom as $e): ?>
          <option value="<?= (int) $e['id'] ?>"><?= h($e['year'] . ' · ' . $e['name']) ?><?= $e['is_test'] ? '（測試 Test）' : '' ?> — <?= (int) $e['tables'] ?> 桌 / <?= (int) $e['seats'] ?> 位</option>
        <?php endforeach; ?>
      </select>
      <button class="<?= $set ? 'mini-btn ghost' : 'primary' ?>" type="submit"
        <?= $set ? 'data-confirm="' . h("用所選活動的桌子取代目前的 " . count($set) . " 張桌子？\nReplace this event's tables with the chosen event's?") . '"' : '' ?>><?= icon('refresh') ?> 複製桌子 <span class="en">Copy tables</span></button>
    </div>
  </form>
<?php endif; ?>
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
<div class="panel zai-reset" id="reset">
  <h2 style="margin-top:0"><?= icon('trash') ?> 清空施齋資料 <span class="en">Clear Day 3 (start again)</span></h2>
  <p class="help" style="margin-top:0">
    新一年請先建立新活動：新活動的施齋本來就是空的，往年的資料會保留在往年的活動裡。這裡只用於<b>重來同一個活動</b>（例如演練之後）。<br>
    <span class="en">For a new year, create a new event: its Day 3 starts empty and past years stay with their own event. This is only to <b>start the same event again</b> (e.g. after a rehearsal).</span></p>
  <ul class="zai-reset-list">
    <li>清空 <span class="en">Clears</span>：<b><?= (int) $dayCounts['people'] ?></b> 位已登記的入座／狀態（<?= (int) $dayCounts['seated'] ?> 位有桌）· <b><?= (int) $dayCounts['waiting'] ?></b> 位候補 · <b><?= (int) $dayCounts['groups'] ?></b> 個團體的到達／桌號</li>
    <li>保留 <span class="en">Keeps</span>：桌子設定、團體名單、支票資料、所有報名 <span class="en">— tables, group list, cheque details, registrations</span></li>
  </ul>
  <?php if ($isSystem): ?>
    <form method="POST" action="<?= url('/admin/zai/reset') ?>" class="zai-reset-form">
      <?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
      <label for="resetWord">請輸入「清空」確認 <span class="en">Type 清空 to confirm</span></label>
      <div class="zai-copy-row">
        <input id="resetWord" name="confirm_word" autocomplete="off" placeholder="清空" required style="max-width:10rem">
        <button class="mini-btn danger" type="submit" data-danger
          data-confirm="<?= h("清空 {$event['year']} {$event['name']} 的施齋資料？此操作無法復原。\nClear Day 3 for this event? This cannot be undone.") ?>"
          data-confirm-title="清空施齋資料 Clear Day 3"><?= icon('trash') ?> 清空 <span class="en">Clear</span></button>
      </div>
    </form>
  <?php else: ?>
    <p class="help">只有系統管理員可以清空。<span class="en">Only a system admin can clear it.</span></p>
  <?php endif; ?>
</div>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
