<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Rsvp;

/**
 * PrintController — paper for the on-site counter.
 *
 * These pages are plain HTML with a print stylesheet rather than
 * generated PDFs. That is a deliberate choice: the browser's own print
 * dialog already produces a PDF on every platform, so a PDF library
 * would add a dependency (and a Composer install on the server) to
 * deliver something the committee can already do with Ctrl+P.
 */
class PrintController extends Controller
{
    /** GET /admin/print/attendees?event=<id> */
    public function attendees(): void
    {
        $this->requireAdmin();

        $event = $this->resolveEvent();
        $rsvp  = new Rsvp();

        $this->view('print/attendees', [
            'event'     => $event,
            'attendees' => $rsvp->attendeeSheet((int) $event['id']),
            'totals'    => [
                'attendees' => $rsvp->totalAttendees((int) $event['id']),
                'groups'    => $rsvp->totalGroups((int) $event['id']),
            ],
            'printedAt' => date('Y-m-d H:i'),
        ]);
    }

    /** GET /admin/print/donations?event=<id> */
    public function donations(): void
    {
        $this->requireAdmin();

        $event    = $this->resolveEvent();
        $donation = new Donation();
        $eventId  = (int) $event['id'];

        $this->view('print/donations', [
            'event'     => $event,
            'donations' => $donation->all($eventId, 1000),
            'totals'    => [
                'pledged' => $donation->totalAmount($eventId),
                'paid'    => $donation->totalPaid($eventId),
                'seats'   => $donation->totalTables($eventId),
                'online'  => $donation->totalsBySource($eventId)['online'],
                'counter' => $donation->totalsBySource($eventId)['counter'],
            ],
            'printedAt' => date('Y-m-d H:i'),
        ]);
    }

    /**
     * GET /admin/qr?event=<id>
     *
     * The event QR for posters and WhatsApp broadcasts. This one DOES
     * encode a URL — it points at the public homepage, which is meant
     * to be shared. Only the per-registration QR has to avoid URLs.
     */
    public function eventQr(): void
    {
        $this->requireAdmin();

        $event = $this->resolveEvent();

        // Build the absolute public URL from the request, so the QR
        // works whether the site is on localhost, a staging host, or
        // the real domain — nobody has to remember to edit a constant.
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $siteUrl = $scheme . '://' . $host . (BASE_URL ?: '') . '/';

        $this->view('print/qr', [
            'event'   => $event,
            'siteUrl' => $siteUrl,
        ]);
    }

    /** The requested event, or the active one. */
    private function resolveEvent(): array
    {
        $eventModel = new Event();
        $requested  = (int) ($_GET['event'] ?? 0);
        $event      = $requested > 0 ? $eventModel->find($requested) : null;

        return $event ?? $eventModel->active();
    }
}
