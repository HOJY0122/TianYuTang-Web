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
        // Checks never extend a session, and an idle or stolen one gets public answers only.
        $signedIn = \App\Core\Session::isStaff();
        // A system admin signed in again on another device? Tell
        // the page, which reloads and lands on the sign-in page with the reason.
        $out = $signedIn && \App\Core\Session::singleDevice() && \App\Core\Session::replacedElsewhere();
        $signedIn = $signedIn && !$out;
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
        // 't' = the server clock in ms, so countdowns (js/window-timer.js) follow
        // the server's time, never a phone that runs a few minutes off.
        echo json_encode(['v' => Live::versions($topics), 't' => (int) round(microtime(true) * 1000)] + ($out ? ['out' => 1] : []));
        exit;
    }
}
