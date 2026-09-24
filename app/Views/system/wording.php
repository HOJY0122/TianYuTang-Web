<?php
/**
 * 網站文字 Wording — every fixed text on the public site, one page at a
 * time, with that page shown live beside the boxes.
 *
 * What you type appears in the preview straight away (nothing is saved
 * until 儲存 Save). Each box holds the text the site shows NOW; empty a
 * box to show nothing there, or press ↺ to put the default back.
 */
use App\Core\Text;

require BASE_PATH . '/app/Views/layouts/admin_header.php';

$previewUrl = [
    'nav'      => url('/'),
    'home'     => url('/'),
    'register' => url('/register'),
    'donate'   => url('/donate'),
    'success'  => url('/system/wording/preview') . '?kind=rsvp',
    'gallery'  => url('/gallery'),
    'help'     => url('/') . '#help',
    'pdf'      => url('/admin/print/attendees'),
];
$items = array_filter(Text::ITEMS, static fn($i) => $i[0] === $group);
$current = static function (string $key, string $lang) use ($saved): string {
    $k = "txt.{$key}.{$lang}";
    return array_key_exists($k, $saved) && $saved[$k] !== null ? (string) $saved[$k] : Text::fallback($key, $lang);
};
?>
<nav class="word-tabs" aria-label="頁面 Pages">
  <?php foreach (Text::GROUPS as $g => $label): ?>
    <a href="<?= url('/system/wording') ?>?group=<?= $g ?>" class="word-tab<?= $g === $group ? ' is-active' : '' ?>"
       <?= $g === $group ? 'aria-current="page"' : '' ?> data-leave><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>

<div class="word-layout">
  <form method="POST" action="<?= url('/system/wording') ?>" class="panel wording" id="wordForm" data-trad-check>
    <?= csrf_field() ?>
    <input type="hidden" name="group" value="<?= h($group) ?>">
    <p class="help" style="margin-top:0">
      ✍️ 框內是網站<strong>現在</strong>顯示的文字，改動會即時出現在右邊預覽（按儲存才生效）。<strong>留空＝不顯示</strong>；按 ↺ 放回預設文字。
      <code>{n}</code>、<code>{max}</code> 會自動換成數字。<br>
      <span class="en">Each box holds the text the site shows now. Changes appear in the preview at once and go live when you Save.
      <strong>Empty = show nothing</strong>; ↺ puts the default back. Keep <code>{n}</code> / <code>{max}</code> — they become numbers.</span>
    </p>

    <?php foreach ($items as $key => $item): $long = !empty($item[3]); ?>
      <fieldset class="word-item" data-key="<?= h($key) ?>">
        <legend><code><?= h($key) ?></code></legend>
        <?php foreach (['zh' => '中文', 'en' => 'English'] as $lang => $langLabel): ?>
          <?php $val = $current($key, $lang); $def = Text::fallback($key, $lang); $id = 'w_' . str_replace('.', '_', $key) . '_' . $lang; ?>
          <div class="word-field<?= $val !== $def ? ' is-edited' : '' ?>">
            <label for="<?= $id ?>"><?= $langLabel ?>
              <?php if ($val !== $def): ?><span class="word-badge">✏️ 已修改 Edited</span><?php endif; ?></label>
            <div class="input-reset">
              <?php if ($long): ?>
                <textarea id="<?= $id ?>" name="txt[<?= h($key) ?>][<?= $lang ?>]" rows="<?= max(2, substr_count($val, "\n") + 2) ?>" maxlength="1000"
                          data-live data-saved="<?= h($val) ?>" data-default="<?= h($def) ?>"><?= h($val) ?></textarea>
              <?php else: ?>
                <input id="<?= $id ?>" name="txt[<?= h($key) ?>][<?= $lang ?>]" maxlength="1000" value="<?= h($val) ?>"
                       data-live data-saved="<?= h($val) ?>" data-default="<?= h($def) ?>">
              <?php endif; ?>
              <button type="button" class="mini-btn ghost" data-default-for="<?= $id ?>" title="預設 Default: <?= h($def) ?>">↺</button>
            </div>
            <p class="word-miss help" hidden>ℹ️ 這段文字目前不在預覽頁面上（例如要先設定 Waze 連結、活動已截止才顯示，或已留空）。
              <span class="en">Not on the preview page right now (shown only in some situations, or currently empty).</span></p>
          </div>
        <?php endforeach; ?>
      </fieldset>
    <?php endforeach; ?>

    <div class="form-actions sticky-actions">
      <button class="primary" type="submit">💾 儲存 Save</button>
      <span class="help" id="dirtyNote" hidden>● 有未儲存的修改 Unsaved changes</span>
    </div>
  </form>

  <aside class="panel word-preview">
    <div class="word-preview-bar">
      <strong>👀 即時預覽 <span class="en">Live preview</span></strong>
      <?php if ($group === 'success'): ?>
        <span class="seg-mini">
          <button type="button" data-preview="<?= url('/system/wording/preview') ?>?kind=rsvp" class="is-on">報名 RSVP</button>
          <button type="button" data-preview="<?= url('/system/wording/preview') ?>?kind=don">布施 Donation</button>
        </span>
      <?php endif; ?>
      <span class="seg-mini">
        <button type="button" data-width="100%" class="is-on" title="電腦 Computer">🖥️</button>
        <button type="button" data-width="390px" title="手機 Phone">📱</button>
      </span>
      <a class="mini-btn ghost" href="<?= h($previewUrl[$group]) ?>" target="_blank" id="openPage">↗</a>
    </div>
    <div class="word-frame-wrap"><iframe id="preview" src="<?= h($previewUrl[$group]) ?>" title="預覽 Preview"></iframe></div>
    <p class="help" style="margin:.5rem 0 0">點左邊的框，預覽會捲到那段文字並以黃色標出。Click a box and the preview scrolls to that text and marks it in yellow.</p>
  </aside>
