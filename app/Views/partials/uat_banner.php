<?php
/**
 * UAT test-mode announcement bar (System → UAT). Shown at the top of every
 * page, public and admin, while UAT is on; the messages take turns
 * (js/uat.js). The wrapper is always there so it appears and disappears
 * on open pages by itself (realtime, data-live).
 */
$_uSite = $site ?? (new App\Models\Setting())->site();
?>
<div id="liveUat" data-live="settings">
<?php if (App\Core\Uat::isOn($_uSite)): $_uMsgs = App\Core\Uat::messages($_uSite); ?>
  <div class="uat-bar" role="region" aria-label="UAT 測試模式 Test mode" data-interval="<?= App\Core\Uat::interval($_uSite) ?>"
       style="--uat-scale:<?= App\Core\Uat::size($_uSite) / 100 ?>">
    <span class="uat-badge"><span aria-hidden="true"><?= icon('flask') ?></span> UAT<small>測試中 Testing</small></span>
    <div class="uat-track">
      <ul>
        <?php foreach ($_uMsgs as $_uI => [$_uZh, $_uEn]): ?>
          <li<?= $_uI === 0 ? ' class="is-on"' : '' ?>><strong><?= h($_uZh) ?></strong><?php if ($_uEn !== ''): ?> <span><?= h($_uEn) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php if (count($_uMsgs) > 1): ?><span class="uat-dots" aria-hidden="true"><?php foreach ($_uMsgs as $_uI => $_): ?><i<?= $_uI === 0 ? ' class="on"' : '' ?>></i><?php endforeach; ?></span><?php endif; ?>
  </div>
<?php endif; unset($_uSite, $_uMsgs, $_uI, $_uZh, $_uEn, $_); ?>
</div>
