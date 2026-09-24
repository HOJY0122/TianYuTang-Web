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
session_start();

// ---------- 5. Routes ----------
$router = new App\Core\Router();

// Public site
$router->get('/',                   'HomeController@index');
$router->get('/gallery',            'GalleryController@index');
$router->post('/rsvp/submit',       'RsvpController@submit');
$router->post('/donation/submit',   'DonationController@submit');
$router->get('/rsvp/success',       'ConfirmController@rsvp');
$router->get('/donation/success',   'ConfirmController@donation');

// Admin
$router->get('/admin/login',        'AdminController@loginForm');
$router->post('/admin/login',       'AdminController@login');
$router->post('/admin/logout',      'AdminController@logout');
$router->get('/admin/password',     'AdminController@passwordForm');
$router->post('/admin/password',    'AdminController@changePassword');
$router->get('/admin/dashboard',    'AdminController@dashboard');
$router->post('/admin/rsvp/confirm','AdminController@confirmRsvp');
$router->post('/admin/rsvp/cancel', 'AdminController@cancelRsvp');
$router->post('/admin/donation/paid','AdminController@markDonationPaid');
$router->post('/admin/event/activate','AdminController@activateEvent');
$router->post('/admin/event/test-copy',  'AdminController@createTestCopy');
$router->post('/admin/event/test-delete','AdminController@deleteTestEvent');
$router->get('/admin/event/edit',   'AdminController@editEvent');
$router->get('/admin/event/new',    'AdminController@newEvent');
$router->post('/admin/event/save',  'AdminController@saveEvent');

// Counter (cash) donations
$router->get('/admin/counter',      'CounterController@index');
$router->post('/admin/counter/save','CounterController@save');
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

// Photo gallery
$router->get('/admin/photos',          'AdminController@photos');
$router->post('/admin/photos/upload',  'AdminController@uploadPhotos');
$router->post('/admin/photos/caption', 'AdminController@updateCaption');
$router->post('/admin/photos/move',    'AdminController@movePhoto');
$router->post('/admin/photos/delete',  'AdminController@deletePhoto');

// ---------- 6. Go ----------
try {
    $router->dispatch();
} catch (PDOException $e) {
    // A missing table or column means the database has not been brought
    // up to date with the code. Show something a committee member can
    // act on rather than a PHP stack trace.
    //   42S02 = table not found, 42S22 = column not found
    if (in_array($e->getCode(), ['42S02', '42S22'], true)) {
        http_response_code(503);
        $detail = DEBUG_MODE ? $e->getMessage() : '';
        require BASE_PATH . '/app/Views/errors/schema.php';
        exit;
    }
    throw $e;
}
