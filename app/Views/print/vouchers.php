<?php
/**
 * 布施憑單 Payment vouchers, ready to print — the committee's sample
 * (Donation_Payment_Voucher.pdf) with only these filled in per voucher:
 * Date, Description of Payment, Payable to, Amount, Method of Payment,
 * Bank Account No. Everything else — header, logo, motto, "Approved by:
 * Leong Chuan Hoe, Chairman" — is the sample itself
 * (public/assets/img/voucher-template.png, the sample page with those
 * fields cleared). The approver's signature is uploaded once by a system
 * admin and kept private (it is not in the public code).
 *
 * Positions are the sample's own, in points (A4 = 595 × 842 pt), the same
 * numbers the desktop Bulk Voucher Generator used. One voucher per A4
 * page; A5 is the same page at 70.7 %. "Save as PDF" gives the combined PDF.
 */
use App\Models\Setting;
use App\Models\Voucher;

$site    = (new Setting())->site();
$signUrl = $site['voucher_signature'] ? url('/admin/vouchers/signature') . '?v=' . substr(md5((string) $site['voucher_signature']), 0, 8) : null;
$date    = $batch['voucher_date'] ? date('j M Y', strtotime($batch['voucher_date'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Voucher｜<?= h($batch['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=LXGW+WenKai+TC:wght@400;700&family=Tinos:wght@400;700&display=swap" rel="stylesheet">
<style>
@page { size: <?= $paper === 'a5' ? 'A5' : 'A4' ?> portrait; margin: 0; }
* { box-sizing: border-box; }
body { margin: 0; background: #e9e6df; color: #000; }
.toolbar { position: sticky; top: 0; z-index: 2; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; padding: 12px 16px;
  background: #fff; border-bottom: 1px solid #ccc; font-family: "Noto Sans TC", "Microsoft JhengHei", sans-serif; font-size: 13px; }
.toolbar button, .toolbar a { border: 1px solid #999; background: #f4f4f4; color: #1a1a1a; border-radius: 8px; padding: 8px 14px; font: inherit; text-decoration: none; cursor: pointer; }
.toolbar .primary { background: #7d3b2f; border-color: #7d3b2f; color: #fff; font-weight: 700; }
.toolbar .hint { color: #555; font-size: 12px; }
.toolbar .warn { color: #a3361f; font-weight: 700; }
.sheets { padding: 18px 12px 40px; }

/* One voucher = one A4 page: 595.28 × 841.89 pt. */
.voucher { position: relative; width: 595.28pt; height: 841.89pt; margin: 0 auto 16px; background: #fff; overflow: hidden;
  box-shadow: 0 2px 10px rgba(0,0,0,.12); <?= $paper === 'a5' ? 'zoom: .7071;' : '' ?>
  font-family: "Times New Roman", Tinos, serif; font-size: 14pt; }
/* An <img>, not a CSS background: browsers leave backgrounds out when printing. */
.voucher .bg { position: absolute; inset: 0; width: 100%; height: 100%; }
.f { position: absolute; line-height: 18.9pt; white-space: nowrap; }
.f.b { font-weight: 700; }
.f.wrap { white-space: normal; line-height: 16.08pt; }
.f.zh { font-family: KaiTi, "楷体", STKaiti, DFKai-SB, BiauKai, "LXGW WenKai TC", serif; font-size: 18pt; line-height: 18.75pt; letter-spacing: .02em; }
.sig { position: absolute; left: 57.6pt; top: 627pt; width: 176pt; height: 60pt; display: flex; align-items: flex-end; }
.sig img { max-width: 100%; max-height: 100%; object-fit: contain; object-position: left bottom; }

@media print {
  body { background: #fff; }
  .no-print { display: none !important; }
  .sheets { padding: 0; }
  .voucher { margin: 0; box-shadow: none; break-after: page; }
  .voucher:last-child { break-after: auto; }
}
@media screen and (max-width: 860px) { .sheets { overflow-x: auto; } }
</style>
</head>
<body>

<div class="toolbar no-print">
  <button class="primary" onclick="window.print()">列印 / 存成 PDF　Print / Save as PDF</button>
  <a href="<?= url('/admin/vouchers/batch') ?>?id=<?= (int) $batch['id'] ?>">← 返回 Back</a>
  <?php foreach (Voucher::PAPERS as $pk => [$pzh, $pen]): ?>
    <?php if ($pk !== $paper): ?>
      <a href="?<?= h(http_build_query(['batch' => (int) $batch['id'], 'ids' => $_GET['ids'] ?? null, 'paper' => $pk])) ?>"><?= h($pzh) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
  <span class="hint"><?= count($vouchers) ?> 張 vouchers · 在列印視窗選擇「另存為 PDF」，紙張 <?= $paper === 'a5' ? 'A5' : 'A4' ?>，邊界「無」。Choose “Save as PDF”, margins “None”.</span>
  <?php if (!$signUrl): ?><span class="warn">尚未上傳批准人簽名 No signature uploaded yet</span><?php endif; ?>
</div>

<div class="sheets">
<?php if (!$vouchers): ?>
  <p style="text-align:center;color:#666;font-family:sans-serif">沒有可列印的憑單。No vouchers to print.</p>
<?php endif; ?>
<?php foreach ($vouchers as $v): ?>
  <?php [$zh, $en] = Voucher::payee($v); ?>
  <section class="voucher">
    <img class="bg" src="<?= asset('img/voucher-template.png') ?>" alt="">

    <div class="f" style="left:420.6pt;top:167.25pt">Date : <?= h($date) ?></div>
    <div class="f b" style="left:207.36pt;top:242.87pt;max-width:350pt;white-space:normal"><?= h($batch['description']) ?></div>

    <?php if ($zh !== null): ?>
      <div class="f zh b" style="left:210.12pt;top:295.51pt"><?= h($zh) ?></div>
      <?php if ($en !== null): ?><div class="f b wrap" style="left:210.6pt;top:315.8pt;width:305pt"><?= h($en) ?></div><?php endif; ?>
    <?php elseif ($en !== null): ?>
      <div class="f b wrap" style="left:210.6pt;top:297.8pt;width:305pt"><?= h($en) ?></div>
    <?php endif; ?>

    <div class="f b" style="left:211.32pt;top:362.75pt"><?= h(Voucher::rm(Voucher::amount($batch, $v))) ?></div>
    <div class="f b" style="left:308.16pt;top:362.75pt;max-width:250pt;white-space:normal"><?= h(Voucher::wordsEn($batch, $v)) ?></div>
    <div class="f b" style="left:210.12pt;top:410.99pt"><?= h($batch['method']) ?></div>
    <div class="f b" style="left:210.6pt;top:459.57pt"><?= h($v['bank_account'] ?? '') ?></div>

    <?php if ($signUrl): ?><div class="sig"><img src="<?= h($signUrl) ?>" alt=""></div><?php endif; ?>
  </section>
<?php endforeach; ?>
</div>
</body>
</html>
