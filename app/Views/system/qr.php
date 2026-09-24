<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>QR Code 產生器｜系統管理</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@700;900&family=Noto+Sans+TC:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body>

<div class="adminbar system">
  <h1>🔳 QR Code 產生器</h1>
  <div style="display:flex;align-items:center;gap:14px">
    <span class="who"><?= h($_SESSION['admin_display'] ?? $_SESSION['admin_username']) ?></span>
    <a class="logout" href="<?= url('/system') ?>">← 系統管理</a>
  </div>
</div>

<div class="wrap qr-wrap">

  <div class="qr-layout">

    <!-- ---------- Controls ---------- -->
    <div class="panel form-panel qr-controls">
      <h2 style="margin-top:0">設定</h2>

      <label for="qrText">內容｜Content *</label>
      <textarea id="qrText" rows="3" oninput="render()"><?= h($siteUrl) ?></textarea>
      <div class="preset-row">
        <button class="mini-btn ghost" type="button"
                onclick="setText(<?= json_encode($siteUrl, JSON_UNESCAPED_SLASHES) ?>)">活動首頁</button>
        <button class="mini-btn ghost" type="button"
                onclick="setText(<?= json_encode($siteUrl . '#rsvp', JSON_UNESCAPED_SLASHES) ?>)">報名頁</button>
        <button class="mini-btn ghost" type="button"
                onclick="setText(<?= json_encode($siteUrl . '#donation', JSON_UNESCAPED_SLASHES) ?>)">布施頁</button>
        <button class="mini-btn ghost" type="button"
                onclick="setText(<?= json_encode($siteUrl . 'gallery', JSON_UNESCAPED_SLASHES) ?>)">相簿</button>
      </div>
      <p class="help">可放網址、Waze 連結、電話、或任何文字。</p>

      <label for="qrStyle">樣式｜Style</label>
      <select id="qrStyle" onchange="render()">
        <option value="classic">Classic — 標準方塊（相容性最高）</option>
        <option value="rounded">Rounded — 圓角方塊</option>
        <option value="dots">Dots — 圓點</option>
        <option value="gapped">Gapped — 方塊留縫</option>
        <option value="radial_gradient">Radial Gradient — 放射漸層</option>
        <option value="square_gradient">Square Gradient — 對角漸層</option>
      </select>
      <p class="help" id="styleDesc">標準黑白矩陣，任何掃描器都讀得到。</p>

      <div class="form-row">
        <div>
          <label for="qrDark">前景色</label>
          <input id="qrDark" type="color" value="#241b16" oninput="render()">
        </div>
        <div>
          <label for="qrLight">背景色</label>
          <input id="qrLight" type="color" value="#ffffff" oninput="render()">
        </div>
      </div>

      <label for="qrEcl">容錯等級｜Error Correction</label>
      <select id="qrEcl" onchange="render()">
        <option value="L">L — 約 7%</option>
        <option value="M" selected>M — 約 15%</option>
        <option value="Q">Q — 約 25%</option>
        <option value="H">H — 約 30%（放標誌或印海報時建議）</option>
      </select>
      <p class="help" id="eclNote"></p>

      <label for="qrLogo">中央標誌｜Logo（選填）</label>
      <input id="qrLogo" type="file" class="file-input" accept="image/*" onchange="loadLogo(event)">
      <p class="help">
        放上標誌會自動提升容錯等級至 H，並把覆蓋面積限制在安全範圍內 ——
        蓋太多會掃不到。
        <button class="link-btn" type="button" onclick="clearLogo()">移除標誌</button>
      </p>

      <label class="check-row">
        <input id="qrEmboss" type="checkbox" onchange="render()">
        <span>立體浮雕效果（純視覺，資料不變）</span>
      </label>

      <div class="form-row">
        <div>
          <label for="qrSize">輸出尺寸 (px)</label>
          <input id="qrSize" type="number" min="200" max="3000" step="100" value="1200">
        </div>
        <div>
          <label for="qrFilename">檔名</label>
          <input id="qrFilename" type="text" value="tianyutang-qr">
        </div>
      </div>

      <div class="form-actions">
        <button class="primary" type="button" onclick="download()">⬇️ 下載 PNG</button>
        <button class="mini-btn ghost" type="button" onclick="window.print()">🖨️ 列印</button>
      </div>
    </div>

    <!-- ---------- Preview ---------- -->
    <div class="panel qr-preview">
      <h2 style="margin-top:0">預覽</h2>
      <div class="qr-stage">
        <canvas id="qrCanvas"></canvas>
      </div>
      <div class="qr-meta" id="qrMeta"></div>
      <div class="qr-warn hidden" id="qrWarn"></div>
    </div>

  </div>
