<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Event;
use App\Models\Photo;
use App\Models\Post;

/**
 * HomeController — the public front page.
 *
 * The home page is for READING: the banner, a welcome, the event's
 * dates and place (with Waze / Google Maps), news posts, and photo
 * albums. The two forms live on their own pages (/register, /donate)
 * so an older visitor is never faced with one very long page.
 *
 * All content comes from the database — the active event (admin) and
 * site settings (system admin) — so nothing here needs a developer.
 */
class HomeController extends Controller
{
    /** GET / */
    public function index(): void
    {
        $event = (new Event())->active();
        $site  = (new \App\Models\Setting())->site();

        $this->view('home/index', [
            'event'          => $event,
            'activeNav'      => 'home',
            'dateLines'      => Event::formatDateLines($event),
            'dateRange'      => Event::formatDateRangeEn($event),
            'rsvpWindow'     => Event::windowStatus($event, Event::SECTION_RSVP),
            'donationWindow' => Event::windowStatus($event, Event::SECTION_DONATION),
            'posts'          => (new Post())->feed(10),
            // Which year(s) and how many photos: System → Forms & fonts. The gallery has the rest.
            'albums'         => (new Photo())->homeAlbums(
                (string) $site['home_albums'],
                $event,
                max(4, min(40, (int) $site['home_album_photos']))
            ),
            'flash'          => $this->takeFlash(),
        ]);
    }
}
