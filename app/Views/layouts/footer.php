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
$fStyle = sprintf('--f-pad:%dpx;--f-size:%d%%;--f-pad-m:%dpx;--f-size-m:%d%%;--f-gap:%dpx;--f-width:%dpx;--f-bg:%s;--f-fg:%s;--f-accent:%s',
    (int) $site['footer_pad'], (int) $site['footer_size'], (int) $site['footer_pad_m'], (int) $site['footer_size_m'],
    (int) $site['footer_gap'], (int) $site['footer_width'], $fTheme[1], $fTheme[2], $fTheme[3]);
// Lines left out on phones (Site settings → Footer look → 手機 Phones).
$fHideM = array_flip(array_filter(explode(',', (string) $site['footer_m_hide'])));
$fm = static fn(string $k): string => isset($fHideM[$k]) ? ' f-hide-m' : '';
?>
<footer id="liveFooter" data-live="settings events" class="site-footer<?= $site['footer_align'] === 'left' ? ' f-left' : '' ?>" style="<?= h($fStyle) ?>">
  <?php $fTitle = App\Models\Setting::effective('footer_title', $site); ?>
  <?php if ($fTitle !== ''): ?><div class="f-name"><?= h($fTitle) ?></div><?php endif; ?>
  <?php if ($site['footer_org'] !== ''): ?>
    <div class="f-org<?= $fm('org') ?>" data-fline="org"><?= h($site['footer_org']) ?></div>
  <?php endif; ?>
  <?php if ($site['footer_address'] !== ''): ?>
    <p class="f-line<?= $fm('address') ?>" data-fline="address"><?= icon('map-pin') ?> <?= h($site['footer_address']) ?></p>
  <?php endif; ?>
  <?php if ($site['footer_contact'] !== ''): ?>
    <p class="f-line<?= $fm('contact') ?>" data-fline="contact"><?= icon('phone') ?> <?= h($site['footer_contact']) ?></p>
  <?php endif; ?>
  <?php if ($site['footer_note_zh'] !== '' || $site['footer_note_en'] !== ''): ?>
    <p class="f-note<?= $fm('note') ?>" data-fline="note"><?= h($site['footer_note_zh']) ?><?php if ($site['footer_note_en'] !== ''): ?><br><?= h($site['footer_note_en']) ?><?php endif; ?></p>
  <?php endif; ?>
  <?php $fCopy = App\Models\Setting::effective('footer_copyright', $site); ?>
  <?php if ($fCopy !== ''): ?>
  <div class="f-small<?= $fm('copyright') ?>" data-fline="copyright">
    <?= h(strtr($fCopy, ['{year}' => (string) $footerYear])) ?>
    <?php /* No admin link on purpose: the committee knows the address, and
             advertising it to every visitor only invites password guessing. */ ?>
  </div>
  <?php endif; ?>
</footer>
<script src="<?= asset('js/photos.js') ?>"></script>
<script src="<?= asset('js/dialog.js') ?>"></script>
<script src="<?= asset('js/live.js') ?>"></script>
<script src="<?= asset('js/uat.js') ?>"></script>
<?php if (($activeNav ?? "") === "home"): ?><script src="<?= asset("js/banner.js") ?>"></script><?php endif; ?>
<?php if (!empty($protectAreas)): ?><script src="<?= asset('js/protect.js') ?>"></script><?php endif; ?>
</body>
</html>
