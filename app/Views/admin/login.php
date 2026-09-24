<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理登入｜天玉堂 2026</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@700;900&family=Noto+Sans+TC:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body class="login">
  <div class="login-card">
    <h1>🙏 天玉堂管理登入</h1>
    <div class="sub">Admin Login — 2026 中壇元帥千秋寶誕</div>

    <form method="POST" action="<?= url('/admin/login') ?>">
      <?= csrf_field() ?>
      <label for="username">帳號｜Username</label>
      <input id="username" name="username" required autofocus autocomplete="username">

      <label for="password">密碼｜Password</label>
      <input id="password" name="password" type="password" required autocomplete="current-password">

      <?php if (!empty($error)): ?>
        <div class="error"><?= h($error) ?></div>
      <?php endif; ?>

      <button type="submit">登入 Login</button>
    </form>

    <a class="back" href="<?= url('/') ?>">← 返回網站首頁</a>
  </div>
</body>
</html>
