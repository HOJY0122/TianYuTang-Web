<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理後台｜天玉堂 2026</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@700;900&family=Noto+Sans+TC:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body>

<div class="adminbar">
  <h1>🙏 天玉堂 2026 管理系統</h1>
  <div style="display:flex;align-items:center;gap:14px">
    <span class="who">您好，<?= h($_SESSION['admin_username']) ?></span>
    <a class="logout" href="<?= url('/admin/logout') ?>">登出 Logout</a>
  </div>
</div>

<div class="wrap">

  <?php if ($event['is_test']): ?>
    <div class="flash test">
      🧪 <strong>您正在檢視測試活動</strong> — 以下數據僅供測試，不會列入正式紀錄。
    </div>
  <?php endif; ?>

  <?php if (!empty($flash)): ?>
    <div class="flash <?= h($flash['type']) ?>">
      <strong><?= h($flash['title']) ?></strong> — <?= h($flash['message']) ?>
    </div>
  <?php endif; ?>

  <!-- ---------- Event switcher ---------- -->
  <div class="panel eventbar">
    <div>
      <h2 style="margin:0 0 4px"><?= h($event['year']) ?> · <?= h($event['name']) ?></h2>
      <div class="help">
        <?= h($event['start_date']) ?> → <?= h($event['end_date']) ?>
        · 功德席 RM<?= number_format((float) $event['merit_table_price'], 0) ?>
        <?php if (!$event['is_active']): ?>
          · <span class="badge pending">未啟用 Not live</span>
        <?php else: ?>
          · <span class="badge">目前公開 Live</span>
        <?php endif; ?>
        <?php if ($event['is_test']): ?>
          · <span class="badge cancelled">測試 Test</span>
        <?php endif; ?>
      </div>
      <div class="help" style="margin-top:6px">
        <?php
        foreach ([
            App\Models\Event::SECTION_RSVP     => '報名',
            App\Models\Event::SECTION_DONATION => '布施',
        ] as $section => $label):
            $w = App\Models\Event::windowStatus($event, $section);
        ?>
          <span style="margin-right:14px">
            <?= h($label) ?>：
            <?php if ($w['open'] && !$w['closes_at']): ?>
              <span class="badge">開放中</span>
            <?php elseif ($w['open']): ?>
              <span class="badge">開放中</span>
              <span style="opacity:.75">至 <?= h(App\Models\Event::formatDateTime($w['closes_at'])) ?></span>
            <?php elseif ($w['reason'] === 'not_yet'): ?>
              <span class="badge pending">尚未開放</span>
              <span style="opacity:.75"><?= h(App\Models\Event::formatDateTime($w['opens_at'])) ?> 開始</span>
            <?php else: ?>
              <span class="badge cancelled">已截止</span>
              <span style="opacity:.75"><?= h(App\Models\Event::formatDateTime($w['closes_at'])) ?></span>
            <?php endif; ?>
          </span>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="eventbar-actions">
      <?php if (count($allEvents) > 1): ?>
        <form method="GET" action="<?= url('/admin/dashboard') ?>" style="margin:0">
          <select name="event" onchange="this.form.submit()">
            <?php foreach ($allEvents as $e): ?>
              <option value="<?= (int) $e['id'] ?>"<?= (int) $e['id'] === (int) $event['id'] ? ' selected' : '' ?>>
                <?= h($e['year']) ?> — <?= h($e['name']) ?><?= $e['is_test'] ? ' (測試)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>

      <a class="mini-btn" href="<?= url('/admin/event/edit') ?>?id=<?= (int) $event['id'] ?>">✏️ 編輯活動資料</a>
      <a class="mini-btn" href="<?= url('/admin/checkin') ?>?event=<?= (int) $event['id'] ?>">✅ 現場報到</a>
      <a class="mini-btn" href="<?= url('/admin/counter') ?>?event=<?= (int) $event['id'] ?>">💰 現場布施</a>
      <a class="mini-btn" href="<?= url('/admin/photos') ?>?event=<?= (int) $event['id'] ?>">📸 相簿管理</a>
      <a class="mini-btn" href="<?= url('/admin/qr') ?>?event=<?= (int) $event['id'] ?>" target="_blank">🔳 活動 QR</a>
      <a class="mini-btn ghost" href="<?= url('/admin/event/new') ?>">＋ 新增活動</a>

      <?php if ($event['is_test']): ?>
        <form method="POST" action="<?= url('/admin/event/test-delete') ?>" style="margin:0"
              onsubmit="return confirm('確定要刪除這個測試活動嗎？\n所有測試的報名與布施紀錄都會一併刪除，此操作無法復原。');">
          <?= csrf_field() ?>
          <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
          <button class="mini-btn danger" type="submit">🗑 刪除測試資料</button>
        </form>
      <?php else: ?>
        <form method="POST" action="<?= url('/admin/event/test-copy') ?>" style="margin:0"
              onsubmit="return confirm('建立這個活動的測試副本？\n測試資料不會計入正式統計。');">
          <?= csrf_field() ?>
          <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
          <button class="mini-btn ghost" type="submit">🧪 建立測試副本</button>
        </form>
      <?php endif; ?>

      <?php if (!$event['is_active']): ?>
        <form method="POST" action="<?= url('/admin/event/activate') ?>" style="margin:0"
              onsubmit="return confirm('確定要將此活動設為公開顯示嗎？網站首頁會立即切換到這個活動。');">
          <?= csrf_field() ?>
          <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
          <button class="mini-btn" type="submit">設為公開 Make live</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- ---------- Statistics ---------- -->
  <div class="statboard">
    <div class="stats">
      <div class="stat">
        <small>參加人數 Attendees</small>
        <strong><?= (int) $totalAttendees ?></strong>
        <div class="sub">已報到 Arrived: <?= (int) $totalCheckedIn ?></div>
      </div>
      <div class="stat">
        <small>報名組數 Registrations</small>
        <strong><?= (int) $totalGroups ?></strong>
      </div>
      <div class="stat">
        <small>功德席 Merit Seats</small>
        <strong><?= (int) $totalTables ?></strong>
      </div>
      <div class="stat">
        <small>布施總額 Total Pledged</small>
        <strong><?= rm($totalAmount) ?></strong>
        <div class="sub">已收 Received: <?= rm($totalPaid) ?></div>
        <div class="sub">線上 <?= rm($bySource['online']) ?> · 現場 <?= rm($bySource['counter']) ?></div>
      </div>
    </div>
  </div>

  <!-- ---------- RSVP table ---------- -->
  <div class="panel">
    <h2>報名紀錄 · RSVP
      <span class="panel-actions">
        <a class="mini-btn ghost"
           href="<?= url('/admin/export/attendees') ?>?event=<?= (int) $event['id'] ?>">⬇️ CSV</a>
        <a class="mini-btn ghost"
           href="<?= url('/admin/print/attendees') ?>?event=<?= (int) $event['id'] ?>"
           target="_blank">🖨️ 列印報到表</a>
      </span>
    </h2>
    <div class="scroll">
      <table>
        <thead>
          <tr><th>編號</th><th>代表姓名</th><th>人數</th><th>提交時間</th><th>狀態</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php if (empty($rsvpGroups)): ?>
          <tr><td colspan="6" class="empty">尚無報名資料。</td></tr>
        <?php else: ?>
          <?php foreach ($rsvpGroups as $g): ?>
            <tr>
              <td><?= h($g['ref_code']) ?></td>
              <td><?= h($g['lead_name'] ?? '—') ?></td>
              <td><?= (int) $g['attendee_count'] ?> 位</td>
              <td><?= h($g['created_at']) ?></td>
              <td>
                <?php if ($g['status'] === 'confirmed'): ?>
                  <span class="badge">已確認 Confirmed</span>
                <?php elseif ($g['status'] === 'cancelled'): ?>
                  <span class="badge cancelled">已取消 Cancelled</span>
                <?php else: ?>
                  <span class="badge pending">待確認 Pending</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="actions-cell">
                  <?php if ($g['status'] !== 'confirmed'): ?>
                    <form method="POST" action="<?= url('/admin/rsvp/confirm') ?>" style="margin:0">
                      <?= csrf_field() ?>
                      <input type="hidden" name="group_id" value="<?= (int) $g['id'] ?>">
                      <button class="mini-btn" type="submit">確認</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($g['status'] !== 'cancelled'): ?>
                    <form method="POST" action="<?= url('/admin/rsvp/cancel') ?>" style="margin:0"
                          onsubmit="return confirm('確定要取消這筆報名嗎？');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="group_id" value="<?= (int) $g['id'] ?>">
                      <button class="mini-btn ghost" type="submit">取消</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ---------- Donations table ---------- -->
  <div class="panel">
    <h2>布施紀錄 · Donations
      <span class="panel-actions">
        <a class="mini-btn ghost"
           href="<?= url('/admin/export/donations') ?>?event=<?= (int) $event['id'] ?>">⬇️ CSV</a>
        <a class="mini-btn ghost"
           href="<?= url('/admin/print/donations') ?>?event=<?= (int) $event['id'] ?>"
           target="_blank">🖨️ 列印布施表</a>
      </span>
    </h2>
    <div class="scroll">
      <table>
        <thead>
          <tr><th>編號</th><th>姓名</th><th>聯絡</th><th>方式</th><th>詳情</th><th>金額</th><th>狀態</th><th>操作</th></tr>
        </thead>
        <tbody>
        <?php if (empty($donations)): ?>
          <tr><td colspan="9" class="empty">尚無布施資料。</td></tr>
        <?php else: ?>
          <?php foreach ($donations as $d): ?>
            <tr>
              <td><?= h($d['ref_code']) ?></td>
              <td>
                <?php if (($d['source'] ?? 'online') === 'counter'): ?>
                  <span class="badge counter">現場</span>
                  <?php if (!empty($d['receipt_path'])): ?>
                    <a href="<?= h(BASE_URL . '/' . $d['receipt_path']) ?>" target="_blank" title="查看收據">📄</a>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="help">線上</span>
                <?php endif; ?>
              </td>
              <td><?= h($d['name']) ?></td>
              <td><?= h($d['contact_no']) ?></td>
              <td><?= $d['method'] === 'table' ? '功德席' : '隨喜布施' ?></td>
              <td><?= $d['method'] === 'table' ? ((int) $d['table_count'] . ' 席') : '—' ?></td>
              <td><?= rm((float) $d['amount']) ?></td>
              <td>
                <?php if ($d['status'] === 'paid'): ?>
                  <span class="badge">已付 Paid</span>
                <?php else: ?>
                  <span class="badge pending">待付 Pending</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($d['status'] !== 'paid'): ?>
                  <form method="POST" action="<?= url('/admin/donation/paid') ?>" style="margin:0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="donation_id" value="<?= (int) $d['id'] ?>">
                    <button class="mini-btn" type="submit">標記已付</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>
