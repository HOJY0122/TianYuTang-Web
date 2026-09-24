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
    <form class="logout-form" method="POST" action="<?= url('/admin/logout') ?>">
      <?= csrf_field() ?>
      <button type="submit" class="logout">登出 Log out</button>
    </form>
  </div>
</div>

<div class="wrap checkin-wrap">

  <?php if (!empty($flash)): ?>
    <div class="flash <?= h($flash['type']) ?>">
      <strong><?= h($flash['title']) ?></strong> — <?= h($flash['message']) ?>
    </div>
  <?php endif; ?>

  <div class="flash system-note">
    ⚙️ 這個帳號負責<strong>網站本身</strong>：名稱、橫幅、小圖示、帳號與 QR Code。
    報名、布施、報到與相簿由<strong>管理員帳號</strong>在一般後台處理，
    系統管理員帳號無法開啟那些頁面 —— 這是刻意分開的。
  </div>

  <?php if ((int) $adminCount === 0): ?>
    <div class="flash error">
      ⚠️ 目前<strong>沒有任何管理員帳號</strong>，代表沒有人能處理報名與布施。
      請在下方「新增帳號」建立一個權限為「管理員」的帳號。
    </div>
  <?php endif; ?>

  <?php if (!empty($activeEvent)): ?>
    <div class="panel">
      <h2>目前公開的活動</h2>
      <p style="margin:0">
        <strong><?= h($activeEvent['year']) ?> · <?= h($activeEvent['name']) ?></strong>
        <?= !empty($activeEvent['is_test']) ? ' <span class="badge">測試</span>' : '' ?>
      </p>
      <p class="help" style="margin-bottom:0">
        僅供參考。活動內容由管理員維護。
      </p>
    </div>
  <?php endif; ?>

  <!-- ---------- Quick links ---------- -->
  <div class="panel">
    <h2>系統工具</h2>
    <div class="sys-links">
      <a class="sys-link" href="<?= url('/system/qr') ?>">
        <span class="sys-ico">🔳</span>
        <span><strong>QR Code 產生器</strong><small>活動連結、海報、自訂樣式與置中標誌</small></span>
      </a>
      <a class="sys-link" href="<?= url('/account/password') ?>">
        <span class="sys-ico">🔑</span>
        <span><strong>變更我的密碼</strong><small>更新自己的登入密碼</small></span>
      </a>
    </div>
  </div>

  <!-- ---------- Site settings ---------- -->
  <div class="panel form-panel">
    <h2 style="margin-top:0">網站設定 Site Settings</h2>
    <p class="help" style="margin-top:0">
      名稱、標語、橫幅與小圖示都屬於網站本身，換了年度也不必重新設定，
      因此全部集中在這一頁。
    </p>

    <!-- enctype is required: without it the browser sends only the file
         NAME, never the file itself, and $_FILES arrives empty. -->
    <form method="POST" action="<?= url('/system/settings') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <label for="site_name">網站名稱｜Header Name *</label>
      <input id="site_name" name="site_name" required maxlength="80"
             value="<?= h($settings['site_name'] ?? '天玉堂') ?>">
      <p class="help">顯示在網站左上角與頁尾。</p>

      <label for="site_tagline">頂部標語｜Top Banner Text</label>
      <input id="site_tagline" name="site_tagline" maxlength="255"
             value="<?= h($settings['site_tagline'] ?? '') ?>">
      <p class="help">顯示在網站最上方的深紅色橫條。留空即不顯示。</p>

      <h3>圖片</h3>
      <p class="help" style="margin-top:0">
        支援 JPG、PNG、GIF、WebP，單檔 5MB 以內。上傳後系統會自動重新產生圖片並調整尺寸。
      </p>

      <label for="hero_banner">首頁橫幅｜Hero Banner</label>
      <?php $banner = $settings['site_banner_path'] ?? null; ?>
      <?php if ($banner): ?>
        <div class="image-preview">
          <img src="<?= h(BASE_URL . '/' . $banner) ?>" alt="目前的橫幅">
          <label class="remove-check">
            <input type="checkbox" name="remove_hero_banner" value="1"> 移除這張橫幅
          </label>
        </div>
      <?php endif; ?>
      <input id="hero_banner" name="hero_banner" type="file"
             accept="image/jpeg,image/png,image/gif,image/webp">
      <p class="help">
        建議寬度 1600–1920px。過寬的圖片會自動縮小到 1920px。
        <?= $banner ? '選擇新檔案即可取代現有橫幅。' : '未設定時，首頁會使用原本的漸層背景。' ?>
      </p>

      <label for="favicon">網站小圖示｜Favicon</label>
      <?php $favicon = $settings['site_favicon_path'] ?? null; ?>
      <?php if ($favicon): ?>
        <div class="image-preview favicon">
          <img src="<?= h(BASE_URL . '/' . $favicon) ?>" alt="目前的圖示">
          <label class="remove-check">
            <input type="checkbox" name="remove_favicon" value="1"> 移除這個圖示
          </label>
        </div>
      <?php endif; ?>
      <input id="favicon" name="favicon" type="file"
             accept="image/jpeg,image/png,image/gif,image/webp">
      <p class="help">
        建議正方形，180×180px 以上。
        <strong>注意：</strong>瀏覽器會長時間快取小圖示，更換後可能需要強制重新整理（Ctrl+F5）才看得到新的。
      </p>

      <div class="form-actions">
        <button class="primary" type="submit">儲存設定</button>
      </div>
    </form>
  </div>

  <!-- ---------- Users ---------- -->
  <div class="panel">
    <h2>管理帳號 Accounts</h2>
    <p class="help" style="margin-bottom:16px">
      目前共 <?= count($users) ?> 個帳號：<?= (int) $adminCount ?> 位管理員、<?= (int) $systemCount ?> 位系統管理員。
      <strong>每位委員應有自己的帳號</strong> —— 現場布施與現場報名都會記錄是誰登記的，
      共用帳號會讓這項紀錄失去意義。
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
                <?php
                // The last account of either role is load-bearing: lose
                // the last system admin and nobody can reach these
                // settings; lose the last admin and nobody can take a
                // registration on the day. The server refuses both — the
                // buttons are hidden here so nobody keeps clicking one
                // that always fails.
                $isLastOfRole = ($u['role'] === 'system_admin')
                    ? ((int) $systemCount <= 1)
                    : ((int) $adminCount <= 1);
                ?>
                <div class="actions-cell">
                  <?php if ($isLastOfRole): ?>
                    <span class="help" style="margin:0">
                      最後一位<?= $u['role'] === 'system_admin' ? '系統管理員' : '管理員' ?>，
                      無法變更或刪除
                    </span>
                  <?php else: ?>
                  <form method="POST" action="<?= url('/system/users/role') ?>" style="margin:0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <input type="hidden" name="role"
                           value="<?= $u['role'] === 'system_admin' ? 'admin' : 'system_admin' ?>">
                    <button class="mini-btn ghost" type="submit">
                      <?= $u['role'] === 'system_admin' ? '降為管理員' : '升為系統管理員' ?>
                    </button>
                  </form>
                  <?php endif; ?>

                  <button class="mini-btn ghost" type="button"
                          onclick="togglePw(<?= (int) $u['id'] ?>)">重設密碼</button>

                  <?php if (!$isSelf && !$isLastOfRole): ?>
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
        <option value="admin">管理員 Admin — 報名、現場登記、布施、報到、活動、相簿</option>
        <option value="system_admin">系統管理員 System Admin — 網站設定、橫幅、小圖示、帳號、QR Code</option>
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
