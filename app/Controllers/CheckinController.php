<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Event;
use App\Models\Rsvp;

/**
 * CheckinController — the counter's arrival desk.
 *
 * Designed around one assumption: on the day, this runs on a volunteer's
 * phone, in a crowded hall, possibly on bad wifi. So:
 *
 *  - Manual reference entry is the PRIMARY path and always works. The
 *    camera scanner is an enhancement layered on top, because cameras
 *    need HTTPS, permission, and light — none guaranteed in a hall.
 *  - Every action is a plain form POST, so it works without JavaScript.
 *  - Check-in is per attendee, since families arrive in pieces.
 */
class CheckinController extends Controller
{
    /** GET /admin/checkin  (optionally ?ref=RSVP-0007) */
    public function index(): void
    {
        $this->requireAdmin();

        $eventModel = new Event();
        $rsvp       = new Rsvp();

        $requestedEvent = (int) ($_GET['event'] ?? 0);
        $event = $requestedEvent > 0 ? $eventModel->find($requestedEvent) : null;
        $event = $event ?? $eventModel->active();
        $eventId = (int) $event['id'];

        $ref     = trim((string) ($_GET['ref'] ?? ''));
        $group   = null;
        $people  = [];
        $notFound = false;

        if ($ref !== '') {
            $group = $rsvp->findByRefCode($ref, $eventId);
            if ($group === null) {
                $notFound = true;
            } else {
                $people = $rsvp->attendeesOf((int) $group['id']);
            }
        }

        $this->view('admin/checkin', [
            'event'      => $event,
            'allEvents'  => $eventModel->all(),
            'ref'        => $ref,
            'group'      => $group,
            'people'     => $people,
            'notFound'   => $notFound,
            'stats'      => [
                'expected'  => $rsvp->totalAttendees($eventId),
                'arrived'   => $rsvp->totalCheckedIn($eventId),
            ],
            'recent'     => $rsvp->recentCheckIns($eventId),
            'flash'      => $this->takeFlash(),
        ]);
    }

    /** POST /admin/checkin/person — check one person in, or undo. */
    public function person(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $rsvp        = new Rsvp();
        $attendeeId  = (int) ($_POST['attendee_id'] ?? 0);
        $undo        = !empty($_POST['undo']);
        $ref         = trim((string) ($_POST['ref'] ?? ''));

        // Confirm the person really belongs to the event being worked on
        // before touching them.
        $eventId = $rsvp->eventIdOfAttendee($attendeeId);
        if ($eventId === null) {
            $this->flash('error', '找不到資料', '找不到這位參加者。');
            $this->redirect('/admin/checkin');
        }

        if ($undo) {
            $rsvp->undoCheckIn($attendeeId);
        } else {
            $rsvp->checkIn($attendeeId);
        }

        $this->backToLookup($eventId, $ref);
    }

    /** POST /admin/checkin/group — check in everyone still outstanding. */
    public function group(): void
    {
        $this->requireAdmin();
        $this->requireCsrf();

        $rsvp    = new Rsvp();
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $ref     = trim((string) ($_POST['ref'] ?? ''));

        $eventId = $rsvp->eventIdOf($groupId);
        if ($eventId === null) {
            $this->flash('error', '找不到資料', '找不到這筆報名。');
            $this->redirect('/admin/checkin');
        }

        $n = $rsvp->checkInGroup($groupId);
        $this->flash('success', '報到完成', "已為 {$n} 位參加者完成報到。");

        $this->backToLookup($eventId, $ref);
    }

    /** Return to the lookup, keeping the reference on screen. */
    private function backToLookup(int $eventId, string $ref): void
    {
        $query = '?event=' . $eventId . ($ref !== '' ? '&ref=' . urlencode($ref) : '');
        $this->redirect('/admin/checkin' . $query);
    }
}
