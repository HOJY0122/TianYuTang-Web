<?php
/**
 * 施齋 Day 3 — tabs shared by every Day 3 page, and the table picker.
 * Expects $tab, $event, $seating (table_no => seats / used / who).
 */
$evq = '?event=' . (int) $event['id'];
$zaiTabs = [
    'overview'   => ['chart',        '總覽',  'Overview',     '/admin/zai'],
    'map'        => ['map',          '平面圖', 'Table map',    '/admin/zai/map'],
    'registered' => ['check-circle', '已登記', 'Registered',   '/admin/zai/registered'],
    'groups'     => ['users',        '團體',  'Groups',       '/admin/zai/groups'],
    'waiting'    => ['hourglass',    '候補',  'Waiting list', '/admin/zai/waiting'],
    'tables'     => ['settings',     '桌子',  'Tables',       '/admin/zai/tables'],
];
/**
 * The table picker: a "🪑 選桌 Choose table" button (not a drop-down) that
 * opens the hall map in a pop-up; tapping a table fills the hidden field.
 *   $tableSelect(name, current table or null, attrs, people it is for, who)
 * A button with data-needs-table (e.g. Confirm / Seat) opens the map first
 * when no table is chosen, then sends the form. See js/zai-pick.js.
 */
$tableSelect = static function (string $name, ?int $current, string $attrs = '', int $need = 1, string $who = ''): string {
    return '<span class="tpick">'
        . '<input type="hidden" name="' . h($name) . '" value="' . ($current !== null ? $current : '') . '" data-tpick-value>'
        . '<button type="button" class="mini-btn tpick-btn' . ($current !== null ? ' has-table' : '') . '" data-tpick data-need="' . max(1, $need) . '" data-who="' . h($who) . '" ' . $attrs . '>'
        . icon('chair') . ' <span data-tpick-label>' . ($current !== null ? '第 ' . $current . ' 桌' : '選桌 Choose table') . '</span></button>'
        . '</span>';
};
?>
<div class="zai-head">
  <div class="zai-title"><?= icon('chair') ?> <strong>施齋入座 <span class="en">Day 3 meal seating</span></strong>
    <span class="help"><?= h($event['year'] . ' ' . $event['name']) ?></span>
    <a class="mini-btn zai-easy-link" href="<?= url('/admin/zai/easy') ?>"><?= icon('smartphone') ?> 簡易模式 <span class="en">Easy mode for helpers</span></a></div>
  <nav class="zai-tabs" aria-label="施齋 Day 3">
    <?php foreach ($zaiTabs as $k => [$ic, $zh, $en, $href]): ?>
      <a href="<?= url($href) . $evq ?>"<?= $tab === $k ? ' class="is-on" aria-current="page"' : '' ?>><?= icon($ic) ?> <?= $zh ?> <span class="en"><?= $en ?></span></a>
    <?php endforeach; ?>
  </nav>
</div>
<?php if (!$seating && $tab !== 'tables'): ?>
  <p class="flash info"><?= icon('chair') ?> 還沒設定桌子。<a href="<?= url('/admin/zai/tables') . $evq ?>">先到「桌子」設定桌數和每桌人數 →</a>
    <span class="en">No tables yet — set them up under Tables first.</span></p>
<?php endif; ?>

<?php if ($seating && in_array($tab, ['registered', 'groups', 'waiting'], true)): ?>
<!-- The hall map for the table picker (js/zai-pick.js), one per page — only where tables are assigned. -->
<div class="tpick-modal" id="tpickModal" hidden role="dialog" aria-modal="true" aria-labelledby="tpickTitle">
  <div class="tpick-box">
    <div class="tpick-head">
      <h2 id="tpickTitle"><?= icon('chair') ?> 選桌 <span class="en">Choose a table</span></h2>
      <p class="tpick-who" data-tpick-who></p>
      <button type="button" class="tpick-x" data-tpick-close aria-label="關閉 Close">×</button>
    </div>
    <div class="tpick-scroll">
    <?php
    $hall = App\Models\Zai::hallLayout();
    $hallNos = array_keys(array_filter($seating, static fn($t) => $t['seats'] > 0));
    $hallClass = 'hall-pick';
    $hallCell = static function (int $no) use ($seating): string {
        $t = $seating[$no];
        return '<button type="button" class="tp-cell" data-no="' . $no . '" data-left="' . ($t['seats'] - $t['used']) . '" data-seats="' . (int) $t['seats'] . '"'
            . ' data-kind="' . h($t['kind'] ?? 'open') . '"><b>' . $no . '</b><small></small></button>';
    };
    require BASE_PATH . '/app/Views/partials/zai_hall.php';
    ?>
    </div>
    <p class="tpick-legend"><i class="lg-fits"></i> 夠位 <i class="lg-some"></i> 位不夠 <i class="lg-grp"></i> 團體桌（個人不能坐） <i class="lg-full"></i> 滿 / 有人（不能按）
      <span class="en">Green = enough · Yellow = not enough · Purple = group table (not for individuals) · Red = full / taken</span></p>
    <div class="tpick-foot">
      <button type="button" class="mini-btn ghost" data-tpick-none hidden>只確認，不選桌 <span class="en">Confirm without a table</span></button>
      <button type="button" class="mini-btn ghost" data-tpick-clear hidden>清除桌號 <span class="en">Clear</span></button>
      <span class="spacer"></span>
      <button type="button" class="mini-btn ghost" data-tpick-close>取消 <span class="en">Cancel</span></button>
      <button type="button" class="mini-btn" data-tpick-done hidden>✓ 完成 <span class="en">Done</span></button>
    </div>
  </div>
</div>
<script src="<?= asset('js/zai-pick.js') ?>"></script>
<?php endif; ?>
