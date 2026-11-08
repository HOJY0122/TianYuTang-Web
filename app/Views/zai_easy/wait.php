<?php
/**
 * Easy mode ③ — not registered (walk-in): one person, one IC → a waiting number.
 * The IC is required and checked like the online form (format + age rule);
 * it is also how someone who did register online is spotted.
 */
$title = '沒有登記：排候補'; $titleEn = 'Not registered — add to the waiting list';
require __DIR__ . '/_top.php';
$old = $old ?? [];
?>
<?php if (!empty($error)): ?><div class="ez-msg ez-warn"><b>未加入 Not added</b><p><?= nl2br(h($error)) ?></p></div><?php endif; ?>
<p class="ez-hint"><b>1 人 1 身份證。</b>一起來的家人，每人各排一次。<br>
  <span class="en">One person, one IC. Family members each get their own number.</span></p>
<form method="POST" action="<?= url('/admin/zai/easy/wait') ?>" class="ez-form">
  <?= csrf_field() ?>
  <label>姓名 <span class="en">Name</span><input name="name" required maxlength="100" autocomplete="off" value="<?= h($old['name'] ?? '') ?>"<?= empty($old) ? ' autofocus' : '' ?>></label>
  <label><span>身份證 <b class="ez-req">必填 Required</b></span> <span class="en">IC</span>
    <input name="ic" required maxlength="30" inputmode="numeric" autocomplete="off" data-validate="ic" data-exists-url="<?= h(url('/admin/zai/easy/ic-check')) ?>" placeholder="例 650101-10-1234" value="<?= h($old['ic'] ?? '') ?>"<?= empty($old) ? '' : ' autofocus' ?>></label>
  <label>電話 <span class="en">Phone</span><input name="contact" maxlength="30" inputmode="tel" autocomplete="off" data-validate="phone" value="<?= h($old['contact'] ?? '') ?>"></label>
  <button type="submit" class="ez-btn ez-ok">✓ 給候補號碼 <small>Give a waiting number</small></button>
</form>
<?php require BASE_PATH . '/app/Views/partials/ic_validate.php'; ?>
<?php require __DIR__ . '/_bottom.php'; ?>