</div>

<script>
(function () {
  var frame = document.getElementById('preview');
  var fields = Array.prototype.slice.call(document.querySelectorAll('[data-live]'));
  var dirty = false;
  // What each box's text currently looks like INSIDE the preview.
  fields.forEach(function (f) { f._shown = f.dataset.saved; });

  function esc(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
  // "起 · {n} 天" → a pattern that also matches "起 · 3 天", remembering the 3.
  function pattern(tpl) { return new RegExp(esc(tpl).replace(/\\\{\w+\\\}/g, '(.+?)'), 'g'); }
  function fill(tpl, caps) { var i = 0; return tpl.replace(/\{\w+\}/g, function (m) { return caps[i++] !== undefined ? caps[i - 1] : m; }); }

  function textNodes(doc) {
    var out = [], w = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT);
    while (w.nextNode()) out.push(w.currentNode);
    return out;
  }
  function doc() { try { return frame.contentDocument; } catch (e) { return null; } }

  /** Change every place the old wording appears in the preview to the new wording. */
  function swap(from, to) {
    var d = doc();
    if (!d || !d.body || from === '' || from === to) return;
    var re = pattern(from);
    textNodes(d).forEach(function (n) {
      if (!re.test(n.nodeValue)) return;
      re.lastIndex = 0;
      n.nodeValue = n.nodeValue.replace(re, function () {
        return fill(to, Array.prototype.slice.call(arguments, 1, -2));
      });
    });
  }

  function highlight(text, f) {
    var d = doc();
    if (!d || !d.body) return;
    var note = f.closest('.word-field').querySelector('.word-miss');
    d.querySelectorAll('.tyt-hl').forEach(function (e) { e.classList.remove('tyt-hl'); });
    if (!text) return;
    var re = pattern(text);
    var hit = textNodes(d).filter(function (n) { re.lastIndex = 0; return re.test(n.nodeValue); })[0];
    if (hit && hit.parentElement) {
      var el = hit.parentElement;
      // The help panel starts closed: open it when its text is chosen.
      var panel = el.closest('#helpPanel');
      if (panel && panel.hidden) { var b = d.getElementById('helpBtn'); if (b) b.click(); }
      el.classList.add('tyt-hl');
      el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
    note.hidden = !!hit;
  }

  function markDirty() {
    dirty = fields.some(function (f) { return f.value !== f.dataset.saved; });
    document.getElementById('dirtyNote').hidden = !dirty;
  }

  // An emptied box leaves an invisible marker in the preview, so typing
  // into it again knows where the words belong.
  function marker(f) { return new Array(fields.indexOf(f) + 2).join('\u200B') + '\u2063'; }
  function shownAs(f) { return f.value === '' ? marker(f) : f.value; }

  fields.forEach(function (f) {
    f.addEventListener('input', function () {
      var now = shownAs(f);
      swap(f._shown, now);
      f._shown = now;
      f.closest('.word-field').classList.toggle('is-edited', f.value !== f.dataset.default);
      markDirty();
    });
    f.addEventListener('focus', function () { highlight(f._shown, f); });
  });

  document.querySelectorAll('[data-default-for]').forEach(function (b) {
    b.addEventListener('click', function () {
      var f = document.getElementById(b.dataset.defaultFor);
      f.value = f.dataset.default;
      f.dispatchEvent(new Event('input'));
      f.focus();
    });
  });

  // Each time the preview (re)loads, show the unsaved changes in it again.
  frame.addEventListener('load', function () {
    var d = doc();
    if (!d) return;
    var st = d.createElement('style');
    st.textContent = '.tyt-hl{outline:4px solid #f5c400!important;outline-offset:3px;background:#fff6b3!important;border-radius:4px}';
    d.head.appendChild(st);
    fields.forEach(function (f) {
      f._shown = f.dataset.saved;
      if (f.value !== f.dataset.saved) { var now = shownAs(f); swap(f.dataset.saved, now); f._shown = now; }
    });
    if (location.search.indexOf('group=help') !== -1 || frame.src.indexOf('#help') !== -1) {
      var b = d.getElementById('helpBtn'); if (b) b.click();
    }
  });

  document.querySelectorAll('[data-width]').forEach(function (b) {
    b.addEventListener('click', function () {
      frame.style.width = b.dataset.width;
      b.parentNode.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
    });
  });
  document.querySelectorAll('[data-preview]').forEach(function (b) {
    b.addEventListener('click', function () {
      frame.src = b.dataset.preview;
      document.getElementById('openPage').href = b.dataset.preview;
      b.parentNode.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
    });
  });

  // Moving to another tab with unsaved changes asks first.
  document.getElementById('wordForm').addEventListener('submit', function () { dirty = false; });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
