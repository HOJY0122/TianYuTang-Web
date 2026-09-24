  </div><!-- /.content -->
</div><!-- /.main -->
<script>
// Phone / tablet menu: the sidebar slides in over the page.
(function () {
  var side = document.getElementById('sideMenu');
  var btn  = document.getElementById('menuBtn');
  var back = document.getElementById('sideBackdrop');
  function set(open) {
    document.body.classList.toggle('menu-open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  btn.addEventListener('click', function () { set(!document.body.classList.contains('menu-open')); });
  back.addEventListener('click', function () { set(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
})();

// Chart tooltips: any element with data-tip, on hover AND keyboard focus.
// textContent (never innerHTML) — the text can include typed-in names.
(function () {
  var tip = document.createElement('div');
  tip.className = 'tip hidden';
  document.body.appendChild(tip);
  function show(el, x, y) {
    tip.textContent = el.getAttribute('data-tip');
    tip.classList.remove('hidden');
    var w = tip.offsetWidth;
    tip.style.left = Math.max(8, Math.min(window.innerWidth - w - 8, x - w / 2)) + 'px';
    tip.style.top = Math.max(8, y - tip.offsetHeight - 12) + 'px';
  }
  function hide() { tip.classList.add('hidden'); }
  document.querySelectorAll('[data-tip]').forEach(function (el) {
    el.addEventListener('pointermove', function (e) { show(el, e.clientX, e.clientY); });
    el.addEventListener('pointerleave', hide);
    el.addEventListener('focus', function () { var r = el.getBoundingClientRect(); show(el, r.left + r.width / 2, r.top); });
    el.addEventListener('blur', hide);
  });
})();

// Any password box with a 👁 button next to it can be shown / hidden.
document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var input = document.getElementById(btn.dataset.togglePassword);
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    btn.textContent = show ? '🙈' : '👁';
    btn.title = show ? '隱藏密碼 Hide password' : '顯示密碼 Show password';
  });
});
</script>
<script src="<?= asset('js/dialog.js') ?>"></script>
<script src="<?= asset('js/image-editor.js') ?>"></script>
</body>
</html>
