<?php
/**
 * Event details form — used for both editing and creating.
 * $mode is 'edit' or 'new'; $old holds the values of a rejected submit.
 * Everything here appears on the public site without touching code.
 */
$isNew     = ($mode === 'new');
$v         = static fn(string $key, $fallback = '') => $old[$key] ?? ($event[$key] ?? $fallback);
$dt        = static fn(string $key): string => h(str_replace(' ', 'T', substr((string) $v($key), 0, 16)));
$pageTitle = $isNew ? '新增活動 New Event' : '活動資料 Event Details';
$nav       = 'event';
require BASE_PATH . '/app/Views/layouts/admin_header.php';
?>
<?php if (!empty($errors)): ?>
  <div class="flash error"><strong>請修正以下問題 Please fix:</strong>
    <ul style="margin:6px 0 0;padding-left:20px"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<?php if (!$isNew && !empty($allEvents) && count($allEvents) > 1): ?>
  <form method="GET" action="<?= url('/admin/event/edit') ?>" class="panel toolbar" style="padding:12px 16px">
    <label class="field">正在編輯 Editing
      <select name="id" onchange="this.form.submit()">
        <?php foreach ($allEvents as $e): ?>
          <option value="<?= (int) $e['id'] ?>"<?= (int) $e['id'] === (int) $event['id'] ? ' selected' : '' ?>>
            <?= h($e['year']) ?> — <?= h($e['name']) ?><?= $e['is_test'] ? '（測試 Test）' : '' ?><?= $e['is_active'] ? ' ✓ 公開 Live' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <span class="spacer"></span>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/event/new') ?>">＋ 新增活動 New event</a>
  </form>
<?php endif; ?>

