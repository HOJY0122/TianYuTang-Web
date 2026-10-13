<?php
/**
 * 布施憑單 Donation vouchers, ready to print — one per A5 page, or two
 * per A4 page with a cut line. "Save as PDF" in the print window gives
 * the combined PDF. The letterhead (logo, name, address) comes from Site
 * settings → Printouts, like every other printout.
 */
use App\Models\Setting;
use App\Models\Voucher;

$lh      = (new Setting())->site();
$lhLogo  = $lh['pdf_show_logo'] === '1' ? ($lh['site_logo_path'] ?: $lh['site_favicon_path']) : null;
// A voucher always says who issued it, even if the printout name was left blank.
$lhName  = Setting::effective('pdf_name', $lh) ?: (string) $lh['site_name'];
$lhNameEn= Setting::effective('pdf_name_en', $lh);
$lhLines = array_filter([Setting::effective('pdf_line1', $lh), Setting::effective('pdf_line2', $lh),
                         Setting::effective('pdf_line3', $lh)], static fn($l) => $l !== '');
$date    = $batch['voucher_date'] ? date('j M Y', strtotime($batch['voucher_date'])) : '';
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>布施憑單｜<?= h($batch['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=LXGW+WenKai+TC:wght@400;700&family=Noto+Sans+TC:wght@400;700;800&family=Noto+Serif+TC:wght@700;900&display=swap" rel="stylesheet">
<style>
@page { size: <?= $paper === 'a4' ? 'A4 portrait' : 'A5 landscape' ?>; margin: 8mm 10mm; }
* { box-sizing: border-box; }
body { margin: 0; background: #e9e6df; color: #1a1a1a; font-family: "Noto Sans TC", "Microsoft JhengHei", sans-serif; font-size: 13px; }
.toolbar { position: sticky; top: 0; z-index: 2; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; padding: 12px 16px; background: #fff; border-bottom: 1px solid #ccc; }
.toolbar button, .toolbar a { border: 1px solid #999; background: #f4f4f4; color: #1a1a1a; border-radius: 8px; padding: 8px 14px; font: inherit; text-decoration: none; cursor: pointer; }
.toolbar .primary { background: #7d3b2f; border-color: #7d3b2f; color: #fff; font-weight: 700; }
.toolbar .hint { color: #555; font-size: 12px; }
.sheets { padding: 18px 12px 40px; }

/* One voucher. 190 × 128 mm fits A5 landscape and half of A4 portrait. */
.voucher { width: 190mm; height: 126mm; margin: 0 auto 14px; background: #fff; padding: 7mm 8mm 6mm; position: relative;
  border: 1.4px solid #5a3a2a; outline: 0.6px solid #5a3a2a; outline-offset: -2.6mm; display: flex; flex-direction: column; }
.v-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 6mm; padding-bottom: 3mm; border-bottom: 1.4px solid #5a3a2a; }
.v-org { display: flex; gap: 3mm; align-items: center; min-width: 0; }
.v-org img { width: 15mm; height: 15mm; object-fit: contain; }
.v-name { font-family: "Noto Serif TC", serif; font-weight: 900; font-size: 17px; line-height: 1.2; }
.v-name-en { font-size: 11px; font-weight: 700; letter-spacing: .02em; }
.v-line { font-size: 9.5px; color: #444; line-height: 1.35; }
.v-doc { text-align: right; flex: none; }
.v-title { font-family: "Noto Serif TC", serif; font-weight: 900; font-size: 18px; letter-spacing: .12em; color: #5a3a2a; }
.v-title-en { font-size: 10.5px; font-weight: 800; letter-spacing: .16em; }
.v-meta { margin-top: 2mm; font-size: 11.5px; line-height: 1.55; }
.v-meta b { font-family: ui-monospace, "Courier New", monospace; font-size: 13px; }

.v-body { flex: 1; display: grid; grid-template-columns: 34mm 1fr; align-content: start; row-gap: 0; margin-top: 2mm; }
.v-body dt { padding: 2.4mm 0 1.6mm; font-size: 10.5px; font-weight: 700; color: #555; line-height: 1.25; }
.v-body dt span { display: block; font-size: 9px; font-weight: 600; letter-spacing: .03em; }
.v-body dd { margin: 0; padding: 2mm 0 1.6mm; border-bottom: 0.8px dotted #8a7a6a; font-size: 13px; font-weight: 700; min-height: 8mm; overflow-wrap: anywhere; }
.v-zh { font-family: "LXGW WenKai TC", "Noto Serif TC", serif; font-size: 17px; font-weight: 700; display: block; line-height: 1.3; }
.v-en { display: block; font-size: 12.5px; line-height: 1.35; }
.v-amount { display: flex; gap: 5mm; align-items: baseline; flex-wrap: wrap; }
.v-rm { font-size: 16px; font-weight: 800; border: 1.2px solid #5a3a2a; padding: 0.6mm 3mm; border-radius: 2px; white-space: nowrap; }
.v-words { font-size: 12px; }
.v-words-zh { font-family: "LXGW WenKai TC", serif; font-size: 13.5px; display: block; }
.v-blank { color: #aaa; font-weight: 400; }

.v-sign { display: grid; grid-template-columns: repeat(3, 1fr); gap: 7mm; margin-top: 3mm; font-size: 10px; text-align: center; }
.v-sign div { border-top: 0.9px solid #333; padding-top: 1.2mm; line-height: 1.3; }
.v-sign small { display: block; color: #666; font-size: 8.5px; }

.cut { width: 190mm; margin: 0 auto 14px; border-top: 1px dashed #999; position: relative; height: 0; }
.cut::after { content: "✂"; position: absolute; left: -2mm; top: -9px; font-size: 13px; color: #777; background: #fff; }

@media print {
  body { background: #fff; }
  .no-print { display: none !important; }
  .sheets { padding: 0; }
  .voucher { margin: 0 auto; break-inside: avoid; }
  .page { break-after: page; }
  .page:last-child { break-after: auto; }
  .cut { margin: 6mm auto; }
  .cut::after { background: #fff; }
}
@media screen and (max-width: 760px) {
  .sheets { overflow-x: auto; }
}
</style>
</head>
<body>

<div class="toolbar no-print">
  <button class="primary" onclick="window.print()">列印 / 存成 PDF　Print / Save as PDF</button>
  <a href="<?= url('/admin/vouchers/batch') ?>?id=<?= (int) $batch['id'] ?>">← 返回 Back</a>
  <?php foreach (Voucher::PAPERS as $pk => [$pzh, $pen]): ?>
    <?php if ($pk !== $paper): ?>
      <a href="?<?= h(http_build_query(['batch' => (int) $batch['id'], 'ids' => $_GET['ids'] ?? null, 'paper' => $pk])) ?>"><?= h($pzh) ?> <?= h($pen) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
  <span class="hint"><?= count($vouchers) ?> 張 vouchers · 在列印視窗選擇「另存為 PDF」，紙張選 <?= $paper === 'a4' ? 'A4' : 'A5（橫向）' ?>。Choose “Save as PDF”.</span>
</div>

<div class="sheets">
<?php if (!$vouchers): ?>
  <p style="text-align:center;color:#666">沒有可列印的憑單。No vouchers to print.</p>
<?php endif; ?>
<?php
$perPage = $paper === 'a4' ? 2 : 1;
foreach (array_chunk($vouchers, $perPage) as $page): ?>
  <div class="page">
  <?php foreach ($page as $i => $v): ?>
    <?php if ($i > 0): ?><div class="cut" aria-hidden="true"></div><?php endif; ?>
    <section class="voucher">
      <header class="v-head">
        <div class="v-org">
          <?php if ($lhLogo): ?><img src="<?= h(BASE_URL . '/' . $lhLogo) ?>" alt=""><?php endif; ?>
          <div>
            <div class="v-name"><?= h($lhName) ?></div>
            <?php if ($lhNameEn !== ''): ?><div class="v-name-en"><?= h($lhNameEn) ?></div><?php endif; ?>
            <?php foreach ($lhLines as $line): ?><div class="v-line"><?= h($line) ?></div><?php endforeach; ?>
          </div>
        </div>
        <div class="v-doc">
          <div class="v-title">布施憑單</div>
          <div class="v-title-en">DONATION VOUCHER</div>
          <div class="v-meta">No. <b><?= h(Voucher::number($batch, $v)) ?></b><br>
            日期 Date : <strong><?= h($date) ?></strong></div>
        </div>
      </header>

      <dl class="v-body">
        <dt>項目<span>Description</span></dt>
        <dd><?= h($batch['description']) ?></dd>

        <dt>受款機構<span>Pay to</span></dt>
        <dd><?php if ($v['name_zh'] !== null): ?><span class="v-zh"><?= h($v['name_zh']) ?></span><?php endif; ?>
            <?php if ($v['name_en'] !== null): ?><span class="v-en"><?= h($v['name_en']) ?></span><?php endif; ?></dd>

        <dt>金額<span>Amount</span></dt>
        <dd class="v-amount">
          <span class="v-rm"><?= h(rm(Voucher::amount($batch, $v))) ?></span>
          <span class="v-words"><?= h(Voucher::wordsEn($batch, $v)) ?>
            <span class="v-words-zh">馬幣<?= h(Voucher::wordsZh($batch, $v)) ?></span></span>
        </dd>

        <dt>付款方式<span>Payment method</span></dt>
        <dd><?= h($batch['method']) ?></dd>

        <dt>銀行戶口<span>Bank account</span></dt>
        <dd><?= $v['bank_account'] !== null ? h($v['bank_account']) : '<span class="v-blank">&nbsp;</span>' ?></dd>
      </dl>

      <footer class="v-sign">
        <div>經手人<small>Prepared by</small></div>
        <div>批准人<small>Approved by</small></div>
        <div>收款人簽名及蓋章<small>Received by (signature &amp; chop)</small></div>
      </footer>
    </section>
  <?php endforeach; ?>
  </div>
<?php endforeach; ?>
</div>
</body>
</html>
