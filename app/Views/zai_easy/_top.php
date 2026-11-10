<?php
/**
 * 施齋入座 簡易模式 Easy mode — the frame of every screen: no menu, a big
 * 🏠 button home, the page's one question, and huge buttons below.
 * Expects $title (Chinese), $titleEn, $screen.
 */
$site = (new App\Models\Setting())->site();
$fav  = $site['site_favicon_path'] ?: $site['site_logo_path'];
?>
<!DOCTYPE html>
<html lang="zh-Hant" data-base="<?= h(BASE_URL) ?>" data-staff>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#9f211b">
<title><?= h($title) ?>｜施齋入座</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<?php if ($fav): ?><link rel="icon" href="<?= h(BASE_URL . '/' . $fav) ?>"><?php endif; ?>
</head>
<body class="ez">
<header class="ez-bar">
  <?php if ($screen !== 'start'): ?>
    <a class="ez-home" href="<?= url('/admin/zai/easy') ?>"><?= icon('home') ?><span>首頁</span></a>
  <?php else: ?><span class="ez-home ez-home-ph"></span><?php endif; ?>
  <div class="ez-bar-title"><?= icon('chair') ?> 施齋入座</div>
  <?php if (!$onlyZai): ?>
    <a class="ez-home ez-full" href="<?= url('/admin/zai') ?>" title="完整版 Full view"><?= icon('list') ?><span>完整版</span></a>
  <?php elseif ($screen === 'start'): ?>
    <?php // Log out only on the first screen — never next to the working buttons. ?>
    <form method="POST" action="<?= url('/admin/logout') ?>" class="ez-out"><?= csrf_field() ?>
      <button type="submit" class="ez-home" data-confirm="要登出嗎？&#10;Log out?" data-confirm-title="登出 Log out" data-danger><?= icon('log-out') ?><span>登出</span></button></form>
  <?php else: ?><span class="ez-home ez-home-ph"></span><?php endif; ?>
</header>
<main class="ez-main">
  <h1 class="ez-q"><?= h($title) ?><small><?= h($titleEn) ?></small></h1>
