<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>系統管理｜天玉堂</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@700;900&family=Noto+Sans+TC:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body>

<div class="adminbar system">
  <h1>⚙️ 系統管理 System Admin</h1>
  <div style="display:flex;align-items:center;gap:14px">
    <span class="who"><?= h($_SESSION['admin_display'] ?? $_SESSION['admin_username']) ?></span>
    <a class="logout" href="<?= url('/admin/dashboard') ?>">← 一般後台</a>
  </div>
</div>

<div class="wrap checkin-wrap">

  <?php if (!empty($flash)): ?>
    <div class="flash <?= h($flash['type']) ?>">
      <strong><?= h($flash['title']) ?></strong> — <?= h($flash['message']) ?>
    </div>
  <?php endif; ?>

  <div class="flash system-note">
    ⚙️ 這些設定會影響整個網站，與個別活動無關。日常的報名、布施、相簿管理請在
    <a href="<?= url('/admin/dashboard') ?>">一般後台</a>。
  </div>

  <!-- ---------- Quick links ---------- -->
  <div class="panel">
    <h2>系統工具</h2>
    <div class="sys-links">
      <a class="sys-link" href="<?= url('/system/qr') ?>">
        <span class="sys-ico">🔳</span>
        <span><strong>QR Code 產生器</strong><small>活動連結、海報、自訂樣式與置中標誌</small></span>
      </a>
      <a class="sys-link" href="<?= url('/admin/password') ?>">
        <span class="sys-ico">🔑</span>
        <span><strong>變更我的密碼</strong><small>更新自己的登入密碼</small></span>
      </a>
    </div>
  </div>

  <!-- ---------- Site settings ---------- -->
  <div class="panel form-panel">
    <h2 style="margin-top:0">網站設定</h2>
    <p class="help" style="margin-top:0">
      網站名稱與標語不隨年度改變，因此設定在這裡。
      <strong>橫幅與小圖示是每年不同的</strong>，請在各活動的「編輯活動資料」中設定。
    </p>

    <form method="POST" action="<?= url('/system/settings') ?>">
      <?= csrf_field() ?>

      <label for="site_name">網站名稱｜Header Name *</label>
      <input id="site_name" name="site_name" required maxlength="80"
             value="<?= h($settings['site_name'] ?? '天玉堂') ?>">
      <p class="help">顯示在網站左上角與頁尾。</p>

      <label for="site_tagline">頂部標語｜Top Banner Text</label>
      <input id="site_tagline" name="site_tagline" maxlength="255"
             value="<?= h($settings['site_tagline'] ?? '') ?>">
      <p class="help">顯示在網站最上方的深紅色橫條。</p>

      <div class="form-actions">
        <button class="primary" type="submit">儲存設定</button>
      </div>
    </form>
  </div>

  <!-- ---------- Users ---------- -->
  <div class="panel">
    <h2>管理帳號 Accounts</h2>
    <p class="help" style="margin-bottom:16px">
      目前共 <?= count($users) ?> 個帳號，其中 <?= (int) $systemCount ?> 位系統管理員。
      <strong>每位委員應有自己的帳號</strong> —— 現場布施會記錄是誰登記的，共用帳號會讓這項紀錄失去意義。
    </p>

    <div class="scroll">
      <table>
        <thead>
          <tr><th>帳號</th><th>姓名</th><th>權限</th><th>最後登入</th><th>操作</th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <?php $isSelf = (int) $u['id'] === (int) ($_SESSION['admin_id'] ?? 0); ?>
            <tr>
              <td><strong><?= h($u['username']) ?></strong><?= $isSelf ? ' <span class="help">(您)</span>' : '' ?></td>
              <td><?= h($u['display_name'] ?? '—') ?></td>
              <td>
                <?php if ($u['role'] === 'system_admin'): ?>
                  <span class="badge sysadmin">系統管理員</span>
                <?php else: ?>
                  <span class="badge">管理員</span>
                <?php endif; ?>
              </td>
              <td class="help"><?= $u['last_login_at'] ? h(date('Y-m-d H:i', strtotime($u['last_login_at']))) : '從未登入' ?></td>
              <td>
                <div class="actions-cell">
                  <form method="POST" action="<?= url('/system/users/role') ?>" style="margin:0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <input type="hidden" name="role"
                           value="<?= $u['role'] === 'system_admin' ? 'admin' : 'system_admin' ?>">
                    <button class="mini-btn ghost" type="submit">
                      <?= $u['role'] === 'system_admin' ? '降為管理員' : '升為系統管理員' ?>
                    </button>
                  </form>

                  <button class="mini-btn ghost" type="button"
                          onclick="togglePw(<?= (int) $u['id'] ?>)">重設密碼</button>

                  <?php if (!$isSelf): ?>
                    <form method="POST" action="<?= url('/system/users/delete') ?>" style="margin:0"
                          onsubmit="return confirm('確定刪除帳號 <?= h($u['username']) ?>？此操作無法復原。');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                      <button class="mini-btn danger" type="submit">刪除</button>
                    </form>
                  <?php endif; ?>
                </div>

                <form method="POST" action="<?= url('/system/users/password') ?>"
                      id="pw<?= (int) $u['id'] ?>" class="pw-form hidden">
                  <?= csrf_field() ?>
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <input type="password" name="password" placeholder="新密碼（至少 8 字元）" required minlength="8">
                  <input type="password" name="password_confirm" placeholder="再次輸入" required minlength="8">
                  <button class="mini-btn" type="submit">儲存</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ---------- Add user ---------- -->
  <div class="panel form-panel">
    <h2 style="margin-top:0">新增帳號</h2>
    <form method="POST" action="<?= url('/system/users/create') ?>">
      <?= csrf_field() ?>

      <div class="form-row">
        <div>
          <label for="username">帳號｜Username *</label>
          <input id="username" name="username" required maxlength="50"
                 pattern="[A-Za-z0-9._\-]{3,50}" placeholder="例如：siew.ling">
          <p class="help">英文字母、數字、. _ - ，3–50 字元。</p>
        </div>
        <div>
          <label for="display_name">姓名｜Display Name</label>
          <input id="display_name" name="display_name" maxlength="80" placeholder="例如：陳秀玲">
        </div>
      </div>

      <div class="form-row">
        <div>
          <label for="password">密碼｜Password *</label>
          <input id="password" name="password" type="password" required minlength="8">
        </div>
        <div>
          <label for="password_confirm">再次輸入｜Confirm *</label>
          <input id="password_confirm" name="password_confirm" type="password" required minlength="8">
        </div>
      </div>

      <label for="role">權限｜Role *</label>
      <select id="role" name="role">
        <option value="admin">管理員 Admin — 報名、布施、活動、相簿</option>
        <option value="system_admin">系統管理員 System Admin — 以上全部，加上網站設定與帳號管理</option>
      </select>

      <div class="form-actions">
        <button class="primary" type="submit">建立帳號</button>
      </div>
    </form>
  </div>

</div>

<script>
function togglePw(id) {
  document.getElementById('pw' + id).classList.toggle('hidden');
}
</script>
</body>
</html>
