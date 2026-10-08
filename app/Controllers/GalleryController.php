<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Event;
use App\Models\Photo;

/**
 * GalleryController — the public photo archive.
 *
 * Photos are grouped by event, so each year is its own album with no
 * extra bookkeeping. Every year stays visible: creating the 2027 event
 * never hides the 2026 album. Test events are never shown.
 */
class GalleryController extends Controller
{
    /** GET /gallery */
    public function index(): void
    {
        // ?year=2025 shows that year only; no year = every album.
        $all   = (new Photo())->albums();
        $years = array_values(array_unique(array_map(static fn($a) => (string) $a['year'], $all)));
        $year  = is_string($_GET['year'] ?? null) && in_array($_GET['year'], $years, true) ? $_GET['year'] : '';
        $this->view('gallery/index', [
            'event'     => (new Event())->active(),   // for header/footer branding
            'activeNav' => 'gallery',
            'albums'    => $year === '' ? $all : array_values(array_filter($all, static fn($a) => (string) $a['year'] === $year)),
            'years'     => $years,
            'year'      => $year,
        ]);
    }
}
