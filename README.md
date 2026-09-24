# 天玉堂 2026 中壇元帥千秋寶誕 — Event Registration System

A PHP + MySQL web application for event registration (報名) and merit
donations (功德布施), built on a hand-rolled MVC architecture with no
framework dependencies.

---

## 1. Architecture

The project follows the **Model–View–Controller** pattern. The guiding
rule is that each layer has exactly one job, and no layer reaches past
its neighbour:

| Layer | Folder | Responsibility | Never does |
|---|---|---|---|
| **Model** | `app/Models/` | Database access, business rules (e.g. how a donation total is calculated) | Echo HTML, read `$_POST` |
| **View** | `app/Views/` | Presentation only | Run SQL, decide logic |
| **Controller** | `app/Controllers/` | Read the request, validate it, call a Model, choose a View | Contain SQL |

A request flows in one direction:

```
Browser
   │
   ▼
public/index.php          ← front controller: the ONE entry point
   │  boots config, autoloader, session
   ▼
app/Core/Router.php       ← matches "POST /rsvp/submit" to a handler
   │
   ▼
app/Controllers/…         ← validates input, decides what happens
   │
   ▼
app/Models/…              ← talks to MySQL, returns plain arrays
   │
   ▼
app/Views/…               ← renders HTML
   │
   ▼
Browser
```

### Why a front controller?

Every request enters through `public/index.php`. Nothing else in the
project is reachable from a browser — `app/` and `config/` sit *outside*
the web root. This means database credentials and business logic cannot
be requested directly, even if someone guesses the file names.

### Folder layout

```
tianyutang/
├── public/                     ← the ONLY folder exposed to the web
│   ├── index.php               ← front controller
│   ├── .htaccess               ← rewrites all requests to index.php
│   └── assets/css/
│       ├── style.css           ← public site
│       └── admin.css           ← admin area
│
├── app/
│   ├── Core/                   ← the mini-framework
│   │   ├── Database.php        ← single shared PDO connection
│   │   ├── Model.php           ← base model + query helpers
│   │   ├── Controller.php      ← base controller: view/redirect/flash/CSRF
│   │   ├── Router.php          ← URL → Controller@action table
│   │   └── helpers.php         ← h(), url(), asset(), csrf_field(), rm()
│   │
│   ├── Models/
│   │   ├── Rsvp.php            ← registrations + attendees
│   │   ├── Donation.php        ← donations + merit tables
│   │   └── AdminUser.php       ← committee login accounts
│   │
│   ├── Controllers/
│   │   ├── HomeController.php
│   │   ├── RsvpController.php
│   │   ├── DonationController.php
│   │   └── AdminController.php
│   │
│   └── Views/
│       ├── layouts/            ← header.php, footer.php
│       ├── partials/           ← modal.php
│       ├── home/index.php
│       ├── admin/              ← login.php, dashboard.php
│       └── errors/404.php
│
├── config/config.php           ← the only file you edit per environment
├── schema.sql                  ← database structure
├── server.php                  ← local dev server helper (delete before upload)
└── README.md
```

---

## 2. Database

Five tables, with `events` as the spine:

- **`events`** — one row per year or season. Holds everything the
  committee changes yearly: name, dates, venue, merit-seat price,
  attendee limit, banner, and the submission windows.
- **`rsvp_groups`** — one row per submission (`RSVP-0001`, head count,
  status), belonging to one event
- **`rsvp_attendees`** — one row per person, linked by `group_id`
- **`donations`** — one row per donation (`DON-0001`, method, amount,
  status), belonging to one event
- **`admin_users`** — committee logins, passwords stored as bcrypt hashes

### Why events exist

Nothing about a particular year is hardcoded. Change the dates, venue or
price on the event row and the homepage follows — no code edit, no
redeploy. Registrations and donations each carry an `event_id`, so 2027's
dashboard never shows 2026's numbers, and each year's figures stay
separable for as long as the temple keeps the records.

Exactly one event is `is_active` at a time; that is the one the public
site shows and the one new submissions attach to. Admin switches it.

### Running the event from admin

No SQL needed for any of this:

- **編輯活動資料** — change name, year, lunar year, subtitle, venue,
  dates, counter note, merit-seat price and attendee limit. Saving
  updates the homepage immediately.