</div>

<script src="<?= asset('js/qrcode.min.js') ?>"></script>
<script>
/* ------------------------------------------------------------------
   Styled QR rendering.

   The library gives us the module matrix; everything below draws it by
   hand so we can do rounded/dot modules, gradients and a centre logo.
   The data grid is never altered — only how each module is painted —
   so every style stays a real, scannable 2D code.
   ------------------------------------------------------------------ */

const STYLE_DESC = {
  classic:         '標準黑白矩陣，任何掃描器都讀得到。',
  rounded:         '圓角方塊，外觀柔和，仍是標準資料格。',
  dots:            '圓點模組。建議搭配較高容錯等級。',
  gapped:          '方塊之間留縫，略帶層次感。',
  radial_gradient: '由中心向外的放射漸層，最接近「立體」觀感。',
  square_gradient: '對角漸層搭配圓點。',
};

let logoImg = null;

function setText(t) {
  document.getElementById('qrText').value = t;
  render();
}

function loadLogo(e) {
  const file = e.target.files && e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = () => {
    const img = new Image();
    img.onload = () => { logoImg = img; render(); };
    img.src = reader.result;
  };
  reader.readAsDataURL(file);
}

function clearLogo() {
  logoImg = null;
  document.getElementById('qrLogo').value = '';
  render();
}

/**
 * Safe TOTAL overlay ratio (logo + its white padding) relative to the
 * QR width, ported from the Python generator. Capping the whole covered
 * area — not just the logo — is what keeps the code readable.
 */
function autoLogoRatio(ecl, version) {
  const base = { L: 0.10, M: 0.14, Q: 0.18, H: 0.22 }[ecl];
  const bonus = Math.min(0.02, version * 0.001);
  return Math.min(0.24, base + bonus);
}

/**
 * Centre coordinates of the alignment patterns for a given QR version
 * (Nayuki's formulation, including the version-32 special case). These
 * are drawn solid so the scanner can still correct for a tilted phone.
 */
function alignmentCentres(version) {
  if (version < 2) return [];
  const size = version * 4 + 17;
  const count = Math.floor(version / 7) + 2;
  const step = (version === 32)
    ? 26
    : Math.floor((version * 4 + count * 2 + 1) / (count * 2 - 2)) * 2;
  const out = [6];
  for (let pos = size - 7; out.length < count; pos -= step) out.splice(1, 0, pos);
  return out.sort((a, b) => a - b);
}

