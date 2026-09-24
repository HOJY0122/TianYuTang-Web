/**
 * TYTDialog — the site's own pop-up boxes, replacing the browser's plain
 * alert() / confirm() windows (grey, tiny, and they look like errors).
 *
 *   TYTDialog.alert('已儲存', { type: 'success', title: '完成 Done' });
 *   TYTDialog.confirm('刪除？', { danger: true }).then(function (yes) { … });
 *
 * Wired automatically:
 *   <form data-confirm="Delete this?">     asks before the form is sent
 *   <button data-confirm="…">               asks before that button submits
 *     optional: data-confirm-title, data-confirm-ok, data-danger
 *   <div class="flash success" data-flash-modal>   the message after an
 *     action (saved, deleted …) pops up as a card; success closes itself.
 *
 * Esc = cancel, Enter = OK; focus returns to where it was. Styles are
 * built in, so the same file works on the public site and in admin.
 */
(function () {
  'use strict';
  if (window.TYTDialog) return;

  var css = ''
    + '.tyd-back{position:fixed;inset:0;z-index:1000;background:rgba(24,14,8,.55);display:grid;place-items:center;padding:16px;'
    + 'animation:tydFade .15s ease-out}'
    + '.tyd-box{position:relative;width:min(440px,100%);background:#fffdf7;border-radius:20px;padding:26px 24px 20px;text-align:center;'
    + 'box-shadow:0 24px 60px rgba(0,0,0,.35);border-top:6px solid var(--tyd-c,#9f211b);animation:tydPop .18s ease-out;font-family:inherit}'
    + '.tyd-icon{width:62px;height:62px;margin:-58px auto 10px;border-radius:50%;display:grid;place-items:center;font-size:30px;'
    + 'background:var(--tyd-c,#9f211b);color:#fff;box-shadow:0 6px 16px rgba(0,0,0,.2);border:4px solid #fffdf7}'
    + '.tyd-title{margin:4px 0 8px;font-size:1.2rem;font-weight:800;color:#2a1a12}'
    + '.tyd-msg{margin:0;white-space:pre-line;line-height:1.65;color:#4a3a2c;font-size:1rem}'
    + '.tyd-actions{display:flex;gap:10px;justify-content:center;margin-top:20px;flex-wrap:wrap}'
    + '.tyd-btn{min-width:120px;min-height:46px;padding:10px 20px;border-radius:12px;border:2px solid transparent;font:inherit;font-weight:800;cursor:pointer}'
    + '.tyd-ok{background:var(--tyd-c,#9f211b);color:#fff}'
    + '.tyd-ok:hover{filter:brightness(.92)}'
    + '.tyd-cancel{background:#f3ead6;color:#4a3a2c;border-color:#e2d2ad}'
    + '.tyd-cancel:hover{background:#ebdfc4}'
    + '.tyd-btn:focus-visible{outline:3px solid #c89432;outline-offset:2px}'
    + '.tyd-timer{position:absolute;left:0;bottom:0;height:4px;border-radius:0 0 20px 20px;background:var(--tyd-c);opacity:.5;'
    + 'animation:tydTimer linear forwards}'
    + '@keyframes tydFade{from{opacity:0}}@keyframes tydPop{from{transform:translateY(12px) scale(.96);opacity:0}}'
    + '@keyframes tydTimer{from{width:100%}to{width:0}}'
    + '@media (prefers-reduced-motion:reduce){.tyd-back,.tyd-box{animation:none}}'
    + '.flash[data-flash-modal].tyd-shown{display:none}';
  var style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  var TYPES = {
    success: { c: '#2e7d4f', icon: '✓', title: '完成 Done' },
    error:   { c: '#a3281f', icon: '!', title: '請注意 Please check' },
    info:    { c: '#b8862b', icon: 'i', title: '提示 Note' },
    confirm: { c: '#9f211b', icon: '?', title: '請確認 Please confirm' },
    danger:  { c: '#a3281f', icon: '⚠', title: '請確認 Please confirm' },
    test:    { c: '#6b4b8a', icon: '🧪', title: '測試 Test' }
  };

  function open(message, opt, isConfirm) {
    opt = opt || {};
    var t = TYPES[opt.type] || TYPES[isConfirm ? (opt.danger ? 'danger' : 'confirm') : 'info'];
    var before = document.activeElement;
    return new Promise(function (resolve) {
      var back = document.createElement('div');
      back.className = 'tyd-back';
      var box = document.createElement('div');
      box.className = 'tyd-box';
      box.setAttribute('role', isConfirm ? 'alertdialog' : 'dialog');
      box.setAttribute('aria-modal', 'true');
      box.style.setProperty('--tyd-c', t.c);
      var icon = document.createElement('div'); icon.className = 'tyd-icon'; icon.textContent = t.icon; icon.setAttribute('aria-hidden', 'true');
      var title = document.createElement('h2'); title.className = 'tyd-title'; title.id = 'tyd' + Date.now(); title.textContent = opt.title || t.title;
      var msg = document.createElement('p'); msg.className = 'tyd-msg'; msg.textContent = message;
      box.setAttribute('aria-labelledby', title.id);
      var actions = document.createElement('div'); actions.className = 'tyd-actions';
      var ok = document.createElement('button'); ok.type = 'button'; ok.className = 'tyd-btn tyd-ok';
      ok.textContent = opt.okText || (isConfirm ? (opt.danger ? '確定 Yes, go ahead' : '確定 OK') : '好 OK');
      var cancel = null;
      if (isConfirm) {
        cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'tyd-btn tyd-cancel';
        cancel.textContent = opt.cancelText || '取消 Cancel';
        actions.appendChild(cancel);
      }
      actions.appendChild(ok);
      box.append(icon, title, msg, actions);
      if (opt.autoClose) {
        var bar = document.createElement('div'); bar.className = 'tyd-timer'; bar.style.animationDuration = opt.autoClose + 'ms';
        box.appendChild(bar);
      }
      back.appendChild(box);
      document.body.appendChild(back);
      var prevOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';
      (cancel || ok).focus();
      if (!isConfirm) ok.focus();

      var timer = opt.autoClose ? setTimeout(function () { done(true); }, opt.autoClose) : null;
      function done(answer) {
        clearTimeout(timer);
        document.removeEventListener('keydown', keys, true);
        back.remove();
        document.body.style.overflow = prevOverflow;
        if (before && before.focus) { try { before.focus(); } catch (e) {} }
        resolve(answer);
      }
      function keys(e) {
        if (e.key === 'Escape') { e.preventDefault(); done(!isConfirm); }
        else if (e.key === 'Tab') {   // keep focus inside the box
          var f = cancel ? [cancel, ok] : [ok];
          var i = f.indexOf(document.activeElement);
          e.preventDefault();
          f[(i + (e.shiftKey ? -1 : 1) + f.length) % f.length].focus();
        }
      }
      document.addEventListener('keydown', keys, true);
      ok.addEventListener('click', function () { done(true); });
      if (cancel) cancel.addEventListener('click', function () { done(false); });
      back.addEventListener('click', function (e) { if (e.target === back) done(!isConfirm); });
    });
  }

  window.TYTDialog = {
    alert: function (message, opt) { return open(message, opt, false); },
    confirm: function (message, opt) { return open(message, opt, true); }
  };

  // ---- <form data-confirm> and <button data-confirm> ----
  function optsFrom(el) {
    return { title: el.dataset.confirmTitle, okText: el.dataset.confirmOk, danger: el.hasAttribute('data-danger') };
  }
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('button[data-confirm], input[type=submit][data-confirm]') : null;
    if (!btn || btn.dataset.confirmed) return;
    e.preventDefault();
    TYTDialog.confirm(btn.dataset.confirm, optsFrom(btn)).then(function (yes) {
      if (!yes) return;
      btn.dataset.confirmed = '1';
      btn.click();
      delete btn.dataset.confirmed;
    });
  }, true);
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.dataset || !form.dataset.confirm || form.dataset.confirmed) return;
    e.preventDefault();
    var submitter = e.submitter;
    TYTDialog.confirm(form.dataset.confirm, optsFrom(form)).then(function (yes) {
      if (!yes) return;
      form.dataset.confirmed = '1';
      if (form.requestSubmit) form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
      else form.submit();
    });
  }, true);

  // ---- the message after an action, as a pop-up card ----
  function showFlashes() {
    var list = document.querySelectorAll('.flash[data-flash-modal]');
    Array.prototype.reduce.call(list, function (chain, el) {
      return chain.then(function () {
        var type = el.dataset.flashModal || 'info';
        var title = el.querySelector('strong') ? el.querySelector('strong').textContent : '';
        var text = (el.dataset.flashText || el.textContent).trim();
        el.classList.add('tyd-shown');
        return open(text, { type: type, title: title || undefined, autoClose: type === 'success' ? 3500 : 0 }, false);
      });
    }, Promise.resolve());
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', showFlashes);
  else showFlashes();
})();
