<?php
/**
 * Event details form — used for both editing and creating.
 * $mode is 'edit' or 'new'; $old holds the values of a rejected submit.
 */
$isNew = ($mode === 'new');
$v = static fn(string $key, $fallback = '') => $old[$key] ?? ($event[$key] ?? $fallback);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $isNew ? '新增活動' : '編輯活動' ?>｜天玉堂管理</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@700;900&family=Noto+Sans+TC:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body>

<div class="adminbar">
  <h1>🙏 <?= $isNew ? '新增活動 New Event' : '編輯活動 Edit Event' ?></h1>
  <div style="display:flex;align-items:center;gap:14px">
    <span class="who">您好，<?= h($_SESSION['admin_username']) ?></span>
    <a class="logout" href="<?= url('/admin/dashboard') ?>">← 返回後台</a>
  </div>
</div>

<div class="wrap">
  <div class="panel form-panel">

    <?php if (!empty($errors)): ?>
      <div class="error">
        <strong>請修正以下問題：</strong>
        <ul style="margin:8px 0 0;padding-left:20px">
          <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <p class="help" style="margin-top:0">
      這裡修改的內容會直接顯示在網站首頁，不需要改動程式碼。
    </p>

    <!-- enctype is required: without it the browser sends only the file
         NAME, never the file itself, and $_FILES arrives empty. -->
    <form method="POST" action="<?= url('/admin/event/save') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $isNew ? 0 : (int) $event['id'] ?>">

      <h3>基本資料</h3>

      <label for="name">活動名稱｜Event Name *</label>
      <input id="name" name="name" required maxlength="150"
             value="<?= h($v('name')) ?>" placeholder="中壇元帥 · 千秋寶誕">

      <div class="form-row">
        <div>
          <label for="year">年份｜Year *</label>
          <input id="year" name="year" type="number" required min="2000" max="2100"
                 value="<?= h((string) $v('year')) ?>">
        </div>
        <div>
          <label for="year_label">農曆年｜Lunar Year</label>
          <input id="year_label" name="year_label" maxlength="50"
                 value="<?= h($v('year_label')) ?>" placeholder="丙午年">
        </div>
      </div>

      <label for="subtitle">英文副標｜English Subtitle</label>
      <input id="subtitle" name="subtitle" maxlength="200"
             value="<?= h($v('subtitle')) ?>"
             placeholder="2026 Zhong Tan Marshal Birthday Celebration">

      <label for="location">地點｜Location *</label>
      <textarea id="location" name="location" required rows="2"
                placeholder="PERSATUAN PENGANUT DEWA TAI ZHI&#10;KUALA LUMPUR"><?= h($v('location')) ?></textarea>
      <p class="help">可分兩行輸入，網站會照樣換行顯示。</p>

      <h3>日期</h3>

      <div class="form-row">
        <div>
          <label for="start_date">開始日期｜Start Date *</label>
          <input id="start_date" name="start_date" type="date" required
                 value="<?= h($v('start_date')) ?>">
        </div>
        <div>
          <label for="end_date">結束日期｜End Date *</label>
          <input id="end_date" name="end_date" type="date" required
                 value="<?= h($v('end_date')) ?>">
        </div>
      </div>
      <p class="help">
        網站會自動列出這段期間的每一天，並附上星期幾。單日活動請將兩個日期填相同。
      </p>

      <label for="counter_note">現場詢問處說明｜Counter Note</label>
      <input id="counter_note" name="counter_note" maxlength="255"
             value="<?= h($v('counter_note')) ?>"
             placeholder="現場詢問處開放時間：16/10 及 17/10">

      <h3>報名與布施設定</h3>

      <div class="form-row">
        <div>
          <label for="merit_table_price">功德席價格 (RM)｜Merit Seat Price *</label>
          <input id="merit_table_price" name="merit_table_price" type="number"
                 required min="0" max="100000" step="0.01"
                 value="<?= h((string) $v('merit_table_price')) ?>">
          <p class="help">改了這裡，網站的下拉選單和自動計算的總額都會跟著改。</p>
        </div>
        <div>
          <label for="max_attendees">每次報名人數上限｜Max Attendees *</label>
          <input id="max_attendees" name="max_attendees" type="number"
                 required min="1" max="50"
                 value="<?= h((string) $v('max_attendees')) ?>">
          <p class="help">一次報名最多可填幾位參加者。</p>
        </div>
      </div>

      <?php if (!empty($isSystemAdmin)): ?>
      <h3>圖片</h3>
      <p class="help" style="margin-top:0">
        支援 JPG、PNG、GIF、WebP，單檔 5MB 以內。上傳後系統會自動重新產生圖片並調整尺寸。
      </p>

      <label for="hero_banner">首頁橫幅｜Hero Banner</label>
      <?php $currentBanner = $event['hero_banner_path'] ?? null; ?>
      <?php if ($currentBanner): ?>
        <div class="image-preview">
          <img src="<?= h(BASE_URL . '/' . $currentBanner) ?>" alt="目前的橫幅">
          <label class="remove-check">
            <input type="checkbox" name="remove_hero_banner" value="1"> 移除這張橫幅
          </label>
        </div>
      <?php endif; ?>
      <input id="hero_banner" name="hero_banner" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
      <p class="help">建議寬度 1600–1920px。過寬的圖片會自動縮小到 1920px。<?= $currentBanner ? '選擇新檔案即可取代現有橫幅。' : '' ?></p>

      <label for="favicon">網站小圖示｜Favicon</label>
      <?php $currentFavicon = $event['favicon_path'] ?? null; ?>
      <?php if ($currentFavicon): ?>
        <div class="image-preview favicon">
          <img src="<?= h(BASE_URL . '/' . $currentFavicon) ?>" alt="目前的圖示">
          <label class="remove-check">
            <input type="checkbox" name="remove_favicon" value="1"> 移除這個圖示
          </label>
        </div>
      <?php endif; ?>
      <input id="favicon" name="favicon" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
      <p class="help">
        建議正方形，180×180px 以上。
        <strong>注意：</strong>瀏覽器會長時間快取小圖示，更換後可能需要強制重新整理（Ctrl+F5）才看得到新的。
      </p>

      <?php else: ?>
      <div class="note-box">
        🔒 橫幅與網站小圖示由<strong>系統管理員</strong>設定。如需更換，請聯絡系統管理員。
      </div>
      <?php endif; ?>

      <h3>線上開放時間</h3>
      <p class="help" style="margin-top:0">
        設定線上報名與布施的開放與截止時間，就像演唱會售票一樣。
        <strong>留空表示不限制</strong>：兩欄都空白，該項目就一直開放。<br>
        時間一律以馬來西亞時間（<?= h(APP_TIMEZONE) ?>）為準。
      </p>

      <div class="form-row">
        <div>
          <label for="rsvp_opens_at">報名開放時間｜RSVP Opens</label>
          <input id="rsvp_opens_at" name="rsvp_opens_at" type="datetime-local"
                 value="<?= h(str_replace(' ', 'T', (string) $v('rsvp_opens_at'))) ?>">
        </div>
        <div>
          <label for="rsvp_closes_at">報名截止時間｜RSVP Closes</label>
          <input id="rsvp_closes_at" name="rsvp_closes_at" type="datetime-local"
                 value="<?= h(str_replace(' ', 'T', (string) $v('rsvp_closes_at'))) ?>">
        </div>
      </div>

      <div class="form-row">
        <div>
          <label for="donation_opens_at">布施開放時間｜Donation Opens</label>
          <input id="donation_opens_at" name="donation_opens_at" type="datetime-local"
                 value="<?= h(str_replace(' ', 'T', (string) $v('donation_opens_at'))) ?>">
        </div>
        <div>
          <label for="donation_closes_at">布施截止時間｜Donation Closes</label>
          <input id="donation_closes_at" name="donation_closes_at" type="datetime-local"
                 value="<?= h(str_replace(' ', 'T', (string) $v('donation_closes_at'))) ?>">
        </div>
      </div>

      <?php if ($isNew): ?>
        <div class="note-box">
          新活動建立後<strong>不會立即公開</strong>。確認資料無誤後，請在後台按「設為公開」，
          網站才會切換到這個活動。
        </div>
      <?php endif; ?>

      <div class="form-actions">
        <button class="primary" type="submit">
          <?= $isNew ? '建立活動 Create Event' : '儲存變更 Save Changes' ?>
        </button>
        <a class="mini-btn ghost" href="<?= url('/admin/dashboard') ?>">取消 Cancel</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
