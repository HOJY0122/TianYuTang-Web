<?php
/** Easy mode ② — which group? Tap it. */
$title = '哪一個團體？'; $titleEn = 'Which group? Tap it';
require __DIR__ . '/_top.php';
?>
<form method="GET" action="<?= url('/admin/zai/easy/groups') ?>" class="ez-search" data-live-search="#ezList">
  <input type="search" name="q" value="<?= h($q) ?>" autocomplete="off" placeholder="團體名稱或電話 Group name or phone" aria-label="搜尋 Search">
  <button type="submit" class="ez-btn ez-go"><?= icon('search') ?> 找 Find</button>
</form>
<div class="ez-list" id="ezList" aria-live="polite">
  <?php foreach ($groups as $g): $done = $g['status'] === 'arrived'; ?>
    <a class="ez-row<?= $done ? ' is-done' : '' ?>" href="<?= url('/admin/zai/easy/group') ?>?id=<?= (int) $g['id'] ?>">
      <span class="ez-row-main"><b><?= h($g['name']) ?></b>
        <small><?= (int) $g['people'] ?> 人<?= $g['contact'] ? ' · ' . h($g['contact']) : '' ?></small></span>
      <span class="ez-row-state"><?= $done ? '✓ 已到' . (trim((string) ($g['tables'] ?? '')) !== '' ? '<br>第 ' . h($g['tables']) . ' 桌' : '') : '未到 →' ?></span>
    </a>
  <?php endforeach; ?>
  <?php if (!$groups): ?><div class="ez-msg ez-warn"><b>找不到團體 No group found</b><p>請問負責人。Ask the supervisor.</p></div><?php endif; ?>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
