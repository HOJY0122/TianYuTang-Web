<?php
/** Signed in, but the system admin has not given this account any function yet. */
require BASE_PATH . '/app/Views/layouts/admin_header.php';
?>
<div class="panel" style="max-width:640px">
  <h2 style="margin-top:0"><?= icon('lock') ?> 尚未開放任何功能 <span class="en">No functions yet</span></h2>
  <p>您的帳號已登入，但系統管理員還沒有為您開放任何功能。請聯絡系統管理員。</p>
  <p class="help">You are signed in, but a system admin has not given your account any functions yet. Please ask a system admin.</p>
  <form method="POST" action="<?= url('/admin/logout') ?>" class="form-actions">
    <?= csrf_field() ?>
    <button class="mini-btn ghost btn-lg" type="submit"><?= icon('log-out') ?> 登出 <span class="en">Sign out</span></button>
  </form>
</div>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
