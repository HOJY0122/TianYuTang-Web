<?php
/**
 * 施齋 Day 3 — 已登記: someone who registered online arrives. Search their
 * IC (or name / phone / reference), mark them confirmed or pending, and
 * give them a table — one person, or the whole registration at once.
 */
require BASE_PATH . '/app/Views/layouts/admin_header.php';
require BASE_PATH . '/app/Views/admin/_zai_head.php';
$hidden = '<input type="hidden" name="event_id" value="' . (int) $event['id'] . '"><input type="hidden" name="q" value="' . h($q) . '">'
        . ($all ? '<input type="hidden" name="all" value="1"><input type="hidden" name="page" value="' . (int) $page . '">' : '');
?>
<div class="panel zai-search">
  <form method="GET" action="<?= url('/admin/zai/registered') ?>" class="lookup-form" data-live-search="#zaiResults">
    <input type="hidden" name="event" value="<?= (int) $event['id'] ?>">
    <input type="search" name="q" value="<?= h($q) ?>" autofocus autocomplete="off" spellcheck="false" inputmode="text"
           placeholder="身份證 / 姓名 / 電話 / 報名編號　IC, name, phone or reference" aria-label="搜尋 Search"
           data-app-scan="any" data-app-scan-submit>
    <button class="mini-btn btn-lg" type="submit"><?= icon('search') ?> 查詢 Find</button>
    <a class="mini-btn ghost btn-lg<?= $all ? ' is-on' : '' ?>" href="<?= url('/admin/zai/registered') . $evq ?>&amp;all=1" id="showAll"><?= icon('list') ?> 顯示全部 <span class="en">Show all</span></a>
  </form>
  <p class="help" style="margin:6px 0 0">打字就會自動找。身份證可輸入開頭幾位、完整號碼或最後 4 位。<span class="en">Results appear as you type — the start of an IC, the full number, or its last 4 digits.</span></p>
</div>

<div id="zaiResults" aria-live="polite">
<?php if ($all): ?>
  <div class="panel zai-all-head">
    <strong><?= icon('list') ?> 全部已登記 <span class="en">All registrations</span></strong>
    <span class="help">共 <?= number_format($total) ?> 筆（不含已取消）· <?= number_format($total) ?> registration<?= $total === 1 ? '' : 's' ?>, not counting cancelled<?= $pages > 1 ? ' · 第 ' . $page . ' / ' . $pages . ' 頁 page ' . $page . ' of ' . $pages : '' ?></span>
    <a class="mini-btn ghost" href="<?= url('/admin/zai/registered') . $evq ?>">× 關閉 <span class="en">Close</span></a>
  </div>
  <?php if (!$groups): ?><div class="panel"><p class="empty" style="margin:0">還沒有網上登記。No online registrations yet.</p></div><?php endif; ?>
<?php endif; ?>
<?php if ($q !== '' && !$groups): ?>
  <div class="panel"><p class="empty" style="margin:0"><?= icon('search') ?> 找不到「<?= h($q) ?>」的網上登記。<span class="en">No online registration found.</span><br>
    <a href="<?= url('/admin/zai/waiting') . $evq ?>">沒有登記？加入候補名單 → <span class="en">Not registered? Add to the waiting list</span></a></p></div>
<?php endif; ?>

<?php foreach ($groups as $g): $gid = (int) $g['id']; ?>
  <div class="panel zai-reg" id="g<?= $gid ?>">
    <div class="zai-reg-head">
      <strong><?= h($g['ref_code']) ?></strong>
      <?php if ($g['reg_type'] === 'organisation'): ?><span class="badge"><?= icon('users') ?> 團體 <?= h($g['org_name'] ?? '') ?></span><?php endif; ?>
      <span class="badge <?= $g['status'] === 'confirmed' ? 'ok' : 'pending' ?>"><?= $g['status'] === 'confirmed' ? '報名已確認 Confirmed' : '報名待確認 Pending' ?></span>
      <span class="help"><?= count($g['people']) ?> 人 people</span>
    </div>
    <?php if ($g['reg_type'] === 'organisation'): ?>
      <p class="help">這是團體登記，也可以在「團體」頁一起處理。<a href="<?= url('/admin/zai/groups') . $evq ?>&amp;q=<?= urlencode($g['ref_code']) ?>">去團體 → Groups</a></p>
    <?php endif; ?>

    <?php foreach ($g['people'] as $a): $aid = (int) $a['id']; $st = $status[$aid] ?? null; ?>
      <form method="POST" action="<?= url('/admin/zai/person') ?>" class="zai-person" id="p<?= $aid ?>">
        <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="attendee_id" value="<?= $aid ?>">
        <div class="zai-who">
          <strong><?= h($a['name']) ?></strong>
          <span class="help">IC <?= h($a['ic_no'] ?: '—') ?><?= !empty($a['contact_no']) ? ' · ' . h($a['contact_no']) : '' ?></span>
          <?php if ($st): ?>
            <span class="badge <?= $st['status'] === 'confirmed' ? 'ok' : 'pending' ?>"><?= $st['status'] === 'confirmed' ? '✓ 已確認' : '… 待定' ?>
              <?= $st['table_no'] ? ' · 第 ' . (int) $st['table_no'] . ' 桌' : '' ?></span>
          <?php else: ?><span class="badge">未處理 Not yet</span><?php endif; ?>
        </div>
        <div class="zai-act">
          <?= $tableSelect('table_no', $st && $st['table_no'] !== null ? (int) $st['table_no'] : null, 'aria-label="桌號 Table"', 1, (string) $a['name']) ?>
          <button class="mini-btn" name="status" value="confirmed" type="submit" data-needs-table data-table-optional>✓ 確認 <span class="en">Confirm</span></button>
          <button class="mini-btn ghost" name="status" value="pending" type="submit">… 待定 <span class="en">Pending</span></button>
          <?php if ($st): ?><button class="mini-btn ghost" name="status" value="clear" type="submit" title="清除 Clear">↺</button><?php endif; ?>
        </div>
      </form>
    <?php endforeach; ?>

    <?php if (count($g['people']) > 1): ?>
      <form method="POST" action="<?= url('/admin/zai/person-group') ?>" class="zai-all">
        <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="group_id" value="<?= $gid ?>">
        <span><?= icon('users') ?> 全部 <?= count($g['people']) ?> 人 <span class="en">Everyone</span>:</span>
        <?= $tableSelect('table_no', null, 'aria-label="桌號 Table"', count($g['people']), $g['ref_code'] . ' 全部 everyone') ?>
        <button class="mini-btn" name="status" value="confirmed" type="submit" data-needs-table data-table-optional>✓ 全部確認 <span class="en">Confirm all</span></button>
        <button class="mini-btn ghost" name="status" value="pending" type="submit">… 全部待定 <span class="en">All pending</span></button>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php if ($all) { $pagerBase = '/admin/zai/registered'; $pagerQuery = ['event' => (int) $event['id'], 'all' => 1]; require BASE_PATH . '/app/Views/partials/pager.php'; } ?>
</div>
<script src="<?= asset('js/live-search.js') ?>"></script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
