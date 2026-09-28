<?php
/**
 * Migration 015 (data) — encrypt what was saved before encryption existed:
 *   - every IC / passport number (plus its lookup hash and last 4 digits)
 *   - the AI service keys in Site settings
 * Values already encrypted are skipped, so running it twice is harmless.
 * Run by bin/migrate.php, which provides $db (PDO).
 */
require_once BASE_PATH . '/app/Core/Crypto.php';
use App\Core\Crypto;

$norm = static fn(string $ic): string => strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $ic));

$rows = $db->query('SELECT id, ic_no FROM rsvp_attendees')->fetchAll(PDO::FETCH_ASSOC);
$upd  = $db->prepare('UPDATE rsvp_attendees SET ic_no = ?, ic_hash = ?, ic_last4 = ? WHERE id = ?');
$n = 0;
$db->beginTransaction();
foreach ($rows as $r) {
    $plain = Crypto::isEncrypted($r['ic_no']) ? (string) Crypto::decrypt($r['ic_no']) : trim((string) $r['ic_no']);
    $k = $norm($plain);
    $upd->execute([$plain === '' ? '' : Crypto::encrypt($plain), $k === '' ? null : Crypto::blindIndex($k), $k === '' ? null : substr($k, -4), $r['id']]);
    $n++;
}
$db->commit();
echo "    IC numbers encrypted: {$n}\n";

$keys = $db->prepare("SELECT setting_key, setting_value FROM settings
                      WHERE setting_key IN ('anthropic_api_key','nvidia_api_key','google_api_key')");
$keys->execute();
$set = $db->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
foreach ($keys->fetchAll(PDO::FETCH_ASSOC) as $k) {
    if ($k['setting_value'] !== null && $k['setting_value'] !== '' && !Crypto::isEncrypted($k['setting_value'])) {
        $set->execute([Crypto::encrypt($k['setting_value']), $k['setting_key']]);
        echo "    encrypted: {$k['setting_key']}\n";
    }
}
echo "    Back up storage/keys/app.key together with the database — without it these values cannot be read.\n";
