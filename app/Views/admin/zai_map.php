<?php
/**
 * 施齋 Day 3 — 平面圖 Table map: the tables in rows (4 per row by default),
 * like the hall. Each shows its type (團體桌 group / 散客桌 general), seats
 * used, and who sits or is booked there. Tap a table to change it:
 * make it a group table, pre-assign a group, or change its seats.
 */
require BASE_PATH . '/app/Views/layouts/admin_header.php';
require BASE_PATH . '/app/Views/admin/_zai_head.php';
$tables = array_filter($seating, static fn($t) => $t['seats'] > 0);
$groupTables = count(array_filter($tables, static fn($t) => $t['kind'] === 'group'));
$groupName = [];
foreach ($groups as $g) {
    $groupName[(int) $g['id']] = $g;
}
?>
<div class="panel zai-map-head">
  <div class="zai-map-legend">
    <span><i class="lg lg-open"></i> 散客桌 <span class="en">General</span></span>
    <span><i class="lg lg-group"></i> 團體桌 <span class="en">Group table</span></span>
    <span><i class="lg lg-res"></i> 已預留，未到 <span class="en">Booked, not here yet</span></span>
    <span><i class="lg lg-full"></i> 坐滿 <span class="en">Full</span></span>
    <span><i class="lg lg-over"></i> 超出 <span class="en">Over</span></span>
  </div>
  <form method="POST" action="<?= url('/admin/zai/map/cols') ?>" class="zai-map-cols">
    <?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
    <label>每行 <span class="en">Per row</span>
      <select name="cols" onchange="this.form.submit()">
        <?php for ($c = 2; $c <= 10; $c++): ?><option value="<?= $c ?>"<?= $c === $cols ? ' selected' : '' ?>><?= $c ?> 張 tables</option><?php endfor; ?>
      </select></label>
  </form>
  <p class="help" style="margin:6px 0 0">共 <?= count($tables) ?> 張桌，其中團體桌 <?= $groupTables ?> 張。按一張桌來設定。
    <span class="en"><?= count($tables) ?> tables, <?= $groupTables ?> for groups. Tap a table to set it up.</span></p>
</div>