- **新增活動** — create next year's event. It is prefilled from the
  current one and created **inactive**, so building 2027 never disturbs
  the live 2026 site.
- **設為公開** — make an event the live one. The public site and all new
  submissions switch to it.

`is_active` and `is_test` are deliberately *not* editable through the
form — they have their own actions, and the field whitelist in
`Event::EDITABLE` means a crafted POST cannot set them.

### Submission windows

RSVP and donations each have an optional open and close time, set on the
event. Leave both blank and that section stays open indefinitely; set a
close time and it shuts itself at that moment, like a ticket sale.

The window is enforced in the **controller**, not just by hiding the
form. A hidden form is still submittable by anyone who crafts the POST
or leaves a stale tab open past the deadline, so presentation alone is
not protection.

When a section is closed the page explains why and points people at the
on-site counter, rather than simply removing the form with no
explanation.

### Time zone

`APP_TIMEZONE` in `config/config.php` is `Asia/Kuala_Lumpur`, and the
PDO connection sets MySQL's session time zone to match.

This matters more than it looks. Servers commonly run in UTC — AWS
Lightsail does — and MySQL `DATETIME` columns store no zone at all.
Without pinning both, a window set to close at 23:59 would actually
close at 07:59 the next morning, and `created_at` on the dashboard would
read eight hours behind every other time the committee sees.

### Delete behaviour — deliberately different per table

- `rsvp_attendees` → `ON DELETE CASCADE`: removing a registration takes
  its attendees with it, so no orphan rows are left behind.
- `rsvp_groups` and `donations` → `ON DELETE RESTRICT` on `event_id`:
  deleting an event that still has registrations or donations is
  **refused** by the database. A year's records should never disappear
  because someone tidied up the events list.

### Photo gallery

`event_photos` stores two files per picture: a display copy capped at
1600px and a thumbnail capped at 600px. The grid only ever loads
thumbnails — a 3000×2000 phone photo becomes a 4KB thumbnail, so a full
page of twenty is a few hundred KB rather than tens of megabytes. That
matters because most of this site's visitors are 長輩 on mobile data.

Photos belong to an event, so the gallery groups itself by year with no
separate album concept, and the year switcher only lists years that
actually have pictures.

Unlike registrations, `event_photos` uses `ON DELETE CASCADE`: photos
are illustrative rather than records the committee is accountable for,
so removing an event should take its pictures rather than block.

### Printed sheets for the counter

Two printable pages, linked from the dashboard:

- **報到表** — every attendee with a tick box, sorted **by name**. At the
  counter a person says their name; nobody arrives quoting RSVP-0042.
  Cancelled registrations are excluded so they have no tick box waiting.
- **布施名單** — donations with totals and a treasurer sign-off.

These are plain HTML with a print stylesheet, not generated PDFs. The
browser's own print dialog already saves a PDF on every platform, so a
PDF library would add a server dependency to deliver something Ctrl+P
already does.

`print.css` makes the table survive pagination: `thead` repeats on every
page (`display: table-header-group`), rows never split across a page
break, and the zebra striping is dropped because it costs toner and
lowers contrast on paper.

### CSV exports

`⬇️ CSV` beside each dashboard table downloads the data for the selected
event. CSV rather than `.xlsx` deliberately — no library, no Composer
install, and Excel, Numbers, LibreOffice and Sheets all open it.

Two details decide whether the file is usable, and both are handled in
`ExportController`:

**UTF-8 BOM.** Excel on Windows assumes the system codepage unless the
file starts with a byte-order mark, so without it every Chinese name
opens as mojibake. Other tools ignore the BOM harmlessly.

**CSV injection.** Every name and phone number in these files was typed
by a member of the public. A cell starting `=`, `+`, `-` or `@` is
treated as a *formula* by every major spreadsheet, so a "name" of
`=HYPERLINK("http://evil","click")` becomes a live link in the
treasurer's spreadsheet. Those cells are prefixed with a single quote,
which makes them literal text; the quote is not visible once opened.

**`fputcsv()` and PHP 8.4.** The `$escape` argument must be passed
explicitly — relying on the default emits a deprecation notice that is
printed *into the file*, breaking every row. Unlike a broken web page, a
broken CSV fails silently in Excel.

### QR codes

