/**
 * fit-screen.js — phones showing the site in "Desktop site" mode.
 *
 * With Desktop mode on (Chrome, Samsung Internet, Safari "Request Desktop
 * Website"), a phone lays the page out about 980px wide and shrinks it to
 * fit, so everything is tiny and the phone layout never appears. The page
 * cannot switch that mode off, but it can undo the effect:
 *
 *   1. It notices a touch screen whose real width (screen.width, e.g.
 *      390) is much narrower than the page it is given (e.g. 980).
 *   2. It zooms the page by that ratio, so text and buttons are phone-sized
 *      again and the page is laid out for the real width.
 *   3. It scales every width in the site's @media rules by the same ratio,
 *      so the phone layout (single column, menu button …) switches on.
 *
 * Tablets and computers are left alone. A small button lets anyone who
 * really wants the desktop look switch this off (remembered on that phone).
 * Loaded in <head> right after the stylesheets, before anything is drawn.
 */
(function () {
  'use strict';
  var html = document.documentElement;
  var KEY = 'tyt-fit';
  var original = typeof WeakMap === 'function' ? new WeakMap() : null;

  function realWidth() {
    var a = screen.width, b = screen.height;
    // Some phones report portrait sizes even when turned sideways.
    return window.innerWidth > window.innerHeight ? Math.max(a, b) : Math.min(a, b);
  }
  function isTouch() { return (navigator.maxTouchPoints || 0) > 0 || 'ontouchstart' in window; }
  function optedOut() { try { return localStorage.getItem(KEY) === 'off'; } catch (e) { return false; } }

  /**
   * The factor the page needs, or 1 when it already fits.
   * window.innerWidth is the width the browser gave the page (≈980 in
   * desktop mode); CSS zoom does not change it.
   */
  function desktopMode() {
    return isTouch() && realWidth() <= 900 && window.innerWidth >= realWidth() * 1.3;
  }
  function ratio() {
    if (!original || !('zoom' in html.style) || !desktopMode()) return 1;
    return Math.min(4, window.innerWidth / realWidth());
  }

  /**
   * For zoom r, rewrite from the originals (so it can be redone):
   *  - @media widths/heights, so the phone layout switches on;
   *  - screen units (vh, vw …), which zoom would otherwise make r times
   *    too big (a 100vh box would be 2½ screens tall).
   */
  var UNIT = /(-?[\d.]+)(d|s|l)?(vh|vw|vmin|vmax)\b/g;
  function scaleUnits(rule, r) {
    var st = rule.style;
    if (!st) return;
    if (!original.has(st)) {
      var keep = {};
      for (var k = 0; k < st.length; k++) {
        var prop = st[k], val = st.getPropertyValue(prop);
        if (UNIT.test(val)) keep[prop] = [val, st.getPropertyPriority(prop)];
        UNIT.lastIndex = 0;
      }
      original.set(st, keep);
    }
    var saved = original.get(st);
    Object.keys(saved).forEach(function (prop) {
      var val = r === 1 ? saved[prop][0] : saved[prop][0].replace(UNIT, function (m, n, pre, unit) {
        return (parseFloat(n) / r).toFixed(3) + (pre || '') + unit;
      });
      if (st.getPropertyValue(prop) !== val) st.setProperty(prop, val, saved[prop][1]);
    });
  }
  function scaleMedia(r) {
    var re = /\((min|max)-(width|height)\s*:\s*([\d.]+)(px|em|rem)\s*\)/g;
    function walk(rules) {
      for (var i = 0; i < rules.length; i++) {
        var rule = rules[i];
        scaleUnits(rule, r);
        if (rule.media && rule.cssRules) {                     // @media (and @supports-less) blocks
          if (!original.has(rule)) original.set(rule, rule.media.mediaText);
          var text = original.get(rule);
          var next = r === 1 ? text : text.replace(re, function (m, mm, dim, n, unit) {
            return '(' + mm + '-' + dim + ': ' + (parseFloat(n) * r).toFixed(2) + unit + ')';
          });
          if (next !== rule.media.mediaText) rule.media.mediaText = next;
        }
        if (rule.cssRules) walk(rule.cssRules);
      }
    }
    for (var s = 0; s < document.styleSheets.length; s++) {
      var rules;
      try { rules = document.styleSheets[s].cssRules; } catch (e) { continue; }  // other sites' fonts
      if (rules) walk(rules);
    }
  }

  var current = 1;
  function apply() {
    var r = optedOut() ? 1 : ratio();
    if (Math.abs(r - current) < 0.02) return;
    current = r;
    html.style.zoom = r === 1 ? '' : String(r);
    html.classList.toggle('fit-phone', r !== 1);
    scaleMedia(r);
    button();
  }

  // A small switch, shown only on phones in desktop mode.
  var btn = null;
  function button() {
    if (!desktopMode() || !document.body) { if (btn) btn.hidden = true; return; }
    if (!btn) {
      btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'fit-toggle';
      btn.addEventListener('click', function () {
        try { localStorage.setItem(KEY, optedOut() ? 'on' : 'off'); } catch (e) {}
        current = 0; apply();
      });
      document.body.appendChild(btn);
    }
    btn.hidden = false;
    btn.textContent = optedOut() ? '📱 手機版 Phone view' : '🖥 電腦版 Desktop view';
  }

  apply();
  document.addEventListener('DOMContentLoaded', function () { scaleMedia(current); button(); });
  window.addEventListener('load', function () { scaleMedia(current); });
  // Scrolling on a phone shows and hides the address bar, which fires
  // "resize" with only the height changed — nothing to redo then, or the
  // page would jump. Only a new width (turning the phone) counts.
  var lastW = window.innerWidth;
  window.addEventListener('orientationchange', function () { setTimeout(function () { lastW = window.innerWidth; current = 0; apply(); }, 250); });
  window.addEventListener('resize', function () {
    if (window.innerWidth === lastW) return;
    lastW = window.innerWidth; current = 0; apply();
  });
})();
