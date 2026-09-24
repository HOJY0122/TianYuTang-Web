<?php require BASE_PATH . '/app/Views/layouts/admin_header.php'; ?>
<div class="panel">
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
        <td data-label="帳號 Username"><strong><?= h($u['username']) ?></strong><?= $isSelf ? ' <span class="help">（您 you）</span>' : '' ?></td>
        <td data-label="姓名 Name"><?= h($u['display_name'] ?? '—') ?></td>
        <td data-label="權限 Role"><?= $u['role'] === 'system_admin'
            ? '<span class="badge walkin">系統管理員 System admin</span>' : '<span class="badge">管理員 Admin</span>' ?></td>
        <td data-label="最後登入 Last login" class="help"><?= $u['last_login_at'] ? h(date('Y-m-d H:i', strtotime($u['last_login_at']))) : '從未 Never' ?></td>
        <td data-label="操作 Actions">
          <div class="actions-cell" style="flex-wrap:wrap">
            <?php if (!$isLastSystem): ?>
              <form method="POST" action="<?= url('/system/users/role') ?>" style="margin:0">
                <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <input type="hidden" name="role" value="<?= $u['role'] === 'system_admin' ? 'admin' : 'system_admin' ?>">
                <button class="mini-btn ghost" type="submit"><?= $u['role'] === 'system_admin' ? '⬇ 改為管理員 Make admin' : '⬆ 升為系統管理員 Make system admin' ?></button>
              </form>
            <?php endif; ?>
            <button class="mini-btn ghost" type="button" onclick="document.getElementById('pw<?= (int) $u['id'] ?>').classList.toggle('hidden')">🔑 重設密碼 Reset password</button>
            <?php if (!$isSelf && !$isLastSystem): ?>
              <form method="POST" action="<?= url('/system/users/delete') ?>" style="margin:0"
                    onsubmit="return confirm('確定刪除帳號 <?= h($u['username']) ?>？此操作無法復原。\nDelete this account? This cannot be undone.');">
                <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <button class="mini-btn danger" type="submit">🗑 刪除 Delete</button>
              </form>
            <?php endif; ?>
            <?php if ($isLastSystem): ?><span class="help" style="margin:0">最後一位系統管理員 Last system admin</span><?php endif; ?>
          </div>
          <form method="POST" action="<?= url('/system/users/password') ?>" id="pw<?= (int) $u['id'] ?>" class="pw-form hidden form-panel" style="max-width:none;margin-top:8px">
            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
            <div class="pw-field"><input id="np<?= (int) $u['id'] ?>" type="password" name="password" placeholder="新密碼 New password (8+)" required minlength="8" autocomplete="new-password">
              <button type="button" class="pw-eye" data-toggle-password="np<?= (int) $u['id'] ?>" title="顯示密碼 Show password">👁</button></div>
            <div class="pw-field" style="margin-top:6px"><input id="nc<?= (int) $u['id'] ?>" type="password" name="password_confirm" placeholder="再次輸入 Confirm" required minlength="8" autocomplete="new-password">
              <button type="button" class="pw-eye" data-toggle-password="nc<?= (int) $u['id'] ?>" title="顯示密碼 Show password">👁</button></div>
            <button class="mini-btn" type="submit" style="margin-top:6px">💾 儲存 Save</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

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
          <button type="button" class="pw-eye" data-toggle-password="password" title="顯示密碼 Show password">👁</button></div></div>
      <div><label for="password_confirm">再次輸入 <span class="en">Confirm *</span></label>
        <div class="pw-field"><input id="password_confirm" name="password_confirm" type="password" required minlength="8" autocomplete="new-password">
          <button type="button" class="pw-eye" data-toggle-password="password_confirm" title="顯示密碼 Show password">👁</button></div></div>
    </div>
    <label for="role">權限 <span class="en">Role *</span></label>
    <select id="role" name="role">
      <option value="admin">管理員 Admin — 報名、布施、報到、消息、相簿、活動資料 records, forms, news, photos, event</option>
      <option value="system_admin">系統管理員 System admin — 以上全部，加上網站設定與帳號 everything above + site settings &amp; accounts</option>
    </select>
    <div class="form-actions"><button class="primary" type="submit">＋ 建立帳號 Create account</button></div>
  </form>
</div>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
