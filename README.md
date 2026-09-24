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
