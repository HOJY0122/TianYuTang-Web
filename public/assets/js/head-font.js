/*
 * head-font.js — a heading is drawn in ONE font, never two.
 *
 * The brush heading fonts (Yuji Boku / Yuji Syuku) are Japanese: they lack
 * some Chinese characters, e.g. 您. The browser would draw just that one
 * character in the fallback font, so 「感謝您的報名」 came out in two
 * styles. When a heading holds a character the heading font does not have,
 * this gives the whole heading the class "head-whole": it is then drawn
 * entirely in 霞鶩文楷 LXGW WenKai TC (CSS --kai-whole, layouts/header.php),
 * which has every Traditional character.
 *
 * How a missing character is found: draw it with "<heading font>, WenKai"
 * and with WenKai alone; identical pictures mean the heading font fell
 * back, i.e. does not have it. Both fonts are web fonts loaded by the page,
 * so the answer is the same on every computer.
 *
 * Only runs when <html data-head-font="…"> is set (the brush fonts); the
 * Kai / Song heading fonts have every character.
 */
(function () {
  'use strict';
  var fam = document.documentElement.getAttribute('data-head-font');
  if (!fam || !document.fonts || !document.fonts.load) return;
  var FALL = 'LXGW WenKai TC';
  var SEL = 'h1, h2, h3, h4, .kai, .brand-text strong, .cta strong';
  var CJK = /[㐀-鿿豈-﫿]/;
  var cache = {};
  var cv = document.createElement('canvas'), cx;
  cv.width = cv.height = 44;
  try { cx = cv.getContext('2d', { willReadFrequently: true }); } catch (e) { cx = null; }
  if (!cx) return;

  function picture(ch, font) {
    cx.clearRect(0, 0, 44, 44);
    cx.font = '36px ' + font;
    cx.textBaseline = 'top';
    cx.fillText(ch, 2, 2);
    return cx.getImageData(0, 0, 44, 44).data;
  }
  function missing(ch) {
    if (ch in cache) return cache[ch];
    var a = picture(ch, '"' + fam + '", "' + FALL + '"'), b = picture(ch, '"' + FALL + '"');
    for (var i = 3; i < a.length; i += 4) {
      if (a[i] !== b[i]) return (cache[ch] = false);   // drew differently: the heading font has it
    }
    return (cache[ch] = true);
  }
  function headText(el) {
    // The Chinese of the heading only (the English line uses the body font).
    var t = '';
    el.childNodes.forEach(function (n) {
      if (n.nodeType === 3) t += n.textContent;
      else if (n.nodeType === 1 && !(n.classList && n.classList.contains('en'))) t += headText(n);
    });
    return t;
  }
  function scan(root) {
    var els = [].slice.call((root || document).querySelectorAll(SEL));
    var all = els.map(headText).join('');
    var chars = Array.from(new Set(Array.from(all).filter(function (c) { return CJK.test(c); })));
    if (!chars.length) return;
    var sample = chars.join('');
    // Load just the parts of both fonts these characters need, then compare.
    Promise.all([document.fonts.load('36px "' + fam + '"', sample), document.fonts.load('36px "' + FALL + '"', sample)])
      .then(function () {
        els.forEach(function (el) {
          var whole = Array.from(headText(el)).some(function (c) { return CJK.test(c) && missing(c); });
          el.classList.toggle('head-whole', whole);
        });
      })
      .catch(function () {});
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { scan(document); });
  else scan(document);
  document.addEventListener('live:swap', function (e) { scan(e.detail); });
  window.TYTHeadFont = { scan: scan, missing: missing };
})();