function render(targetSize) {
  const text = document.getElementById('qrText').value.trim();
  const canvas = document.getElementById('qrCanvas');
  const meta = document.getElementById('qrMeta');
  const warn = document.getElementById('qrWarn');

  document.getElementById('styleDesc').textContent =
    STYLE_DESC[document.getElementById('qrStyle').value];

  if (!text) {
    canvas.width = canvas.height = 0;
    meta.textContent = '請輸入內容。';
    return null;
  }

  // A logo always forces H, exactly as the Python script does: covering
  // the middle of a low-redundancy code is how you get an unscannable one.
  const eclSelect = document.getElementById('qrEcl');
  let ecl = eclSelect.value;
  if (logoImg && ecl !== 'H') {
    ecl = 'H';
    eclSelect.value = 'H';
  }
  document.getElementById('eclNote').textContent =
    logoImg ? '已因置中標誌自動設為 H。' : '';

  let qr;
  try {
    qr = window.TYTQR.create(text, ecl);
  } catch (err) {
    meta.textContent = '內容過長，無法產生 QR Code。請縮短文字。';
    canvas.width = canvas.height = 0;
    return null;
  }

  const style  = document.getElementById('qrStyle').value;
  const dark   = document.getElementById('qrDark').value;
  const light  = document.getElementById('qrLight').value;
  const emboss = document.getElementById('qrEmboss').checked;

  const size    = targetSize || 560;
  const quiet   = 4;                       // quiet zone, in modules
  const modules = qr.size;
  const total   = modules + quiet * 2;
  const scale   = size / total;

  canvas.width = canvas.height = size;
  const ctx = canvas.getContext('2d');

  ctx.fillStyle = light;
  ctx.fillRect(0, 0, size, size);

  // Gradient styles paint through a clip of all the dark modules.
  let paint = dark;
  if (style === 'radial_gradient') {
    const g = ctx.createRadialGradient(size / 2, size / 2, size * 0.05, size / 2, size / 2, size * 0.7);
    g.addColorStop(0, dark);
    g.addColorStop(1, shade(dark, 0.45));
    paint = g;
  } else if (style === 'square_gradient') {
    const g = ctx.createLinearGradient(0, 0, size, size);
    g.addColorStop(0, dark);
    g.addColorStop(1, shade(dark, 0.45));
    paint = g;
  }
  ctx.fillStyle = paint;

  const isDark = (r, c) =>
    r >= 0 && c >= 0 && r < modules && c < modules && qr.data[r * modules + c];

  // Finder and alignment patterns are what a scanner uses to LOCATE the
  // code and correct for the angle of the phone. Styling them into dots
  // or leaving gaps between them makes the code undetectable no matter
  // how high the error correction is — tested, not assumed. So these
  // always stay solid squares; only the data modules get the styling.
  const finders = [[0, 0], [0, modules - 7], [modules - 7, 0]];
  const aligns  = alignmentCentres(qr.version);
  const isLocator = (r, c) => {
    for (const [fr, fc] of finders) {
      if (r >= fr && r < fr + 7 && c >= fc && c < fc + 7) return true;
    }
    for (const ar of aligns) {
      for (const ac of aligns) {
        // The three alignment slots that collide with finders are unused.
        if ((ar <= 8 && ac <= 8) ||
            (ar <= 8 && ac >= modules - 9) ||
            (ar >= modules - 9 && ac <= 8)) continue;
        if (Math.abs(r - ar) <= 2 && Math.abs(c - ac) <= 2) return true;
      }
    }
    return false;
  };

  for (let r = 0; r < modules; r++) {
    for (let c = 0; c < modules; c++) {
      if (!isDark(r, c)) continue;

      const x = (c + quiet) * scale;
      const y = (r + quiet) * scale;

      if (isLocator(r, c)) {
        ctx.fillRect(x, y, scale + 0.5, scale + 0.5);
      } else if (style === 'dots' || style === 'square_gradient') {
        ctx.beginPath();
        ctx.arc(x + scale / 2, y + scale / 2, scale * 0.44, 0, Math.PI * 2);
        ctx.fill();
      } else if (style === 'gapped') {
        const gap = scale * 0.12;
        ctx.fillRect(x + gap / 2, y + gap / 2, scale - gap, scale - gap);
      } else if (style === 'rounded' || style === 'radial_gradient') {
        roundedModule(ctx, x, y, scale, r, c, isDark);
      } else {
        // +0.5 avoids hairline seams between neighbouring modules.
        ctx.fillRect(x, y, scale + 0.5, scale + 0.5);
      }
    }
  }

  if (emboss) applyEmboss(ctx, size, light);

  let overlay = 0;
  if (logoImg) {
    const ratio = autoLogoRatio(ecl, qr.version);
    overlay = Math.round(size * ratio);
    const pad = Math.max(4, Math.round(overlay / 12));
    const inner = overlay - pad * 2;
    const ox = Math.round((size - overlay) / 2);
    const oy = Math.round((size - overlay) / 2);

    // White pad behind the logo: scanners cope far better with a clean
    // block than with a logo blended into the modules.
    ctx.fillStyle = light;
    roundRect(ctx, ox, oy, overlay, overlay, overlay * 0.12);
    ctx.fill();
    ctx.drawImage(logoImg, ox + pad, oy + pad, inner, inner);
  }

  meta.innerHTML =
    '版本 ' + qr.version + ' · ' + modules + '×' + modules + ' 模組 · 容錯 ' + ecl +
    (logoImg ? ' · 標誌覆蓋 ' + Math.round((overlay / size) * 100) + '%' : '');

  // Honest warning rather than a silent risk.
  const risky = (style === 'dots' || style === 'square_gradient') && (ecl === 'L' || ecl === 'M');
  warn.classList.toggle('hidden', !risky);
  if (risky) {
    warn.textContent = '⚠️ 圓點樣式搭配較低容錯等級，在列印或光線不佳時可能較難掃描。建議改用 Q 或 H。';
  }

  return canvas;
}

