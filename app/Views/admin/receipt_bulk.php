<?php
/**
 * 整本上傳 Upload a receipt book — every photo of one paper book at once.
 * Each photo becomes a receipt marked “待核對 To check”; the AI (when on)
 * reads them one after another. Then a person goes through the book with
 * “Save & check next”, photo beside the form, as with a single scan.
 */
require BASE_PATH . '/app/Views/layouts/admin_header.php';
?>
<form class="panel form-panel" id="bulkForm" style="max-width:860px" action="<?= url('/admin/receipts/bulk-upload') ?>" method="POST" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <h2 style="margin-top:0"><?= icon('list') ?> 整本上傳 <span class="en">Upload a receipt book</span></h2>
  <ul class="scan-tips">
    <li><?= icon('camera') ?> 每張收據拍一張相片，平放、整張入鏡、光線充足。<span class="en">One photo per receipt — flat, whole receipt in frame, good light.</span></li>
    <li><?= icon('image') ?> 一次可選 50、100 張或更多。上傳中請不要關閉此頁；完成後可再上傳下一本。<span class="en">Choose 50, 100 or more at once. Keep this page open while they upload, then do the next book.</span></li>
    <li><?= icon('clipboard') ?> 每張都會標示「待核對」，之後逐張對照相片核對。<span class="en">Each one is marked “To check”, then checked against its photo one by one.</span></li>
  </ul>

  <div class="form-grid">
    <div class="book-field">
      <label for="bookNo">簿號 <span class="en">Book number</span> <span class="req">*</span></label>
      <input id="bookNo" name="book_no" value="<?= h($book) ?>" maxlength="30" list="bookList" autocomplete="off" required placeholder="例 e.g. 12">
      <datalist id="bookList"><?php foreach ($bookList as $b): ?><option value="<?= h($b) ?>"><?php endforeach; ?></datalist>
      <p class="help">寫在收據簿封面的號碼，例如 12 或 A03。<span class="en">The number on the book's cover, e.g. 12 or A03.</span></p>
    </div>
    <div>
      <label for="holder">負責人 <span class="en">Member in charge</span> <span class="req">*</span></label>
      <input id="holder" name="holder" maxlength="100" autocomplete="off" required placeholder="例 e.g. 陳大文">
      <label for="holderPhone">負責人電話 <span class="en">Their phone</span> <span class="help">（選填 optional）</span></label>
      <input id="holderPhone" name="holder_phone" maxlength="30" inputmode="tel" autocomplete="off" placeholder="012-345 6789">
      <p class="help">誰拿了這本收據簿。已登記的簿號會自動帶出。<span class="en">Who has this receipt book. Filled in for books already registered.</span></p>
    </div>
  </div>
  <div class="form-grid">
    <div>
      <label for="bookFiles">收據相片 <span class="en">Receipt photos</span> <span class="req">*</span></label>
      <input id="bookFiles" type="file" multiple required data-no-editor class="file-input"
             accept="image/jpeg,image/png,image/gif,image/webp">
      <p class="help" id="bookPicked" aria-live="polite"></p>
    </div>
  </div>

  <?php if ($aiReady): ?>
    <label class="check-inline" style="margin-top:6px"><input type="checkbox" id="useAi" checked>
      <?= icon('bot') ?> 上傳後由 AI 逐張讀取 <span class="en">Let the AI read each one after upload</span></label>
    <p class="help">AI 每張約 10–40 秒，同時讀 2 張。可以先開始核對已讀好的。<span class="en">About 10–40 s each, two at a time. You can start checking the ones already read.</span></p>
  <?php else: ?>
    <p class="flash info"><?= icon('bot') ?> AI 讀取尚未啟用：相片會先存好，之後手動輸入。<span class="en">AI reading is off — the photos are kept and typed in later.</span></p>
  <?php endif; ?>

  <div class="form-actions">
    <button class="primary" type="submit" id="bulkStart"><?= icon('upload') ?> 開始上傳 <span class="en">Start upload</span></button>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/receipts/new') ?>"><?= icon('camera') ?> 單張掃描 <span class="en">One at a time</span></a>
    <a class="mini-btn ghost btn-lg" href="<?= url('/admin/receipts') ?>">← 返回 Back</a>
  </div>

  <div class="bulk-panel" id="bulkProgress" hidden aria-live="polite"></div>
  <div class="form-actions" id="bulkAfter" hidden>
    <a class="primary" id="bulkCheck" href="#"><?= icon('clipboard') ?> 開始核對這本 <span class="en">Check this book</span></a>
    <button class="mini-btn ghost btn-lg" type="button" id="bulkMore"><?= icon('plus') ?> 上傳更多 <span class="en">Upload more</span></button>
  </div>
