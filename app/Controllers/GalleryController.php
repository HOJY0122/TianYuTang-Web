<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Event;
use App\Models\Photo;

/**
 * GalleryController — the public photo archive.
 *
 * Photos are grouped by event, so browsing "2026" versus "2027" needs no
 * separate concept of an album: the event IS the album.
 */
class GalleryController extends Controller
{
    /** GET /gallery  (optionally ?year=<event id>) */
    public function index(): void
    {
        $photoModel = new Photo();
        $eventModel = new Event();

        // Only years that actually have pictures — an empty tab is worse
        // than no tab.
        $years = $photoModel->eventsWithPhotos();

        $requestedId = (int) ($_GET['year'] ?? 0);
        $selected    = null;

        foreach ($years as $year) {
            if ((int) $year['id'] === $requestedId) {
                $selected = $year;
                break;
            }
        }

        // Nothing requested, or a year that has no photos → newest year
        // that does. Falling back rather than 404-ing keeps a stale
        // shared link working.
        if ($selected === null && $years) {
            $selected = $years[0];
        }

        $photos = $selected ? $photoModel->forEvent((int) $selected['id']) : [];

        $this->view('gallery/index', [
            'event'        => $eventModel->active(),   // for header/footer branding
            'years'        => $years,
            'selectedYear' => $selected,
            'photos'       => $photos,
        ]);
    }
}
