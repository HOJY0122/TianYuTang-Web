<?php
/**
 * Public site footer. Every line is a site setting the system admin
 * edits at /system (Footer section); empty lines are simply skipped.
 */
$site       = $site ?? (new App\Models\Setting())->site();
$footerYear = isset($event['year']) ? (int) $event['year'] : (int) date('Y');
?>
<footer class="site-footer">
  <div class="f-name">🙏 <?= h($site['site_name']) ?></div>
  <?php if ($site['footer_org'] !== ''): ?>
    <div class="f-org"><?= h($site['footer_org']) ?></div>
  <?php endif; ?>
  <?php if ($site['footer_address'] !== ''): ?>
    <p>📍 <?= h($site['footer_address']) ?></p>
  <?php endif; ?>
  <?php if ($site['footer_contact'] !== ''): ?>
    <p>📞 <?= h($site['footer_contact']) ?></p>
  <?php endif; ?>
  <?php if ($site['footer_note_zh'] !== '' || $site['footer_note_en'] !== ''): ?>
    <p class="f-note"><?= h($site['footer_note_zh']) ?><?php if ($site['footer_note_en'] !== ''): ?><br><?= h($site['footer_note_en']) ?><?php endif; ?></p>
  <?php endif; ?>
  <div class="f-small">
    © <?= $footerYear ?> <?= h($site['footer_org'] !== '' ? $site['footer_org'] : $site['site_name']) ?>
    <?php /* No admin link on purpose: the committee knows the address, and
             advertising it to every visitor only invites password guessing. */ ?>
  </div>
</footer>
</body>
</html>
