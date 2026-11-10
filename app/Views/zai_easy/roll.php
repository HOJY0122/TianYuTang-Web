<?php
/**
 * Easy mode — 名單 (from the 已到 card on the first screen):
 *   未到 Not yet  everyone reserved who is not here; tap to confirm them
 *                 (a person → ① with their reference, a group → ②,
 *                 the waiting list → choose a table)
 *   已到 Here     everyone here, with their table
 */
$title = '名單'; $titleEn = 'Who is here, who is not yet';
require __DIR__ . '/_top.php';
$isHere = static fn(array $p): bool => $p['zstatus'] === 'confirmed';
$gone = array_filter($people, static fn($p) => $p['zstatus'] === 'left');
$todo = ['people' => array_filter($people, static fn($p) => !$isHere($p) && $p['zstatus'] !== 'left'),
         'groups' => array_filter($groups, static fn($g) => $g['status'] !== 'arrived'),
         'waiting' => array_filter($waiting, static fn($w) => $w['status'] === 'waiting')];
$here = ['people' => array_filter($people, $isHere),
         'groups' => array_filter($groups, static fn($g) => $g['status'] === 'arrived'),
         'waiting' => array_filter($waiting, static fn($w) => $w['status'] === 'seated')];
$n = static fn(array $s): int => count($s['people']) + array_sum(array_map('intval', array_column($s['groups'], 'people'))) + array_sum(array_map('intval', array_column($s['waiting'], 'people')));
$show = $tab === 'here' ? $here : $todo;
$tbl = static fn($t): string => trim((string) $t) !== '' ? '第 ' . h((string) $t) . ' 桌' : '';
?>
<nav class="ez-tabs" aria-label="名單 List">
  <a href="<?= url('/admin/zai/easy/roll') ?>"<?= $tab === 'todo' ? ' class="is-on" aria-current="page"' : '' ?>>未到 <small>Not yet</small><b><?= $n($todo) + count($gone) ?></b></a>
  <a href="<?= url('/admin/zai/easy/roll') ?>?tab=here"<?= $tab === 'here' ? ' class="is-on" aria-current="page"' : '' ?>>已到 <small>Here</small><b><?= $n($here) ?></b></a>
</nav>
<div class="ez-search">
  <input type="search" data-filter-list="#ezRoll" autocomplete="off" placeholder="名字、電話、編號或桌號 Name, phone, ref or table" aria-label="搜尋 Search">
</div>
<?php if ($tab === 'todo'): ?><p class="ez-hint">按一個名字去確認他們到了。<span class="en">Tap a name to confirm they are here.</span></p><?php endif; ?>
<div class="ez-list" id="ezRoll">
  <?php if ($show['people']): ?><p class="ez-sec" data-filter-row><?= icon('id-card') ?> 已登記 <small>Registered</small> · <?= count($show['people']) ?></p><?php endif; ?>
  <?php foreach ($show['people'] as $p): $pend = $p['zstatus'] === 'pending'; ?>
    <a class="ez-row<?= $tab === 'here' ? ' is-done' : '' ?>" data-filter-row
       href="<?= url('/admin/zai/easy/find') ?>?q=<?= rawurlencode((string) $p['ref_code']) ?>">
      <span class="ez-row-main"><b><?= h($p['name']) ?></b>
        <small class="ez-id"><b>IC …<?= h($p['ic_last4'] ?: '—') ?></b><?= !empty($p['contact_no']) && $p['contact_no'] !== '—' ? ' · ' . h($p['contact_no']) : '' ?></small>
        <small><?= h($p['ref_code']) ?><?= $pend ? ' · <em class="warn">待定 Pending</em>' : '' ?></small></span>
      <span class="ez-row-state"><?= $tab === 'here' ? ($p['table_no'] ? '✓ ' . $tbl($p['table_no']) : '✓ 已到<br><small>未有桌 No table</small>') : '確認 →<br><small>Confirm</small>' ?></span>
    </a>
  <?php endforeach; ?>

  <?php if ($show['groups']): ?><p class="ez-sec" data-filter-row><?= icon('users') ?> 團體 <small>Groups</small> · <?= count($show['groups']) ?></p><?php endif; ?>
  <?php foreach ($show['groups'] as $g): ?>
    <a class="ez-row<?= $tab === 'here' ? ' is-done' : '' ?>" data-filter-row href="<?= url('/admin/zai/easy/group') ?>?id=<?= (int) $g['id'] ?>">
      <span class="ez-row-main"><b><?= h($g['name']) ?></b>
        <small><?= (int) $g['people'] ?> 人<?= $g['contact'] ? ' · ' . h($g['contact']) : '' ?><?= !empty($g['ref_code']) ? ' · ' . h($g['ref_code']) : '' ?></small></span>
      <span class="ez-row-state"><?= $tab === 'here' ? '✓ ' . ($tbl($g['tables']) ?: '已到') : '團體 →<br><small>Group</small>' ?></span>
    </a>
  <?php endforeach; ?>

  <?php if ($show['waiting']): ?><p class="ez-sec" data-filter-row><?= icon('hourglass') ?> 候補 <small>Waiting list</small> · <?= count($show['waiting']) ?></p><?php endif; ?>
  <?php foreach ($show['waiting'] as $w): ?>
    <a class="ez-row<?= $tab === 'here' ? ' is-done' : '' ?>" data-filter-row href="<?= url('/admin/zai/easy/table') ?>?for=wait&amp;id=<?= (int) $w['id'] ?>">
      <span class="ez-qno">#<?= (int) $w['queue_no'] ?></span>
      <span class="ez-row-main"><b><?= h($w['name']) ?></b>
        <small><?= (int) $w['people'] > 1 ? (int) $w['people'] . ' 人 · ' : '' ?><?= $w['contact'] ? h($w['contact']) : '' ?></small></span>
      <span class="ez-row-state"><?= $tab === 'here' ? '✓ ' . ($tbl($w['table_no']) ?: '已入座') : '選桌 →' ?></span>
    </a>
  <?php endforeach; ?>

  <?php if ($tab === 'todo' && $gone): ?>
    <p class="ez-sec" data-filter-row><?= icon('log-out') ?> 已離開 <small>Left — tap if they came back</small> · <?= count($gone) ?></p>
    <?php foreach ($gone as $p): ?>
      <a class="ez-row is-gone" data-filter-row href="<?= url('/admin/zai/easy/find') ?>?q=<?= rawurlencode((string) $p['ref_code']) ?>">
        <span class="ez-row-main"><b><?= h($p['name']) ?></b>
          <small><?= h($p['ref_code']) ?></small></span>
        <span class="ez-row-state">回來了？<br><small>Came back</small></span>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>
  <div class="ez-msg ez-warn" data-filter-empty hidden><b>找不到 Not found</b></div>
  <?php if (!$show['people'] && !$show['groups'] && !$show['waiting']): ?>
    <div class="ez-msg ez-info"><b><?= $tab === 'todo' ? '全部都到了 ✓' : '還沒有人到' ?></b><p><?= $tab === 'todo' ? 'Everyone is here.' : 'No one is here yet.' ?></p></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
