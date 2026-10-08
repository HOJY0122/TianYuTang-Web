<?php
/**
 * Migration 017 (data) — the site now draws SVG icons instead of emoji
 * (some phones and computers show emoji as empty boxes). Older installs
 * still have wording saved with an emoji at the front, e.g. the default
 * tagline "🙏 感恩您的參與與支持". Remove that leading emoji from the
 * site wording settings; the rest of each text is left exactly as typed.
 * Safe to run again: text without a leading emoji is not touched.
 */
$rows = $db->query("SELECT setting_key, setting_value FROM settings
                    WHERE setting_key IN ('site_tagline', 'footer_title') OR setting_key LIKE 'txt.%'")->fetchAll(PDO::FETCH_ASSOC);
$upd  = $db->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
$fixed = 0;
foreach ($rows as $r) {
    $v = (string) $r['setting_value'];
    $clean = preg_replace('/^(?:[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{23E9}-\x{23FA}]\x{FE0F}?\s*)+/u', '', $v);
    if ($clean !== null && $clean !== $v) {
        $upd->execute([$clean, $r['setting_key']]);
        $fixed++;
    }
}
echo "    wording with a leading emoji tidied: {$fixed}\n";
