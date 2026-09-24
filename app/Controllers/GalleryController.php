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
        $this->view('gallery/index', [
            'event'     => (new Event())->active(),   // for header/footer branding
            'activeNav' => 'gallery',
            'albums'    => (new Photo())->albums(),
        ]);
    }
}
