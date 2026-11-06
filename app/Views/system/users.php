<?php require BASE_PATH . '/app/Views/layouts/admin_header.php'; ?>
<div class="panel device-panel">
  <div class="device-head">
    <span class="device-icon on"><?= icon('smartphone', 'lg') ?></span>
    <div>
      <h2 style="margin:0">系統管理員只限一部裝置 <span class="en">System admins: one device at a time</span></h2>
      <p class="help" style="margin:4px 0 0">系統管理員帳號在裝置 B 登入，裝置 A 會在幾秒內自動登出；裝置 A 再登入，裝置 B 就會登出。此規則一直生效。
        <span class="en">When a system admin signs in on device B, device A is signed out within seconds; signing in on A again signs B out. Always on.</span></p>
      <p class="help" style="margin:6px 0 0"><?= icon('users') ?> 一般管理員不受限制，可同時在幾部裝置使用（例如櫃台電腦與手機）。
        <span class="en">Ordinary admins are not limited — they can stay signed in on several devices (e.g. a counter computer and a phone).</span></p>
    </div>
  </div>
</div>
<div class="panel" id="liveUsers" data-live="admin_users">
  <p class="help" style="margin-top:0">
    共 <?= count($users) ?> 個帳號：<?= (int) $adminCount ?> 位管理員、<?= (int) $systemCount ?> 位系統管理員。
    <strong>每位委員應有自己的帳號</strong>——現場布施與現場報名都會記錄是誰登記的。<br>
    <span class="en"><?= count($users) ?> accounts. Give every committee member their own login — counter entries record who made them.</span>
  </p>
  <table class="records">
    <thead><tr><th>帳號 Username</th><th>姓名 Name</th><th>權限 Role</th><th>最後登入 Last login</th><th>操作 Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u):
        $isSelf = (int) $u['id'] === (int) ($_SESSION['admin_id'] ?? 0);
        // The last system admin is load-bearing: without one, nobody can
        // reach these settings. The server refuses; the buttons hide.
        $isLastSystem = $u['role'] === 'system_admin' && (int) $systemCount <= 1; ?>
      <tr>
        <td data-label="帳號 Username"><strong><?= h($u['username']) ?></strong><?= $isSelf ? ' <span class="help">（您 you）</span>' : '' ?>
          <?php if (isset($locked[$u['username']])): ?>
            <span class="sub-line warn-text"><?= icon('lock') ?> 已鎖 <?= (int) $locked[$u['username']] ?> 分鐘 · Locked <?= (int) $locked[$u['username']] ?> min
              <span class="help">（重設密碼即解鎖 Reset password to unlock）</span></span>
          <?php endif; ?></td>
        <td data-label="姓名 Name"><?= h($u['display_name'] ?? '—') ?></td>
        <?php $uPerms = App\Core\Access::decode($u['permissions'] ?? null); ?>
        <td data-label="權限 Role"><?= ['system_admin' => '<span class="badge walkin">系統管理員 System admin</span>',
                                      'helper' => '<span class="badge helper">' . icon('chair') . ' 施齋助理 Day 3 helper</span>'][$u['role']] ?? '<span class="badge">管理員 Admin</span>' ?>
          <?php if ($u['role'] === 'helper'): ?><span class="sub-line help">只有施齋入座簡易模式 Easy Day 3 screens only</span><?php endif; ?>
          <?php if ($u['role'] === 'admin'): ?>
            <div class="access-chips">
              <?php if ($uPerms === null): ?><span class="access-chip all">全部功能 Everything</span>
              <?php elseif (!$uPerms): ?><span class="access-chip none">沒有功能 Nothing</span>
              <?php else: foreach ($uPerms as $pk): ?><span class="access-chip"><?= h(App\Core\Access::MODULES[$pk][1]) ?></span><?php endforeach; endif; ?>
            </div>
          <?php endif; ?></td>
        <td data-label="最後登入 Last login" class="help"><?= $u['last_login_at'] ? h(date('Y-m-d H:i', strtotime($u['last_login_at']))) : '從未 Never' ?></td>
        <td data-label="操作 Actions">
          <div class="actions-cell" style="flex-wrap:wrap">
            <?php if (!$isLastSystem): ?>
              <form method="POST" action="<?= url('/system/users/role') ?>" class="role-form" style="margin:0">
                <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <select name="role" aria-label="權限 Role">
                  <?php foreach (App\Models\AdminUser::ROLES as $rk => [$rzh, $ren]): ?>
                    <option value="<?= $rk ?>"<?= $u['role'] === $rk ? ' selected' : '' ?>><?= $rzh ?> <?= $ren ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="mini-btn ghost" type="submit">改權限 Change role</button>
              </form>
            <?php endif; ?>
            <?php if ($u['role'] === 'admin'): ?>
              <button class="mini-btn ghost" type="button" onclick="document.getElementById('ac<?= (int) $u['id'] ?>').classList.toggle('hidden')"><?= icon('settings') ?> 可用功能 Functions</button>
            <?php endif; ?>
            <button class="mini-btn ghost" type="button" onclick="document.getElementById('pw<?= (int) $u['id'] ?>').classList.toggle('hidden')"><?= icon('key') ?> 重設密碼 Reset password</button>
            <?php if (!$isSelf && !$isLastSystem): ?>
              <form method="POST" action="<?= url('/system/users/delete') ?>" style="margin:0"
                    data-confirm="確定刪除帳號 <?= h($u['username']) ?>？此操作無法復原。&#10;Delete this account? This cannot be undone." data-danger>
                <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <button class="mini-btn danger" type="submit"><?= icon('trash') ?> 刪除 Delete</button>
              </form>
            <?php endif; ?>
            <?php if ($isLastSystem): ?><span class="help" style="margin:0">最後一位系統管理員 Last system admin</span><?php endif; ?>
          </div>
          <form method="POST" action="<?= url('/system/users/password') ?>" id="pw<?= (int) $u['id'] ?>" class="pw-form hidden form-panel" style="max-width:none;margin-top:8px">
            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
            <div class="pw-field"><input id="np<?= (int) $u['id'] ?>" type="password" name="password" placeholder="新密碼 New password (8+)" required minlength="8" autocomplete="new-password">
              <button type="button" class="pw-eye" data-toggle-password="np<?= (int) $u['id'] ?>" title="顯示密碼 Show password"><?= icon('eye') ?></button></div>
            <div class="pw-field" style="margin-top:6px"><input id="nc<?= (int) $u['id'] ?>" type="password" name="password_confirm" placeholder="再次輸入 Confirm" required minlength="8" autocomplete="new-password">
              <button type="button" class="pw-eye" data-toggle-password="nc<?= (int) $u['id'] ?>" title="顯示密碼 Show password"><?= icon('eye') ?></button></div>
            <button class="mini-btn" type="submit" style="margin-top:6px"><?= icon('save') ?> 儲存 Save</button>
          </form>
        </td>
      </tr>
      <?php if ($u['role'] === 'admin'): ?>
      <tr class="access-row hidden" id="ac<?= (int) $u['id'] ?>"><td colspan="5">
                      <form method="POST" action="<?= url('/system/users/access') ?>" class="access-form">
              <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
              <strong><?= h($u['username']) ?> 可以使用 <span class="en">can use</span>：</strong>
              <?php $pickSelected = $uPerms; require BASE_PATH . '/app/Views/system/_access_picker.php'; ?>
              <button class="mini-btn" type="submit"><?= icon('save') ?> 儲存功能 Save functions</button>
              <span class="help">即時生效，不必重新登入。<span class="en">Applies at once — no need to sign in again.</span></span>
            </form>
                </td></tr>
      <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="page-grid">
