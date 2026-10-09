<?php
/**
 * 控制台 Control panel — running the events: which one the website shows,
 * stop / resume online responses, edit or add events, test copies, and
 * every event at a glance. (The dashboard is only for the numbers.)
 */
use App\Models\Event;

require BASE_PATH . '/app/Views/layouts/admin_header.php';
require BASE_PATH . '/app/Views/partials/event_bar.php';
$eid = (int) $event['id'];
$winBadge = static function (array $e, string $section): string {
    $w = Event::windowStatus($e, $section);
    if ($w['open']) {
        return '<span class="badge ok">開放中 Open</span>';
    }
    return match ($w['reason']) {
        'stopped' => '<span class="badge cancelled">已停止 Stopped</span>',
        'not_yet' => '<span class="badge pending">未開放 Not yet</span>',
        default   => '<span class="badge cancelled">已截止 Closed</span>',
    };
};
?>
<!-- ---------- Actions on the chosen event ---------- -->
<div class="panel">
  <h2 style="margin-top:0"><?= icon('calendar') ?> 這個活動 <span class="en">This event</span></h2>
  <div class="eventbar-actions">
    <a class="mini-btn btn-lg" href="<?= url('/admin/event/edit') ?>?id=<?= $eid ?>"><?= icon('pencil') ?> 編輯活動 Edit event</a>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/event/new') ?>">＋ 新增活動 New event</a>
    <?php if (!$event['is_active']): ?>
      <form method="POST" action="<?= url('/admin/event/activate') ?>" style="margin:0"
            data-confirm="確定將此活動設為公開？網站首頁會立即切換。&#10;Make this event live on the website now?">
        <?= csrf_field() ?><input type="hidden" name="event_id" value="<?= $eid ?>">
        <button class="mini-btn btn-lg" type="submit"><?= icon('globe') ?> 設為公開 Make live</button>
      </form>
    <?php endif; ?>
    <?php if ($event['is_test']): ?>
      <form method="POST" action="<?= url('/admin/event/test-delete') ?>" style="margin:0"
            data-confirm="刪除此測試活動及其所有測試資料？此操作無法復原。&#10;Delete this test event and all its test data? This cannot be undone." data-danger>
        <?= csrf_field() ?><input type="hidden" name="event_id" value="<?= $eid ?>">
        <button class="mini-btn danger btn-lg" type="submit"><?= icon('trash') ?> 刪除測試資料 Delete test data</button>
      </form>
    <?php else: ?>
      <form method="POST" action="<?= url('/admin/event/test-copy') ?>" style="margin:0"
            data-confirm="建立測試副本？測試資料不會計入正式統計。&#10;Create a test copy? Test data is not counted.">
        <?= csrf_field() ?><input type="hidden" name="event_id" value="<?= $eid ?>">
        <button class="mini-btn ghost btn-lg" type="submit"><?= icon('flask') ?> 建立測試副本 Test copy</button>
      </form>
    <?php endif; ?>
  </div>
</div>


<!-- ---------- Every event ---------- -->
<div class="panel" id="liveEvents" data-live="events">
  <h2 style="margin-top:0"><?= icon('list') ?> 所有活動 <span class="en">All events</span></h2>
  <div class="table-scroll">
  <table class="records">
    <thead><tr><th>活動 Event</th><th>日期 Dates</th><th>狀態 Status</th><th>報名 Registration</th><th>布施 Donation</th><th>操作 Actions</th></tr></thead>
    <tbody>
    <?php foreach ($allEvents as $e): ?>
      <tr<?= (int) $e['id'] === $eid ? ' class="is-current"' : '' ?>>
        <td data-label="活動 Event"><strong><?= h($e['year']) ?> · <?= h($e['name']) ?></strong></td>
        <td data-label="日期 Dates" class="help"><?= h($e['start_date']) ?> → <?= h($e['end_date']) ?></td>
        <td data-label="狀態 Status"><?= $e['is_active'] ? '<span class="badge ok">目前公開 Live</span>' : '<span class="badge pending">未公開 Not live</span>' ?>
          <?= $e['is_test'] ? '<span class="badge cancelled">測試 Test</span>' : '' ?></td>
        <td data-label="報名 Registration"><?= $winBadge($e, Event::SECTION_RSVP) ?></td>
        <td data-label="布施 Donation"><?= $winBadge($e, Event::SECTION_DONATION) ?></td>
        <td data-label="操作 Actions">
          <div class="actions-cell" style="flex-wrap:wrap">
            <?php if ((int) $e['id'] !== $eid): ?>
              <a class="mini-btn ghost" href="<?= url('/admin/control') ?>?event=<?= (int) $e['id'] ?>"><?= icon('settings') ?> 管理 Manage</a>
            <?php else: ?><span class="help">正在管理 Managing</span><?php endif; ?>
            <?php if (!$e['is_active']): ?>
              <form method="POST" action="<?= url('/admin/event/activate') ?>" style="margin:0"
                    data-confirm="確定將此活動設為公開？網站首頁會立即切換。&#10;Make this event live on the website now?">
                <?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $e['id'] ?>">
                <button class="mini-btn ghost" type="submit"><?= icon('globe') ?> 設為公開 Make live</button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
