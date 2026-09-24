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

  /** The factor the page needs, or 1 when it already fits. */
  function ratio() {
    if (!isTouch() || !original || !('zoom' in html.style)) return 1;
    var real = realWidth(), given = window.innerWidth * (parseFloat(html.style.zoom) || 1);
    if (real > 900 || given < real * 1.3) return 1;       // a tablet or computer, or already a phone layout
    return Math.min(4, given / real);
  }

  /** Rewrite @media widths/heights for zoom r (from the originals, so it can be redone). */
  function scaleMedia(r) {
    var re = /\((min|max)-(width|height)\s*:\s*([\d.]+)(px|em|rem)\s*\)/g;
    function walk(rules) {
      for (var i = 0; i < rules.length; i++) {
        var rule = rules[i];
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
    var desktopMode = isTouch() && realWidth() <= 900 && window.innerWidth * (parseFloat(html.style.zoom) || 1) >= realWidth() * 1.3;
    if (!desktopMode || !document.body) { if (btn) btn.hidden = true; return; }
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
  window.addEventListener('orientationchange', function () { setTimeout(function () { current = 0; apply(); }, 250); });
  window.addEventListener('resize', function () { apply(); });
})();