<div class="panel form-panel">
  <h2 style="margin-top:0">新增帳號 <span class="en">Add an account</span></h2>
  <form method="POST" action="<?= url('/system/users/create') ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div><label for="username">帳號 <span class="en">Username *</span></label>
        <input id="username" name="username" required maxlength="50" pattern="[A-Za-z0-9._\-]{3,50}" placeholder="siew.ling" autocapitalize="none">
        <p class="help">英文字母、數字、. _ -，3–50 字元。Letters, numbers, . _ - (3–50).</p></div>
      <div><label for="display_name">姓名 <span class="en">Display name</span></label>
        <input id="display_name" name="display_name" maxlength="80" placeholder="陳秀玲"></div>
      <div><label for="password">密碼 <span class="en">Password *</span></label>
        <div class="pw-field"><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
          <button type="button" class="pw-eye" data-toggle-password="password" title="顯示密碼 Show password"><?= icon('eye') ?></button></div></div>
      <div><label for="password_confirm">再次輸入 <span class="en">Confirm *</span></label>
        <div class="pw-field"><input id="password_confirm" name="password_confirm" type="password" required minlength="8" autocomplete="new-password">
          <button type="button" class="pw-eye" data-toggle-password="password_confirm" title="顯示密碼 Show password"><?= icon('eye') ?></button></div></div>
    </div>
    <label for="role">權限 <span class="en">Role *</span></label>
    <select id="role" name="role">
      <?php foreach (App\Models\AdminUser::ROLES as $rk => [$rzh, $ren, $rwhat]): ?>
        <option value="<?= $rk ?>"><?= $rzh ?> <?= $ren ?> — <?= $rwhat ?></option>
      <?php endforeach; ?>
    </select>
    <p class="help" id="helperNote" hidden><?= icon('chair') ?> 施齋助理登入後直接進入「誰來了？」大按鈕畫面，看不到其他頁面和金額。
      <span class="en">A Day 3 helper signs in straight to the big-button "Who is here?" screens and cannot see any other page or money.</span></p>
    <div id="newAccess">
      <label>可使用的功能 <span class="en">Functions this admin can use</span></label>
      <p class="help" style="margin-top:0">只勾選他需要的。例如當天幫忙的委員只需「現場報到」；不勾「儀表板」就看不到總金額。
        <span class="en">Tick only what they need — e.g. a helper on the day needs just Check-in; without Dashboard they cannot see the totals.</span></p>
      <?php $pickSelected = null; require BASE_PATH . '/app/Views/system/_access_picker.php'; ?>
    </div>
    <div class="form-actions"><button class="primary" type="submit"><?= icon('plus') ?> 建立帳號 <span class="en">Create account</span></button></div>
  </form>
