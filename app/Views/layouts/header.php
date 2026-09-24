<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? SITE_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@400;500;600;700;800;900&family=Noto+Sans+TC:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
<?php
// Site-level branding, set by a system admin. Read before the <head>
// closes because the favicon lives up here.
//
// The fallback to the event's own column is what keeps a site working
// mid-upgrade: before migration 006 the banner and icon belonged to the
// event, and an install that has not run it yet must still show them.
$siteSettings   = (new App\Models\Setting())->all();
$siteName       = $siteSettings['site_name']    ?? '天玉堂';
$siteTagline    = $siteSettings['site_tagline'] ?? '🙏 感恩您的參與與支持　｜　Thank you for your kind support';
$siteFavicon    = $siteSettings['site_favicon_path'] ?? ($event['favicon_path']     ?? null);
$siteHeroBanner = $siteSettings['site_banner_path']  ?? ($event['hero_banner_path'] ?? null);
?>
<?php if (!empty($siteFavicon)): ?>
  <?php
  // The ?v= is not decoration. Browsers cache favicons far longer than
  // other assets, so without a changing query string the committee
  // uploads a new icon, sees the old one, and assumes it failed.
  $faviconUrl = BASE_URL . '/' . $siteFavicon;
  $faviconVer = substr(md5($siteFavicon), 0, 8);
  ?>
  <link rel="icon" href="<?= h($faviconUrl) ?>?v=<?= h($faviconVer) ?>">
  <link rel="apple-touch-icon" href="<?= h($faviconUrl) ?>?v=<?= h($faviconVer) ?>">
<?php endif; ?>
</head>
<body>
<?php if (!empty($event['is_test'])): ?>
  <div class="testbar">
    ⚠️ 測試模式 TEST MODE — 此頁面僅供內部測試，所有報名與布施資料<strong>不會列入正式紀錄</strong>。
  </div>
<?php endif; ?>
<?php if ($siteTagline !== ''): ?>
  <div class="topbar"><?= h($siteTagline) ?></div>
<?php endif; ?>

<header>
  <nav class="nav">
    <a class="brand" href="<?= url('/') ?>"><?= h($siteName) ?></a>
    <div class="navlinks">
      <a href="<?= url('/') ?>#home">首頁</a>
      <a href="<?= url('/') ?>#rsvp">報名</a>
      <a href="<?= url('/') ?>#donation">布施</a>
      <a href="<?= url('/gallery') ?>">相簿</a>
      <a href="<?= url('/admin/login') ?>">管理</a>
    </div>
  </nav>
</header>
