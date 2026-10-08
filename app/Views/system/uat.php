<?php
/**
 * System → 🧪 UAT 測試模式 (system admin only).
 * Switch test mode on/off, see what has been tried on the test event,
 * and write the announcements that slide across the top of every page.
 */
use App\Core\Uat;

require BASE_PATH . '/app/Views/layouts/admin_header.php';
$msgText = trim((string) ($site['uat_messages'] ?? '')) !== '' ? $site['uat_messages'] : Uat::DEFAULT_MESSAGES;
?>
<div class="uat-page">

  <!-- ① On / off -->
  <section class="panel uat-status <?= $on ? 'is-on' : 'is-off' ?>">
    <div class="uat-status-head">
      <div class="uat-light" aria-hidden="true"></div>
      <div>
        <h2 style="margin:0"><?= $on ? 'UAT 測試模式：開啟中' : 'UAT 測試模式：關閉' ?>
          <span class="en"><?= $on ? 'UAT test mode is ON' : 'UAT test mode is off' ?></span></h2>
        <p class="help" style="margin:4px 0 0">
          目前上線的活動 <span class="en">Live event</span>：<strong><?= h($live['year'] . ' · ' . $live['name']) ?></strong>
          <?= !empty($live['is_test']) ? '<span class="badge pending">測試 Test</span>' : '<span class="badge ok">正式 Real</span>' ?>
        </p>
      </div>
    </div>

    <?php if (!$on): ?>
      <ul class="uat-explain">
        <li>開啟後，網站改用<strong>正式活動的測試副本</strong>：測試人員的報名、布施、報到、櫃台紀錄全部記在測試活動（編號以 TEST- 開頭），<strong>不會碰到正式資料</strong>。
          <span class="en">The site switches to a <strong>test copy</strong> of the real event: every registration, donation, check-in and counter record goes there (codes start with TEST-) — <strong>real records are never touched</strong>.</span></li>
        <li>前台與後台每一頁頂部都會出現滑動的測試公告。<span class="en">A sliding test announcement appears at the top of every page, public and admin.</span></li>
        <li>只有系統管理員可以開關。<span class="en">Only system admins can switch it.</span></li>
      </ul>
      <form method="POST" action="<?= url('/system/uat/toggle') ?>" data-confirm="開啟 UAT 測試模式？網站會改用測試活動。&#10;Turn UAT test mode on? The site will run on a test event.">
        <?= csrf_field() ?><input type="hidden" name="to" value="on">
        <button class="primary uat-big" type="submit"><?= icon('flask') ?> 開啟 UAT 測試模式 <span class="en">Turn UAT on</span></button>
      </form>
    <?php else: ?>
      <?php if ($test): ?>
        <div class="uat-stats">
          <div><small>測試活動 Test event</small><strong><?= h($test['name']) ?></strong></div>
          <div><small>測試報名 Test registrations</small><strong><?= (int) $testStats['groups'] ?> 組 · <?= (int) $testStats['people'] ?> 人</strong></div>
          <div><small>測試布施 Test donations</small><strong><?= (int) $testStats['donations'] ?></strong></div>
        </div>
      <?php endif; ?>
      <div class="uat-actions">
        <form method="POST" action="<?= url('/system/uat/toggle') ?>" class="uat-off-form"
              data-confirm="關閉 UAT，恢復正式活動？&#10;Turn UAT off and bring the real event back?">
          <?= csrf_field() ?><input type="hidden" name="to" value="off">
          <label class="check-inline"><input type="checkbox" name="delete_test_data" value="1">
            同時刪除測試資料 <span class="en">Also delete the test data</span></label>
          <button class="primary uat-big" type="submit"><?= icon('stop') ?> 關閉 UAT，恢復正式 <span class="en">Turn UAT off</span></button>
        </form>
        <form method="POST" action="<?= url('/system/uat/reset') ?>"
              data-confirm="刪除所有測試資料，重新開始？（正式資料不受影響）&#10;Delete all test data and start over? (Real records are not affected.)" data-danger>
          <?= csrf_field() ?>
          <button class="mini-btn ghost btn-lg" type="submit">↺ 重設測試資料 <span class="en">Reset test data</span></button>
        </form>
      </div>
      <p class="help">不勾選時測試資料會保留，可在活動清單選測試活動查看，日後再刪除。
        <span class="en">If unticked, the test data is kept — pick the test event in the event list to review it, and delete it later.</span></p>
    <?php endif; ?>
  </section>

  <!-- ② Announcements -->
  <form method="POST" action="<?= url('/system/uat/save') ?>" class="panel form-panel uat-msgs" id="uatForm" data-live="settings" data-live-mode="form">
    <?= csrf_field() ?>
    <h2 style="margin-top:0"><?= icon('megaphone') ?> 測試公告 <span class="en">Announcements</span></h2>
    <p class="help" style="margin-top:0">每行一則，<code>中文 | English</code>（用 | 分開）。最多 12 則，輪流滑動顯示。
      <span class="en">One per line, <code>Chinese | English</code>. Up to 12; they take turns.</span></p>
    <textarea name="uat_messages" id="uatMessages" rows="6" maxlength="2600"><?= h($msgText) ?></textarea>

    <label for="uatInterval"><?= icon('clock') ?> 每則顯示 <span class="en">Time per announcement</span></label>
    <div class="seg-toggle uat-speed" role="radiogroup">
      <?php foreach (Uat::INTERVALS as $sec): ?>
        <label class="seg-on"><input type="radio" name="uat_interval" value="<?= $sec ?>"<?= Uat::interval($site) === $sec ? ' checked' : '' ?>><span><?= $sec ?> 秒 s</span></label>
      <?php endforeach; ?>
    </div>

    <label for="uatSize"><?= icon('type') ?> 大小 <span class="en">Size</span></label>
    <div class="fl-range uat-size">
      <span class="help">小 A</span>
      <input type="range" id="uatSize" name="uat_size" min="<?= Uat::SIZE_MIN ?>" max="<?= Uat::SIZE_MAX ?>" step="5" value="<?= Uat::size($site) ?>">
      <span class="help" style="font-size:1.3em">大 A</span>
      <output for="uatSize"><?= Uat::size($site) ?>%</output>
    </div>

    <label><?= icon('eye') ?> 預覽 <span class="en">Preview</span></label>
    <div class="uat-preview" id="uatPreview"></div>

    <div class="form-actions">
      <button class="primary" type="submit"><?= icon('save') ?> 儲存 <span class="en">Save</span></button>
      <button class="mini-btn ghost btn-lg" type="button" id="uatDefault">↺ 預設公告 <span class="en">Default announcements</span></button>
    </div>
  </form>
