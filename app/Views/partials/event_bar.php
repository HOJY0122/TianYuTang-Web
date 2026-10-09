<?php
/**
 * Which event this admin page is showing, with a switcher.
 *
 * Expects: $event, $allEvents, $eventBarPath (the page to reload with
 * ?event=<id>, e.g. '/admin/registrations'). Pages keep every year's
 * records apart; this bar is how the committee moves between them.
 */
use App\Models\Event;
?>
<div class="panel eventbar">
  <div>
    <h2 style="margin:0 0 4px"><?= h($event['year']) ?> · <?= h($event['name']) ?></h2>
    <div class="help">
      <?= icon('calendar') ?> <?= h($event['start_date']) ?> → <?= h($event['end_date']) ?>
      · 功德席 Seat RM<?= number_format((float) $event['merit_table_price'], 0) ?>
      · <?= $event['is_active'] ? '<span class="badge ok">目前公開 Live</span>' : '<span class="badge pending">未公開 Not live</span>' ?>
      <?= $event['is_test'] ? '<span class="badge cancelled">測試 Test</span>' : '' ?>
    </div>
    <div class="help" style="margin-top:4px">
      <?php foreach ([Event::SECTION_RSVP => '報名 Registration', Event::SECTION_DONATION => '布施 Donation'] as $_ebSection => $_ebLabel):
          $_ebW = Event::windowStatus($event, $_ebSection); ?>
        <span class="eb-window"><?= h($_ebLabel) ?>:
          <?php if ($_ebW['open']): ?><span class="badge ok">開放中 Open</span><?= $_ebW['closes_at'] ? ' → ' . h(Event::formatDateTime($_ebW['closes_at'])) : '' ?>
          <?php elseif ($_ebW['reason'] === 'stopped'): ?><span class="badge cancelled">已停止 Stopped</span>
          <?php elseif ($_ebW['reason'] === 'not_yet'): ?><span class="badge pending">未開放 Not yet</span> <?= h(Event::formatDateTime($_ebW['opens_at'])) ?>
          <?php else: ?><span class="badge cancelled">已截止 Closed</span><?php endif; ?>
          <?php // Stop / resume by hand — e.g. enough people already registered on paper. ?>
          <form method="POST" action="<?= url('/admin/event/responses') ?>" class="eb-toggle"
                data-confirm="<?= !empty($event[$_ebSection . '_stopped'])
                    ? h('恢復接受線上' . mb_substr($_ebLabel, 0, 2) . '？（按設定的時間開放）&#10;Resume online ' . strtolower(substr($_ebLabel, 7)) . '? (follows the opening times again)')
                    : h('立即停止接受線上' . mb_substr($_ebLabel, 0, 2) . '？網站會顯示「已停止接受」。&#10;Stop online ' . strtolower(substr($_ebLabel, 7)) . ' now? The website will say it is no longer accepted.') ?>"
                <?= empty($event[$_ebSection . '_stopped']) ? 'data-danger' : '' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
            <input type="hidden" name="section" value="<?= $_ebSection ?>">
            <?php if (!empty($event[$_ebSection . '_stopped'])): ?>
              <input type="hidden" name="to" value="resume">
              <button type="submit" class="mini-btn eb-resume"><?= icon('play') ?> 恢復接受 <span class="en">Resume</span></button>
            <?php else: ?>
              <input type="hidden" name="to" value="stop">
              <button type="submit" class="mini-btn ghost eb-stop"><?= icon('stop') ?> 停止接受 <span class="en">Stop</span></button>
            <?php endif; ?>
          </form>
        </span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php if (count($allEvents) > 1): ?>
    <form method="GET" action="<?= url($eventBarPath) ?>" class="eventbar-actions" style="margin:0">
      <label class="sr-only" for="eventSwitch">切換活動 Switch event</label>
      <select id="eventSwitch" name="event" onchange="this.form.submit()">
        <?php foreach ($allEvents as $_ebE): ?>
          <option value="<?= (int) $_ebE['id'] ?>"<?= (int) $_ebE['id'] === (int) $event['id'] ? ' selected' : '' ?>>
            <?= h($_ebE['year']) ?> — <?= h($_ebE['name']) ?><?= $_ebE['is_test'] ? '（測試 Test）' : '' ?><?= $_ebE['is_active'] ? ' ✓' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  <?php endif; ?>
</div>
<?php if ($event['is_test']): ?>
  <div class="flash test"><?= icon('flask') ?> 這是測試活動，資料不會列入正式統計。This is a test event — its data is not counted in real figures.</div>
<?php endif; ?>
