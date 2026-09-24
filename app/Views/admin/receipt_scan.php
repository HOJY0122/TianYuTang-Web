<?php
/**
 * Step 1 of adding a receipt: take / choose a photo, then let the AI
 * read it — or type it in by hand. Nothing is saved until step 2.
 */
require BASE_PATH . '/app/Views/layouts/admin_header.php';
?>
<form method="POST" action="<?= url('/admin/receipts/scan') ?>" enctype="multipart/form-data" class="panel form-panel scan-form" id="scanForm" style="max-width:760px">
  <?= csrf_field() ?>
  <h2 style="margin-top:0">① 拍下收據 <span class="en">Photograph the receipt</span></h2>
  <ul class="scan-tips">
    <li>📐 平放、整張入鏡、光線充足、不要反光。<span class="en">Lay it flat, fit the whole receipt in, good light, no glare.</span></li>
    <li>✏️ 選好相片後可旋轉或裁切。<span class="en">After choosing, you can rotate or crop it.</span></li>
    <li>🔒 相片只存放在伺服器內部，只有登入的工作人員看得到。<span class="en">Photos are stored privately — only signed-in staff can see them.</span></li>
  </ul>

  <label for="photo">收據相片 <span class="en">Receipt photo</span></label>
  <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/gif,image/webp" capture="environment"
         data-aspects="original,3:2,4:3" data-max-width="2000">

  <div class="form-actions scan-actions">
    <?php if ($aiReady): ?>
      <button class="primary" type="submit" name="use_ai" value="1" id="aiBtn">🤖 AI 讀取 <span class="en">Read it with AI</span></button>
    <?php endif; ?>
    <button class="<?= $aiReady ? 'mini-btn ghost btn-lg' : 'primary' ?>" type="submit" name="use_ai" value="0">✍️ 手動輸入 <span class="en">Type it in myself</span></button>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/receipts') ?>">返回 Back</a>
  </div>
  <?php if (!$aiReady): ?>
    <p class="help">AI 讀取尚未啟用（config/config.php 未填 ANTHROPIC_API_KEY）。仍可附上相片並手動輸入。
      <span class="en">AI reading is off (no ANTHROPIC_API_KEY in config/config.php). You can still attach the photo and type it in.</span></p>
  <?php else: ?>
    <p class="help">AI 只會填好草稿，下一步請對照相片核對後才儲存。沒有相片也可以直接手動輸入。
      <span class="en">The AI only prepares a draft — you check it against the photo before saving. No photo? Just type it in.</span></p>
  <?php endif; ?>
</form>

<div class="busy" id="busy" hidden role="status" aria-live="polite">
  <div class="busy-box">
    <div class="spinner" aria-hidden="true"></div>
    <strong>🤖 AI 正在讀取收據… <span class="en">Reading the receipt…</span></strong>
    <p class="help">通常需要 10–40 秒，請勿關閉此頁。Usually 10–40 seconds — please keep this page open.</p>
  </div>
</div>
<script>
(function () {
  var form = document.getElementById('scanForm'), busy = document.getElementById('busy');
  form.addEventListener('submit', function (e) {
    var ai = e.submitter && e.submitter.id === 'aiBtn';
    if (ai && !document.getElementById('photo').files.length) {
      e.preventDefault();
      alert('請先選擇收據相片。\nPlease choose a photo of the receipt first.');
      return;
    }
    if (ai) busy.hidden = false;
  });
})();
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
