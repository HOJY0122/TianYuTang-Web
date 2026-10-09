<?php
require BASE_PATH . '/app/Views/layouts/admin_header.php';
require BASE_PATH . '/app/Views/partials/charts.php';

$eid        = (int) $event['id'];
$q          = '?event=' . $eid;
$arrivedPct = $totalAttendees > 0 ? min(100, round($totalCheckedIn / $totalAttendees * 100)) : 0;
$paidPct    = $totalAmount > 0 ? min(100, round($totalPaid / $totalAmount * 100)) : 0;
$statusText = ['pending' => '待確認 Pending', 'confirmed' => '已確認 Confirmed', 'cancelled' => '已取消 Cancelled'];
?>

<!-- ---------- Which event (controls live in the Control panel) ---------- -->
<div class="dash-head">
  <div>
    <h2 style="margin:0"><?= h($event['year']) ?> · <?= h($event['name']) ?>
      <?= $event['is_active'] ? '<span class="badge ok">目前公開 Live</span>' : '' ?><?= $event['is_test'] ? ' <span class="badge cancelled">測試 Test</span>' : '' ?></h2>
  </div>
  <div class="dash-head-actions">
    <?php if (count($allEvents) > 1): ?>
      <form method="GET" action="<?= url('/admin/dashboard') ?>" style="margin:0">
        <label class="sr-only" for="dashEvent">查看活動 View event</label>
        <select id="dashEvent" name="event" onchange="this.form.submit()">
          <?php foreach ($allEvents as $_dE): ?>
            <option value="<?= (int) $_dE['id'] ?>"<?= (int) $_dE['id'] === $eid ? ' selected' : '' ?>><?= h($_dE['year']) ?> — <?= h($_dE['name']) ?><?= $_dE['is_test'] ? '（測試 Test）' : '' ?></option>
          <?php endforeach; unset($_dE); ?>
        </select>
      </form>
    <?php endif; ?>
    <?php if (App\Core\Access::can('control')): ?>
      <a class="mini-btn ghost btn-lg" href="<?= url('/admin/control') . $q ?>"><?= icon('settings') ?> 控制台 <span class="en">Control panel</span></a>
    <?php endif; ?>
  </div>
</div>
<?php if ($event['is_test']): ?>
  <div class="flash test"><?= icon('flask') ?> 這是測試活動，資料不會列入正式統計。This is a test event — its data is not counted in real figures.</div>
<?php endif; ?>

<!-- ---------- Headline numbers ---------- -->
<div class="kpis" id="dashKpis" data-live="rsvp_groups rsvp_attendees donations events">
  <div class="kpi">
    <div class="k-label">參加人數<span class="en">People registered</span></div>
    <div class="k-value"><?= number_format($totalAttendees) ?></div>
    <div class="k-sub"><?= number_format($totalGroups) ?> 組 groups · 現場 walk-in <?= (int) $bySourceRsvp['walkin']['people'] ?></div>
  </div>
  <div class="kpi">
    <div class="k-label">已報到<span class="en">Checked in</span></div>
    <div class="k-value"><?= number_format($totalCheckedIn) ?></div>
    <div class="k-sub"><?= $arrivedPct ?>% of <?= number_format($totalAttendees) ?></div>
    <div class="meter" title="<?= $arrivedPct ?>%"><i style="width:<?= $arrivedPct ?>%"></i></div>
  </div>
  <div class="kpi">
    <div class="k-label">待確認報名<span class="en">Pending registrations</span></div>
    <div class="k-value"><?= number_format($byStatus['pending']) ?></div>
    <div class="k-sub">已確認 confirmed <?= (int) $byStatus['confirmed'] ?> · 取消 cancelled <?= (int) $byStatus['cancelled'] ?></div>
  </div>
  <div class="kpi">
    <div class="k-label">布施總額<span class="en">Total pledged</span></div>
    <div class="k-value"><?= rm_compact($totalAmount) ?></div>
    <div class="k-sub"><?= number_format($donationCount) ?> 筆 donations · 功德席 <?= number_format($totalTables) ?> 席 seats</div>
  </div>
  <div class="kpi">
    <div class="k-label">已收款<span class="en">Received</span></div>
    <div class="k-value"><?= rm_compact($totalPaid) ?></div>
    <div class="k-sub"><?= $paidPct ?>% · 未收 outstanding <?= rm(max(0, $totalAmount - $totalPaid)) ?></div>
    <div class="meter" title="<?= $paidPct ?>%"><i style="width:<?= $paidPct ?>%"></i></div>
  </div>
</div>

<!-- ---------- Trends ---------- -->
<div class="grid-2" id="dashGrid1" data-live="rsvp_groups rsvp_attendees donations events">
  <div class="panel"><?php chart_columns([
      'title' => '每日報名人數', 'subtitle' => 'People registered per day', 'unit' => '人 people',
      'rows'  => array_map(static fn($r) => ['day' => $r['day'], 'value' => $r['people']], $dailyPeople),
  ]); ?></div>
  <div class="panel"><?php chart_columns([
      'title' => '每日布施金額', 'subtitle' => 'Donations per day (RM)', 'money' => true,
      'rows'  => array_map(static fn($r) => ['day' => $r['day'], 'value' => $r['total']], $dailyMoney),
  ]); ?></div>
