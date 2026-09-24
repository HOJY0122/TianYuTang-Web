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
<?php if (!empty($event['favicon_path'])): ?>
  <?php
  // The ?v= is not decoration. Browsers cache favicons far longer than
  // other assets, so without a changing query string the committee
  // uploads a new icon, sees the old one, and assumes it failed.
  $faviconUrl = BASE_URL . '/' . $event['favicon_path'];
  $faviconVer = substr(md5($event['favicon_path']), 0, 8);
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
<div class="topbar">🙏 感恩您的參與與支持　｜　Thank you for your kind support</div>

<header>
  <nav class="nav">
    <a class="brand" href="<?= url('/') ?>">天玉堂</a>
    <div class="navlinks">
      <a href="<?= url('/') ?>#home">首頁</a>
      <a href="<?= url('/') ?>#rsvp">報名</a>
      <a href="<?= url('/') ?>#donation">布施</a>
      <a href="<?= url('/gallery') ?>">相簿</a>
      <a href="<?= url('/admin/login') ?>">管理</a>
    </div>
  </nav>
</header>
