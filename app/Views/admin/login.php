<?php
$logo    = $site['site_logo_path'] ?: $site['site_favicon_path'];
$favicon = $site['site_favicon_path'];
// Same heading typeface as the public site, so the name looks the same everywhere.
[$loginFont, $loginFontParam] = (new App\Models\Setting())->headingFont();
$names   = ['site' => $site['site_name'], 'site_en' => $site['site_name_en']];
$titleZh = t('login.title', 'zh', $names);
$titleEn = t('login.title', 'en', $names);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理登入 Admin Login｜<?= h($site['site_name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=<?= $loginFontParam ?><?= $loginFontParam !== 'LXGW+WenKai+TC:wght@400;700' ? '&family=LXGW+WenKai+TC:wght@400;700' : '' ?>&family=Noto+Sans+TC:wght@400;500;700;800&family=Noto+Sans+SC:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<?php if ($favicon): ?><link rel="icon" href="<?= h(BASE_URL . '/' . $favicon) ?>"><?php endif; ?>
<meta name="robots" content="noindex">
<style>
.login-card h1{font-family:"<?= h($loginFont) ?>","LXGW WenKai TC","KaiTi","STKaiti",serif;font-weight:<?= $loginFont === 'LXGW WenKai TC' ? 700 : 400 ?>}
</style>
<script src="<?= asset('js/fit-screen.js') ?>"></script>
</head>
<body class="login">
  <div class="login-card">
    <?php if ($logo): ?><img class="login-logo" src="<?= h(BASE_URL . '/' . $logo) ?>" alt=""><?php endif; ?>
    <?php if ($titleZh !== ''): ?><h1><?= h($titleZh) ?></h1><?php endif; ?>
    <?php if ($titleEn !== ''): ?><div class="login-en"><?= h($titleEn) ?></div><?php endif; ?>
    <?php $subZh = t('login.sub'); $subEn = t('login.sub', 'en'); ?>
    <?php if ($subZh !== '' || $subEn !== ''): ?><div class="sub"><?= h(trim($subZh . ($subZh !== '' && $subEn !== '' ? ' · ' : '') . $subEn)) ?></div><?php endif; ?>

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

      <button type="submit"<?= !empty($preview) ? ' disabled' : '' ?>><?= h(trim(t('login.button') . ' ' . t('login.button', 'en'))) ?></button>
    </form>

    <?php $backZh = t('login.back'); $backEn = t('login.back', 'en'); ?>
    <?php if ($backZh !== '' || $backEn !== ''): ?><a class="back" href="<?= url('/') ?>"><?= h(trim($backZh . ' ' . $backEn)) ?></a><?php endif; ?>
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
