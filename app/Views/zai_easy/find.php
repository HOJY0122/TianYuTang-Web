<?php
/** Easy mode ① — registered online: type the IC, tick the people, go on. */
$title = '請輸入身份證號碼'; $titleEn = 'Type the IC number — results show from the first digit';
require __DIR__ . '/_top.php';
?>
<form method="GET" action="<?= url('/admin/zai/easy/find') ?>" class="ez-search" data-live-search="#ezResults">
  <input type="search" name="q" value="<?= h($q) ?>" inputmode="numeric" autocomplete="off" autofocus
         placeholder="例 700101-14-5678 或 5678" aria-label="身份證號碼 IC number">
  <button type="submit" class="ez-btn ez-go"><?= icon('search') ?> 找 Find</button>
</form>
<p class="ez-hint">打字就會自動找，不用按「找」。也可以輸入姓名或電話。<span class="en">Results appear as you type. A name or phone number works too.</span></p>

<div id="ezResults" aria-live="polite">

<?php foreach ($orgs ?? [] as $zid => $o): $done = $o['status'] === 'arrived'; ?>
  <a class="ez-row ez-org-hit<?= $done ? ' is-done' : '' ?>" href="<?= url('/admin/zai/easy/group') ?>?id=<?= (int) $zid ?>">
    <span class="ez-row-main"><small>這是團體報名 · Registered as a group</small>
      <b><?= icon('users') ?> <?= h($o['name']) ?></b>
      <small><?= h($o['ref_code']) ?> · <?= (int) $o['people'] ?> 人 · <?= h(implode('、', array_slice($o['members'], 0, 4))) ?><?= count($o['members']) > 4 ? ' …' : '' ?></small></span>
    <span class="ez-row-state"><?= $done ? '✓ 已到<br>第 ' . h($o['tables'] ?? '') . ' 桌' : '去團體 →<br><small>Go to group</small>' ?></span>
  </a>
<?php endforeach; ?>
<?php if ($q !== '' && !$people && empty($orgs)): ?>
  <div class="ez-msg ez-warn">
    <b>找不到 Not found</b>
    <p>請再看一次號碼。如果真的沒有登記，請排候補。<br><span class="en">Check the number again. If they really did not register, add them to the waiting list.</span></p>
    <a class="ez-btn ez-alt" href="<?= url('/admin/zai/easy/wait') ?>"><?= icon('hand') ?> 加入候補 Add to waiting list</a>
  </div>
<?php elseif ($people): ?>
  <?php if (!empty($_GET['found'])): ?><div class="ez-msg ez-info"><b>這個身份證已經網上登記 ✓</b><p>This IC is already registered — confirm them here.</p></div><?php endif; ?>
  <?php if (!empty($more)): ?><p class="ez-hint">只顯示前 <?= (int) $more ?> 筆，請多打幾個字。<span class="en">Showing the first <?= (int) $more ?> — keep typing to narrow it down.</span></p><?php endif; ?>
  <form method="POST" action="<?= url('/admin/zai/easy/pick') ?>" class="ez-people">
    <?= csrf_field() ?>
    <p class="ez-step"><span>1</span> 按名字選人（可選多位）<small>Tap each person who is here</small></p>
    <?php foreach ($people as $a): $z = $a['zai']; ?>
      <label class="ez-person<?= $z && $z['table_no'] ? ' is-seated' : '' ?>">
        <input type="checkbox" name="ids[]" value="<?= (int) $a['id'] ?>"<?= $z && $z['table_no'] ? '' : ' checked' ?>>
        <span class="ez-tick" aria-hidden="true">✓</span>
        <span class="ez-pname"><b><?= h($a['name']) ?></b>
          <small class="ez-id"><b>IC <?= h($a['ic_no'] ?: '—') ?></b></small>
          <?php if (!empty($a['contact_no']) && $a['contact_no'] !== '—'): ?><small class="ez-id"><?= icon('phone') ?> <?= h($a['contact_no']) ?></small><?php endif; ?>
          <small><?= h($a['ref_code']) ?><?= $a['org_name'] ? ' · 團體 ' . h($a['org_name']) : '' ?></small>
          <?php if ($z): ?><em class="<?= $z['status'] === 'confirmed' ? 'ok' : 'warn' ?>"><?= $z['status'] === 'confirmed' ? '已確認' : '待定' ?><?= $z['table_no'] ? ' · 已在第 ' . (int) $z['table_no'] . ' 桌' : '' ?></em><?php endif; ?></span>
      </label>
    <?php endforeach; ?>
    <p class="ez-step"><span>2</span> 然後按 <small>Then tap</small></p>
    <button type="submit" name="go" value="table" class="ez-btn ez-ok">✓ 確認，選桌號 <small>Confirm &amp; choose a table</small></button>
    <button type="submit" name="go" value="pending" class="ez-btn ez-alt">… 待定，等一下 <small>Pending — not yet</small></button>
  </form>
<?php endif; ?>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
