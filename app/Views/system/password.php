<?php
$pageTitle = '變更密碼 Change Password';
$nav       = 'password';
require BASE_PATH . '/app/Views/layouts/admin_header.php';
?>
<div class="page-grid pw-grid">
<div class="panel form-panel">
  <?php if (!empty($forced)): ?>
    <div class="flash error">
      <strong>請先更改密碼 Please change your password first</strong><br>
      您正在使用系統預設密碼，任何人都可以在說明文件中看到它。設定新密碼後才能繼續使用後台。<br>
      <span class="en">You are using a published default password. Choose a new one to continue.</span>
    </div>
  <?php endif; ?>

  <p class="help" style="margin-top:0">
    變更 <strong><?= h($_SESSION['admin_username']) ?></strong> 的登入密碼，變更後仍會保持登入。
    <span class="en">Change the password for <?= h($_SESSION['admin_username']) ?>. You will stay logged in.</span>
  </p>

  <form method="POST" action="<?= url('/account/password') ?>" id="pwForm">
    <?= csrf_field() ?>

    <label for="current_password">目前密碼 <span class="en">Current password</span></label>
    <div class="pw-field">
      <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
      <button type="button" class="pw-eye" data-toggle-password="current_password" title="顯示密碼 Show password">👁</button>
    </div>

    <label for="password">新密碼 <span class="en">New password</span></label>
    <div class="pw-field">
      <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
      <button type="button" class="pw-eye" data-toggle-password="password" title="顯示密碼 Show password">👁</button>
    </div>
    <div class="strength" aria-live="polite">
      <div class="strength-bar"><i id="strengthBar"></i></div>
      <div class="strength-text" id="strengthText">&nbsp;</div>
    </div>
    <ul class="pw-rules" id="pwRules">
      <li data-rule="length">至少 8 個字元 <span class="en">At least 8 characters</span></li>
      <li data-rule="mix">包含英文字母和數字 <span class="en">Letters and numbers</span></li>
      <li data-rule="long">12 個字元以上更安全 <span class="en">12+ characters is stronger</span></li>
    </ul>

    <label for="password_confirm">再次輸入新密碼 <span class="en">Confirm new password</span></label>
    <div class="pw-field">
      <input id="password_confirm" name="password_confirm" type="password" required minlength="8" autocomplete="new-password">
      <button type="button" class="pw-eye" data-toggle-password="password_confirm" title="顯示密碼 Show password">👁</button>
    </div>
    <p class="help" id="matchText">&nbsp;</p>

    <div class="form-actions">
      <button class="primary" type="submit">💾 變更密碼 Change password</button>
      <?php if (empty($forced)): ?>
        <a class="mini-btn ghost" href="<?= url($homePath ?? '/admin/dashboard') ?>">取消 Cancel</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="panel guide">
  <h2 style="margin-top:0">🔐 設定好密碼 <span class="en">A good password</span></h2>
  <ul>
    <li>用一句容易記的短句，例如「Tyt中壇2026平安」。<span class="en">A short phrase you can remember works well.</span></li>
    <li>不要用生日、電話或 123456。<span class="en">Avoid birthdays, phone numbers or 123456.</span></li>
    <li>不要與其他網站的密碼相同。<span class="en">Don't reuse a password from another site.</span></li>
    <li>忘記密碼？請系統管理員在「帳號管理」重設。<span class="en">Forgot it? A system admin can reset it in User accounts.</span></li>
  </ul>
</div>
</div>

<script>
(function () {
  var pw = document.getElementById('password');
  var confirm = document.getElementById('password_confirm');
  var bar = document.getElementById('strengthBar');
  var text = document.getElementById('strengthText');
  var LEVELS = [
    ['#c0392b', '太弱 Too weak'], ['#d35400', '弱 Weak'], ['#b58a35', '普通 Fair'],
    ['#2f6b3a', '強 Strong'], ['#1e5a2a', '很強 Very strong']
  ];

  // A simple, explainable score — every point matches a rule the person can see.
  function score(p) {
    if (!p) return -1;
    var s = 0;
    if (p.length >= 8) s++;
    if (p.length >= 12) s++;
    if (/[a-z]/i.test(p) && /\d/.test(p)) s++;
    if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++;
    if (/[^A-Za-z0-9]/.test(p)) s++;
    if (p.length < 8) s = Math.min(s, 0);
    return Math.min(4, s);
  }

  function update() {
    var p = pw.value, s = score(p);
    bar.style.width = s < 0 ? '0' : ((s + 1) * 20) + '%';
    bar.style.background = s < 0 ? 'transparent' : LEVELS[s][0];
    text.textContent = s < 0 ? ' ' : '密碼強度 Strength：' + LEVELS[s][1];
    text.style.color = s < 0 ? '' : LEVELS[s][0];
    var rules = { length: p.length >= 8, mix: /[a-z]/i.test(p) && /\d/.test(p), long: p.length >= 12 };
    document.querySelectorAll('#pwRules li').forEach(function (li) { li.classList.toggle('ok', rules[li.dataset.rule]); });

    var m = document.getElementById('matchText');
    if (!confirm.value) { m.innerHTML = '&nbsp;'; m.style.color = ''; }
    else if (confirm.value === p) { m.textContent = '✓ 兩次輸入相同 Passwords match'; m.style.color = '#2f6b3a'; }
    else { m.textContent = '✗ 兩次輸入不同 Passwords do not match'; m.style.color = '#c0392b'; }
  }
  pw.addEventListener('input', update);
  confirm.addEventListener('input', update);
})();
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
