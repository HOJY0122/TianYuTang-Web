<?php
// $event is in scope when rendered from the public site; fall back for safety.
$footerTitle = isset($event['name']) ? ($siteName ?? '天玉堂') . $event['name'] : ($siteName ?? SITE_NAME);
$footerYear  = isset($event['year']) ? (int) $event['year'] : (int) date('Y');
?>
<footer>
  <div class="footer-title">🙏 <?= h($footerTitle) ?></div>
  <div><?= nl2br(h($event['location'] ?? 'PERSATUAN PENGANUT DEWA TAI ZHI KUALA LUMPUR')) ?></div>
  <div style="margin-top:12px">活動報名　•　功德布施</div>
  <div style="margin-top:12px;font-size:13px;opacity:.8">
    © <?= $footerYear ?> PERSATUAN PENGANUT DEWA TAI ZHI KUALA LUMPUR
  </div>
</footer>
</body>
</html>
