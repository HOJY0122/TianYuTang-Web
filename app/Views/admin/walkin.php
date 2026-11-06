<?php
/**
 * Walk-in register — used at the counter on the day, often on a phone.
 *
 * Kept deliberately plain: big fields, one column, no clever widgets.
 * Whoever is on the desk is typing with a queue in front of them.
 */
$oldNames    = $old['values']['names']    ?? [];
$oldIcs      = $old['values']['ics']      ?? [];
$oldContacts = $old['values']['contacts'] ?? [];
$oldSource   = ($old['values']['source'] ?? '') === 'whatsapp' ? 'whatsapp' : 'walkin';
$startRows   = $oldSource === 'walkin' ? 1 : max(1, min(count($oldNames), $maxPerForm));
?>
<?php
$pageTitle = '現場報名 Walk-in Registration';
$nav = 'walkin';
require BASE_PATH . '/app/Views/layouts/admin_header.php';
?>
<div class="wrap checkin-wrap">


  <?php if ($event['is_test']): ?>
    <div class="flash test"><?= icon('flask') ?> 測試活動 — 此處登記不列入正式統計。</div>
  <?php endif; ?>

  <!-- ---------- Head count, by how they registered ---------- -->
  <div class="checkin-stats" id="liveStats" data-live="rsvp_groups rsvp_attendees events">
    <div><small>現場登記 Walk-in</small><strong><?= (int) $counts['walkin']['people'] ?></strong></div>
    <div><small>線上報名 Online</small><strong><?= (int) $counts['online']['people'] ?></strong></div>
    <div><small>合計人數 Total</small><strong><?= (int) $counts['walkin']['people'] + (int) $counts['online']['people'] ?></strong></div>
  </div>

  <div class="page-grid">
  <div class="page-col">
  <!-- ---------- Entry form ---------- -->
  <div class="panel form-panel">
    <h2 style="margin-top:0"><?= h($event['year']) ?> · <?= h($event['name']) ?></h2>

    <?php if (!empty($old['errors'])): ?>
      <div class="error">
        <strong>請修正以下問題：</strong>
        <ul style="margin:8px 0 0;padding-left:20px">
          <?php foreach ($old['errors'] as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <p class="help" style="margin-top:14px">
      登記當天才到場、沒有事先在網站報名的善信。
      儲存後會<strong>直接確認並完成報到</strong>，不必再到報到頁掃描，
      人數會立刻計入現場統計。<br>
      <span class="en">For people who arrive on the day without registering online. Saving confirms and checks them in at once — no scan needed.</span>
    </p>

    <?php if (count($allEvents) > 1): ?>
      <form method="GET" action="<?= url('/admin/walkin') ?>" class="event-switch">
        <label for="eventPick">活動｜Event</label>
        <select id="eventPick" name="event" onchange="this.form.submit()">
          <?php foreach ($allEvents as $e): ?>
            <option value="<?= (int) $e['id'] ?>"<?= (int) $e['id'] === (int) $event['id'] ? ' selected' : '' ?>>
              <?= h($e['year']) ?> <?= h($e['name']) ?><?= $e['is_test'] ? '（測試）' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>

    <form method="POST" action="<?= url('/admin/walkin/save') ?>" id="walkinForm">
      <?= csrf_field() ?>
      <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">

      <label>來源 <span class="en">Where the booking came from</span></label>
      <div class="seg-toggle walkin-source" role="radiogroup">
        <label class="seg-on"><input type="radio" name="source" value="walkin"<?= $oldSource === 'walkin' ? ' checked' : '' ?>><span><?= icon('walk') ?> 現場 Walk-in</span></label>
        <label class="seg-on"><input type="radio" name="source" value="whatsapp"<?= $oldSource === 'whatsapp' ? ' checked' : '' ?>><span><?= icon('phone') ?> WhatsApp 預約</span></label>
      </div>
      <p class="help" id="sourceHelp">現場：1 人 1 身份證，登記並立即報到。WhatsApp：只登記，當天到場再報到。<span class="en">Walk-in: one person, one IC — registered and checked in. WhatsApp: registered only — they check in on the day.</span></p>

      <div id="rowCountBox"<?= $oldSource === 'walkin' ? ' hidden' : '' ?>>
      <label for="rowCount">人數｜Number of People</label>
      <select id="rowCount" onchange="renderRows()">
        <?php for ($i = 1; $i <= $maxPerForm; $i++): ?>
          <option value="<?= $i ?>"<?= $i === $startRows ? ' selected' : '' ?>><?= $i ?> 位</option>
        <?php endfor; ?>
      </select>
      </div>

      <div id="walkinRows"></div>

      <div class="form-actions">
        <button class="primary" type="submit" id="walkinSubmit"><?= icon('walk') ?> <span data-for="walkin"<?= $oldSource === 'walkin' ? '' : ' hidden' ?>>登記並報到 <span class="en">Register &amp; check in</span></span><span data-for="whatsapp"<?= $oldSource === 'whatsapp' ? '' : ' hidden' ?>>登記 WhatsApp 預約 <span class="en">Save booking</span></span></button>
      </div>
    </form>
  </div>

  </div>
  <div class="page-col">
  <!-- ---------- Recently registered ---------- -->
  <div id="liveRecent" data-live="rsvp_groups rsvp_attendees events">
  <?php if ($recent): ?>
    <div class="panel">
      <h2>最近現場 / WhatsApp 登記 <span class="en">Recent walk-ins &amp; WhatsApp</span></h2>
      <div class="scroll">
        <table>
          <thead>
            <tr><th>編號</th><th>姓名</th><th class="amount">人數</th><th>登記者</th><th>時間</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $g): ?>
              <tr>
                <td><?= h($g['ref_code'] ?? '—') ?><?= ($g['source'] ?? '') === 'whatsapp' ? ' <span class="badge">WhatsApp</span>' : '' ?></td>
                <td><?= h($g['lead_name'] ?? '—') ?><?= (int) $g['attendee_count'] > 1 ? ' 等' : '' ?></td>
                <td><?= (int) $g['attendee_count'] ?></td>
                <td><?= h($g['recorded_by'] ?? '—') ?></td>
                <td><?= h(date('m-d H:i', strtotime($g['created_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="help">
        登記錯了？到<a href="<?= url('/admin/dashboard') ?>">後台</a>取消該筆報名，
        紀錄會保留但不列入人數。<span class="en">Made a mistake? Cancel it from Registrations — it is kept but not counted.</span>
      </p>
    </div>
  <?php else: ?>
    <div class="panel empty-card">
      <div class="empty-icon" aria-hidden="true"><?= icon('walk') ?></div>
      <strong>今天還沒有現場報名 <span class="en">No walk-ins yet</span></strong>
      <p class="help">登記後會顯示在這裡，方便核對。Each walk-in appears here once saved.</p>
    </div>
  <?php endif; ?>
  </div>

  <details class="panel guide">
    <summary><span><?= icon('compass') ?> 現場報名 <span class="en">Walk-in tips</span></span><span class="guide-toggle" aria-hidden="true">顯示 Show ▾</span></summary>

    <ul>
      <li>適用於當天才到、沒有網上報名的善信。<span class="en">For people who did not register online.</span></li>
      <li>儲存後即自動報到，不必再掃描。<span class="en">Saving also checks them in — no scan needed.</span></li>
      <li>現場報名 1 人 1 身份證（必填），年齡規定與網上報名相同。<span class="en">Walk-in: one person, one IC (required); same age rule as online registration.</span></li>
      <li>電話可留空。<span class="en">The phone number is optional.</span></li>
      <li>已在網上報名？請用「現場報到」。<span class="en">Registered online? Use Check-in instead.</span></li>
    </ul>
  </details>
  </div>
  </div>
</div>

<script>
// Values typed into a form that was rejected, so nothing has to be
// retyped at a busy counter.
const OLD = {
  names:    <?= json_encode(array_values(array_map('strval', $oldNames)), JSON_UNESCAPED_UNICODE) ?>,
  ics:      <?= json_encode(array_values(array_map('strval', $oldIcs)), JSON_UNESCAPED_UNICODE) ?>,
  contacts: <?= json_encode(array_values(array_map('strval', $oldContacts)), JSON_UNESCAPED_UNICODE) ?>,
};

function renderRows() {
  // Walk-in: always one person. WhatsApp booking: the number picked.
  const walkin = document.querySelector('.walkin-source input[value="walkin"]').checked;
  const n   = walkin ? 1 : Number(document.getElementById('rowCount').value);
  const box = document.getElementById('walkinRows');
  box.innerHTML = '';

  for (let i = 0; i < n; i++) {
    const name    = OLD.names[i]    || '';
    const ic      = OLD.ics[i]      || '';
    const contact = OLD.contacts[i] || '';
    const div = document.createElement('div');
    div.className = 'attendee';
    div.innerHTML = `
      <h4>參加者 Person ${i + 1}</h4>
      <label>姓名｜Name *</label>
      <input name="attendee_name[]" maxlength="100" placeholder="請輸入姓名" required>
      <div class="form-row">
        <div>
          <label>身份證號碼｜IC No. *</label>
          <input name="attendee_ic[]" maxlength="30" placeholder="例如：651020-10-2020" required data-validate="ic" data-exists-url="<?= h(url('/admin/walkin/ic-check') . '?event=' . (int) $event['id']) ?>" inputmode="numeric" autocomplete="off">
        </div>
        <div>
          <label>聯絡號碼｜Contact（選填）</label>
          <input name="attendee_contact[]" maxlength="30" inputmode="tel" placeholder="例如：012 345 6789" data-validate="phone" autocomplete="off">
        </div>
      </div>`;
    // Set through .value rather than the HTML, so a name containing a
    // quote or an angle bracket cannot break out of the markup.
    div.querySelector('[name="attendee_name[]"]').value    = name;
    div.querySelector('[name="attendee_ic[]"]').value      = ic;
    div.querySelector('[name="attendee_contact[]"]').value = contact;
    box.appendChild(div);
  }

  const first = box.querySelector('input');
  if (first && !first.value) first.focus();
}
renderRows();
</script>
<script>
// 來源: the button says what will happen.
document.querySelectorAll('.walkin-source input').forEach(function (r) {
  r.addEventListener('change', function () {
    document.querySelectorAll('#walkinSubmit [data-for]').forEach(function (s) { s.hidden = s.dataset.for !== r.value; });
    document.getElementById('rowCountBox').hidden = r.value === 'walkin';
    renderRows();
  });
});
</script>
<?php require BASE_PATH . '/app/Views/partials/ic_validate.php'; ?>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
