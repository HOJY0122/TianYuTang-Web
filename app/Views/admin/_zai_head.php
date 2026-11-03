<?php
/**
 * 施齋 Day 3 — tabs shared by every Day 3 page, and the table picker.
 * Expects $tab, $event, $seating (table_no => seats / used / who).
 */
$evq = '?event=' . (int) $event['id'];
$zaiTabs = [
    'overview'   => ['chart',        '總覽',  'Overview',     '/admin/zai'],
    'registered' => ['check-circle', '已登記', 'Registered',   '/admin/zai/registered'],
    'groups'     => ['users',        '團體',  'Groups',       '/admin/zai/groups'],
    'waiting'    => ['hourglass',    '候補',  'Waiting list', '/admin/zai/waiting'],
    'tables'     => ['settings',     '桌子',  'Tables',       '/admin/zai/tables'],
];
/** <select> of tables showing seats left, e.g. "第 5 桌 · 剩 3". */
$tableSelect = static function (string $name, ?int $current, string $attrs = '') use ($seating): string {
    $o = '<select name="' . h($name) . '" ' . $attrs . '><option value="">桌號 Table</option>';
    $known = false;
    foreach ($seating as $no => $t) {
        if ($t['seats'] === 0) {
            continue;
        }
        $left = $t['seats'] - $t['used'];
        $sel = $current === (int) $no;
        $known = $known || $sel;
        $o .= '<option value="' . (int) $no . '"' . ($sel ? ' selected' : '') . '>第 ' . (int) $no . ' 桌 · '
            . ($left > 0 ? '剩 ' . $left : ($left === 0 ? '滿 full' : '超 ' . -$left)) . '</option>';
    }
    if ($current !== null && !$known) {
        $o .= '<option value="' . $current . '" selected>第 ' . $current . ' 桌</option>';
    }
    return $o . '</select>';
};
?>
<div class="zai-head">
  <div class="zai-title"><?= icon('chair') ?> <strong>施齋入座 <span class="en">Day 3 meal seating</span></strong>
    <span class="help"><?= h($event['year'] . ' ' . $event['name']) ?></span></div>
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