<form method="POST" action="<?= url('/admin/event/save') ?>" enctype="multipart/form-data" class="panel form-panel" style="max-width:900px">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $isNew ? 0 : (int) $event['id'] ?>">
  <p class="help" style="margin-top:0">這裡的內容會直接顯示在網站上。Everything here appears on the public website.
    <?php if (!empty($isSystemAdmin)): ?><br>網站名稱、標誌、橫幅與頁尾在「網站設定」。Site name, logo, banner and footer are in Site settings.<?php endif; ?></p>

  <h3>① 基本資料 <span class="en">Basics</span></h3>
  <label for="name">活動名稱 <span class="en">Event name *</span></label>
  <input id="name" name="name" required maxlength="150" value="<?= h($v('name')) ?>" placeholder="中壇元帥千秋寶誕">
  <div class="form-grid">
    <div><label for="year">年份 <span class="en">Year *</span></label>
      <input id="year" name="year" type="number" required min="2000" max="2100" value="<?= h((string) $v('year')) ?>"></div>
    <div><label for="year_label">干支年 <span class="en">Year label</span></label>
      <input id="year_label" name="year_label" maxlength="50" value="<?= h($v('year_label')) ?>" placeholder="丙午年"></div>
  </div>
  <label for="subtitle">英文副標題 <span class="en">English subtitle</span></label>
  <input id="subtitle" name="subtitle" maxlength="200" value="<?= h($v('subtitle')) ?>" placeholder="2026 Zhong Tan Marshal Birthday Celebration">

  <h3>② 日期與地點 <span class="en">Date &amp; venue</span></h3>
  <div class="form-grid">
    <div><label for="start_date">開始日期 <span class="en">Start date *</span></label>
      <input id="start_date" name="start_date" type="date" required value="<?= h($v('start_date')) ?>"></div>
    <div><label for="end_date">結束日期 <span class="en">End date *</span></label>
      <input id="end_date" name="end_date" type="date" required value="<?= h($v('end_date')) ?>"></div>
  </div>
  <label for="location">地點 <span class="en">Venue *</span></label>
  <textarea id="location" name="location" rows="2" required maxlength="255"><?= h($v('location')) ?></textarea>

  <h3>③ 首頁內容 <span class="en">Home page content</span></h3>
  <label for="welcome_zh">歡迎詞（中文）<span class="en">Welcome text (Chinese)</span></label>
  <textarea id="welcome_zh" name="welcome_zh" rows="3" maxlength="3000"><?= h($v('welcome_zh')) ?></textarea>
  <label for="welcome_en">歡迎詞（英文）<span class="en">Welcome text (English)</span></label>
  <textarea id="welcome_en" name="welcome_en" rows="3" maxlength="3000"><?= h($v('welcome_en')) ?></textarea>
  <div class="form-grid">
    <div><label for="counter_note">現場詢問說明 <span class="en">Counter / enquiry note</span></label>
      <input id="counter_note" name="counter_note" maxlength="255" value="<?= h($v('counter_note')) ?>" placeholder="現場詢問處開放時間：16/10 及 17/10"></div>
    <div><label for="contact_info">聯絡電話 <span class="en">Contact number</span></label>
      <input id="contact_info" name="contact_info" maxlength="255" value="<?= h($v('contact_info')) ?>" placeholder="012-345 6789 (WhatsApp)"></div>
  </div>

  <h3>④ 導航 <span class="en">Directions (Waze &amp; Google Maps)</span></h3>
  <p class="help">在 Waze 或 Google 地圖按「分享」，複製連結貼在這裡。In Waze or Google Maps tap Share, copy the link, paste it here.</p>
  <div class="form-grid">
    <div><label for="waze_url">Waze 連結 <span class="en">Waze link</span></label>
      <input id="waze_url" name="waze_url" type="url" maxlength="500" value="<?= h($v('waze_url')) ?>" placeholder="https://waze.com/ul/..."></div>
    <div><label for="maps_url">Google 地圖連結 <span class="en">Google Maps link</span></label>
      <input id="maps_url" name="maps_url" type="url" maxlength="500" value="<?= h($v('maps_url')) ?>" placeholder="https://maps.app.goo.gl/..."></div>
  </div>
  <label for="waze_qr">Waze QR Code 圖片 <span class="en">Waze QR image (optional)</span></label>
  <?php $qr = $event['waze_qr_path'] ?? null; ?>
  <?php if ($qr): ?>
    <div class="image-preview favicon">
      <img src="<?= h(BASE_URL . '/' . $qr) ?>" alt="Waze QR" style="max-height:120px">
      <label class="remove-check"><input type="checkbox" name="remove_waze_qr" value="1"> 移除 Remove</label>
    </div>
  <?php endif; ?>
  <input id="waze_qr" name="waze_qr" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
  <p class="help">不上傳也可以：有 Waze 連結時，網站會自動產生 QR Code。Optional — without an image, a QR code is made from the Waze link automatically.</p>

  <h3>⑤ 報名與布施設定 <span class="en">Registration &amp; donation settings</span></h3>
  <div class="form-grid">
    <div><label for="merit_table_price">功德席每席價格 <span class="en">Price per merit seat (RM) *</span></label>
      <input id="merit_table_price" name="merit_table_price" type="number" required min="0" max="100000" step="0.01" value="<?= h((string) $v('merit_table_price')) ?>"></div>
    <div><label for="max_attendees">每次報名最多人數 <span class="en">Max people per registration *</span></label>
      <input id="max_attendees" name="max_attendees" type="number" required min="1" max="50" value="<?= h((string) $v('max_attendees')) ?>"></div>
  </div>
  <p class="help" style="margin:18px 0 0"><strong>線上布施上下限 Online donation limits</strong>（留空＝不限 blank = no limit）。
    超過上限時，網站會感謝布施者，並請他們再提交一次餘額或於活動當日到櫃台辦理。現場布施不受限制。<br>
    <span class="en">Above the maximum, donors are thanked and asked to submit again for the remainder or visit the counter. Counter donations are never limited.</span></p>
  <div class="form-grid">
    <div><label for="seats_min">功德席最少 <span class="en">Min seats</span></label>
      <input id="seats_min" name="seats_min" type="number" min="1" max="200" value="<?= h((string) $v('seats_min')) ?>" placeholder="1"></div>
    <div><label for="seats_max">功德席最多 <span class="en">Max seats per submission</span></label>
      <input id="seats_max" name="seats_max" type="number" min="1" max="200" value="<?= h((string) $v('seats_max')) ?>" placeholder="例 e.g. 10"></div>
    <div><label for="free_min">隨喜最少 (RM) <span class="en">Min freewill</span></label>
      <input id="free_min" name="free_min" type="number" min="1" step="0.01" value="<?= h((string) $v('free_min')) ?>" placeholder="例 e.g. 10"></div>
    <div><label for="free_max">隨喜最多 (RM) <span class="en">Max freewill per submission</span></label>
      <input id="free_max" name="free_max" type="number" min="1" step="0.01" value="<?= h((string) $v('free_max')) ?>" placeholder="例 e.g. 5000"></div>
  </div>

  <label for="rsvp_note">報名頁說明 <span class="en">Note on the registration page</span></label>
  <textarea id="rsvp_note" name="rsvp_note" rows="2" maxlength="3000" placeholder="席位有限，敬請提前登記。"><?= h($v('rsvp_note')) ?></textarea>
  <label for="donation_note">布施頁說明 <span class="en">Note on the donation page</span></label>
  <textarea id="donation_note" name="donation_note" rows="2" maxlength="3000" placeholder="例：銀行轉帳資料"><?= h($v('donation_note')) ?></textarea>

  <h3>⑥ 開放時間 <span class="en">When forms are open</span></h3>
  <p class="help">留空代表不限制。Leave blank for no limit. 時間以馬來西亞時間計算 Malaysia time.</p>
  <div class="form-grid">
    <div><label for="rsvp_opens_at">報名開始 <span class="en">Registration opens</span></label>
      <input id="rsvp_opens_at" name="rsvp_opens_at" type="datetime-local" value="<?= $dt('rsvp_opens_at') ?>"></div>
    <div><label for="rsvp_closes_at">報名截止 <span class="en">Registration closes</span></label>
      <input id="rsvp_closes_at" name="rsvp_closes_at" type="datetime-local" value="<?= $dt('rsvp_closes_at') ?>"></div>
    <div><label for="donation_opens_at">布施開始 <span class="en">Donation opens</span></label>
      <input id="donation_opens_at" name="donation_opens_at" type="datetime-local" value="<?= $dt('donation_opens_at') ?>"></div>
    <div><label for="donation_closes_at">布施截止 <span class="en">Donation closes</span></label>
      <input id="donation_closes_at" name="donation_closes_at" type="datetime-local" value="<?= $dt('donation_closes_at') ?>"></div>
  </div>

  <div class="form-actions">
    <button class="primary" type="submit">💾 <?= $isNew ? '建立活動 Create event' : '儲存 Save changes' ?></button>
    <?php if (!$isNew): ?><a class="mini-btn ghost" href="<?= url('/') ?>" target="_blank">👀 查看網站 View site</a><?php endif; ?>
  </div>
</form>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
