<?php
/**
 * Tick boxes for the admin functions an account may use, grouped like the
 * menu, with ready-made presets. Expects $pickSelected (null = all).
 * Posts perms[] (App\Core\Access::fromForm).
 */
use App\Core\Access;

$pickGroups = [];
foreach (Access::MODULES as $_k => $_m) {
    $pickGroups[$_m[3]][$_k] = $_m;
}
?>
<div class="access-picker" data-access-picker>
  <div class="access-presets" role="group" aria-label="快速選擇 Quick choices">
    <span class="help">快速選擇 <span class="en">Quick choice</span>：</span>
    <?php foreach (Access::PRESETS as $_pk => [$_pzh, $_pen, $_pkeys]): ?>
      <button type="button" class="mini-btn ghost" data-preset="<?= h($_pkeys === null ? '*' : implode(',', $_pkeys)) ?>"><?= h($_pzh) ?> <span class="en"><?= h($_pen) ?></span></button>
    <?php endforeach; ?>
  </div>
  <?php foreach ($pickGroups as $_g => $_items): ?>
    <fieldset class="access-group">
      <legend><?= h($_g) ?></legend>
      <?php foreach ($_items as $_k => [$_ic, $_zh, $_en]): ?>
        <label class="access-item"><input type="checkbox" name="perms[]" value="<?= h($_k) ?>"<?= $pickSelected === null || in_array($_k, $pickSelected, true) ? ' checked' : '' ?>>
          <?= icon($_ic) ?> <span><?= h($_zh) ?><small><?= h($_en) ?></small></span></label>
      <?php endforeach; ?>
    </fieldset>
  <?php endforeach; ?>
  <p class="help access-count" aria-live="polite"></p>
</div>
<?php unset($pickGroups, $_k, $_m, $_g, $_items, $_ic, $_zh, $_en, $_pk, $_pzh, $_pen, $_pkeys); ?>
