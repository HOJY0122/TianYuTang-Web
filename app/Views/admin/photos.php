<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>相簿管理｜天玉堂</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@700;900&family=Noto+Sans+TC:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body>

<div class="adminbar">
  <h1>📸 相簿管理 Photo Gallery</h1>
  <div style="display:flex;align-items:center;gap:14px">
    <span class="who">您好，<?= h($_SESSION['admin_username']) ?></span>
    <a class="logout" href="<?= url('/admin/dashboard') ?>">← 返回後台</a>
  </div>
</div>

<div class="wrap">

  <?php if (!empty($flash)): ?>
    <div class="flash <?= h($flash['type']) ?>">
      <strong><?= h($flash['title']) ?></strong> — <?= h($flash['message']) ?>
    </div>
  <?php endif; ?>

  <?php if ($event['is_test']): ?>
    <div class="flash test">🧪 這是測試活動的相簿，不會出現在正式網站上。</div>
  <?php endif; ?>

  <!-- ---------- Which event ---------- -->
  <div class="panel eventbar">
    <div>
      <h2 style="margin:0 0 4px"><?= h($event['year']) ?> · <?= h($event['name']) ?></h2>
      <div class="help">目前相簿共 <?= count($photos) ?> 張相片</div>
    </div>
    <div class="eventbar-actions">
      <?php if (count($allEvents) > 1): ?>
        <form method="GET" action="<?= url('/admin/photos') ?>" style="margin:0">
          <select name="event" onchange="this.form.submit()">
            <?php foreach ($allEvents as $e): ?>
              <option value="<?= (int) $e['id'] ?>"<?= (int) $e['id'] === (int) $event['id'] ? ' selected' : '' ?>>
                <?= h($e['year']) ?> — <?= h($e['name']) ?><?= $e['is_test'] ? ' (測試)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>
      <a class="mini-btn ghost" href="<?= url('/gallery') ?>?year=<?= (int) $event['id'] ?>" target="_blank">預覽前台相簿 ↗</a>
    </div>
  </div>

  <!-- ---------- Upload ---------- -->
  <div class="panel">
    <h2>上傳相片</h2>
    <form method="POST" action="<?= url('/admin/photos/upload') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">

      <input type="file" name="photos[]" multiple required
             accept="image/jpeg,image/png,image/gif,image/webp"
             class="file-input">

      <p class="help" style="margin-top:10px">
        可一次選擇多張相片（按住 Ctrl 或 Shift 多選）。單張 5MB 以內，
        一次最多 <?= (int) $maxFiles ?> 張，且整批總大小不可超過 <?= h($postMax) ?>。
        手機拍的相片通常每張 2–4MB，建議<strong>每次上傳 3–5 張</strong>較保險。
        系統會自動產生縮圖，前台不會載入原始大圖。
      </p>

      <button class="mini-btn" type="submit" style="margin-top:14px;padding:12px 22px">⬆️ 上傳相片</button>
    </form>
  </div>

  <!-- ---------- Manage ---------- -->
  <div class="panel">
    <h2>相片管理</h2>

    <?php if (!$photos): ?>
      <p class="help">尚未上傳任何相片。</p>
    <?php else: ?>
      <p class="help" style="margin-bottom:16px">
        排列順序即為前台顯示順序。使用 ↑ ↓ 調整，說明文字可留空。
      </p>

      <div class="admin-photo-grid">
        <?php foreach ($photos as $i => $photo): ?>
          <div class="admin-photo">
            <img src="<?= h(BASE_URL . '/' . $photo['thumb_path']) ?>" alt="相片 <?= $i + 1 ?>">

            <form method="POST" action="<?= url('/admin/photos/caption') ?>" class="caption-form">
              <?= csrf_field() ?>
              <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
              <input type="text" name="caption" maxlength="255"
                     value="<?= h($photo['caption'] ?? '') ?>" placeholder="相片說明（選填）">
              <button class="mini-btn ghost" type="submit">儲存</button>
            </form>

            <div class="admin-photo-actions">
              <form method="POST" action="<?= url('/admin/photos/move') ?>" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                <input type="hidden" name="direction" value="up">
                <button class="mini-btn ghost" type="submit" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
              </form>

              <form method="POST" action="<?= url('/admin/photos/move') ?>" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                <input type="hidden" name="direction" value="down">
                <button class="mini-btn ghost" type="submit" <?= $i === count($photos) - 1 ? 'disabled' : '' ?>>↓</button>
              </form>

              <form method="POST" action="<?= url('/admin/photos/delete') ?>" style="margin:0"
                    onsubmit="return confirm('確定要刪除這張相片嗎？此操作無法復原。');">
                <?= csrf_field() ?>
                <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                <button class="mini-btn danger" type="submit">🗑</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
