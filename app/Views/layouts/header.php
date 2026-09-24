<?php
/**
 * Public site header.
 *
 * Expects (all optional): $pageTitle, $event, $activeNav
 * ('home' | 'register' | 'donate' | 'gallery').
 *
 * Everything shown here — name, logo, favicon, top-bar text — is a site
 * setting the system admin edits at /system. Nothing is hard-coded.
 */
$site      = (new App\Models\Setting())->site();
$siteName  = $site['site_name'];
$activeNav = $activeNav ?? '';

// Before migration 006 the favicon/banner lived on the event row; keep
// reading it there so an install mid-upgrade still shows them.
$siteFavicon    = $site['site_favicon_path'] ?: ($event['favicon_path'] ?? null);
$siteHeroBanner = $site['site_banner_path']  ?: ($event['hero_banner_path'] ?? null);
$siteLogo       = $site['site_logo_path'];

/** Versioned upload URL: browsers cache icons hard, a new path forces a refresh. */
$uploadUrl = static fn(string $path): string => BASE_URL . '/' . $path . '?v=' . substr(md5($path), 0, 8);

$navItems = [
    'home'     => ['/',         '首頁', 'Home'],
    'register' => ['/register', '報名', 'Register'],
    'donate'   => ['/donate',   '布施', 'Donate'],
    'gallery'  => ['/gallery',  '相簿', 'Gallery'],
];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h(($pageTitle ?? '') !== '' ? $pageTitle . '｜' . $siteName : $siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=LXGW+WenKai+TC:wght@400;700&family=Noto+Sans+TC:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
<?php if ($siteFavicon): ?>
<link rel="icon" href="<?= h($uploadUrl($siteFavicon)) ?>">
<link rel="apple-touch-icon" href="<?= h($uploadUrl($siteFavicon)) ?>">
<?php endif; ?>
<script>
// Remembered larger text, applied before the page draws so it never jumps.
try { if (localStorage.getItem('tyt-text') === 'lg') document.documentElement.classList.add('text-lg'); } catch (e) {}
</script>
</head>
<body>
<a class="skip" href="#main">跳到內容 Skip to content</a>

<?php if (!empty($event['is_test'])): ?>
  <div class="testbar">
    ⚠️ 測試模式 TEST MODE — 此頁面僅供內部測試，所有報名與布施資料<strong>不會列入正式紀錄</strong>。
    Submissions here are <strong>not</strong> real records.
  </div>
<?php endif; ?>
<?php if (($site['site_tagline'] ?? '') !== ''): ?>
  <div class="topbar"><?= h($site['site_tagline']) ?></div>
<?php endif; ?>

<header class="site-header">
  <div class="header-inner">
    <a class="brand" href="<?= url('/') ?>" aria-label="<?= h($siteName) ?> 首頁 Home">
      <?php if ($siteLogo): ?>
        <img src="<?= h($uploadUrl($siteLogo)) ?>" alt="">
      <?php endif; ?>
      <span class="brand-text">
        <strong><?= h($siteName) ?></strong>
        <?php if (($site['site_name_en'] ?? '') !== ''): ?>
          <small><?= h($site['site_name_en']) ?></small>
        <?php endif; ?>
      </span>
    </a>

    <button type="button" class="text-toggle" id="textToggle" aria-pressed="false"
            title="放大字體 Larger text">A+</button>

    <nav class="site-nav" aria-label="主選單 Main menu">
      <?php // Prefixed loop variables: this file shares scope with the page.
      foreach ($navItems as $_nKey => [$_nHref, $_nZh, $_nEn]): ?>
        <a href="<?= url($_nHref) ?>"<?= $activeNav === $_nKey ? ' class="active" aria-current="page"' : '' ?>>
          <?= h($_nZh) ?><small><?= h($_nEn) ?></small>
        </a>
      <?php endforeach; unset($_nKey, $_nHref, $_nZh, $_nEn); ?>
    </nav>
  </div>
</header>
<script>
(function () {
  var btn = document.getElementById('textToggle');
  var root = document.documentElement;
  btn.setAttribute('aria-pressed', root.classList.contains('text-lg') ? 'true' : 'false');
  btn.addEventListener('click', function () {
    var on = root.classList.toggle('text-lg');
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    try { localStorage.setItem('tyt-text', on ? 'lg' : ''); } catch (e) {}
  });
})();
</script>