Two kinds, generated in the browser by `public/assets/js/qrcode.min.js`
— a self-contained esbuild bundle of the `qrcode` npm package. Vendored
rather than loaded from a CDN: the hall wifi is unreliable and a CDN is
one more thing that can be blocked or change underneath you.

**Event QR** (`/admin/qr`) encodes the public site URL for posters and
WhatsApp. It uses error-correction level `H`, which still scans with
roughly 30% of the code obscured — posters get scuffed and photographed
at an angle. The download button renders at 1200px for print.

**Registration QR** appears on the confirmation page after someone
submits. It encodes **only the reference code** (`RSVP-0007`), never a
URL.

That distinction is a privacy decision, not a style one. Reference codes
are sequential, so a page like `/rsvp/success?ref=RSVP-0007` would let
anyone count upward and read every registration — names, and under PDPA
far more seriously, IC numbers. The confirmation page therefore reads
from the **session**, is consumed on first view, and redirects home if
there is nothing to show.

### Counter (cash) donations

The public form only covers people who pledge online. `/admin/counter`
covers the other half: somebody walks up on the day, hands over cash,
and a volunteer writes a paper receipt. Without this none of that money
appears in the system and the treasurer's totals are simply wrong.

A counter donation differs from a public one, deliberately:

- **Marked paid on save** — the money is already in hand.
- **Stamped with the admin who entered it** (`recorded_by`). Cash
  without an owner is how money goes missing.
- **The paper receipt can be photographed and attached**, so a disputed
  amount can be checked against the original.
- **Submission windows do not apply** — the counter runs on the day,
  often after online registration has closed.
- Contact number is **optional**; refusing a donation over a missing
  phone number would be absurd.

Totals are split online vs counter on the dashboard, the printed sheet
and the CSV, so the cash box can be reconciled against one figure rather
than by subtraction.

### On-site check-in

`/admin/checkin` is the counter's arrival desk, built for a volunteer's
phone in a crowded hall:

- **Manual reference entry is the primary path** and always works.
  Camera scanning is layered on top, because cameras need HTTPS, a
  permission grant and decent light — none guaranteed in a temple hall.
  The scan button disables itself with an explanation when the page is
  not on HTTPS, rather than silently doing nothing.
- Every action is a plain form POST, so it works without JavaScript.
- **Check-in is per attendee, not per registration.** A family of four
  registers once but does not always arrive together, so `checked_in_at`
  lives on `rsvp_attendees`. There is also a "check in everyone" button
  for the common case.
- Check-in is **idempotent** — scanning the same person twice does not
  move their arrival time — and every check-in can be undone.
- Lookups are **scoped to the event**, so a code from another year is
  reported as not found rather than checking in the wrong family.

The printed check-in sheet shows arrival state too, so paper and screen
agree if the counter switches between them mid-event.

### Upgrading an existing v1 database

A fresh install just loads `schema.sql`. If you already have a v1
database with live data, run the migration instead — it creates
`events`, attaches every existing row to the 2026 event, then locks the
foreign keys down:

```bash
mysqldump -u USER -p tianyutang2026 > backup.sql   # always first
mysql -u USER -p < migrations/001_add_events.sql       # events table
mysql -u USER -p < migrations/002_add_event_photos.sql # photo gallery
mysql -u USER -p < migrations/003_add_checkin.sql      # on-site check-in
mysql -u USER -p < migrations/004_add_counter_donations.sql # cash at the counter
mysql -u USER -p < migrations/005_add_roles_and_settings.sql # roles + site settings
```

Run them in order. Each one is safe to run twice.

Migration 005 promotes **every existing admin account to
`system_admin`**, so nobody is locked out of the settings the moment it
runs. Demote the accounts that should be plain admins afterwards, from
系統設定 → 管理帳號.

It prints a row count at the end; `rows_total` and `rows_with_event`
must match for both tables.

> **Importing on the command line:** both `schema.sql` and the migration
> start with `SET NAMES utf8mb4;`. Without it the `mysql` CLI reads the
> file as latin1 and stores every Chinese character double-encoded
> (中 becomes ä¸­). Keep that line if you edit these files.

### Reference codes

Codes such as `RSVP-0007` are generated from the row's own
auto-increment id, immediately *after* the insert. This matters: reading
`MAX(id)` beforehand would produce gaps whenever a row is deleted, and
two people submitting at the same moment would compute the same code and
collide on the unique index.

---

## 3. Installation

