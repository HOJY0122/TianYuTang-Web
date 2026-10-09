<?php
/**
 * System → 休眠模式 Sleep mode. Switch the public site to the one
 * "see you next year" page, write its wording, and download it as a file
 * for Cloudflare (for when the server itself is switched off).
 */
require BASE_PATH . '/app/Views/layouts/admin_header.php';
$raw = static fn(string $k): string => (string) ($site[$k] ?? '');
?>
<div class="uat-page sleep-page">

  <section class="panel uat-status sleep-status <?= $on ? 'is-on' : 'is-off' ?>">
    <div class="uat-status-head">
      <div class="sleep-moon" aria-hidden="true"><?= icon($on ? 'pause' : 'globe', 'lg') ?></div>
      <div>
        <h2 style="margin:0"><?= $on ? '網站休眠中' : '網站運作中' ?>
          <span class="en"><?= $on ? 'The public site is asleep' : 'The public site is awake' ?></span></h2>
        <p class="help" style="margin:4px 0 0"><?= $on
            ? '訪客只會看到休眠頁面（HTTP 503）。後台、系統設定照常運作；您登入時仍可查看網站。'
            : '開啟後，網站所有公開頁面只顯示一個雙語訊息頁面，不顯示任何內容、不接受任何報名或布施。' ?>
          <span class="en"><?= $on
            ? 'Visitors see only the sleep page (HTTP 503). Admin and system pages work as normal, and you can still view the site while signed in.'
            : 'When on, every public page shows only one bilingual message — no content, no registration, no donation.' ?></span></p>
      </div>
    </div>
    <form method="POST" action="<?= url('/system/sleep') ?>"
          data-confirm="<?= $on ? '喚醒網站？訪客會再看到所有頁面。&#10;Wake the site? Visitors will see every page again.'
                                : '讓網站進入休眠？訪客只會看到休眠頁面。&#10;Put the site to sleep? Visitors will see only the sleep page.' ?>"<?= $on ? '' : ' data-danger' ?>>
      <?= csrf_field() ?>
      <input type="hidden" name="to" value="<?= $on ? 'off' : 'on' ?>">
      <button class="primary uat-big" type="submit"><?= $on ? icon('play') . ' 喚醒網站 <span class="en">Wake the site</span>' : icon('pause') . ' 進入休眠 <span class="en">Put to sleep</span>' ?></button>
      <?php if ($on): ?>
        <a class="mini-btn ghost btn-lg" href="<?= url('/') ?>" target="_blank"><?= icon('eye') ?> 查看網站（只有您看到）<span class="en">View site (only you)</span></a>
      <?php endif; ?>
    </form>
  </section>

  <div class="word-layout sleep-layout">
    <form method="POST" action="<?= url('/system/sleep') ?>" class="panel form-panel" id="sleepForm" data-live="settings" data-live-mode="form" data-trad-check>
      <?= csrf_field() ?>
      <h2 style="margin-top:0"><?= icon('pencil') ?> 休眠頁面文字 <span class="en">Sleep page wording</span></h2>
      <p class="help" style="margin-top:0">留空 = 使用預設文字。<code>{site}</code> 會換成網站名稱。<span class="en">Empty = the default text. <code>{site}</code> becomes the site name.</span></p>
      <label for="sleep_title_zh">標題（中文）<span class="en">Heading (Chinese)</span></label>
      <input id="sleep_title_zh" name="sleep_title_zh" maxlength="80" value="<?= h($raw('sleep_title_zh')) ?>" placeholder="<?= h(App\Core\Sleep::DEFAULTS['sleep_title_zh']) ?>">
      <label for="sleep_title_en">標題（英文）<span class="en">Heading (English)</span></label>
      <input id="sleep_title_en" name="sleep_title_en" maxlength="120" value="<?= h($raw('sleep_title_en')) ?>" placeholder="<?= h(App\Core\Sleep::DEFAULTS['sleep_title_en']) ?>">
      <label for="sleep_msg_zh">訊息（中文）<span class="en">Message (Chinese)</span></label>
      <textarea id="sleep_msg_zh" name="sleep_msg_zh" rows="3" maxlength="600" placeholder="<?= h(App\Core\Sleep::DEFAULTS['sleep_msg_zh']) ?>"><?= h($raw('sleep_msg_zh')) ?></textarea>
      <label for="sleep_msg_en">訊息（英文）<span class="en">Message (English)</span></label>
      <textarea id="sleep_msg_en" name="sleep_msg_en" rows="3" maxlength="900" placeholder="<?= h(App\Core\Sleep::DEFAULTS['sleep_msg_en']) ?>"><?= h($raw('sleep_msg_en')) ?></textarea>
      <label for="sleep_next">下次活動（選填）<span class="en">Next event line (optional)</span></label>
      <input id="sleep_next" name="sleep_next" maxlength="160" value="<?= h($raw('sleep_next')) ?>" placeholder="例 e.g. 2027年10月 再會 · See you in October 2027">
      <div class="form-actions">
        <button class="primary" type="submit"><?= icon('save') ?> 儲存文字 <span class="en">Save wording</span></button>
        <a class="mini-btn ghost btn-lg" href="<?= url('/system/sleep/download') ?>"><?= icon('download') ?> 下載休眠頁面 <span class="en">Download the page</span></a>
      </div>
    </form>

    <aside class="panel word-preview">
      <div class="word-preview-bar">
        <strong><?= icon('eye') ?> 即時預覽 <span class="en">Live preview</span></strong>
        <span class="seg-mini">
          <button type="button" data-width="100%" title="電腦 Computer"><?= icon('monitor') ?></button>
          <button type="button" data-width="390px" class="is-on" title="手機 Phone"><?= icon('smartphone') ?></button>
        </span>
      </div>
      <div class="word-frame-wrap"><iframe id="sleepPreview" title="休眠頁面預覽 Sleep page preview" style="width:390px" sandbox="allow-same-origin"></iframe></div>
    </aside>
  </div>

  <details class="panel guide">
    <summary><span><?= icon('cloud') ?> 關掉伺服器時（AWS + Cloudflare）<span class="en">When the server is switched off</span></span><span class="guide-toggle" aria-hidden="true">顯示 Show ▾</span></summary>
    <ol>
      <li>休眠模式只在伺服器開著時有效。要關掉 AWS 伺服器省錢時，先按上面「下載休眠頁面」，得到一個獨立的 <code>sleep-page.html</code>（標誌已包含在內）。
        <span class="en">Sleep mode works while the server is running. Before switching the AWS server off, press “Download the page” to get one self-contained <code>sleep-page.html</code> (logo included).</span></li>
      <li>在 Cloudflare 建立一個免費的 <strong>Pages</strong> 專案，把這個檔案改名為 <code>index.html</code> 上傳。<span class="en">In Cloudflare, create a free <strong>Pages</strong> project and upload the file renamed to <code>index.html</code>.</span></li>
      <li>伺服器關掉期間，把網址（DNS）指向這個 Pages 專案；網址不變，訪客看到休眠頁面。
        <span class="en">While the server is off, point the domain (DNS) to that Pages project — same address, visitors see the sleep page.</span></li>
      <li>新年度要重開時：開回 AWS 伺服器 → 把網址指回伺服器 → 在這裡按「喚醒網站」。
        <span class="en">For the new year: start the AWS server → point the domain back to it → press “Wake the site” here.</span></li>
    </ol>
  </details>
</div>

<script>
(function () {
  var form = document.getElementById('sleepForm'), frame = document.getElementById('sleepPreview'), t = null;
  var BASE = <?= json_encode(BASE_URL) ?>;
  function load() {
    var data = new FormData(form);
    fetch(BASE + '/system/sleep/preview', { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (html) { frame.srcdoc = html; })
      .catch(function () {});
  }
  form.addEventListener('input', function () { clearTimeout(t); t = setTimeout(load, 300); });
  document.querySelectorAll('[data-width]').forEach(function (b) {
    b.addEventListener('click', function () {
      frame.style.width = b.dataset.width;
      document.querySelectorAll('[data-width]').forEach(function (x) { x.classList.toggle('is-on', x === b); });
    });
  });
  load();
})();
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
