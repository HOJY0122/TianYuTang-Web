<?php
/**
 * Migration 016 (data) — counter receipt photos saved before they moved to
 * private storage still sat in public/uploads/receipts, where anyone with
 * the address could open them. Move each into storage/receipts (reachable
 * only through the signed-in /admin/receipt page) and update its record.
 * Safe to run again: only files still in the public folder are moved.
 */
$rows = $db->query("SELECT id, receipt_path FROM donations WHERE receipt_path LIKE 'uploads/receipts/%'")->fetchAll(PDO::FETCH_ASSOC);
$upd  = $db->prepare('UPDATE donations SET receipt_path = ? WHERE id = ?');
$dest = BASE_PATH . '/storage/receipts';
if (!is_dir($dest)) {
    mkdir($dest, 0755, true);
}
$moved = 0;
foreach ($rows as $r) {
    $from = BASE_PATH . '/public/' . $r['receipt_path'];
    $name = basename($r['receipt_path']);
    if (is_file($from) && preg_match('/^[a-f0-9]{16,64}\.(jpe?g|png|gif|webp)$/i', $name) && rename($from, $dest . '/' . $name)) {
        $upd->execute(['receipts/' . $name, $r['id']]);
        $moved++;
    }
}
// Anything else left in the public receipts folder is not linked to a record: move it out of reach too.
foreach (glob(BASE_PATH . '/public/uploads/receipts/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [] as $f) {
    @rename($f, $dest . '/' . basename($f));
}
echo "    receipt photos moved to private storage: {$moved}\n";
