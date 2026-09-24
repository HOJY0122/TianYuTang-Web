# 天玉堂 2026 中壇元帥千秋寶誕 — Event Registration System

A PHP + MySQL web application for event registration (報名) and merit
donations (功德布施), built on a hand-rolled MVC architecture with no
framework dependencies.

## Updating the database

After pulling new code, bring the database up to date from the project root:

```bash
php bin/migrate.php --status   # see what has run and what is pending (changes nothing)
php bin/migrate.php            # back up, then run everything pending
```

- It remembers what has run (in a `schema_migrations` table), so running it
  again is always safe. On an older database it works out what is already
  there the first time.
- It uses the database name from `config/config.php`, not the
  `USE tianyutang2026;` line in each file, so it works with hosting
  prefixes such as `myacc_tianyutang`.
- It stops at the first error and tells you which statement failed.
- **Back up first:** `mysqldump -u USER -p DBNAME > backup.sql`

A fresh install still starts with importing `schema.sql`; the command then
simply records that everything is already in place.

Adding a migration: create the next numbered file in `migrations/`
(e.g. `008_add_something.sql`) and run the command.

## Who does what

| | Admin 管理員 | System admin 系統管理員 |
|---|---|---|
| Dashboard, charts, CSV downloads, printing | ✓ | ✓ |
| Registrations & donations — search, edit every record | ✓ | ✓ |
| Event details — dates, venue, welcome text, Waze / Google Maps, form notes | ✓ | ✓ |
| News posts on the home page, photo albums | ✓ | ✓ |
| On the day — check-in, walk-in registration, counter donations | ✓ | ✓ |
| Receipts — scan the paper receipt book (AI), search, edit, export | ✓ | ✓ |
| Site settings — name, logo, banner, favicon, top bar (on/off), footer, printout letterhead | | ✓ |
| Wording — every fixed text on the public site, both languages | | ✓ |
| User accounts, QR generator | | ✓ |

The system admin is a super admin: everything an admin can do, plus the
site itself. Admins can never open `/system`.

## Public site

- `/` — banner, welcome, event information (with Waze QR), news, photo albums
- `/register` — one reference number for the whole group, every person's details
- `/donate` — merit seats, freewill, or both in one donation
- `/gallery` — every year's album; older years stay after a new event is created

## Exports, printing and limits

- **Excel** (`📊 Excel` buttons) — a Dashboard sheet of totals plus a Master
  sheet of every row, with borders, shaded rows, filters, frozen headers, a
  Status dropdown and A4 print setup, laid out like the committee's own
  spreadsheets. Built without the PHP zip extension, so it works on any
  host. CSV is still available.
- **Printed sheets / PDF** carry a letterhead (logo, name, organisation,
  address), the document title in both languages, who printed it, and
  page numbers. Use the browser's *Save as PDF*.
- **Event QR** can show the site logo in the middle; it still scans
  (error correction H).
- **Online donation limits** — set min/max merit seats and freewill per
  event under *活動資料 Event details*. Above a maximum the donor is thanked
  and asked to submit again or visit the counter. Counter donations are
  never limited.
- **IC and phone numbers** are checked in the browser and on the server,
  and stored in one format (`650101-10-1234`, `012-345 6789`).

## Wording, images and the counter

- **網站文字 Wording** (system admin) — every fixed text on the public site,
  one tab per page, with that page shown live beside the boxes as you type.
  Boxes hold what the site shows now; **empty = show nothing**, ↺ puts the
  default back. Includes the ⓘ help panel text and printout titles.
- **Date line** — the home page writes it from the event dates; fill in
  *日期文字 Date line* in Event details to use your own words (e.g. the lunar date).
- **Uploading pictures** — after choosing a file, an editor opens: drag to
  move, zoom, pick a crop shape, rotate. You see the result before saving.
- **Donation QR at the counter** — a donor shows the QR from their donation
  confirmation; *現場布施 Counter donation* scans or looks it up, shows the
  amount, and *確認收款* marks it paid with the time and the staff name.
  A donation QR scanned at check-in (or a registration QR at the counter)
  opens the right page automatically.

## Receipts (收據紀錄)

The temple's paper receipt book, kept online.

1. **📷 掃描收據 Scan receipt** — photograph a receipt (rotate / crop if needed).
2. **🤖 AI 讀取** — Claude (Anthropic) reads the handwriting into a draft:
   number, date, name, item, each box (布施 祈福 蓮花燈 添油 龍香 塔香 樂捐 施齋 其他),
   Cash / Bank-In, total and issued-by. Fields it is unsure of are yellow.
3. **Check it against the photo, then Save.** Nothing is stored before that.

The list can be searched, filtered by date and payment, sorted by any
column, and exported to Excel. Photos are private (`storage/receipts`).

**Choosing the AI service:** Site settings → ⑤ AI offers **Anthropic Claude**
(most accurate on handwritten Chinese, paid per use) or **NVIDIA**
(build.nvidia.com, free credits, open vision models such as
`meta/llama-3.2-90b-vision-instruct` — expect more corrections). Each has
its own key box; NVIDIA also has a model box. Test connection checks the
service that is selected.

**Turning AI reading on:** a system admin opens 網站設定 Site settings →
⑤ AI, pastes an API key from console.anthropic.com, presses
**🔌 測試連線 Test connection** (a free check), then Save. The key can also go in
`config/config.php` (`define('ANTHROPIC_API_KEY', 'sk-ant-…');`) or the
server's `ANTHROPIC_API_KEY` environment variable — the settings page shows
which one is in use, and points out common pasting mistakes. Without a key,
receipts are typed in by hand beside the photo. `app/Core/cacert.pem`
(Mozilla's certificate list) is used only if the server cannot check HTTPS
certificates itself, as on many Windows XAMPP installs.
Each reading is one request to Claude Opus 5 (`claude-opus-5`); the
key is billed per use by Anthropic.

## Ordering

Photos and news posts are put in order by **dragging** (mouse or finger);
the order saves when you let go. The ↑ ↓ buttons on photos still work.
Registration, donation and receipt lists sort by clicking a column heading.
