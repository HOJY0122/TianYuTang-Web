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
| Site settings — name, logo, banner, favicon, top bar, footer | | ✓ |
| User accounts, QR generator | | ✓ |

The system admin is a super admin: everything an admin can do, plus the
site itself. Admins can never open `/system`.

## Public site

- `/` — banner, welcome, event information (with Waze QR), news, photo albums
- `/register` — one reference number for the whole group, every person's details
- `/donate` — merit seats, freewill, or both in one donation
- `/gallery` — every year's album; older years stay after a new event is created
