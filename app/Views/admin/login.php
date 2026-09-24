<?php
$logo    = $site['site_logo_path'] ?: $site['site_favicon_path'];
$favicon = $site['site_favicon_path'];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理登入 Admin Login｜<?= h($site['site_name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=LXGW+WenKai+TC:wght@400;700&family=Noto+Sans+TC:wght@400;500;700;800&family=Noto+Sans+SC:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<?php if ($favicon): ?><link rel="icon" href="<?= h(BASE_URL . '/' . $favicon) ?>"><?php endif; ?>
<meta name="robots" content="noindex">
</head>
<body class="login">
  <div class="login-card">
    <?php if ($logo): ?><img class="login-logo" src="<?= h(BASE_URL . '/' . $logo) ?>" alt=""><?php endif; ?>
    <h1><?= h($site['site_name']) ?></h1>
    <div class="sub">管理登入 · Admin Login</div>

    <form method="POST" action="<?= url('/admin/login') ?>">
      <?= csrf_field() ?>
      <label for="username">帳號 <span class="en">Username</span></label>
      <input id="username" name="username" required autofocus autocomplete="username" autocapitalize="none">

      <label for="password">密碼 <span class="en">Password</span></label>
      <div class="pw-field">
        <input id="password" name="password" type="password" required autocomplete="current-password">
        <button type="button" class="pw-eye" data-toggle-password="password" aria-pressed="false"
                title="顯示密碼 Show password">👁</button>
      </div>

      <?php if (!empty($error)): ?>
        <div class="error" role="alert">
          <?= nl2br(h($error['message'])) ?>
          <?php if (isset($error['attempts']) && $error['attempts'] > 0): ?>
            <span class="attempts">
              還剩 <?= (int) $error['attempts'] ?> 次機會，之後將暫停登入 <?= App\Models\LoginAttempt::WINDOW_MINUTES ?> 分鐘。<br>
              <?= (int) $error['attempts'] ?> attempt<?= $error['attempts'] > 1 ? 's' : '' ?> left before a <?= App\Models\LoginAttempt::WINDOW_MINUTES ?>-minute lock.
            </span>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <button type="submit">登入 Log in</button>
    </form>

    <a class="back" href="<?= url('/') ?>">← 返回網站首頁 Back to website</a>
  </div>
<script>
document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var input = document.getElementById(btn.dataset.togglePassword);
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    btn.textContent = show ? '🙈' : '👁';
    btn.title = show ? '隱藏密碼 Hide password' : '顯示密碼 Show password';
  });
});
</script>
</body>
</html>
