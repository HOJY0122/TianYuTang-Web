<?php
/** Easy mode ④ — the waiting list, oldest first. Tap someone to give them a table. */
$title = '候補名單'; $titleEn = 'Waiting list — tap a person to seat them';
require __DIR__ . '/_top.php';
?>
<div class="ez-list">
  <?php foreach ($rows as $r): ?>
    <a class="ez-row" href="<?= url('/admin/zai/easy/table') ?>?for=wait&amp;id=<?= (int) $r['id'] ?>">
      <span class="ez-qno">#<?= (int) $r['queue_no'] ?></span>
      <span class="ez-row-main"><b><?= h($r['name']) ?></b>
        <small><?= (int) $r['people'] ?> 人<?= $r['contact'] ? ' · ' . h($r['contact']) : '' ?> · <?= h(date('H:i', strtotime($r['created_at']))) ?></small></span>
      <span class="ez-row-state">選桌 →</span>
    </a>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="ez-msg ez-info"><b>沒有人在候補 ✓</b><p>No one is waiting.</p></div><?php endif; ?>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
