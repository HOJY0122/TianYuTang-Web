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
$siteHeroBanner = $event['hero_banner_path'] ?? null;   // old single banner, used only when no slides exist
$siteLogo       = $site['site_logo_path'];

/** Versioned upload URL: browsers cache icons hard, a new path forces a refresh. */
$uploadUrl = static fn(string $path): string => BASE_URL . '/' . $path . '?v=' . substr(md5($path), 0, 8);

$navItems = [
    'home'     => ['/',         t('nav.home'),     t('nav.home', 'en')],
    'register' => ['/register', t('nav.register'), t('nav.register', 'en')],
    'donate'   => ['/donate',   t('nav.donate'),   t('nav.donate', 'en')],
    'gallery'  => ['/gallery',  t('nav.gallery'),  t('nav.gallery', 'en')],
];
// Which ⓘ help text this page shows (see the floating button below).
$helpKey = in_array($activeNav, ['home', 'register', 'donate', 'gallery'], true) ? 'help.' . $activeNav : 'help.other';
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h(($pageTitle ?? '') !== '' ? $pageTitle . '｜' . $siteName : $siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php [$_hFamily, $_hParam] = (new App\Models\Setting())->headingFont(); ?>
<link href="https://fonts.googleapis.com/css2?family=<?= $_hParam ?><?= $_hParam !== 'LXGW+WenKai+TC:wght@400;700' ? '&family=LXGW+WenKai+TC:wght@400;700' : '' ?>&family=Noto+Sans+TC:wght@400;500;700;800&family=Noto+Sans+SC:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
<?php if (($activeNav ?? '') === 'home'): ?><link rel="stylesheet" href="<?= asset('css/banner.css') ?>"><?php endif; ?>
<style>
/* Heading typeface chosen by the system admin (Site settings). Rare
   characters it lacks fall back to LXGW WenKai TC, glyph by glyph;
   Simplified ones (帅 乐 …) to a Kai font that has them (KaiTi on
   Windows, STKaiti on Mac) rather than a plain sans-serif. */
:root{--kai:"<?= h($_hFamily) ?>","LXGW WenKai TC","BiauKai","DFKai-SB","標楷體","KaiTi","STKaiti",serif;--kai-weight:<?= $_hFamily === 'LXGW WenKai TC' ? 700 : 400 ?>}
</style>
<?php if ($siteFavicon): ?>
<link rel="icon" href="<?= h($uploadUrl($siteFavicon)) ?>">
<link rel="apple-touch-icon" href="<?= h($uploadUrl($siteFavicon)) ?>">
<?php endif; ?>
<script>
// Remembered accessibility choices, applied before the page draws so it never jumps.
try {
  var a = JSON.parse(localStorage.getItem('tyt-a11y') || '{}');
  if (a.size === 'lg') document.documentElement.classList.add('text-lg');
  if (a.size === 'xl') document.documentElement.classList.add('text-xl');
  if (a.hc) document.documentElement.classList.add('hc');
} catch (e) {}
</script>
<script src="<?= asset('js/fit-screen.js') ?>"></script>
</head>
<body>
<a class="skip" href="#main">跳到內容 Skip to content</a>

<?php if (!empty($event['is_test'])): ?>
  <div class="testbar">
    ⚠️ 測試模式 TEST MODE — 此頁面僅供內部測試，所有報名與布施資料<strong>不會列入正式紀錄</strong>。
    Submissions here are <strong>not</strong> real records.
  </div>
<?php endif; ?>
<?php if (($site['site_tagline'] ?? '') !== '' && ($site['site_tagline_on'] ?? '1') === '1'): ?>
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

    <nav class="site-nav" aria-label="主選單 Main menu">
      <?php // Prefixed loop variables: this file shares scope with the page.
      foreach ($navItems as $_nKey => [$_nHref, $_nZh, $_nEn]): ?>
        <a href="<?= url($_nHref) ?>"<?= $activeNav === $_nKey ? ' class="active" aria-current="page"' : '' ?>>
          <?= h($_nZh) ?><small><?= h($_nEn) ?></small>
        </a>
      <?php endforeach; unset($_nKey, $_nHref, $_nZh, $_nEn); ?>
    </nav>
    <div class="a11y">
      <button type="button" class="a11y-btn" id="a11yBtn" aria-expanded="false" aria-controls="a11yPanel"
              title="無障礙設定 Accessibility">
        <!-- Universal access symbol: a person with open arms -->
        <svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" focusable="false">
          <circle cx="12" cy="3.6" r="2.3" fill="currentColor"/>
          <path d="M3.5 7.6l8.5 1.8 8.5-1.8M12 9.4v5.2M12 14.6l-3.6 6.6M12 14.6l3.6 6.6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <small aria-hidden="true">無障礙<br>Access</small>
        <span class="sr-only">無障礙設定 Accessibility</span>
      </button>
      <div class="a11y-panel" id="a11yPanel" role="dialog" aria-label="無障礙設定 Accessibility" hidden>
        <div class="a11y-title">無障礙設定 <span class="en">Accessibility</span></div>
        <div class="a11y-label">文字大小 <span class="en">Text size</span></div>
        <div class="a11y-sizes" role="group" aria-label="文字大小 Text size">
          <button type="button" data-size="md" style="font-size:1rem">A<small>標準 Normal</small></button>
          <button type="button" data-size="lg" style="font-size:1.25rem">A<small>大 Large</small></button>
          <button type="button" data-size="xl" style="font-size:1.5rem">A<small>特大 X-Large</small></button>
        </div>
        <label class="a11y-switch"><input type="checkbox" id="a11yContrast"> 高對比 <span class="en">High contrast</span></label>
        <button type="button" class="a11y-reset" id="a11yReset">↺ 重設 Reset</button>
      </div>
    </div>

  </div>
</header>

<!-- ⓘ Floating help: always in the same corner, explains the page you are on.
     The wording is the system admin's to change (網站文字 Wording). -->
<div class="help-fab">
  <div class="help-panel" id="helpPanel" role="dialog" aria-labelledby="helpTitle" hidden>
    <button type="button" class="help-close" id="helpClose" aria-label="關閉 Close">×</button>
    <div class="help-title" id="helpTitle"><?= tb('help.title') ?></div>
    <p class="help-zh"><?= h(t($helpKey)) ?></p>
    <?php if (t($helpKey, 'en') !== ''): ?><p class="help-en"><?= h(t($helpKey, 'en')) ?></p><?php endif; ?>
    <?php if (!empty($event['contact_info'])): ?>
      <p class="help-contact">📞 <?= h($event['contact_info']) ?></p>
    <?php endif; ?>
  </div>
  <button type="button" class="help-btn" id="helpBtn" aria-expanded="false" aria-controls="helpPanel" title="說明 Help">
    <span aria-hidden="true">i</span><span class="sr-only">說明 Help</span>
  </button>
</div>
<script>
(function () {
  var root = document.documentElement, btn = document.getElementById('a11yBtn'), panel = document.getElementById('a11yPanel');
  var contrast = document.getElementById('a11yContrast');
  function load() { try { return JSON.parse(localStorage.getItem('tyt-a11y') || '{}'); } catch (e) { return {}; } }
  function apply(a) {
    root.classList.toggle('text-lg', a.size === 'lg');
    root.classList.toggle('text-xl', a.size === 'xl');
    root.classList.toggle('hc', !!a.hc);
    panel.querySelectorAll('[data-size]').forEach(function (b) {
      b.setAttribute('aria-pressed', (a.size || 'md') === b.dataset.size ? 'true' : 'false');
    });
    contrast.checked = !!a.hc;
    try { localStorage.setItem('tyt-a11y', JSON.stringify(a)); } catch (e) {}
  }
  function open(show) { panel.hidden = !show; btn.setAttribute('aria-expanded', show ? 'true' : 'false'); }
  var state = load();
  apply(state);
  btn.addEventListener('click', function (e) { e.stopPropagation(); help(false); open(panel.hidden); });
  panel.addEventListener('click', function (e) { e.stopPropagation(); });
  panel.querySelectorAll('[data-size]').forEach(function (b) {
    b.addEventListener('click', function () { state.size = b.dataset.size; apply(state); });
  });
  contrast.addEventListener('change', function () { state.hc = contrast.checked; apply(state); });
  document.getElementById('a11yReset').addEventListener('click', function () { state = {}; apply(state); });
  // ⓘ help panel — same open/close manners as the accessibility panel.
  var hBtn = document.getElementById('helpBtn'), hPanel = document.getElementById('helpPanel');
  function help(show) { hPanel.hidden = !show; hBtn.setAttribute('aria-expanded', show ? 'true' : 'false'); }
  hBtn.addEventListener('click', function (e) { e.stopPropagation(); open(false); help(hPanel.hidden); });
  hPanel.addEventListener('click', function (e) { e.stopPropagation(); });
  document.getElementById('helpClose').addEventListener('click', function () { help(false); hBtn.focus(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !hPanel.hidden) { help(false); hBtn.focus(); } });

  document.addEventListener('click', function () { open(false); help(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { open(false); btn.focus(); } });

  // ⓘ info buttons anywhere on the page: tap to show, tap again / elsewhere to hide.
  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('.info-btn') : null;
    document.querySelectorAll('.info-pop:not([hidden])').forEach(function (p) {
      if (!t || p.id !== t.getAttribute('aria-controls')) { p.hidden = true; var o = document.querySelector('[aria-controls="' + p.id + '"]'); if (o) o.setAttribute('aria-expanded', 'false'); }
    });
    if (t) {
      e.preventDefault();
      var pop = document.getElementById(t.getAttribute('aria-controls'));
      pop.hidden = !pop.hidden;
      t.setAttribute('aria-expanded', pop.hidden ? 'false' : 'true');
    }
  });
})();
</script>
