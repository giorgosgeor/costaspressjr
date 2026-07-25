# Database

This directory holds the SQL schema and migrations for Costaspressjr.

## Layout

- `migrations/` — numbered, idempotent migrations (`001_*.sql`, `002_*.sql`, …) managed by `migrate.php`. Anything new should land here.
- `migrate.php` — the runner. Applies pending migrations and tracks them in the `schema_migrations` table.
- Loose `.sql` and `.php` files at the top of this directory are historical, manually-applied scripts from before the runner existed. Leave them alone unless you're rebuilding from scratch.
- `repair_product_variants.sql` — one-shot data-repair script. See its own header for usage.

## Running migrations

```bash
php database/migrate.php           # apply pending migrations
php database/migrate.php --status  # list applied / pending
php database/migrate.php --baseline# mark all present files as applied
                                   # without running them — use ONCE on an
                                   # existing DB so the runner doesn't try
                                   # to re-apply already-installed schema
```

## On an existing database

If your database already has every table the app needs (you've been running it before the runner existed), do this once:

```bash
php database/migrate.php --baseline
```

That marks each numbered migration file as already applied. From then on, only newly-added migrations will run.

## Adding a new migration

1. Create `migrations/NNN_descriptive_name.sql` with the next number.
2. Make it idempotent where you can (`CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`, etc.). The runner also swallows "Duplicate column", "already exists", "Duplicate key name", and "Can't DROP" errors as a safety net.
3. Run `php database/migrate.php`.

## Building from scratch (fresh install)

The legacy loose files were never numbered, so a clean reinstall is currently a manual job. When you want to formalise this, copy the relevant loose files into `migrations/` with sequential numbers and they'll be picked up automatically.
