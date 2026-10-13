<?php
/**
 * 布施憑單 Payment vouchers, ready to print — laid out like the committee's
 * sample (Donation_Payment_Voucher.pdf): the temple's letterhead, "Payment
 * Voucher", the payment lines, the motto, and the approver's signature.
 * One voucher per A4 page (or the same page scaled to A5). "Save as PDF"
 * in the print window gives the combined PDF.
 *
 * The header lines, title, motto, approver and signature are set in
 * Admin → Vouchers → 憑單版面 Voucher layout (system admin).
 */
use App\Models\Setting;
use App\Models\Voucher;

$site     = (new Setting())->site();
$logo     = $site['voucher_show_logo'] === '1' ? ($site['site_logo_path'] ?: null) : null;
$head     = Voucher::headerLines($site);
$title    = trim((string) $site['voucher_title']);
$mottoEn  = trim((string) $site['voucher_motto_en']);
$mottoZh  = trim((string) $site['voucher_motto_zh']);
$approver = trim((string) $site['voucher_approver']);
$apprTitle= trim((string) $site['voucher_approver_title']);
$signUrl  = $site['voucher_signature'] ? url('/admin/vouchers/signature') . '?v=' . substr(md5((string) $site['voucher_signature']), 0, 8) : null;
$date     = $batch['voucher_date'] ? date('j M Y', strtotime($batch['voucher_date'])) : '';
// Header line styles, as on the sample: the first line is the big Chinese
// name; English lines in capitals are bold; address / email / phone are plain.
$lineClass = static function (int $i, string $line): string {
    if ($i === 0) {
        return 'h-main';
    }
    return preg_match('/[A-Z]/', $line) && !preg_match('/[a-z0-9@]/', $line) ? 'h-bold' : 'h-plain';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Voucher｜<?= h($batch['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=LXGW+WenKai+TC:wght@400;700&family=Tinos:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
<style>
@page { size: <?= $paper === 'a5' ? 'A5' : 'A4' ?> portrait; margin: 0; }
* { box-sizing: border-box; }
body { margin: 0; background: #e9e6df; color: #111; font-family: "Times New Roman", Tinos, "LXGW WenKai TC", serif; }
.toolbar { position: sticky; top: 0; z-index: 2; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; padding: 12px 16px;
  background: #fff; border-bottom: 1px solid #ccc; font-family: "Noto Sans TC", "Microsoft JhengHei", sans-serif; font-size: 13px; }
.toolbar button, .toolbar a { border: 1px solid #999; background: #f4f4f4; color: #1a1a1a; border-radius: 8px; padding: 8px 14px; font: inherit; text-decoration: none; cursor: pointer; }
.toolbar .primary { background: #7d3b2f; border-color: #7d3b2f; color: #fff; font-weight: 700; }
.toolbar .hint { color: #555; font-size: 12px; }
.sheets { padding: 18px 12px 40px; }

/* One voucher = one A4 page, measured from the sample. A5 is the same page at 70.7 %. */
.voucher { width: 210mm; height: 297mm; margin: 0 auto 16px; background: #fff; padding: 12mm 18mm 0 19mm; position: relative; overflow: hidden;
  box-shadow: 0 2px 10px rgba(0,0,0,.12); <?= $paper === 'a5' ? 'zoom: .7071;' : '' ?> }

.v-head { display: flex; align-items: center; min-height: 44mm; }
.v-logo { flex: none; width: 34mm; height: 44mm; display: flex; align-items: center; justify-content: center; margin-left: -4mm; }
.v-logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
.v-org { flex: 1; text-align: center; line-height: 1.16; padding-left: 2mm; }
.v-org .h-main { font-family: "LXGW WenKai TC", "DFKai-SB", "BiauKai", serif; font-weight: 700; font-size: 21.5pt; letter-spacing: .02em; line-height: 1.2; margin-bottom: .4mm; }
.v-org .h-bold { font-weight: 700; font-size: 13.4pt; }
.v-org .h-plain { font-size: 11.6pt; }

.v-date { display: flex; justify-content: space-between; align-items: baseline; font-size: 14pt; margin: 8mm 1mm 0 0; }
.v-date .v-no { font-size: 10.5pt; color: #555; }
.v-title { text-align: center; font-weight: 700; font-size: 24pt; margin: 3mm 0 8mm; }

.v-rows { display: grid; grid-template-columns: 50mm 4.5mm 1fr; font-size: 14pt; line-height: 1.22; }
.v-rows > div { padding-bottom: 11mm; }
.v-rows .lbl { white-space: nowrap; }
.v-rows .val { font-weight: 700; overflow-wrap: anywhere; }
.v-rows .first { padding-bottom: 13mm; }
.v-zh { display: block; font-family: "LXGW WenKai TC", "DFKai-SB", "BiauKai", serif; font-size: 17pt; font-weight: 700; letter-spacing: .06em; line-height: 1.12; margin-bottom: .8mm; }
.v-en { display: block; max-width: 112mm; }
.v-amt { display: flex; gap: 0; align-items: baseline; flex-wrap: wrap; }
.v-amt .rm { min-width: 39mm; }

.v-motto { text-align: center; margin: 1mm 0 0; }
.v-motto .en { font-size: 25pt; color: #5b5b5b; letter-spacing: .005em; text-shadow: 1.2px 1.6px 1.5px rgba(0,0,0,.45); }
.v-motto .zh { font-family: "LXGW WenKai TC", "DFKai-SB", "BiauKai", serif; font-weight: 700; font-size: 27pt; letter-spacing: .55em; margin-top: 1mm;
  padding-left: .55em; text-shadow: 1.6px 2px 2px rgba(0,0,0,.45); }

.v-sign { position: absolute; left: 19mm; right: 18mm; bottom: 26mm; display: grid; grid-template-columns: 70mm 1fr 68mm; font-size: 12pt; }
.v-sign .col { position: relative; }
.v-sign .line { border-top: 1.1px solid #111; padding-top: 3.4mm; line-height: 1.6; }
.v-sign .sig { position: absolute; left: 2mm; bottom: calc(100% - 1mm); height: 28mm; max-width: 66mm; display: flex; align-items: flex-end; }
.v-sign .sig img { max-height: 28mm; max-width: 66mm; object-fit: contain; }
.v-sign b { font-weight: 700; }
.v-sign .who { margin-top: 1.6mm; }
.v-sign .role { line-height: 1.2; }

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
</div>

<div class="sheets">
<?php if (!$vouchers): ?>
  <p style="text-align:center;color:#666">沒有可列印的憑單。No vouchers to print.</p>
<?php endif; ?>
<?php foreach ($vouchers as $v): ?>
  <section class="voucher">
    <header class="v-head">
      <?php if ($logo): ?><div class="v-logo"><img src="<?= h(BASE_URL . '/' . $logo) ?>" alt=""></div><?php endif; ?>
      <div class="v-org">
        <?php foreach ($head as $i => $line): ?><div class="<?= $lineClass($i, $line) ?>"><?= h($line) ?></div><?php endforeach; ?>
      </div>
    </header>

    <div class="v-date"><span class="v-no">Voucher No. : <?= h(Voucher::number($batch, $v)) ?></span><span>Date : <?= h($date) ?></span></div>
    <?php if ($title !== ''): ?><div class="v-title"><?= h($title) ?></div><?php endif; ?>

    <div class="v-rows">
      <div class="lbl first">Description of Payment</div><div class="first">:</div><div class="val first"><?= h($batch['description']) ?></div>

      <div class="lbl">Payable to</div><div>:</div>
      <div class="val"><?php if ($v['name_zh'] !== null): ?><span class="v-zh"><?= h($v['name_zh']) ?></span><?php endif; ?>
        <?php if ($v['name_en'] !== null): ?><span class="v-en"><?= h($v['name_en']) ?></span><?php endif; ?></div>

      <div class="lbl">Amount</div><div>:</div>
      <div class="val v-amt"><span class="rm"><?= h(Voucher::rm(Voucher::amount($batch, $v))) ?></span><span><?= h(Voucher::wordsEn($batch, $v)) ?></span></div>

      <div class="lbl">Method of Payment</div><div>:</div><div class="val"><?= h($batch['method']) ?></div>

      <div class="lbl">Bank Account No.</div><div>:</div><div class="val"><?= h($v['bank_account'] ?? '') ?></div>
    </div>

    <?php if ($mottoEn !== '' || $mottoZh !== ''): ?>
      <div class="v-motto">
        <?php if ($mottoEn !== ''): ?><div class="en"><?= h($mottoEn) ?></div><?php endif; ?>
        <?php if ($mottoZh !== ''): ?><div class="zh"><?= h(preg_replace('/\s+/u', '　', $mottoZh)) ?></div><?php endif; ?>
      </div>
    <?php endif; ?>

    <footer class="v-sign">
      <div class="col">
        <?php if ($signUrl): ?><div class="sig"><img src="<?= h($signUrl) ?>" alt=""></div><?php endif; ?>
        <div class="line"><b>Approved by:</b>
          <div class="who"><b>Name : <?= h($approver) ?></b></div>
          <?php if ($apprTitle !== ''): ?><div class="role"><?= h($apprTitle) ?></div><?php endif; ?></div>
      </div>
      <div></div>
      <div class="col">
        <div class="line"><b>Received by :</b>
          <div class="who"><b>Name :</b></div></div>
      </div>
    </footer>
  </section>
<?php endforeach; ?>
</div>
</body>
</html>
