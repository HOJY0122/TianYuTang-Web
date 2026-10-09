<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Event;
use App\Models\Photo;
use App\Models\PhotoCategory;

/**
 * GalleryController — the public photo albums, one layer at a time:
 *
 *   /gallery                    年份 every year with photos (cards)
 *   /gallery?year=2026          類別 that year's albums: 新年, 中壇千秋寶誕 …
 *   /gallery?year=2026&cat=2    相片 that album's photos (tap to enlarge)
 *
 * Categories are set in Admin → Photos; photos with none are "其他 Others"
 * (cat=0). Test events and hidden categories never show.
 */
class GalleryController extends Controller
{
    /** GET /gallery */
    public function index(): void
    {
        $photos = new Photo();
        $years  = $photos->galleryYears();
        $yearList = array_map(static fn($y) => (string) $y['year'], $years);

        $year = is_string($_GET['year'] ?? null) && in_array($_GET['year'], $yearList, true) ? $_GET['year'] : '';
        $albums = $year !== '' ? $photos->galleryAlbums($year) : [];
        $catIds = array_map(static fn($a) => (int) $a['cat_id'], $albums);
        $cat = $year !== '' && isset($_GET['cat']) && ctype_digit((string) $_GET['cat']) && in_array((int) $_GET['cat'], $catIds, true)
            ? (int) $_GET['cat'] : null;
        // A year with only one album: straight to its photos.
        if ($year !== '' && $cat === null && count($albums) === 1 && !isset($_GET['cat'])) {
            $cat = (int) $albums[0]['cat_id'];
        }
        $album = null;
        foreach ($albums as $a) {
            if ((int) $a['cat_id'] === $cat) {
                $album = $a;
            }
        }

        $this->view('gallery/index', [
            'event'     => (new Event())->active(),   // for header/footer branding
            'activeNav' => 'gallery',
            'years'     => $years,
            'year'      => $year,
            'yearRow'   => $year !== '' ? $years[array_search($year, $yearList, true)] : null,
            'albums'    => $albums,
            'album'     => $album,
            'photos'    => $album ? $photos->galleryPhotos($year, (int) $album['cat_id']) : [],
        ]);
    }
}
