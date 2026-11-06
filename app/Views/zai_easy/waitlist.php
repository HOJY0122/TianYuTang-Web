<?php
/** Easy mode ④ — the waiting list, oldest first. Tap someone to give them a table. */
$title = '候補名單'; $titleEn = 'Waiting list — tap a person to seat them';
require __DIR__ . '/_top.php';
?>
<?php if (count($rows) > 3): ?>
<div class="ez-search">
  <input type="search" data-filter-list="#ezWait" autocomplete="off" placeholder="名字、號碼、電話或身份證 Name, number, phone or IC" aria-label="搜尋 Search">
</div>
<?php endif; ?>
<div class="ez-list" id="ezWait">
  <?php foreach ($rows as $r): ?>
    <?php $l4 = App\Models\Zai::waitingLast4($r); ?>
    <a class="ez-row" data-filter-row href="<?= url('/admin/zai/easy/table') ?>?for=wait&amp;id=<?= (int) $r['id'] ?>">
      <span class="ez-qno">#<?= (int) $r['queue_no'] ?></span>
      <span class="ez-row-main"><b><?= h($r['name']) ?></b>
        <small class="ez-id"><b>IC <?= !empty($r['ic_no']) ? h($r['ic_no']) : ($l4 ? '…' . h(implode('、…', $l4)) : '—') ?></b></small>
        <?php if ($r['contact']): ?><small class="ez-id"><?= icon('phone') ?> <?= h($r['contact']) ?></small><?php endif; ?>
        <small><?= (int) $r['people'] > 1 ? (int) $r['people'] . ' 人 · ' : '' ?><?= h(date('H:i', strtotime($r['created_at']))) ?></small></span>
      <span class="ez-row-state">選桌 →</span>
    </a>
  <?php endforeach; ?>
  <div class="ez-msg ez-warn" data-filter-empty hidden><b>找不到 Not found</b></div>
  <?php if (!$rows): ?><div class="ez-msg ez-info"><b>沒有人在候補 ✓</b><p>No one is waiting.</p></div><?php endif; ?>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