### Local (XAMPP)

1. Copy the project into `C:\xampp\htdocs\tianyutang`
2. Start Apache and MySQL from the XAMPP control panel
3. Open phpMyAdmin → **Import** → choose `schema.sql` → **Go**
4. Open `config/config.php` and set your DB user (XAMPP default is
   `root` with an empty password)
5. Visit **http://localhost/tianyutang/**

### Local (no XAMPP — PHP built-in server)

```bash
php -S localhost:8000 -t public server.php
```
Then open http://localhost:8000

### Shared hosting (cPanel)

1. **MySQL Databases** → create a database and a user, and attach the
   user to the database with *All Privileges*
2. **phpMyAdmin** → select the database → **Import** → `schema.sql`
3. Edit `config/config.php` with the database name, user and password
   cPanel gave you (these usually carry an account prefix, e.g.
   `myacc_tianyutang`)
4. Upload the project folder via File Manager or FTP
5. **Preferably**, point the domain's document root at `public/`.
   If your host does not allow that, just upload everything into
   `public_html` — the root `.htaccess` forwards requests into
   `public/` automatically, and `app/` and `config/` stay protected.

---

## 4. Before going live — checklist

- [ ] `config/config.php` → set `DEBUG_MODE` to **`false`**
      (otherwise PHP errors, including SQL, are shown to visitors)
- [ ] Log in and change the default admin password
- [ ] Confirm `app/` and `config/` are not browsable
      (try `yoursite.com/config/config.php` — it must not display)
- [ ] Verify the event dates (16, 17 & 18 Oct 2026), venue and 功德席 price

### Admin roles

There are two roles, and **system admin is a superset of admin** — not a
parallel account type. A system admin can do everything an admin can,
plus the settings below.

| | 管理員 admin | 系統管理員 system_admin |
|---|---|---|
| Dashboard, registrations, donations | ✅ | ✅ |
| Excel export, print sheets, check-in, counter donations | ✅ | ✅ |
| Create / edit events, activate, test mode | ✅ | ✅ |
| Photo gallery upload and ordering | ✅ | ✅ |
| Hero banner and favicon | ❌ | ✅ |
| Site name and tagline | ❌ | ✅ |
| Admin accounts (create, promote, reset, delete) | ❌ | ✅ |
| QR code generator | ❌ | ✅ |

The split is enforced **server-side** in `SystemController` and in
`AdminController::saveEvent()`, not by hiding buttons: a plain admin who
crafts the POST by hand still cannot change branding or promote
themselves. Two safety rails exist by design — you cannot delete your
own account, and you cannot remove or demote the **last** system admin,
so the system can never end up with nobody able to administer it.

Everyone should have their own account rather than sharing one. Counter
donations record who took the money in `recorded_by`, and that column is
worthless if the whole committee logs in as `admin`.

### Default admin login

```
Username: admin
Password: tianyutang2026
```

**Change this immediately.** The easiest way is in the browser:
log in, open **系統設定 → 更改我的密碼** (`/system/password`). Creating
the committee's individual accounts is on the same page's 管理帳號
section, which also avoids anyone needing phpMyAdmin.

If you are locked out and have to do it in SQL, generate a hash:

```bash
php -r "echo password_hash('your-new-password', PASSWORD_DEFAULT);"
```

Then update it in phpMyAdmin:

```sql
UPDATE admin_users SET password_hash = 'paste-the-hash-here' WHERE username = 'admin';
```

---

## 5. Security measures in place

| Risk | Mitigation |
|---|---|
| SQL injection | PDO prepared statements everywhere; `EMULATE_PREPARES` off |
| XSS | Every dynamic value printed through `h()` (`htmlspecialchars`) |
| CSRF | A per-session token required on every POST route |
| Session fixation | `session_regenerate_id(true)` on successful login |
| Price tampering | Merit-table totals computed server-side from `MERIT_TABLE_PRICE`, never from the submitted form value |
| Username enumeration | Login returns the same message and takes similar time whether the user exists or not |
| Source code exposure | `app/` and `config/` outside the web root, plus deny-all `.htaccess` |
| Password storage | bcrypt via `password_hash()` — plain passwords are never stored |
| Privilege escalation | Role checked server-side on every system route; branding fields stripped from a plain admin's event POST before it reaches the model |
| Lockout | The last system admin cannot be deleted or demoted, and no account can delete itself |
| Password change | Changing your own password requires the current one, so a walk-up at an unlocked screen cannot take the account over |

