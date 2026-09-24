<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>變更密碼｜天玉堂</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@700;900&family=Noto+Sans+TC:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body>

<div class="adminbar">
  <h1>🔑 變更密碼</h1>
  <div style="display:flex;align-items:center;gap:14px">
    <span class="who"><?= h($_SESSION['admin_display'] ?? $_SESSION['admin_username']) ?></span>
    <a class="logout" href="<?= url($homePath ?? '/admin/dashboard') ?>">← 返回</a>
  </div>
</div>

<div class="wrap" style="max-width:520px">

  <?php if (!empty($flash)): ?>
    <div class="flash <?= h($flash['type']) ?>">
      <strong><?= h($flash['title']) ?></strong> — <?= h($flash['message']) ?>
    </div>
  <?php endif; ?>

  <div class="panel form-panel">
    <p class="help" style="margin-top:0">
      變更 <strong><?= h($_SESSION['admin_username']) ?></strong> 的登入密碼。
      變更後仍會保持登入狀態。
    </p>

    <form method="POST" action="<?= url('/account/password') ?>">
      <?= csrf_field() ?>

      <label for="current_password">目前密碼｜Current Password *</label>
      <input id="current_password" name="current_password" type="password" required
             autocomplete="current-password">
      <p class="help">先確認是本人操作，避免有人趁電腦未鎖定時更改密碼。</p>

      <label for="password">新密碼｜New Password *</label>
      <input id="password" name="password" type="password" required minlength="8"
             autocomplete="new-password">
      <p class="help">至少 8 個字元。</p>

      <label for="password_confirm">再次輸入｜Confirm *</label>
      <input id="password_confirm" name="password_confirm" type="password" required minlength="8"
             autocomplete="new-password">

      <div class="form-actions">
        <button class="primary" type="submit">變更密碼</button>
        <a class="mini-btn ghost" href="<?= url('/admin/dashboard') ?>">取消</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