</form>

<?php if ($aiReady && $unread): ?>
  <div class="panel" style="max-width:860px" id="unreadPanel">
    <h2 style="margin-top:0"><?= icon('bot') ?> 簿 <?= h($book) ?>：<?= count($unread) ?> 張尚未由 AI 讀取
      <span class="en">Book <?= h($book) ?>: <?= count($unread) ?> not read by the AI yet</span></h2>
    <p class="help">之前上傳時未開啟 AI、或讀取失敗的收據。<span class="en">Uploaded with the AI off, or the reading failed.</span></p>
    <button class="primary" type="button" id="readUnread"><?= icon('bot') ?> AI 讀取這 <?= count($unread) ?> 張 <span class="en">Read these <?= count($unread) ?></span></button>
    <div class="bulk-panel" id="unreadProgress" hidden aria-live="polite"></div>
  </div>
<?php endif; ?>

<script src="<?= asset('js/bulk-upload.js') ?>"></script>
<script>
(function () {
  var BASE = <?= json_encode(BASE_URL) ?>, AI = <?= $aiReady ? 'true' : 'false' ?>;
  var form = document.getElementById('bulkForm'), input = document.getElementById('bookFiles'), book = document.getElementById('bookNo'),
      start = document.getElementById('bulkStart'), picked = document.getElementById('bookPicked'),
      box = document.getElementById('bulkProgress'), after = document.getElementById('bulkAfter'), useAi = document.getElementById('useAi');
  var csrf = form.querySelector('[name="csrf_token"]').value;
  // Who holds each registered book: chosen book → name and phone filled in.
  var REGISTER = <?= json_encode((object) array_map(static fn($b) => ['h' => $b['holder'], 'p' => $b['holder_phone']], $register), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  var holder = document.getElementById('holder'), phone = document.getElementById('holderPhone');
  function fillHolder() {
    var r = REGISTER[book.value.trim().toUpperCase()];
    if (r && r.h) holder.value = r.h;
    if (r && r.p) phone.value = r.p;
  }
  book.addEventListener('change', fillHolder);
  fillHolder();
  var CHECK = <?= json_encode(url('/admin/receipts') . '?') ?>;
  var esc = TYTBulk.esc, aiLimit = TYTBulk.limiter(2);

  function checkLink(b) { return CHECK + new URLSearchParams({ book: b, check: '1', sort: 'no', dir: 'asc' }).toString(); }

  // AI-read one receipt; updates its row. Resolves true/false, never rejects.
  function read(id, row, edit) {
    row.set('is-ai', 'AI 讀取中 Reading…');
    var fd = new FormData();
    fd.append('csrf_token', csrf);
    fd.append('id', id);
    return TYTBulk.send(BASE + '/admin/receipts/ai-read', fd).then(function (j) {
      var line = [j.receipt_no ? 'No. ' + esc(j.receipt_no) : '', j.name ? esc(j.name) : '', j.total].filter(Boolean).join(' · ');
      row.set('is-ok', line + (j.unsure ? ' <span class="badge check">再看 Check</span>' : '') +
        ' · <a href="' + edit + '" target="_blank">核對 Check</a>');
      return true;
    }, function (err) {
      row.set('is-bad', '已上傳，AI 未讀 Uploaded, not read: ' + esc(err.message) + ' · <a href="' + edit + '" target="_blank">輸入 Type in</a>');
      return false;
    });
  }

  input.addEventListener('change', function () {
    var n = input.files.length;
    picked.textContent = n ? '已選 ' + n + ' 張 · ' + n + ' selected' : '';
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var b = book.value.trim();
    var files = Array.prototype.slice.call(input.files);
    if (!b) { book.focus(); return; }
    if (!holder.value.trim()) { holder.focus(); return; }
    if (!files.length) { input.focus(); return; }
    // Phone photos are named in the order they were taken: keep the book's order.
    files.sort(function (x, y) { return x.name.localeCompare(y.name, undefined, { numeric: true }) || x.lastModified - y.lastModified; });
    var ai = AI && useAi && useAi.checked, tally = { up: 0, read: 0, unread: 0, bad: 0 }, uploaded = false, finished = false;
    start.disabled = input.disabled = book.readOnly = holder.readOnly = phone.readOnly = true;
    after.hidden = true;
    var p = TYTBulk.panel(box, files.length);
    TYTBulk.queue(files, function (file) {
      var row = p.row(file.name);
      row.set('is-busy', '縮小中 Preparing…');
      return TYTBulk.shrink(file, 2600).then(function (small) {
        row.set('is-busy', '上傳中 Uploading…');
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('book_no', b);
        fd.append('holder', holder.value.trim());
        fd.append('holder_phone', phone.value.trim());
        fd.append('photo', small, small.name);
        return TYTBulk.send(form.action, fd);
      }).then(function (j) {
        tally.up++;
        if (!ai) { row.set('is-ok', '已上傳 Uploaded · <a href="' + j.edit + '" target="_blank">輸入 Type in</a>'); p.step(true); return; }
        row.set('is-wait', '已上傳，等候 AI Uploaded, waiting for AI');
        // Not returned: the next upload starts while the AI reads this one.
        aiLimit(function () { return read(j.id, row, j.edit); }).then(function (ok) {
          if (ok) tally.read++; else tally.unread++;
          p.step(true); maybeDone();
        });
      }).catch(function (err) {
        tally.bad++; row.set('is-bad', esc(err.message)); p.step(false);
      });
    }, 2).then(function () { uploaded = true; maybeDone(); });

    function maybeDone() {
      if (!uploaded || finished || tally.up + tally.bad < files.length) return;
      if (ai && tally.read + tally.unread < tally.up) return;
      finished = true;
      p.finish();
      document.getElementById('bulkCheck').href = checkLink(b);
      after.hidden = false;
      start.disabled = input.disabled = book.readOnly = holder.readOnly = phone.readOnly = false;
      input.value = ''; picked.textContent = '';
      TYTDialog.alert(
        '簿 ' + b + '：上傳 ' + tally.up + ' 張' + (ai ? '，AI 已讀 ' + tally.read + ' 張' : '') +
        (tally.unread ? '，' + tally.unread + ' 張 AI 未讀' : '') + (tally.bad ? '，' + tally.bad + ' 張上傳失敗' : '') + '。\n' +
        'Book ' + b + ': ' + tally.up + ' uploaded' + (ai ? ', ' + tally.read + ' read by AI' : '') +
        (tally.unread ? ', ' + tally.unread + ' not read' : '') + (tally.bad ? ', ' + tally.bad + ' failed' : '') + '.',
        { type: tally.bad ? 'error' : 'info', title: '完成 Done' });
    }
  });

  document.getElementById('bulkMore').addEventListener('click', function () {
    after.hidden = true; box.hidden = true; box.classList.remove('is-done'); input.focus();
  });

  // Earlier uploads the AI has not read yet.
  var again = document.getElementById('readUnread');
  if (again) {
    var ids = <?= json_encode(array_map(static fn($r) => ['id' => (int) $r['id'], 'edit' => url('/admin/receipts/edit') . '?id=' . (int) $r['id']], $unread)) ?>;
    again.addEventListener('click', function () {
      again.disabled = true;
      var p = TYTBulk.panel(document.getElementById('unreadProgress'), ids.length), n = 0;
      TYTBulk.queue(ids, function (r) {
        var row = p.row('#' + r.id);
        return read(r.id, row, r.edit).then(function (ok) { if (ok) n++; p.step(ok); });
      }, 2).then(function () {
        p.finish();
        TYTDialog.alert('AI 已讀 ' + n + ' / ' + ids.length + ' 張。\nThe AI read ' + n + ' of ' + ids.length + '.', { type: 'info', title: '完成 Done' })
          .then(function () { location.href = checkLink(<?= json_encode($book) ?>); });
      });
    });
    <?php if ($resume): ?>again.scrollIntoView({ block: 'center' });<?php endif; ?>
  }
})();
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
