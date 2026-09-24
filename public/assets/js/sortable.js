/**
 * Drag-and-drop ordering for admin lists and grids (photos, news posts).
 *
 * Markup:
 *   <div data-sortable="/admin/photos/reorder" data-extra='{"event_id":3}'>
 *     <div data-id="12"> … <button type="button" data-drag-handle>⠿</button> … </div>
 *   </div>
 *
 * Grab an item by its ⠿ handle (or its picture) with a mouse, finger or
 * pen, move it, let go — the new order is saved at once, and a small
 * message confirms it. Keyboard users and anyone who prefers can still
 * use the ↑ ↓ buttons, which remain as ordinary forms.
 *
 * Pointer events (not the HTML5 drag API) so it works the same on phones.
 */
(function () {
  'use strict';
  var csrf = (document.querySelector('input[name="csrf_token"]') || {}).value || '';

  function toast(text, bad) {
    var t = document.createElement('div');
    t.className = 'sort-toast' + (bad ? ' bad' : '');
    t.setAttribute('role', 'status');
    t.textContent = text;
    document.body.appendChild(t);
    setTimeout(function () { t.classList.add('out'); }, 1600);
    setTimeout(function () { t.remove(); }, 2100);
  }

  function save(list) {
    var body = new URLSearchParams();
    body.append('csrf_token', csrf);
    var extra = {};
    try { extra = JSON.parse(list.dataset.extra || '{}'); } catch (e) {}
    Object.keys(extra).forEach(function (k) { body.append(k, extra[k]); });
    list.querySelectorAll(':scope > [data-id]').forEach(function (el) { body.append('ids[]', el.dataset.id); });
    fetch(list.dataset.sortable, { method: 'POST', body: body, credentials: 'same-origin',
                                   headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
      .then(function () {
        toast('✓ 已儲存排序 Order saved');
        list.querySelectorAll(':scope > [data-id] [data-position]').forEach(function (el, i) { el.textContent = i + 1; });
      })
      .catch(function () { toast('⚠️ 排序未儲存，請重新整理頁面。Order not saved — please reload.', true); });
  }

  function init(list) {
    var dragging = null, ghost = null, startX = 0, startY = 0, offX = 0, offY = 0, moved = false, scrollTimer = null, lastY = 0;

    list.addEventListener('pointerdown', function (e) {
      var handle = e.target.closest('[data-drag-handle]');
      if (!handle || !list.contains(handle) || e.button > 0) return;
      var item = handle.closest('[data-id]');
      if (!item || item.parentNode !== list) return;
      e.preventDefault();
      dragging = item; moved = false;
      startX = e.clientX; startY = e.clientY;
      var r = item.getBoundingClientRect();
      offX = e.clientX - r.left; offY = e.clientY - r.top;
      // Listen on the whole document, not the handle: moving the item in
      // the page would cancel pointer capture and the drag would go deaf.
      document.addEventListener('pointermove', move);
      document.addEventListener('pointerup', end);
      document.addEventListener('pointercancel', end);
    });

    function begin() {
      var r = dragging.getBoundingClientRect();
      ghost = dragging.cloneNode(true);
      ghost.removeAttribute('data-id');      // the floating copy is not part of the list
      ghost.classList.add('sort-ghost');
      ghost.style.width = r.width + 'px';
      ghost.style.height = r.height + 'px';
      document.body.appendChild(ghost);
      dragging.classList.add('sort-placeholder');
      document.body.classList.add('is-sorting');
    }

    function move(e) {
      if (!dragging) return;
      if (!moved) {
        if (Math.abs(e.clientX - startX) + Math.abs(e.clientY - startY) < 6) return;   // a tap, not a drag
        moved = true; begin();
      }
      lastY = e.clientY;
      ghost.style.transform = 'translate(' + (e.clientX - offX) + 'px,' + (e.clientY - offY) + 'px)';
      // Which item is under the finger? Insert before or after it.
      ghost.style.display = 'none';
      var under = document.elementFromPoint(e.clientX, e.clientY);
      ghost.style.display = '';
      var over = under && under.closest('[data-id]');
      if (over && over !== dragging && over.parentNode === list) {
        var r = over.getBoundingClientRect();
        var sameRow = Math.abs(r.top - dragging.getBoundingClientRect().top) < r.height / 2;
        var after = sameRow ? e.clientX > r.left + r.width / 2 : e.clientY > r.top + r.height / 2;
        list.insertBefore(dragging, after ? over.nextSibling : over);
      }
      // Scroll the page when dragging near the top or bottom edge.
      if (!scrollTimer) scrollTimer = setInterval(function () {
        var edge = 70, h = window.innerHeight;
        if (lastY < edge) window.scrollBy(0, -12);
        else if (lastY > h - edge) window.scrollBy(0, 12);
      }, 16);
    }

    function end() {
      document.removeEventListener('pointermove', move);
      document.removeEventListener('pointerup', end);
      document.removeEventListener('pointercancel', end);
      clearInterval(scrollTimer); scrollTimer = null;
      if (!dragging) return;
      var was = dragging;
      dragging = null;
      if (!moved) return;
      was.classList.remove('sort-placeholder');
      document.body.classList.remove('is-sorting');
      if (ghost) { ghost.remove(); ghost = null; }
      save(list);
    }
  }

  document.querySelectorAll('[data-sortable]').forEach(init);
})();
