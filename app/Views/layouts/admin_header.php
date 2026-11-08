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
        ['dashboard',     'chart', '儀表板',   'Dashboard',     '/admin/dashboard'],
        ['control',       'settings', '控制台', 'Control panel', '/admin/control'],
        ['registrations', 'form', '報名紀錄', 'Registrations', '/admin/registrations'],
        ['donations',     'coins', '布施紀錄', 'Donations',     '/admin/donations'],
        ['event',         'calendar', '活動資料', 'Event details', '/admin/event/edit'],
        ['posts',         'newspaper', '最新消息', 'News',          '/admin/posts'],
        ['photos',        'camera', '相簿',     'Photos',        '/admin/photos'],
    ],
    '活動當天 On the day' => [
        ['walkin',  'walk', '現場報名', 'Walk-in register',  '/admin/walkin'],
        ['counter', 'cash', '現場布施', 'Counter donation',  '/admin/counter'],
        ['zai',     'chair', '施齋入座', 'Day 3 meal seating', '/admin/zai'],
    ],
    '財務 Finance' => [
        ['receipts', 'receipt', '收據紀錄', 'Receipts',       '/admin/receipts'],
        ['vouchers', 'gift', '布施憑單', 'Donation vouchers', '/admin/vouchers'],
    ],
];
// Receipts switched off in Site settings: gone for admins; system admins
// keep it, marked off (ReceiptController enforces the same rule).
if (($site['receipts_enabled'] ?? '1') !== '1') {
    if ($isSystem) {
        $menu['財務 Finance'][0][3] = 'Receipts · 停用 Off';
    } else {
        array_shift($menu['財務 Finance']);   // vouchers stay
    }
}
// An admin limited to chosen functions sees only those (the server
// checks every page and action as well — App\Core\Access).
foreach ($menu as $_mGroup => $_mItems) {
    $menu[$_mGroup] = array_values(array_filter($_mItems, static fn($i) => App\Core\Access::can($i[0])));
    if (!$menu[$_mGroup]) {
        unset($menu[$_mGroup]);
    }
}
unset($_mGroup, $_mItems);
if ($isSystem) {
    $menu['系統管理 System'] = [
        ['system', 'settings', '網站設定', 'Site settings', '/system'],
        ['banners','image', '首頁橫幅', 'Home banner',   '/system/banners'],
        ['about',  'users', '關於我們', 'About page',    '/system/about'],
        ['wording','type', '網站文字', 'Wording',       '/system/wording'],
        ['forms',  'palette', '表單與字體', 'Forms & fonts', '/system/forms'],
        ['users',  'users', '帳號管理', 'User accounts', '/system/users'],
        ['qr',     'qr', 'QR 產生器', 'QR generator', '/system/qr'],
        ['uat',    'flask', 'UAT 測試模式', 'UAT test mode', '/system/uat'],
        ['sleep',  'pause', '休眠模式', 'Sleep mode', '/system/sleep'],
    ];
}
// The section of the page being shown (for the top bar: "活動管理 › …").
$_mHere = '';
foreach ($menu as $_mGroup => $_mItems) {
    foreach ($_mItems as $_mItem) {
        if ($_mItem[0] === $nav) {
            $_mHere = $_mGroup;
        }
    }
}
unset($_mGroup, $_mItems, $_mItem);
?>
<!DOCTYPE html>
<html lang="zh-Hant" data-base="<?= h(BASE_URL) ?>" data-staff>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h(($pageTitle ?? '管理 Admin') . '｜' . $site['site_name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=LXGW+WenKai+TC:wght@400;700&family=Noto+Sans+TC:wght@400;500;700;800&family=Noto+Sans+SC:wght@400;500;700;800<?= $nav === 'system' ? '&family=Yuji+Boku&family=Yuji+Syuku' : '' ?>&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<link rel="stylesheet" href="<?= asset('css/uat.css') ?>">
<?php if ($adminFavicon): ?>
<link rel="icon" href="<?= h($versioned($adminFavicon)) ?>">
<?php endif; ?>
<meta name="robots" content="noindex">
<script src="<?= asset('js/fit-screen.js') ?>"></script>
<script>
// Sidebar as a narrow icon rail? Decided before the page draws, so it
// never jumps: the person's own choice, else narrow on mid-size laptops.
(function () {
  try {
    var v = localStorage.getItem('tyt-side-rail'), w = window.innerWidth;
    if (v === '1' || (v === null && w > 1000 && w < 1280)) document.documentElement.classList.add('side-rail');
  } catch (e) {}
})();
</script>
</head>
<body class="admin-app">

<aside class="side" id="sideMenu" aria-label="管理選單 Admin menu">
  <div class="side-top">
    <a class="side-brand" id="liveSideBrand" data-live="settings" href="<?= url(App\Core\Access::home()) ?>">
      <?php if ($adminLogo): ?><img src="<?= h($versioned($adminLogo)) ?>" alt=""><?php else: ?><b class="side-mark" aria-hidden="true"><?= h(mb_substr($site['site_name'], 0, 1)) ?></b><?php endif; ?>
      <span><strong><?= h($site['site_name']) ?></strong><small><?= $isSystem ? '系統管理員 System admin' : '管理員 Admin' ?></small></span>
    </a>
  </div>
  <div class="side-tools">
    <button type="button" class="side-find" data-goto-open data-label="快速前往 Go to a page (Ctrl K)">
      <span class="ico"><?= icon('search') ?></span><span class="side-find-text">快速前往<small>Go to a page</small></span><kbd>Ctrl K</kbd>
    </button>
    <button type="button" class="side-rail-btn" id="sideRailBtn" aria-pressed="false" aria-label="收起選單 Collapse menu" data-label="收起選單 Collapse menu · ["><?= icon('sidebar') ?></button>
  </div>
  <nav id="sideNav">
    <?php
    // Loop variables are prefixed: this file shares scope with the page
    // that includes it, and a plain $group or $key would overwrite the
    // page's own data (a registration's $group, for one).
    // Each section folds up (remembered in this browser); the one holding
    // the current page always stays open.
    foreach ($menu as $_mGroup => $_mItems):
      [$_mGz, $_mGe] = array_pad(explode(' ', $_mGroup, 2), 2, '');
      $_mSec = strtolower(preg_replace('/[^A-Za-z]+/', '-', $_mGe ?: $_mGz));
      $_mHas = $_mGroup === $_mHere; ?>
      <section class="side-sec<?= $_mHas ? ' has-active' : '' ?>" data-sec="<?= h($_mSec) ?>">
        <button type="button" class="side-group" aria-expanded="true">
          <span><?= h($_mGz) ?> <small><?= h($_mGe) ?></small></span><b class="side-count"><?= count($_mItems) ?></b><?= icon('chevron-down', 'chev') ?>
        </button>
        <div class="side-items"><div>
          <?php foreach ($_mItems as [$_mKey, $_mIcon, $_mZh, $_mEn, $_mHref]): ?>
            <a class="side-link<?= $nav === $_mKey ? ' active' : '' ?>" href="<?= url($_mHref) ?>"<?= $nav === $_mKey ? ' aria-current="page"' : '' ?> data-label="<?= h($_mZh . ' ' . $_mEn) ?>" data-group="<?= h($_mGz) ?>">
              <span class="ico"><?= icon($_mIcon) ?></span><span class="side-text"><?= h($_mZh) ?><small><?= h($_mEn) ?></small></span>
            </a>
          <?php endforeach; ?>
        </div></div>
      </section>
    <?php endforeach; unset($_mGroup, $_mItems, $_mKey, $_mIcon, $_mZh, $_mEn, $_mHref, $_mGz, $_mGe, $_mSec, $_mHas); ?>
  </nav>
  <script>
  // Folded sections, before the menu is drawn (no flicker).
  (function () {
    var shut = [];
    try { shut = JSON.parse(localStorage.getItem('tyt-side-shut') || '[]'); } catch (e) {}
    document.querySelectorAll('#sideNav .side-sec').forEach(function (s) {
      if (shut.indexOf(s.dataset.sec) >= 0 && !s.classList.contains('has-active')) {
        s.classList.add('is-shut');
        s.querySelector('.side-group').setAttribute('aria-expanded', 'false');
      }
    });
  })();
  </script>
  <div class="side-foot">
    <a class="side-link<?= $nav === 'password' ? ' active' : '' ?>" href="<?= url('/account/password') ?>" data-label="我的密碼 My password">
      <span class="ico"><?= icon('key') ?></span><span class="side-text">我的密碼<small>My password</small></span></a>
    <a class="side-link" href="<?= url('/') ?>" target="_blank" rel="noopener" data-label="查看網站 View site">
      <span class="ico"><?= icon('globe') ?></span><span class="side-text">查看網站<small>View site</small></span></a>
    <form method="POST" action="<?= url('/admin/logout') ?>">
      <?= csrf_field() ?>
      <button type="submit" class="side-link logout-link" data-label="登出 Log out"><span class="ico"><?= icon('log-out') ?></span><span class="side-text">登出<small>Log out</small></span></button>
    </form>
  </div>
</aside>
<div class="side-backdrop" id="sideBackdrop"></div>

<div class="main">
  <?php require BASE_PATH . '/app/Views/partials/uat_banner.php'; ?>
  <header class="topbar-admin">
    <button type="button" class="menu-btn" id="menuBtn" aria-controls="sideMenu" aria-expanded="false"><?= icon('menu') ?><span class="sr-only"> 選單 Menu</span></button>
    <div class="top-title">
      <?php if ($_mHere !== ''): ?><span class="crumb"><?= h($_mHere) ?></span><?php endif; ?>
      <h1><?= h($pageTitle ?? '管理 Admin') ?></h1>
    </div>
    <button type="button" class="top-find" data-goto-open title="快速前往 Go to a page (Ctrl K)"><?= icon('search') ?><span class="top-find-text">快速前往 <small>Go to…</small></span><kbd>Ctrl K</kbd></button>
    <span class="who"><?= icon('user') ?> <?= h($_SESSION['admin_display'] ?? ($_SESSION['admin_username'] ?? '')) ?></span>
  </header>
  <div class="content">
  <?php if (!empty($flash)): ?>
    <?php // Shown as a pop-up card by js/dialog.js; stays as a plain line if scripts are off. ?>
    <div class="flash <?= h($flash['type']) ?>" data-flash-modal="<?= h($flash['type']) ?>" data-flash-text="<?= h($flash['message']) ?>"><strong><?= h($flash['title']) ?></strong> — <?= nl2br(h($flash['message'])) ?></div>
  <?php endif; ?>