/** Rounds only the corners that have no dark neighbour, so runs of
 *  modules still join into solid shapes. */
function roundedModule(ctx, x, y, s, r, c, isDark) {
  const up = isDark(r - 1, c), down = isDark(r + 1, c);
  const left = isDark(r, c - 1), right = isDark(r, c + 1);
  const rad = s * 0.42;

  ctx.beginPath();
  ctx.moveTo(x + (up || left ? 0 : rad), y);
  ctx.lineTo(x + s - (up || right ? 0 : rad), y);
  if (!(up || right)) ctx.quadraticCurveTo(x + s, y, x + s, y + rad); else ctx.lineTo(x + s, y);
  ctx.lineTo(x + s, y + s - (down || right ? 0 : rad));
  if (!(down || right)) ctx.quadraticCurveTo(x + s, y + s, x + s - rad, y + s); else ctx.lineTo(x + s, y + s);
  ctx.lineTo(x + (down || left ? 0 : rad), y + s);
  if (!(down || left)) ctx.quadraticCurveTo(x, y + s, x, y + s - rad); else ctx.lineTo(x, y + s);
  ctx.lineTo(x, y + (up || left ? 0 : rad));
  if (!(up || left)) ctx.quadraticCurveTo(x, y, x + rad, y); else ctx.lineTo(x, y);
  ctx.closePath();
  ctx.fill();
}

function roundRect(ctx, x, y, w, h, r) {
  ctx.beginPath();
  ctx.moveTo(x + r, y);
  ctx.arcTo(x + w, y, x + w, y + h, r);
  ctx.arcTo(x + w, y + h, x, y + h, r);
  ctx.arcTo(x, y + h, x, y, r);
  ctx.arcTo(x, y, x + w, y, r);
  ctx.closePath();
}

/** Cosmetic bevel, mirroring the Python emboss pass. Deliberately very
 *  light: contrast between modules is what scanners rely on. */
function applyEmboss(ctx, size, light) {
  const img = ctx.getImageData(0, 0, size, size);
  const d = img.data;
  const out = new Uint8ClampedArray(d);
  const w = size;
  for (let y = 1; y < size - 1; y++) {
    for (let x = 1; x < size - 1; x++) {
      const i = (y * w + x) * 4;
      const j = ((y - 1) * w + (x - 1)) * 4;
      for (let k = 0; k < 3; k++) {
        out[i + k] = Math.max(0, Math.min(255, d[i + k] + (d[i + k] - d[j + k]) * 0.35));
      }
    }
  }
  ctx.putImageData(new ImageData(out, size, size), 0, 0);
}

/** Mix a hex colour toward white by `amount`. */
function shade(hex, amount) {
  const n = parseInt(hex.slice(1), 16);
  const r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
  const m = v => Math.round(v + (255 - v) * amount);
  return 'rgb(' + m(r) + ',' + m(g) + ',' + m(b) + ')';
}

function download() {
  const size = Math.max(200, Math.min(3000, Number(document.getElementById('qrSize').value) || 1200));
  const canvas = render(size);
  if (!canvas) return;

  const name = (document.getElementById('qrFilename').value || 'qr').replace(/[^\w.-]+/g, '-');
  const a = document.createElement('a');
  a.href = canvas.toDataURL('image/png');
  a.download = name + '.png';
  a.click();

  render();   // restore the on-screen preview size
}

render();
</script>
</body>
</html>
