<?php
/**
 * Letterhead for every printed sheet / PDF.
 *
 * Expects: $event, $docTitleZh, $docTitleEn, $printedAt.
 * Logo, name, organisation, address and contact come from site settings
 * (system admin), so a new logo appears on paper with no code change.
 */
$lh      = (new App\Models\Setting())->site();
$lhLogo  = $lh['site_logo_path'] ?: $lh['site_favicon_path'];
$lhDates = App\Models\Event::formatDateLines($event);
$lhBy    = $_SESSION['admin_display'] ?? ($_SESSION['admin_username'] ?? '');
?>
<header class="letterhead">
  <div class="lh-org">
    <?php if ($lhLogo): ?><img src="<?= h(BASE_URL . '/' . $lhLogo) ?>" alt=""><?php endif; ?>
    <div>
      <div class="lh-name"><?= h($lh['site_name']) ?></div>
      <?php if ($lh['site_name_en'] !== ''): ?><div class="lh-en"><?= h($lh['site_name_en']) ?></div><?php endif; ?>
      <?php if ($lh['footer_org'] !== ''): ?><div class="lh-line"><?= h($lh['footer_org']) ?></div><?php endif; ?>
      <?php if ($lh['footer_address'] !== '' || $lh['footer_contact'] !== ''): ?>
        <div class="lh-line"><?= h(implode('　·　', array_filter([$lh['footer_address'], $lh['footer_contact']]))) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="lh-doc">
    <div class="lh-title"><?= h($docTitleZh) ?></div>
    <div class="lh-title-en"><?= h($docTitleEn) ?></div>
    <table class="lh-meta">
      <tr><th>活動 Event</th><td><?= h($event['year'] . ' ' . $event['name']) ?></td></tr>
      <?php if ($lhDates): ?><tr><th>日期 Date</th><td><?= h($event['start_date'] === $event['end_date'] ? $event['start_date'] : $event['start_date'] . ' – ' . $event['end_date']) ?></td></tr><?php endif; ?>
      <tr><th>列印 Printed</th><td><?= h($printedAt) ?><?= $lhBy !== '' ? ' · ' . h($lhBy) : '' ?></td></tr>
    </table>
  </div>
</header>
<?php
// Running footer text for the @page margin boxes (see print.css).
$lhFooter = $lh['site_name'] . ' · ' . $docTitleZh . ' ' . $docTitleEn;
?>
<style>
@page { @bottom-left { content: <?= json_encode($lhFooter, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>; } }
</style>
