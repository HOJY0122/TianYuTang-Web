<!DOCTYPE html>
<html lang="zh-Hant">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>資料庫需要更新｜天玉堂</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+TC:wght@700;900&family=Noto+Sans+TC:wght@400;700&display=swap" rel="stylesheet">
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;
background:linear-gradient(180deg,#fff7d9,#fffaf0 45%,#f7e8b6);color:#241b16;
font-family:"Noto Sans TC",sans-serif}
.box{background:rgba(255,250,240,.94);border:1px solid #e4c979;border-radius:18px;
padding:34px;max-width:620px;width:100%;box-shadow:0 14px 35px rgba(67,39,18,.12)}
h1{font-family:"Noto Serif TC",serif;color:#9f211b;font-size:23px;margin:0 0 6px}
.sub{color:#75665a;font-size:14px;margin-bottom:20px}
p{line-height:1.85;color:#3b2e25}
code{background:#f3e7c4;padding:2px 7px;border-radius:5px;font-size:13.5px}
pre{background:#2b1e18;color:#fff3c2;padding:14px 16px;border-radius:10px;
overflow:auto;font-size:12.5px;line-height:1.6;margin:14px 0}
.steps{background:#fff5d0;border-left:5px solid #b58a35;padding:15px 18px;border-radius:8px;line-height:1.9}
.detail{margin-top:22px;padding-top:18px;border-top:1px solid #eadcb5;color:#75665a;font-size:13px}
</style>
</head>
<body>
  <div class="box">
    <h1>⚙️ 資料庫需要更新</h1>
    <div class="sub">Database schema is out of date</div>

    <p>
      網站程式已更新，但資料庫還沒跟上，因此暫時無法顯示。<br>
      The application has been updated but the database has not — the site cannot load until they match.
    </p>

    <div class="steps">
      <strong>給管理員／開發者：</strong><br>
      執行尚未套用的 migration 即可修復，現有的報名與布施資料都會保留。
    </div>

    <pre># 先備份 Back up first
mysqldump -u USER -p tianyutang2026 &gt; backup.sql

# 套用 migration Apply the migration
mysql -u USER -p &lt; migrations/001_add_events.sql</pre>

    <p style="font-size:14px;color:#75665a">
      或使用 phpMyAdmin：選擇 <code>tianyutang2026</code> → <strong>匯入 Import</strong> →
      選擇 <code>migrations/001_add_events.sql</code>。
    </p>

    <p style="font-size:14px;color:#75665a">
      注意：<code>schema.sql</code> 只適用於全新安裝，直接匯入既有資料庫並不會修正此問題。
    </p>

    <?php if (!empty($detail)): ?>
      <div class="detail">
        <strong>Technical detail (DEBUG_MODE is on):</strong><br>
        <?= htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