</div>

<script>
// Live preview of the bar while typing (the same markup and script as the real one).
(function () {
  var box = document.getElementById('uatMessages'), prev = document.getElementById('uatPreview');
  var DEF = <?= json_encode(Uat::DEFAULT_MESSAGES, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function render() {
    var secs = (document.querySelector('input[name=uat_interval]:checked') || {}).value || 4;
    var lines = box.value.split(/\r?\n/).map(function (l) { return l.trim(); }).filter(Boolean).slice(0, 12);
    if (!lines.length) lines = DEF.split('\n');
    var items = lines.map(function (l, i) {
      var p = l.split('|'), zh = p.shift().trim(), en = p.join('|').trim();
      return '<li' + (i ? '' : ' class="is-on"') + '><strong>' + esc(zh) + '</strong>' + (en ? ' <span>' + esc(en) + '</span>' : '') + '</li>';
    }).join('');
    var dots = lines.length > 1 ? '<span class="uat-dots">' + lines.map(function (_, i) { return '<i' + (i ? '' : ' class="on"') + '></i>'; }).join('') + '</span>' : '';
    var size = document.getElementById('uatSize').value;
    document.querySelector('.uat-size output').textContent = size + '%';
    prev.innerHTML = '<div class="uat-bar" data-interval="' + secs + '" style="--uat-scale:' + (size / 100) + '"><span class="uat-badge">UAT<small>測試中 Testing</small></span>'
      + '<div class="uat-track"><ul>' + items + '</ul></div>' + dots + '</div>';
    document.dispatchEvent(new CustomEvent('live:swap', { detail: prev }));   // uat.js starts it
  }
  var t; box.addEventListener('input', function () { clearTimeout(t); t = setTimeout(render, 300); });
  document.querySelectorAll('input[name=uat_interval]').forEach(function (r) { r.addEventListener('change', render); });
  document.getElementById('uatSize').addEventListener('input', render);
  document.getElementById('uatDefault').addEventListener('click', function () { box.value = DEF; render(); });
  render();
})();
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
