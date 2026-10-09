<?php
/**
 * FRONT CONTROLLER
 *
 * Every request to this site enters here — there are no other reachable
 * PHP files. This is what makes the MVC split possible: one entry point
 * that boots the app, then hands the request to the Router.
 */

// ---------- 1. Paths ----------
define('BASE_PATH', dirname(__DIR__));           // project root (one level up)

// Folder the site is served from: '' at a domain root, '/tianyutang' in a subfolder.
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
define('BASE_URL', $scriptDir === '/' ? '' : $scriptDir);

// ---------- 2. Configuration ----------
require BASE_PATH . '/config/config.php';


// ---------- 3. Autoloader ----------
// Maps App\Controllers\RsvpController → app/Controllers/RsvpController.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// ---------- 4. Helpers & session ----------
require BASE_PATH . '/app/Core/helpers.php';
// ---------- Security headers (every response) ----------
// Pages may only be shown inside a frame on THIS site (the Wording
// page's live preview). Another website framing the admin pages could
// trick a signed-in admin into clicking buttons they cannot see.
header_remove('X-Powered-By');
header('X-Frame-Options: SAMEORIGIN');
// Content Security Policy: scripts, styles, images and connections only
// from this site (fonts from Google Fonts). Even if someone managed to
// inject HTML, it could not load a script from elsewhere, send data to
// another server, or post a form off-site.
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; "
     . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; "
     . "img-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self'; frame-src 'self'; worker-src 'self' blob:; "
     . "frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self'");