---

## 6. Routes

| Method | Path | Handler |
|---|---|---|
| GET | `/` | `HomeController@index` |
| POST | `/rsvp/submit` | `RsvpController@submit` |
| POST | `/donation/submit` | `DonationController@submit` |
| GET | `/admin/login` | `AdminController@loginForm` |
| POST | `/admin/login` | `AdminController@login` |
| GET | `/admin/logout` | `AdminController@logout` |
| GET | `/admin/dashboard` | `AdminController@dashboard` |
| POST | `/admin/rsvp/confirm` | `AdminController@confirmRsvp` |
| POST | `/admin/rsvp/cancel` | `AdminController@cancelRsvp` |
| POST | `/admin/donation/paid` | `AdminController@markDonationPaid` |
| POST | `/admin/event/activate` | `AdminController@activateEvent` |
| GET | `/admin/event/edit?id=` | `AdminController@editEvent` |
| GET | `/admin/event/new` | `AdminController@newEvent` |
| POST | `/admin/event/save` | `AdminController@saveEvent` |
| GET | `/gallery` | `GalleryController@index` |
| GET | `/admin/counter` | `CounterController@index` |
| POST | `/admin/counter/save` | `CounterController@save` |
| GET | `/admin/checkin` | `CheckinController@index` |
| POST | `/admin/checkin/person` | `CheckinController@person` |
| POST | `/admin/checkin/group` | `CheckinController@group` |
| GET | `/rsvp/success` | `ConfirmController@rsvp` |
| GET | `/donation/success` | `ConfirmController@donation` |
| GET | `/admin/qr` | `PrintController@eventQr` |
| GET | `/admin/export/attendees` | `ExportController@attendees` |
| GET | `/admin/export/donations` | `ExportController@donations` |
| GET | `/admin/print/attendees` | `PrintController@attendees` |
| GET | `/admin/print/donations` | `PrintController@donations` |
| GET | `/admin/photos` | `AdminController@photos` |
| POST | `/admin/photos/upload` | `AdminController@uploadPhotos` |
| POST | `/admin/photos/caption` | `AdminController@updateCaption` |
| POST | `/admin/photos/move` | `AdminController@movePhoto` |
| POST | `/admin/photos/delete` | `AdminController@deletePhoto` |
| GET | `/system` | `SystemController@index` |
| POST | `/system/settings` | `SystemController@saveSettings` |
| POST | `/system/users/create` | `SystemController@createUser` |
| POST | `/system/users/role` | `SystemController@changeRole` |
| POST | `/system/users/password` | `SystemController@resetPassword` |
| POST | `/system/users/delete` | `SystemController@deleteUser` |
| GET | `/system/password` | `SystemController@passwordForm` |
| POST | `/system/password` | `SystemController@changeOwnPassword` |
| GET | `/system/qr` | `SystemController@qrGenerator` |

To add a page: register the route in `public/index.php`, add the method
to a controller, add the view under `app/Views/`.

---

## 7. Notes for whoever maintains this next

- Cancelled registrations are excluded from the head count, but the rows
  are kept rather than deleted, so the committee retains a full record.
- The donation dashboard separates **pledged** (everything submitted)
  from **received** (marked paid), because a submitted form is not yet
  money in hand.
- `MAX_ATTENDEES` and `MERIT_TABLE_PRICE` live in `config/config.php`.
  Changing the price there updates the form, the running total and the
  stored amount together — do not hard-code it anywhere else.
- **The QR generator's finder patterns must stay solid squares.** The
  styled modes (圓點 / 留縫 / 漸層) paint only the *data* modules; the
  three corner squares and the alignment patterns are always drawn
  solid, because those are what a scanner uses to find the code and
  correct for a tilted phone. Styling them makes the code undetectable
  at *every* error-correction level — this is not theoretical, it was
  the first version's behaviour and 74 of 156 test renders failed to
  decode. If you touch `render()` in `app/Views/system/qr.php`, re-check
  a styled code with a real phone before shipping it.
- Site name and tagline come from the `settings` table, read once per
  request by `Setting::all()`. Anything else that should be editable
  without a deploy belongs there too, rather than in `config.php`.
