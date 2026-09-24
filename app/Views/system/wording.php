<?php
/**
 * 網站文字 Wording — every fixed text on the public site, grouped by page.
 * Each box shows the default as grey hint text; typing replaces it,
 * emptying the box brings the default back.
 */
use App\Core\Text;

require BASE_PATH . '/app/Views/layouts/admin_header.php';
$byGroup = [];
foreach (Text::ITEMS as $key => $item) {
    $byGroup[$item[0]][$key] = $item;
}
?>
<form method="POST" action="<?= url('/system/wording') ?>" class="wording">
  <?= csrf_field() ?>
  <div class="panel">
    <p class="help" style="margin:0">
      修改網站上的固定文字。灰色字是預設內容；在框內輸入即可取代，<strong>清空即恢復預設</strong>。
      像 <code>{n}</code>、<code>{max}</code> 這類記號會自動換成數字，請保留。<br>
      <span class="en">Change any fixed text on the public site. Grey text is the default — type to replace it,
      <strong>empty a box to restore the default</strong>. Keep markers like <code>{n}</code> and <code>{max}</code>; they are filled in automatically.</span>
    </p>
    <div class="wording-search">
      <input type="search" id="wordSearch" placeholder="🔍 搜尋文字 Search wording…" aria-label="搜尋 Search">
    </div>
  </div>

  <?php foreach (Text::GROUPS as $group => $groupLabel): if (empty($byGroup[$group])) continue; ?>
    <?php $edited = 0; foreach ($byGroup[$group] as $k => $_) { $edited += (($saved["txt.$k.zh"] ?? '') !== '' || ($saved["txt.$k.en"] ?? '') !== '') ? 1 : 0; } ?>
    <details class="panel wording-group"<?= $group === 'home' ? ' open' : '' ?>>
      <summary><strong><?= h($groupLabel) ?></strong>
        <span class="help"><?= count($byGroup[$group]) ?> 項 items<?= $edited ? " · ✏️ 已修改 {$edited} edited" : '' ?></span></summary>
      <?php foreach ($byGroup[$group] as $key => [, $zh, $en]): $long = !empty(Text::ITEMS[$key][3]); ?>
        <div class="wording-row" data-search="<?= h(mb_strtolower($key . ' ' . $zh . ' ' . $en . ' ' . ($saved["txt.$key.zh"] ?? '') . ' ' . ($saved["txt.$key.en"] ?? ''))) ?>">
          <div class="wording-key"><code><?= h($key) ?></code></div>
          <?php foreach (['zh' => '中文', 'en' => 'English'] as $lang => $langLabel): ?>
            <?php $val = (string) ($saved["txt.$key.$lang"] ?? ''); $def = $lang === 'zh' ? $zh : $en; $name = 'txt[' . h($key) . '][' . $lang . ']'; ?>
            <label class="wording-field<?= $val !== '' ? ' is-edited' : '' ?>">
              <span class="wording-lang"><?= $langLabel ?></span>
              <?php if ($long): ?>
                <textarea name="<?= $name ?>" rows="<?= substr_count($def, "\n") + 2 ?>" maxlength="1000" placeholder="<?= h($def) ?>"><?= h($val) ?></textarea>
              <?php else: ?>
                <input name="<?= $name ?>" maxlength="1000" value="<?= h($val) ?>" placeholder="<?= h($def) ?>">
              <?php endif; ?>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </details>
  <?php endforeach; ?>

  <div class="form-actions sticky-actions">
    <button class="primary" type="submit">💾 儲存文字 Save wording</button>
    <a class="mini-btn ghost" href="<?= url('/') ?>" target="_blank">👀 查看網站 View site</a>
  </div>
</form>
<script>
// Filter the list as you type, opening any group with a match.
(function () {
  var box = document.getElementById('wordSearch');
  box.addEventListener('input', function () {
    var q = box.value.trim().toLowerCase();
    document.querySelectorAll('.wording-group').forEach(function (g) {
      var hits = 0;
      g.querySelectorAll('.wording-row').forEach(function (r) {
        var show = !q || r.dataset.search.indexOf(q) !== -1;
        r.hidden = !show; if (show) hits++;
      });
      g.hidden = q && !hits;
      if (q && hits) g.open = true;
    });
  });
  document.querySelectorAll('.wording-field input, .wording-field textarea').forEach(function (el) {
    el.addEventListener('input', function () { el.parentNode.classList.toggle('is-edited', el.value.trim() !== ''); });
  });
})();
</script>
<?php require BASE_PATH . '/app/Views/layouts/admin_footer.php'; ?>