</div>
<div class="panel guide">
  <h2 style="margin-top:0"><?= icon('users') ?> 三種權限 <span class="en">The three roles</span></h2>
  <ul>
    <li><strong>管理員 Admin</strong>：可選擇開放哪些功能（報名、布施、報到、現場登記、消息、相簿、收據、活動資料、儀表板）。
      <span class="en">You choose which functions each admin can use (registrations, donations, check-in, counter, news, photos, receipts, event details, dashboard).</span></li>
    <li><strong>施齋助理 Day 3 helper</strong>：只有施齋入座的簡易模式（大按鈕、一步一步），給活動當天幫忙安排座位的人。
      <span class="en">Only the Day 3 seating easy screens (big buttons, one step at a time) — for the people seating guests on the day.</span></li>
    <li><strong>系統管理員 System admin</strong>：以上全部，加上網站設定、網站文字、帳號與 QR 產生器。
      <span class="en">Everything above, plus site settings, wording, accounts and the QR generator.</span></li>
  </ul>
  <h2 style="margin:18px 0 8px"><?= icon('lock') ?> 好習慣 <span class="en">Good habits</span></h2>
  <ul>
    <li>每位委員一個帳號，不要共用。<span class="en">One login per person — never shared.</span></li>
    <li>密碼至少 8 個字元，含字母和數字。<span class="en">At least 8 characters, letters and numbers.</span></li>
    <li>有人離開委員會時，刪除或重設其帳號。<span class="en">When someone leaves, delete or reset their account.</span></li>
  </ul>
</div>
</div>
<script>
(function () {
  // Presets tick the boxes; the count line says what was chosen.
  function count(p) {
    var boxes = p.querySelectorAll('input[name="perms[]"]'), on = 0;
    boxes.forEach(function (b) { if (b.checked) on++; });
    p.querySelector('.access-count').textContent = on === boxes.length ? '已選全部功能 · Everything ticked'
      : on === 0 ? '未選任何功能——此帳號登入後沒有可用頁面 · Nothing ticked: this account will have no pages'
      : '已選 ' + on + ' 項 · ' + on + ' ticked';
  }
  document.querySelectorAll('[data-access-picker]').forEach(function (p) {
    p.addEventListener('change', function () { count(p); });
    p.querySelectorAll('[data-preset]').forEach(function (b) {
      b.addEventListener('click', function () {
        var keys = b.dataset.preset === '*' ? null : b.dataset.preset.split(',');
        p.querySelectorAll('input[name="perms[]"]').forEach(function (c) { c.checked = !keys || keys.indexOf(c.value) > -1; });
        count(p);
      });
    });
    count(p);
  });
  // A system admin always has everything: no picker for that role.
  var role = document.getElementById('role'), box = document.getElementById('newAccess'), note = document.getElementById('helperNote');
  function sync() { box.hidden = role.value !== 'admin'; note.hidden = role.value !== 'helper'; }
  role.addEventListener('change', sync); sync();
})();
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
