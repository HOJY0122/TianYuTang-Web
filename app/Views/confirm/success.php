<?php
$isRsvp    = ($kind === 'rsvp');
$refCode   = $confirmation['ref_code'];
$pageTitle = $isRsvp ? '報名成功 Registered' : '感恩您的布施 Thank you';
require BASE_PATH . '/app/Views/layouts/header.php';
?>

<main id="main">
<section class="confirm-wrap">
  <div class="card confirm-card">
    <div class="confirm-icon"><?= icon($isRsvp ? 'check-circle' : 'pray', 'xl') ?></div>
    <h1><?= tb($isRsvp ? 'success.rsvp_title' : 'success.don_title') ?></h1>
    <p class="confirm-sub">
      <?= tb($isRsvp ? 'success.rsvp_text' : 'success.don_text') ?>
    </p>

    <!-- No QR here: staff check each registration first, then send the
         check-in QR themselves (報名紀錄 → QR Code). Donations need none. -->
    <div class="ref-box">
      <div class="ref-label"><?= $isRsvp ? '報名編號 Reference' : '布施編號 Reference' ?></div>
      <div class="ref-code"><?= h($refCode) ?></div>
    </div>
    <?php if ($isRsvp): ?>
      <p class="qr-later"><?= icon('phone') ?> <span><?= tb('success.rsvp_qr') ?></span></p>
    <?php endif; ?>

    <?php if ($isRsvp): ?>
      <dl class="confirm-details">
        <div><dt>報名編號 Reference</dt><dd><?= h($refCode) ?></dd></div>
        <?php if (!empty($confirmation['org_name'])): ?>
          <div><dt>團體 Organisation</dt><dd><?= h($confirmation['org_name']) ?></dd></div>
        <?php endif; ?>
        <div><dt>參加人數 People</dt><dd><?= (int) $confirmation['count'] ?> 位</dd></div>
        <div><dt>活動 Event</dt><dd><?= h($event['year']) ?> <?= h($event['name']) ?></dd></div>
      </dl>

      <div class="people">
        <h3>參加者名單 <span class="en" style="display:inline">Attendees</span></h3>
        <?php foreach (($confirmation['attendees'] ?? []) as $i => $person): ?>
          <div class="person">
            <span class="num"><?= $i + 1 ?></span>
            <strong data-simp-check><?= h($person['name']) ?></strong>
            <span><?= icon('id-card') ?> <?= h($person['ic']) ?></span>
            <span><?= icon('phone') ?> <?= h($person['contact']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <dl class="confirm-details">
        <div><dt>布施編號 Reference</dt><dd><?= h($refCode) ?></dd></div>
        <div><dt>姓名 Name</dt><dd data-simp-check><?= h($confirmation['name']) ?></dd></div>
        <?php if (!empty($confirmation['seats'])): ?>
          <div><dt>功德席 Merit seats</dt>
            <dd><?= (int) $confirmation['seats'] ?> 席 × <?= rm((float) ($confirmation['seat_price'] ?? 0)) ?></dd></div>
        <?php endif; ?>
        <?php if (!empty($confirmation['free_amount'])): ?>
          <div><dt>隨喜布施 Freewill</dt><dd><?= rm((float) $confirmation['free_amount']) ?></dd></div>
        <?php endif; ?>
        <div><dt>總額 Total</dt><dd style="color:var(--red);font-size:1.2em"><?= rm((float) $confirmation['amount']) ?></dd></div>
        <div><dt>活動 Event</dt><dd><?= h($event['year']) ?> <?= h($event['name']) ?></dd></div>
      </dl>
    <?php endif; ?>

    <div class="confirm-actions no-print">
      <button class="btn" onclick="window.print()"><?= icon('printer') ?> 列印 / 存成 PDF　Print</button>
      <a class="btn ghost" href="<?= url('/') ?>"><?= icon('home') ?> 返回首頁 Home</a>
    </div>

    <p class="confirm-note no-print">
      <?= tb('success.leave') ?>
    </p>
  </div>
</section>
</main>



<?php require BASE_PATH . '/app/Views/layouts/footer.php'; ?>