header('X-Content-Type-Options: nosniff');              // files are only what they say they are
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(self), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
header('Cross-Origin-Opener-Policy: same-origin');
if (App\Core\Session::isHttps()) {
    header('Strict-Transport-Security: max-age=31536000');   // browsers stay on HTTPS for a year
} elseif (defined('FORCE_HTTPS') && FORCE_HTTPS && PHP_SAPI !== 'cli') {
    header('Location: https://' . site_host() . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

// Any unexpected error: details go to the server's error log, the visitor
// sees a plain message — never file paths, SQL or a stack trace.
set_exception_handler(static function (Throwable $e): void {
    error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo DEBUG_MODE
        ? '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>'
        : '<!doctype html><meta charset="utf-8"><title>系統錯誤 Error</title><div style="font-family:sans-serif;max-width:32rem;margin:4rem auto;text-align:center">'
          . '<h1>系統暫時出錯</h1><p>請稍後再試，或返回<a href="' . htmlspecialchars(BASE_URL . '/', ENT_QUOTES) . '">首頁</a>。</p>'
          . '<p>Something went wrong. Please try again later.</p></div>';
});

// Photos (signed addresses, App\Core\Media): answered before any session
// starts — no cookie, no session lock while a page loads many pictures.
if (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === BASE_URL . '/media') {
    // Asleep: photos only for signed-in staff (a quick read-only look at the session).
    if (App\Core\Sleep::isOn()) {
        App\Core\Session::start();
        $staff = App\Core\Session::isStaff();
        session_write_close();
        if (!$staff) {
            http_response_code(503);
            header('Retry-After: 86400');
            exit;
        }
    }
    App\Core\Media::serve();
}

App\Core\Session::start();   // cookie settings and time limits: see app/Core/Session.php

// ---------- 5. Routes ----------
$router = new App\Core\Router();

// Public site
$router->get('/',                   'HomeController@index');
$router->get('/gallery',            'GalleryController@index');
$router->get('/about',              'AboutController@show');
$router->get('/register',           'RsvpController@form');
$router->get('/donate',             'DonationController@form');
$router->post('/rsvp/submit',       'RsvpController@submit');
$router->post('/donation/submit',   'DonationController@submit');
$router->get('/rsvp/success',       'ConfirmController@rsvp');
$router->get('/donation/success',   'ConfirmController@donation');

// Admin
// Realtime: change counters for open pages (js/live.js)
$router->get('/live',               'LiveController@poll');
$router->get('/admin/login',        'AdminController@loginForm');
$router->post('/admin/login',       'AdminController@login');
$router->post('/admin/logout',      'AdminController@logout');
$router->get('/admin/dashboard',    'AdminController@dashboard');
$router->get('/admin/no-access',    'AdminController@noAccess');
$router->get('/admin/control',      'AdminController@control');
$router->post('/admin/rsvp/confirm','AdminController@confirmRsvp');
$router->post('/admin/rsvp/cancel', 'AdminController@cancelRsvp');
$router->post('/admin/donation/paid','AdminController@markDonationPaid');
$router->post('/admin/event/activate','AdminController@activateEvent');
$router->post('/admin/event/test-copy',  'AdminController@createTestCopy');
$router->post('/admin/event/test-delete','AdminController@deleteTestEvent');
$router->get('/admin/event/edit',   'AdminController@editEvent');
$router->get('/admin/event/new',    'AdminController@newEvent');
$router->post('/admin/event/save',  'AdminController@saveEvent');

// Records: search, filter and edit every registration and donation
$router->get('/admin/registrations',        'RecordsController@registrations');
$router->get('/admin/registrations/edit',   'RecordsController@editRegistration');
$router->post('/admin/registrations/save',  'RecordsController@saveRegistration');
$router->post('/admin/registrations/status','RecordsController@registrationStatus');
$router->get('/admin/donations',            'RecordsController@donations');
$router->get('/admin/donations/edit',       'RecordsController@editDonation');
$router->post('/admin/donations/save',      'RecordsController@saveDonation');
$router->post('/admin/donations/paid',      'RecordsController@donationPaid');

// News posts on the home page
$router->get('/admin/posts',          'PostController@index');
$router->get('/admin/posts/new',      'PostController@form');
$router->get('/admin/posts/edit',     'PostController@form');
$router->post('/admin/posts/save',    'PostController@save');
$router->post('/admin/posts/delete',  'PostController@delete');
$router->post('/admin/posts/reorder', 'PostController@reorder');

// Walk-in registration at the counter
$router->get('/admin/walkin',       'WalkinController@index');
$router->post('/admin/walkin/save', 'WalkinController@save');

// System administration (system_admin only — enforced in the controller)
$router->get('/system',                'SystemController@index');
$router->get('/system/users',          'SystemController@users');
$router->post('/system/settings',      'SystemController@saveSettings');
$router->post('/system/ai-test',       'SystemController@aiTest');
$router->get('/system/uat',              'UatController@index');
$router->post('/system/uat/toggle',      'UatController@toggle');
$router->post('/system/uat/save',        'UatController@save');
$router->post('/system/uat/reset',       'UatController@reset');
$router->get('/system/banners',          'BannerController@index');
$router->post('/system/banners/add',     'BannerController@add');
$router->post('/system/banners/save',    'BannerController@save');
$router->post('/system/banners/reorder', 'BannerController@reorder');
$router->post('/system/banners/delete',  'BannerController@delete');
$router->get('/system/wording',        'SystemController@wording');
$router->post('/system/wording',       'SystemController@saveWording');
$router->get('/system/wording/preview', 'SystemController@wordingPreview');
$router->post('/system/users/create',  'SystemController@createUser');
$router->post('/system/users/role',    'SystemController@changeRole');
$router->post('/system/users/access', 'SystemController@updateAccess');
$router->post('/system/users/password','SystemController@resetPassword');
$router->post('/system/users/delete',  'SystemController@deleteUser');
$router->get('/system/qr',             'SystemController@qrGenerator');
$router->get('/system/forms',          'FormsController@index');
$router->post('/system/forms',         'FormsController@save');
$router->post('/system/forms/draft',   'FormsController@draft');
$router->post('/admin/event/responses', 'AdminController@toggleResponses');
$router->get('/system/about',           'AboutController@manage');
$router->post('/system/about/save',     'AboutController@save');
$router->post('/system/about/delete',   'AboutController@delete');
$router->post('/system/about/move',     'AboutController@move');
$router->post('/system/about/reorder',  'AboutController@reorder');
$router->post('/system/about/toggle',   'AboutController@toggle');
$router->get('/system/sleep',            'SleepController@index');
$router->post('/system/sleep',           'SleepController@save');
$router->post('/system/sleep/preview',   'SleepController@preview');
$router->get('/system/sleep/download',   'SleepController@download');

// Own password — the one page BOTH roles share, so it sits under
// neither area's prefix.
$router->get('/account/password',      'SystemController@passwordForm');
$router->post('/account/password',     'SystemController@changeOwnPassword');

// Counter (cash) donations
$router->get('/admin/counter',      'CounterController@index');
$router->post('/admin/counter/save','CounterController@save');
$router->post('/admin/counter/receive', 'CounterController@receive');
$router->get('/admin/receipt',      'CounterController@receipt');

// On-site check-in
$router->get('/admin/checkin',         'CheckinController@index');
$router->post('/admin/checkin/person', 'CheckinController@person');
$router->post('/admin/checkin/group',  'CheckinController@group');

// Printable sheets for the counter
$router->get('/admin/print/attendees', 'PrintController@attendees');
$router->get('/admin/print/donations', 'PrintController@donations');
$router->get('/admin/qr',             'PrintController@eventQr');

// CSV downloads
$router->get('/admin/export/attendees', 'ExportController@attendees');
$router->get('/admin/export/donations', 'ExportController@donations');
// Excel links end in "-excel", not ".xlsx": some hosts serve any URL
// ending in a file extension as a static file, so PHP never saw it.
$router->get('/admin/export/attendees-excel', 'ExportController@attendeesExcel');
$router->get('/admin/export/donations-excel', 'ExportController@donationsExcel');
$router->get('/admin/export/attendees.xlsx', 'ExportController@attendeesExcel');
$router->get('/admin/export/donations.xlsx', 'ExportController@donationsExcel');

// Paper receipt book — scanned (AI) or typed in, then kept and searched
$router->get('/admin/receipts',         'ReceiptController@index');
$router->get('/admin/receipts/new',     'ReceiptController@create');
$router->post('/admin/receipts/scan',   'ReceiptController@scan');
$router->get('/admin/receipts/review',  'ReceiptController@review');
$router->get('/admin/receipts/edit',    'ReceiptController@edit');
$router->post('/admin/receipts/save',   'ReceiptController@save');
$router->post('/admin/receipts/delete', 'ReceiptController@delete');
$router->post('/admin/receipts/cancel', 'ReceiptController@cancel');
$router->get('/admin/receipts/bulk',         'ReceiptController@bulk');
$router->post('/admin/receipts/bulk-upload', 'ReceiptController@bulkUpload');
$router->post('/admin/receipts/ai-read',     'ReceiptController@aiRead');
$router->post('/admin/receipts/book',        'ReceiptController@saveBook');
$router->get('/admin/receipts/image',   'ReceiptController@image');
$router->get('/admin/receipts/excel',   'ReceiptController@excel');

// Photo gallery
$router->get('/admin/photos',          'AdminController@photos');
$router->post('/admin/photos/upload',  'AdminController@uploadPhotos');
$router->post('/admin/photos/caption', 'AdminController@updateCaption');
$router->post('/admin/photos/move',    'AdminController@movePhoto');
$router->post('/admin/photos/reorder', 'AdminController@reorderPhotos');
$router->post('/admin/photos/delete',  'AdminController@deletePhoto');

// ---------- 6. Go ----------
// Sleep mode (System → 休眠模式): visitors get the one "see you next year"
// page; the committee's pages keep working (App\Core\Sleep).
$sleepPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (BASE_URL !== '' && str_starts_with($sleepPath, BASE_URL)) {
    $sleepPath = substr($sleepPath, strlen(BASE_URL)) ?: '/';
}
try {
    if (App\Core\Sleep::blocks($sleepPath)) {
        App\Core\Sleep::render();
    }
} catch (PDOException $e) {
    // settings table not ready yet (mid-upgrade): carry on as normal
}

try {
    $router->dispatch();
} catch (PDOException $e) {
    // A missing table or column means the database has not been brought
    // up to date with the code. Show something a committee member can
    // act on rather than a PHP stack trace.
    //   42S02 = table not found, 42S22 = column not found
    // 22001 = a value longer than its column. Every form checks lengths
    // first (audited against the database); this is the safety net, so a
    // future slip shows a clear message instead of an error page.
    if ($e->getCode() === '22001') {
        http_response_code(422);
        header('Content-Type: text/html; charset=utf-8');
        error_log('Data too long: ' . $e->getMessage());
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>內容太長 Too long</title>'
           . '<div style="font-family:sans-serif;max-width:32rem;margin:4rem auto;padding:0 1rem;text-align:center;line-height:1.7">'
           . '<h1 style="color:#9f211b">內容太長</h1><p>其中一個欄位的文字太長，未能儲存。請返回縮短內容後再試。</p>'
           . '<p>One of the boxes has too much text, so nothing was saved. Please go back, shorten it and try again.</p>'
           . '<p><a href="javascript:history.back()" style="color:#9f211b;font-weight:700">← 返回 Go back</a></p></div>';
        exit;
    }
    if (in_array($e->getCode(), ['42S02', '42S22'], true)) {
        http_response_code(503);
        $detail = DEBUG_MODE ? $e->getMessage() : '';
        require BASE_PATH . '/app/Views/errors/schema.php';
        exit;
    }
    throw $e;
}
