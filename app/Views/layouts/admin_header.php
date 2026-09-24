<?php
/**
 * Shared admin layout — every admin and system page starts with this.
 *
 * Expects: $pageTitle (bilingual, e.g. '報名紀錄 Registrations')
 *          $nav       key of the current menu item (see $menu below)
 * Optional: $flash    one-shot message shown under the title
 *
 * Desktop: fixed sidebar. Phone/tablet: a top bar with a ☰ button that
 * slides the same menu in. One menu, so the two can never disagree.
 */
$site         = (new App\Models\Setting())->site();
$isSystem     = ($_SESSION['admin_role'] ?? 'admin') === 'system_admin';
$nav          = $nav ?? '';
$adminFavicon = $site['site_favicon_path'];
$adminLogo    = $site['site_logo_path'] ?: $adminFavicon;
$versioned    = static fn(string $p): string => BASE_URL . '/' . $p . '?v=' . substr(md5($p), 0, 8);

// [key, icon, 中文, English, url]. The system section only appears for
// system admins — and the server checks the role again on every page.
$menu = [
    '活動管理 Event' => [
        ['dashboard',     '📊', '儀表板',   'Dashboard',     '/admin/dashboard'],
        ['registrations', '📝', '報名紀錄', 'Registrations', '/admin/registrations'],
        ['donations',     '💰', '布施紀錄', 'Donations',     '/admin/donations'],
        ['event',         '📅', '活動資料', 'Event details', '/admin/event/edit'],
        ['posts',         '📰', '最新消息', 'News',          '/admin/posts'],
        ['photos',        '📸', '相簿',     'Photos',        '/admin/photos'],
    ],
    '活動當天 On the day' => [
        ['checkin', '✅', '現場報到', 'Check-in',          '/admin/checkin'],
        ['walkin',  '🚶', '現場報名', 'Walk-in register',  '/admin/walkin'],
        ['counter', '💵', '現場布施', 'Counter donation',  '/admin/counter'],
    ],
    '財務 Finance' => [
        ['receipts', '🧾', '收據紀錄', 'Receipts',       '/admin/receipts'],
    ],
];
if ($isSystem) {
    $menu['系統管理 System'] = [
        ['system', '⚙️', '網站設定', 'Site settings', '/system'],
        ['wording','🔤', '網站文字', 'Wording',       '/system/wording'],
        ['users',  '👥', '帳號管理', 'User accounts', '/system/users'],
        ['qr',     '🔳', 'QR 產生器', 'QR generator', '/system/qr'],
    ];
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h(($pageTitle ?? '管理 Admin') . '｜' . $site['site_name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=LXGW+WenKai+TC:wght@400;700&family=Noto+Sans+TC:wght@400;500;700;800&family=Noto+Sans+SC:wght@400;500;700;800<?= $nav === 'system' ? '&family=Yuji+Boku&family=Yuji+Syuku' : '' ?>&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<?php if ($adminFavicon): ?>
<link rel="icon" href="<?= h($versioned($adminFavicon)) ?>">
<?php endif; ?>
<meta name="robots" content="noindex">
</head>
<body class="admin-app">

<aside class="side" id="sideMenu" aria-label="管理選單 Admin menu">
  <a class="side-brand" href="<?= url($isSystem ? '/system' : '/admin/dashboard') ?>">
    <?php if ($adminLogo): ?><img src="<?= h($versioned($adminLogo)) ?>" alt=""><?php endif; ?>
    <span><strong><?= h($site['site_name']) ?></strong><small><?= $isSystem ? '系統管理員 System admin' : '管理員 Admin' ?></small></span>
  </a>
  <nav>
    <?php
    // Loop variables are prefixed: this file shares scope with the page
    // that includes it, and a plain $group or $key would overwrite the
    // page's own data (a registration's $group, for one).
    foreach ($menu as $_mGroup => $_mItems): ?>
      <div class="side-group"><?= h($_mGroup) ?></div>
      <?php foreach ($_mItems as [$_mKey, $_mIcon, $_mZh, $_mEn, $_mHref]): ?>
        <a class="side-link<?= $nav === $_mKey ? ' active' : '' ?>" href="<?= url($_mHref) ?>"<?= $nav === $_mKey ? ' aria-current="page"' : '' ?>>
          <span class="ico"><?= $_mIcon ?></span><span><?= h($_mZh) ?><small><?= h($_mEn) ?></small></span>
        </a>
      <?php endforeach; ?>
    <?php endforeach; unset($_mGroup, $_mItems, $_mKey, $_mIcon, $_mZh, $_mEn, $_mHref); ?>
  </nav>
  <div class="side-foot">
    <a class="side-link<?= $nav === 'password' ? ' active' : '' ?>" href="<?= url('/account/password') ?>">
      <span class="ico">🔑</span><span>我的密碼<small>My password</small></span></a>
    <a class="side-link" href="<?= url('/') ?>" target="_blank" rel="noopener">
      <span class="ico">🌐</span><span>查看網站<small>View site</small></span></a>
    <form method="POST" action="<?= url('/admin/logout') ?>">
      <?= csrf_field() ?>
      <button type="submit" class="side-link logout-link"><span class="ico">🚪</span><span>登出<small>Log out</small></span></button>
    </form>
  </div>
</aside>
<div class="side-backdrop" id="sideBackdrop"></div>

<div class="main">
  <header class="topbar-admin">
    <button type="button" class="menu-btn" id="menuBtn" aria-controls="sideMenu" aria-expanded="false">☰<span class="sr-only"> 選單 Menu</span></button>
    <h1><?= h($pageTitle ?? '管理 Admin') ?></h1>
    <span class="who">👤 <?= h($_SESSION['admin_display'] ?? ($_SESSION['admin_username'] ?? '')) ?></span>
  </header>
  <div class="content">
  <?php if (!empty($flash)): ?>
    <?php // Shown as a pop-up card by js/dialog.js; stays as a plain line if scripts are off. ?>
    <div class="flash <?= h($flash['type']) ?>" data-flash-modal="<?= h($flash['type']) ?>" data-flash-text="<?= h($flash['message']) ?>"><strong><?= h($flash['title']) ?></strong> — <?= nl2br(h($flash['message'])) ?></div>
  <?php endif; ?>
