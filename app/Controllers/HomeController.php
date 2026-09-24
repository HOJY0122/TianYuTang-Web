<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Event;
use App\Models\Photo;

/**
 * HomeController — the public single-page site.
 *
 * All the event-specific content (name, dates, venue, price) comes from
 * the active event row, so the committee changes it in admin rather
 * than asking a developer to edit a view.
 */
class HomeController extends Controller
{
    /** GET / */
    public function index(): void
    {
        $event = (new Event())->active();

        $this->view('home/index', [
            'event'         => $event,
            'dateLines'     => Event::formatDateLines($event),
            'dateRange'     => Event::formatDateRangeEn($event),
            'rsvpWindow'     => Event::windowStatus($event, Event::SECTION_RSVP),
            'donationWindow' => Event::windowStatus($event, Event::SECTION_DONATION),
            'photoPreview'  => (new Photo())->previewForEvent((int) $event['id'], 6),
            'flash'         => $this->takeFlash(),
            'oldInput'      => $this->takeOldInput(),
        ]);
    }

    /** Recover form values after a validation failure, so the visitor
     *  does not have to retype everything. */
    private function takeOldInput(): array
    {
        $old = $_SESSION['old_input'] ?? [];
        unset($_SESSION['old_input']);
        return $old;
    }
}
