<?php
require BASE_PATH . '/app/Views/layouts/admin_header.php';
$statusText = ['pending' => '待確認 Pending', 'confirmed' => '已確認 Confirmed', 'cancelled' => '已取消 Cancelled'];
?>
<p style="margin:0 0 12px"><a class="mini-btn ghost" href="<?= url('/admin/registrations') ?>?event=<?= (int) $group['event_id'] ?>">← 返回列表 Back to list</a></p>

<?php foreach ($errors as $e): ?><div class="flash error"><?= h($e) ?></div><?php endforeach; ?>

<form method="POST" action="<?= url('/admin/registrations/save') ?>" class="panel form-panel" style="max-width:860px">
  <?= csrf_field() ?>
  <input type="hidden" name="group_id" value="<?= (int) $group['id'] ?>">

  <h2 style="margin:0"><?= h($group['ref_code']) ?>
    <?= $group['source'] === 'walkin' ? '<span class="badge walkin">現場 Walk-in</span>' : '<span class="badge">線上 Online</span>' ?></h2>
  <p class="help" style="margin-top:4px">
    <?= h($event['year'] . ' ' . $event['name']) ?> · 登記於 Registered <?= h($group['created_at']) ?>
    <?= !empty($group['recorded_by']) ? ' · 登記者 Recorded by ' . h($group['recorded_by']) : '' ?>
  </p>

  <label for="status">狀態 <span class="en">Status</span></label>
  <select id="status" name="status">
    <?php foreach ($statusText as $k => $label): ?>
      <option value="<?= $k ?>"<?= $group['status'] === $k ? ' selected' : '' ?>><?= $label ?></option>
    <?php endforeach; ?>
  </select>

  <h3>參加者 <span class="en">People in this registration (<?= count($people) ?>)</span></h3>
  <div id="people">
    <?php foreach ($people as $i => $p): ?>
      <div class="person-edit">
        <h4>第 <?= $i + 1 ?> 位 <span class="en">Person <?= $i + 1 ?></span>
          <?= $p['checked_in_at'] ? '<span class="badge ok">✓ 已報到 Arrived ' . h(date('H:i', strtotime($p['checked_in_at']))) . '</span>' : '' ?></h4>
        <label class="checkline remove"><input type="checkbox" name="person_remove[<?= $i ?>]" value="1"> 移除 Remove</label>
        <input type="hidden" name="person_id[<?= $i ?>]" value="<?= (int) $p['id'] ?>">
        <div class="form-grid">
          <div><label>姓名 <span class="en">Name</span></label><input name="person_name[<?= $i ?>]" value="<?= h($p['name']) ?>" maxlength="100" required></div>
          <div><label>身份證 / 護照 <span class="en">IC / Passport</span></label><input name="person_ic[<?= $i ?>]" value="<?= h($p['ic_no']) ?>" maxlength="30"></div>
          <div><label>聯絡號碼 <span class="en">Contact</span></label><input name="person_contact[<?= $i ?>]" value="<?= h($p['contact_no']) ?>" maxlength="30"></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <p><button type="button" class="mini-btn ghost btn-lg" id="addPerson">＋ 新增一位 Add a person</button></p>

  <div class="form-actions">
    <button class="primary" type="submit">💾 儲存 Save changes</button>
    <a class="mini-btn ghost" href="<?= url('/admin/checkin') ?>?event=<?= (int) $group['event_id'] ?>&amp;ref=<?= urlencode($group['ref_code']) ?>">✅ 報到頁 Check-in</a>
  </div>
</form>

<template id="personTpl">
  <div class="person-edit">
    <h4>新增 <span class="en">New person</span></h4>
    <input type="hidden" name="person_id[__i__]" value="0">
    <div class="form-grid">
      <div><label>姓名 <span class="en">Name</span></label><input name="person_name[__i__]" maxlength="100"></div>
      <div><label>身份證 / 護照 <span class="en">IC / Passport</span></label><input name="person_ic[__i__]" maxlength="30"></div>
      <div><label>聯絡號碼 <span class="en">Contact</span></label><input name="person_contact[__i__]" maxlength="30"></div>
    </div>
  </div>
</template>
<script>
(function () {
  var box = document.getElementById('people');
  var next = <?= count($people) ?>;
  document.getElementById('addPerson').addEventListener('click', function () {
    var html = document.getElementById('personTpl').innerHTML.replace(/__i__/g, String(next++));
    box.insertAdjacentHTML('beforeend', html);
    box.lastElementChild.querySelector('input[name^="person_name"]').focus();
  });
  // Show at a glance which people will be removed on save.
  box.addEventListener('change', function (e) {
    if (e.target.name && e.target.name.indexOf('person_remove') === 0) {
      e.target.closest('.person-edit').classList.toggle('removed', e.target.checked);
    }
  });
})();
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
