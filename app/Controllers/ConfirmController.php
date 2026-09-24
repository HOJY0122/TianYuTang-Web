<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\Event;

/**
 * ConfirmController — the "you're registered" page with a QR pass.
 *
 * IMPORTANT: the reference is read from the SESSION, never from the URL.
 *
 * A page like /rsvp/success?ref=RSVP-0007 would look harmless and be a
 * serious leak: reference codes run 0001, 0002, 0003…, so anyone could
 * count upward and read every registration — names, and under PDPA far
 * worse, IC numbers. Keeping it in the session means the page can only
 * be seen by the browser that just submitted the form.
 *
 * For the same reason the QR encodes ONLY the reference code, not a
 * lookup URL. Counter staff scan it, read "RSVP-0007", and find that
 * line on their printed sheet.
 */
class ConfirmController extends Controller
{
    /** GET /rsvp/success */
    public function rsvp(): void
    {
        $this->show('rsvp');
    }

    /** GET /donation/success */
    public function donation(): void
    {
        $this->show('donation');
    }

    private function show(string $kind): void
    {
        $key = $kind . '_confirmation';
        $confirmation = $_SESSION[$key] ?? null;

        // Nothing in the session — a bookmark, a refresh after the page
        // was consumed, or someone guessing the URL. Send them home
        // rather than showing an empty pass.
        if ($confirmation === null) {
            $this->redirect('/');
        }

        // One-shot: clear it so a shared screen or a back button does not
        // keep displaying someone's details.
        unset($_SESSION[$key]);

        $this->view('confirm/success', [
            'event'        => (new Event())->active(),
            'kind'         => $kind,
            'confirmation' => $confirmation,
        ]);
    }
}
