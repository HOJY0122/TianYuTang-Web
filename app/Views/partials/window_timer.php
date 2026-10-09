<?php
/**
 * Live countdown for a submission window (js/window-timer.js).
 * Expects $window (Event::windowStatus) and $timerForm (form id, or '' on
 * the "not open yet" card). The server still decides every submission to
 * the second; this only keeps the screen honest:
 *   - before opening: counts down and opens the form by itself on the second
 *   - while open: counts down to the deadline (red in the last 5 minutes)
 *     and locks the Submit button once it has passed
 */
$opensTs  = !empty($window['opens_at'])  ? strtotime($window['opens_at'])  : null;
$closesTs = !empty($window['closes_at']) ? strtotime($window['closes_at']) : null;
if (in_array($window['reason'] ?? '', ['closed', 'stopped'], true) || (!$opensTs && !$closesTs)) {
    return;
}
?>
<div class="window-timer" role="timer" aria-live="off"
     data-now="<?= (int) round(microtime(true) * 1000) ?>"
     <?= $opensTs ? 'data-opens="' . $opensTs * 1000 . '"' : '' ?>
     <?= $closesTs ? 'data-closes="' . $closesTs * 1000 . '"' : '' ?>
     data-form="<?= h($timerForm ?? '') ?>" hidden>
  <span class="wt-icon"><?= icon('clock') ?></span>
  <span class="wt-label"></span>
  <strong class="wt-time"></strong>
</div>