<?php if ($edit !== null): $t = $seating[$edit]; ?>
  <div class="panel zai-map-edit" id="t<?= $edit ?>">
    <div class="zai-map-edit-head">
      <h2 style="margin:0">第 <?= $edit ?> 桌 <span class="en">Table <?= $edit ?></span></h2>
      <span class="badge <?= $t['kind'] === 'group' ? 'helper' : 'ok' ?>"><?= $t['kind'] === 'group' ? '團體桌 Group table' : '散客桌 General' ?></span>
      <span class="help"><?= (int) $t['used'] ?> / <?= (int) $t['seats'] ?> 位 seats</span>
      <a class="mini-btn ghost" href="<?= url('/admin/zai/map') . $evq ?>">× 關閉 Close</a>
    </div>
    <form method="POST" action="<?= url('/admin/zai/map/table') ?>" class="zai-map-form">
      <?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><input type="hidden" name="table_no" value="<?= $edit ?>">
      <div class="seg-toggle" role="radiogroup" aria-label="桌子用途 What the table is for">
        <label class="seg-on"><input type="radio" name="kind" value="open"<?= $t['kind'] === 'open' ? ' checked' : '' ?>><span>散客桌 <small>網上 / 現場 / WhatsApp</small></span></label>
        <label class="seg-on"><input type="radio" name="kind" value="group"<?= $t['kind'] === 'group' ? ' checked' : '' ?>><span>團體桌 <small>Group</small></span></label>
      </div>
      <label class="zai-map-seats">座位 <span class="en">Seats</span><input type="number" name="seats" min="1" max="50" value="<?= (int) $t['seats'] ?>"></label>
      <button class="mini-btn" type="submit"><?= icon('save') ?> 儲存 Save</button>
    </form>

    <h3>坐在這桌 <span class="en">At this table</span></h3>
    <?php if (!$t['who']): ?><p class="help">空桌 Empty</p><?php endif; ?>
    <ul class="zai-map-who">
      <?php foreach ($t['who'] as [$k, $label, $n, $x]): ?>
        <li><span class="zai-kind k-<?= $k ?>"><?= ['person' => '已登記', 'group' => '團體', 'waiting' => '候補'][$k] ?></span>
          <?= h($label) ?><?= $n > 1 ? ' × ' . (int) $n : '' ?>
          <?php if ($k === 'group'): ?>
            <?= !empty($x['arrived']) ? '<span class="badge ok">已到</span>' : '<span class="badge pending">預留 Booked</span>' ?>
            <form method="POST" action="<?= url('/admin/zai/map/table') ?>" style="display:inline;margin:0">
              <?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><input type="hidden" name="table_no" value="<?= $edit ?>">
              <input type="hidden" name="remove_group" value="<?= (int) $x['id'] ?>">
              <button class="mini-btn ghost" type="submit" title="從這桌移走 Take off this table">× 移走</button></form>
          <?php endif; ?></li>
      <?php endforeach; ?>
    </ul>

    <h3>預先安排團體 <span class="en">Pre-assign a group</span></h3>
    <form method="POST" action="<?= url('/admin/zai/map/table') ?>" class="zai-map-form">
      <?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><input type="hidden" name="table_no" value="<?= $edit ?>">
      <select name="add_group" required aria-label="團體 Group">
        <option value="">選團體 Choose a group</option>
        <?php foreach ($groups as $g): $has = in_array($edit, App\Models\Zai::tableList((string) ($g['tables'] ?? '')), true); if ($has) continue; ?>
          <option value="<?= (int) $g['id'] ?>"><?= h($g['name']) ?> · <?= (int) $g['people'] ?> 人<?= $g['tables'] ? ' · 已有第 ' . h($g['tables']) . ' 桌' : ' · 未安排' ?></option>
        <?php endforeach; ?>
      </select>
      <button class="mini-btn" type="submit"><?= icon('plus') ?> 安排到這桌 Assign here</button>
    </form>
    <p class="help">安排後這桌會變成團體桌。人多的團體可以安排幾張桌，會依次坐滿。<span class="en">The table becomes a group table. A big group can have several tables; they fill in order.</span></p>
  </div>
<?php endif; ?>

<?php if (!$tables): ?>
  <div class="panel"><p class="empty" style="margin:0">還沒有桌子。No tables yet.</p></div>
<?php else: ?>
<div class="panel zai-map-wrap">
  <div class="zai-map" style="--cols:<?= (int) $cols ?>" id="liveZaiMap" data-live="zai_people zai_groups zai_waiting zai_tables">
    <?php foreach ($tables as $no => $t):
      $left = $t['seats'] - $t['used'];
      $state = $t['used'] === 0 ? 'empty' : ($left > 0 ? 'part' : ($left === 0 ? 'full' : 'over'));
      $names = array_map(static fn($w) => $w[1] . ($w[2] > 1 ? ' ×' . $w[2] : ''), $t['who']);
      $pct = $t['seats'] ? min(100, round($t['used'] / $t['seats'] * 100)) : 0; ?>
      <a class="zai-cell kind-<?= $t['kind'] ?> st-<?= $state ?><?= $t['reserved'] ? ' has-res' : '' ?><?= $edit === $no ? ' is-edit' : '' ?>"
         href="<?= url('/admin/zai/map') . $evq ?>&amp;table=<?= (int) $no ?>#t<?= (int) $no ?>" title="第 <?= (int) $no ?> 桌">
        <span class="zc-top"><b><?= (int) $no ?></b><em><?= $t['kind'] === 'group' ? '團體' : '散客' ?></em></span>
        <span class="zc-bar"><i style="width:<?= $pct ?>%"></i></span>
        <span class="zc-n"><?= (int) $t['used'] ?> / <?= (int) $t['seats'] ?><?= $t['reserved'] ? ' · 預留 ' . (int) $t['reserved'] : '' ?></span>
        <span class="zc-who"><?= $names ? h(implode('、', array_slice($names, 0, 3))) . (count($names) > 3 ? ' 等 ' . count($names) : '') : '空 Empty' ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
