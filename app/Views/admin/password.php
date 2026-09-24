<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>更改密碼｜天玉堂管理</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@700;900&family=Noto+Sans+TC:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body class="login">
  <div class="login-card">
    <h1>🔑 更改密碼</h1>
    <div class="sub">Change Password — <?= h($_SESSION['admin_username'] ?? '') ?></div>

    <?php if ($forced): ?>
      <div class="error">
        您正在使用系統預設密碼，任何人都可以在說明文件中看到它。請先設定新密碼才能繼續使用後台。
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('/admin/password') ?>">
      <?= csrf_field() ?>
      <label for="current_password">目前密碼｜Current password</label>
      <input id="current_password" name="current_password" type="password" required autofocus autocomplete="current-password">

      <label for="new_password">新密碼｜New password（至少 <?= (int) \App\Models\AdminUser::MIN_PASSWORD_LENGTH ?> 個字元）</label>
      <input id="new_password" name="new_password" type="password" required
             minlength="<?= (int) \App\Models\AdminUser::MIN_PASSWORD_LENGTH ?>" autocomplete="new-password">

      <label for="confirm_password">再次輸入新密碼｜Confirm new password</label>
      <input id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password">

      <?php foreach ($errors as $error): ?>
        <div class="error"><?= h($error) ?></div>
      <?php endforeach; ?>

      <button type="submit">儲存新密碼 Save</button>
    </form>

    <?php if (!$forced): ?>
      <a class="back" href="<?= url('/admin/dashboard') ?>">← 返回後台</a>
    <?php else: ?>
      <form method="POST" action="<?= url('/admin/logout') ?>" style="margin-top:14px;text-align:center">
        <?= csrf_field() ?>
        <button type="submit" class="link-button">登出 Logout</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
