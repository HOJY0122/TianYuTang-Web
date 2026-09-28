<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Live;

/**
 * GET /live?t=posts,donations — the change counters an open page asks
 * about every few seconds (see App\Core\Live and js/live.js).
 */
class LiveController extends Controller
{
    public function poll(): void
    {
        $signedIn = !empty($_SESSION['admin_id']);
        // Release the session lock at once: a check must never make the
        // person's own clicks and saves wait.
        session_write_close();

        $asked  = array_slice(explode(',', (string) ($_GET['t'] ?? '')), 0, 20);
        $topics = $signedIn ? $asked : array_values(array_intersect($asked, Live::PUBLIC_TOPICS));

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        echo json_encode(['v' => Live::versions($topics)]);
        exit;
    }
}
