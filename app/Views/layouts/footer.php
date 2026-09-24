<?php
/**
 * Public site footer. Every line is a site setting the system admin
 * edits at /system (Footer section); empty lines are simply skipped.
 */
$site       = $site ?? (new App\Models\Setting())->site();
$footerYear = isset($event['year']) ? (int) $event['year'] : (int) date('Y');
?>
<?php
// Look chosen in Site settings → ③ Footer: space, text size, alignment, colours.
$fTheme = App\Models\Setting::FOOTER_THEMES[$site['footer_theme']] ?? App\Models\Setting::FOOTER_THEMES['red'];
$fStyle = sprintf('--f-pad:%dpx;--f-size:%d%%;--f-bg:%s;--f-fg:%s;--f-accent:%s',
    (int) $site['footer_pad'], (int) $site['footer_size'], $fTheme[1], $fTheme[2], $fTheme[3]);
?>
<footer class="site-footer<?= $site['footer_align'] === 'left' ? ' f-left' : '' ?>" style="<?= h($fStyle) ?>">
  <?php $fTitle = App\Models\Setting::effective('footer_title', $site); ?>
  <?php if ($fTitle !== ''): ?><div class="f-name"><?= h($fTitle) ?></div><?php endif; ?>
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
  <?php $fCopy = App\Models\Setting::effective('footer_copyright', $site); ?>
  <?php if ($fCopy !== ''): ?>
  <div class="f-small">
    <?= h(strtr($fCopy, ['{year}' => (string) $footerYear])) ?>
    <?php /* No admin link on purpose: the committee knows the address, and
             advertising it to every visitor only invites password guessing. */ ?>
  </div>
  <?php endif; ?>
</footer>
<script src="<?= asset('js/dialog.js') ?>"></script>
<?php if (($activeNav ?? "") === "home"): ?><script src="<?= asset("js/banner.js") ?>"></script><?php endif; ?>
</body>
</html>
