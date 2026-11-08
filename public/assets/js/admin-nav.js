/*
 * Admin menu helpers (layouts/admin_header.php):
 *
 *  ① 收起選單 — the sidebar folds to a narrow icon rail (computer only);
 *    remembered in this browser. Shortcut: [ . On the rail, pointing at
 *    an icon shows its name.
 *  ② Sections (活動管理, 活動當天 …) fold up with a tap on their heading;
 *    remembered. The section of the current page always stays open.
 *  ③ 快速前往 Go to a page — Ctrl K (or /): type a few letters of a page
 *    (Chinese or English), ↑ ↓ to choose, Enter to go.
 */
(function () {
  'use strict';
  var root = document.documentElement;
  var side = document.getElementById('sideMenu');
  if (!side) return;
  function store(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function typing(e) { var t = e.target; return t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName)); }

  // ---------- ① rail ----------
  var railBtn = document.getElementById('sideRailBtn');
  function syncRail() {
    var on = root.classList.contains('side-rail');
    railBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
    railBtn.dataset.label = on ? '展開選單 Expand menu · [' : '收起選單 Collapse menu · [';
    railBtn.setAttribute('aria-label', on ? '展開選單 Expand menu' : '收起選單 Collapse menu');
    railBtn.title = window.innerWidth > 1000 && !on ? '收起選單 Collapse menu · [' : '';
  }
  function toggleRail() {
    root.classList.add('side-anim');
    root.classList.toggle('side-rail');
    store('tyt-side-rail', root.classList.contains('side-rail') ? '1' : '0');
    syncRail();
    hideTip();
    setTimeout(function () { root.classList.remove('side-anim'); window.dispatchEvent(new Event('resize')); }, 260);
  }
  railBtn.addEventListener('click', toggleRail);
  syncRail();

  // ---------- ② sections ----------
  side.querySelectorAll('.side-group').forEach(function (b) {
    b.addEventListener('click', function () {
      var sec = b.closest('.side-sec'), shut = !sec.classList.contains('is-shut');
      sec.classList.toggle('is-shut', shut);
      b.setAttribute('aria-expanded', shut ? 'false' : 'true');
      var list = Array.prototype.map.call(side.querySelectorAll('.side-sec.is-shut'), function (s) { return s.dataset.sec; });
      store('tyt-side-shut', JSON.stringify(list));
    });
  });

  // ---------- names beside the rail icons ----------
  var tip = document.createElement('div');
  tip.className = 'side-tip';
  tip.hidden = true;
  document.body.appendChild(tip);
  function railOn() { return root.classList.contains('side-rail') && window.innerWidth > 1000; }
  function showTip(el) {
    if (!railOn() || !el.dataset.label) return;
    var r = el.getBoundingClientRect();
    tip.textContent = el.dataset.label;
    tip.hidden = false;
    tip.style.top = (r.top + r.height / 2 - tip.offsetHeight / 2) + 'px';
    tip.style.left = (r.right + 12) + 'px';
  }
  function hideTip() { tip.hidden = true; }
  side.querySelectorAll('[data-label]').forEach(function (el) {
    el.addEventListener('mouseenter', function () { showTip(el); });
    el.addEventListener('focus', function () { showTip(el); });
    el.addEventListener('mouseleave', hideTip);
    el.addEventListener('blur', hideTip);
  });
  side.addEventListener('scroll', hideTip, { passive: true });

  // ---------- ③ 快速前往 ----------
  var pages = [];
  side.querySelectorAll('a.side-link[href]').forEach(function (a) {
    var t = a.querySelector('.side-text');
    pages.push({
      href: a.getAttribute('href'), blank: a.target === '_blank',
      zh: t.firstChild.textContent.trim(), en: (t.querySelector('small') || {}).textContent || '',
      group: a.dataset.group || '', icon: a.querySelector('.ico').innerHTML, here: a.classList.contains('active')
    });
  });
  var dlg = null, input, list, hits = [], sel = 0;
  function build() {
    dlg = document.createElement('div');
    dlg.className = 'goto';
    dlg.hidden = true;
    dlg.innerHTML = '<div class="goto-box" role="dialog" aria-modal="true" aria-label="快速前往 Go to a page">'
      + '<div class="goto-search"><svg class="ic" aria-hidden="true"><use href="' + (document.querySelector('.side-find use') || {}).getAttribute('href') + '"></use></svg>'
      + '<input type="search" placeholder="輸入頁面名稱… Type a page name…" aria-label="頁面名稱 Page name" autocomplete="off" spellcheck="false">'
      + '<kbd>Esc</kbd></div><ul class="goto-list" role="listbox"></ul>'
      + '<div class="goto-help"><span><kbd>↑</kbd><kbd>↓</kbd> 選擇 Choose</span><span><kbd>Enter</kbd> 前往 Go</span><span><kbd>Ctrl K</kbd> 開啟 Open</span></div></div>';
    document.body.appendChild(dlg);
    input = dlg.querySelector('input');
    list = dlg.querySelector('.goto-list');
    dlg.addEventListener('click', function (e) { if (e.target === dlg) close(); });
    input.addEventListener('input', function () { sel = 0; draw(); });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(hits.length - 1, sel + 1); mark(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(0, sel - 1); mark(); }
      else if (e.key === 'Enter') { e.preventDefault(); if (hits[sel]) go(hits[sel]); }
      else if (e.key === 'Escape') { e.preventDefault(); close(); }
    });
    list.addEventListener('click', function (e) {
      var li = e.target.closest('li[data-i]');
      if (li) go(hits[+li.dataset.i]);
    });
    list.addEventListener('mousemove', function (e) {
      var li = e.target.closest('li[data-i]');
      if (li && +li.dataset.i !== sel) { sel = +li.dataset.i; mark(); }
    });
  }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function draw() {
    var q = input.value.trim().toLowerCase();
    hits = pages.filter(function (p) {
      if (!q) return true;
      return (p.zh + ' ' + p.en + ' ' + p.group).toLowerCase().indexOf(q) >= 0
        || q.split(/\s+/).every(function (w) { return (p.zh + ' ' + p.en).toLowerCase().indexOf(w) >= 0; });
    });
    list.innerHTML = hits.length ? hits.map(function (p, i) {
      return '<li role="option" data-i="' + i + '"><span class="goto-ic">' + p.icon + '</span>'
        + '<span class="goto-name">' + esc(p.zh) + ' <small>' + esc(p.en) + '</small></span>'
        + (p.here ? '<span class="goto-here">目前 Here</span>' : '<span class="goto-group">' + esc(p.group) + '</span>') + '</li>';
    }).join('') : '<li class="goto-none">找不到頁面 No page found</li>';
    mark();
  }
  function mark() {
    list.querySelectorAll('li[data-i]').forEach(function (li) {
      var on = +li.dataset.i === sel;
      li.classList.toggle('is-sel', on);
      li.setAttribute('aria-selected', on ? 'true' : 'false');
      if (on) li.scrollIntoView({ block: 'nearest' });
    });
  }
  var lastFocus = null;
  function open() {
    if (!dlg) build();
    lastFocus = document.activeElement;
    document.body.classList.remove('menu-open');
    dlg.hidden = false;
    input.value = '';
    sel = 0;
    draw();
    input.focus();
  }
  function close() { if (dlg) dlg.hidden = true; if (lastFocus && lastFocus.focus) lastFocus.focus(); }
  function go(p) {
    close();
    if (p.blank) window.open(p.href, '_blank', 'noopener'); else location.href = p.href;
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-goto-open]')) { e.preventDefault(); open(); }
  });
  document.addEventListener('keydown', function (e) {
    var k = (e.key || '').toLowerCase();
    if ((e.ctrlKey || e.metaKey) && k === 'k') { e.preventDefault(); if (dlg && !dlg.hidden) close(); else open(); return; }
    if (typing(e) || e.ctrlKey || e.metaKey || e.altKey) return;
    if (e.key === '/' && (!dlg || dlg.hidden)) { e.preventDefault(); open(); }
    if (e.key === '[' && window.innerWidth > 1000) { e.preventDefault(); toggleRail(); }
  });
})();
