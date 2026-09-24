<?php
/**
 * Small server-drawn SVG charts for the admin dashboard.
 *
 * No chart library: the numbers are known when the page is built, so
 * PHP draws the SVG directly — nothing to download, works offline, and
 * prints. Every chart also has a table view, and every mark carries a
 * tooltip (data-tip) shown on hover AND keyboard focus by the script in
 * layouts/admin_footer.php — tooltips add detail, they never hide it.
 *
 * Colours are the site's own red and gold, validated as a pair for
 * colour-blind separation and contrast on the card surface.
 */

if (!function_exists('chart_columns')) {

    /** Round a maximum up to a clean axis value (1, 2, 5 × 10ⁿ). */
    function chart_nice_max(float $max): float
    {
        if ($max <= 0) {
            return 4;
        }
        $step  = $max / 4;
        $pow   = 10 ** floor(log10($step));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($m * $pow >= $step) {
                return $m * $pow * 4;
            }
        }
        return $max;
    }

    /**
     * Daily column chart for one series.
     *
     * @param array $o [
     *   'title' => '每日報名人數', 'subtitle' => 'People registered per day',
     *   'rows'  => [['day' => '2026-09-20', 'value' => 3], ...],
     *   'days'  => 14,            // window ending today; missing days = 0
     *   'money' => false,         // format values as RM
     *   'unit'  => '人 people',
     * ]
     */
    function chart_columns(array $o): void
    {
        $days  = (int) ($o['days'] ?? 14);
        $money = !empty($o['money']);
        $fmt   = static fn(float $v): string => $money ? 'RM ' . number_format($v, $v >= 1000 ? 0 : 2) : number_format($v);

        // A continuous window, so a quiet day shows as a gap rather than
        // silently disappearing and making the chart look busier than it was.
        $byDay = [];
        foreach ($o['rows'] as $r) {
            $byDay[$r['day']] = (float) $r['value'];
        }
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} day"));
            $series[] = ['day' => $d, 'value' => $byDay[$d] ?? 0.0];
        }

        $W = 640; $H = 230; $padL = 56; $padR = 12; $padT = 26; $padB = 30;
        $plotW = $W - $padL - $padR; $plotH = $H - $padT - $padB;
        $max   = max(array_column($series, 'value'));
        $top   = chart_nice_max($max);
        $slot  = $plotW / count($series);
        $barW  = min(24, $slot * 0.62);
        $y     = static fn(float $v): float => $padT + $plotH - ($v / $top) * $plotH;
        $maxIdx = $max > 0 ? array_search($max, array_column($series, 'value'), true) : null;
        $total = array_sum(array_column($series, 'value'));
        $id    = 'c' . substr(md5($o['title']), 0, 6);
        ?>
        <figure class="chart" aria-labelledby="<?= $id ?>-t">
          <figcaption>
            <strong id="<?= $id ?>-t"><?= h($o['title']) ?></strong>
            <span class="en"><?= h($o['subtitle'] ?? '') ?> · <?= $days ?> 天 days · 合計 total <?= h($fmt($total)) ?></span>
          </figcaption>
          <svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="<?= h($o['title']) ?>">
            <?php for ($t = 0; $t <= 4; $t++): $v = $top * $t / 4; $ty = $y($v); ?>
              <line x1="<?= $padL ?>" x2="<?= $W - $padR ?>" y1="<?= $ty ?>" y2="<?= $ty ?>" class="grid"/>
              <text x="<?= $padL - 8 ?>" y="<?= $ty + 4 ?>" class="tick" text-anchor="end"><?= h($money ? ($v >= 1000 ? number_format($v / 1000, fmod($v, 1000) ? 1 : 0) . 'k' : number_format($v)) : number_format($v)) ?></text>
            <?php endfor; ?>
            <?php foreach ($series as $i => $p):
                $cx = $padL + $slot * $i + $slot / 2;
                $by = $y($p['value']); $bh = $padT + $plotH - $by;
                $label = date('n/j', strtotime($p['day']));
                $tip = $label . '　' . $fmt($p['value']) . ($money ? '' : ' ' . ($o['unit'] ?? ''));
            ?>
              <?php if ($bh > 0.5): $r = min(4, $bh, $barW / 2); $x0 = $cx - $barW / 2; ?>
                <path class="bar" d="M<?= $x0 ?>,<?= $by + $bh ?> V<?= $by + $r ?> Q<?= $x0 ?>,<?= $by ?> <?= $x0 + $r ?>,<?= $by ?> H<?= $x0 + $barW - $r ?> Q<?= $x0 + $barW ?>,<?= $by ?> <?= $x0 + $barW ?>,<?= $by + $r ?> V<?= $by + $bh ?> Z"/>
              <?php endif; ?>
              <?php if ($i === $maxIdx): ?>
                <text x="<?= $cx ?>" y="<?= $by - 7 ?>" class="val" text-anchor="middle"><?= h($fmt($p['value'])) ?></text>
              <?php endif; ?>
              <?php if ($i % 2 === (count($series) - 1) % 2): ?>
                <text x="<?= $cx ?>" y="<?= $H - 10 ?>" class="tick" text-anchor="middle"><?= h($label) ?></text>
              <?php endif; ?>
              <!-- Hit area: the whole column slot, far bigger than the bar. -->
              <rect class="hit" x="<?= $cx - $slot / 2 ?>" y="<?= $padT ?>" width="<?= $slot ?>" height="<?= $plotH ?>"
                    tabindex="0" data-tip="<?= h($tip) ?>"><title><?= h($tip) ?></title></rect>
            <?php endforeach; ?>
            <line x1="<?= $padL ?>" x2="<?= $W - $padR ?>" y1="<?= $padT + $plotH ?>" y2="<?= $padT + $plotH ?>" class="axis"/>
          </svg>
          <?php if ($max <= 0): ?><p class="chart-empty">這段期間尚無資料 No data in this period yet</p><?php endif; ?>
          <details class="chart-table">
            <summary>表格 Table view</summary>
            <table><thead><tr><th>日期 Date</th><th class="num"><?= $money ? '金額 Amount' : '數量 Count' ?></th></tr></thead><tbody>
              <?php foreach (array_reverse($series) as $p): ?>
                <tr><td><?= h($p['day']) ?></td><td class="num"><?= h($fmt($p['value'])) ?></td></tr>
              <?php endforeach; ?>
            </tbody></table>
          </details>
        </figure>
        <?php
    }

    /**
     * Part-to-whole bar for two parts (e.g. seats vs freewill), with a
     * legend carrying each value and share. 2px surface gap between parts.
     *
     * @param array $o ['title' =>, 'subtitle' =>, 'money' => bool,
     *                  'parts' => [['label' => '…', 'value' => 12.5], ['label' => …]]]
     */
    function chart_split(array $o): void
    {
        $money = !empty($o['money']);
        $fmt   = static fn(float $v): string => $money ? rm($v) : number_format($v);
        $total = array_sum(array_column($o['parts'], 'value'));
        $cls   = ['s1', 's2'];
        ?>
        <figure class="chart split">
          <figcaption><strong><?= h($o['title']) ?></strong><span class="en"><?= h($o['subtitle'] ?? '') ?></span></figcaption>
          <?php if ($total <= 0): ?>
            <p class="chart-empty">尚無資料 No data yet</p>
          <?php else: ?>
            <div class="split-bar" role="img" aria-label="<?= h($o['title']) ?>">
              <?php foreach ($o['parts'] as $i => $p): if ($p['value'] <= 0) continue;
                  $pct = $p['value'] / $total * 100; $tip = $p['label'] . '　' . $fmt($p['value']) . '（' . round($pct) . '%）'; ?>
                <span class="<?= $cls[$i] ?>" style="flex:<?= round($pct, 3) ?>" tabindex="0" data-tip="<?= h($tip) ?>" title="<?= h($tip) ?>"></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <ul class="legend">
            <?php foreach ($o['parts'] as $i => $p): ?>
              <li><i class="<?= $cls[$i] ?>"></i><span><?= h($p['label']) ?></span>
                <strong><?= h($fmt($p['value'])) ?></strong>
                <small><?= $total > 0 ? round($p['value'] / $total * 100) : 0 ?>%</small></li>
            <?php endforeach; ?>
          </ul>
        </figure>
        <?php
    }
}