</div>

<div class="grid-2" id="dashGrid2" data-live="rsvp_groups rsvp_attendees donations events">
  <div class="panel"><?php chart_split([
      'title' => '布施類別', 'subtitle' => 'Merit seats vs freewill', 'money' => true,
      'parts' => [['label' => '功德席 Merit seats', 'value' => $byKind['seats']],
                  ['label' => '隨喜 Freewill', 'value' => $byKind['freewill']]],
  ]); ?></div>
  <div class="panel"><?php chart_split([
      'title' => '布施來源', 'subtitle' => 'Online vs counter (cash)', 'money' => true,
      'parts' => [['label' => '線上 Online', 'value' => $bySource['online']],
                  ['label' => '現場 Counter', 'value' => $bySource['counter']]],
  ]); ?></div>
</div>

<!-- ---------- Latest records ---------- -->
<div class="grid-2" id="dashGrid3" data-live="rsvp_groups rsvp_attendees donations events">
  <div class="panel">
    <h2>最新報名 <span class="en">Latest registrations</span></h2>
    <?php if (!$recentGroups): ?>
      <p class="empty">尚無報名。No registrations yet.</p>
    <?php else: ?>
      <table class="records">
        <?php foreach ($recentGroups as $g): ?>
          <tr>
            <td data-label="編號 Ref"><a class="rowlink" href="<?= url('/admin/registrations/edit') ?>?id=<?= (int) $g['id'] ?>"><?= h($g['ref_code']) ?></a>
              <span class="sub-line"><?= h(date('m-d H:i', strtotime($g['created_at']))) ?></span></td>
            <td data-label="聯絡人 Contact"><?= h($g['lead_name']) ?><span class="sub-line"><?= (int) $g['attendee_count'] ?> 位 people</span></td>
            <td data-label="狀態 Status"><span class="badge <?= $g['status'] === 'confirmed' ? 'ok' : h($g['status']) ?>"><?= h($statusText[$g['status']]) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
    <p style="margin:12px 0 0"><a class="mini-btn ghost" href="<?= url('/admin/registrations') . $q ?>">查看全部 View all →</a></p>
  </div>
  <div class="panel">
    <h2>最新布施 <span class="en">Latest donations</span></h2>
    <?php if (!$recentDonations): ?>
      <p class="empty">尚無布施。No donations yet.</p>
    <?php else: ?>
      <table class="records">
        <?php foreach ($recentDonations as $d): ?>
          <tr>
            <td data-label="編號 Ref"><a class="rowlink" href="<?= url('/admin/donations/edit') ?>?id=<?= (int) $d['id'] ?>"><?= h($d['ref_code']) ?></a>
              <span class="sub-line"><?= h(date('m-d H:i', strtotime($d['created_at']))) ?></span></td>
            <td data-label="姓名 Name"><?= h($d['name']) ?><span class="sub-line"><?= h(App\Models\Donation::describe($d)) ?></span></td>
            <td data-label="金額 Amount" class="num"><strong><?= rm((float) $d['amount']) ?></strong>
              <span class="sub-line"><?= $d['status'] === 'paid' ? '✓ 已付 Paid' : '待付 Pending' ?></span></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
    <p style="margin:12px 0 0"><a class="mini-btn ghost" href="<?= url('/admin/donations') . $q ?>">查看全部 View all →</a></p>
  </div>
</div>

<!-- ---------- Downloads & printing ---------- -->
<div class="panel">
  <h2>下載與列印 <span class="en">Downloads &amp; printing</span></h2>
  <div class="eventbar-actions">
    <a class="mini-btn btn-lg" href="<?= url('/admin/export/attendees-excel') . $q ?>"><?= icon('chart') ?> 報名 Excel Registrations</a>
    <a class="mini-btn btn-lg" href="<?= url('/admin/export/donations-excel') . $q ?>"><?= icon('chart') ?> 布施 Excel Donations</a>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/export/attendees') . $q ?>"><?= icon('download') ?> CSV 報名</a>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/export/donations') . $q ?>"><?= icon('download') ?> CSV 布施</a>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/print/attendees') . $q ?>" target="_blank"><?= icon('printer') ?> 報到名單 Check-in sheet</a>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/print/donations') . $q ?>" target="_blank"><?= icon('printer') ?> 布施清單 Donation list</a>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/qr') . $q ?>" target="_blank"><?= icon('qr') ?> 活動 QR Event QR</a>
  </div>
  <p class="help" style="margin-bottom:0">Excel 檔含統計頁與完整名單（可篩選、狀態下拉選單）。CSV 為純資料，適合匯入其他系統。<br>
    <span class="en">Excel files have a summary sheet and a full list with filters and a status dropdown. CSV is plain data for importing elsewhere.</span></p>
</div>

<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
