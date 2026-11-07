<?php
namespace App\Controllers;

use App\Core\Controller;

/**
 * /admin/checkin — the old 現場報到 page, now an address that forwards.
 *
 * Registration is only for the Day 3 meal, so arriving is recorded in one
 * place: 施齋入座 (✓ 確認 = arrived; rsvp_attendees.checked_in_at follows it,
 * see Zai::setPerson). Old bookmarks and the check-in QR flow still work:
 *   ?ref=RSVP-0007  → that registration in Day 3
 *   ?ref=…DON-0012  → the counter (a donation QR)
 */
class CheckinController extends Controller
{
    /** GET /admin/checkin  (optionally ?ref=RSVP-0007) */
    public function index(): void
    {
        $this->requireAdmin();
        $ref = strtoupper(trim((string) ($_GET['ref'] ?? '')));
        $ref = preg_match('/^[A-Z0-9-]{1,40}$/', $ref) ? $ref : '';
        $event = (int) ($_GET['event'] ?? 0);

        if ($ref !== '' && preg_match('/(^|-)DON-\d+$/', $ref)) {
            $this->redirect('/admin/counter?' . http_build_query(array_filter(['event' => $event ?: null, 'ref' => $ref])));
        }
        $this->redirect('/admin/zai/registered' . ($ref !== '' ? '?q=' . urlencode($ref) : ''));
    }
}
