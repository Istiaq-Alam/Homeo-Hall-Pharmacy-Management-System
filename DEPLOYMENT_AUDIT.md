# Homeo-Hall (HPMS) — Deployment Audit

Audit date: 2026-11-16. Scope: existing repository only; no cloud resources were
created and no production database was touched.

## 1. Executive summary

The application is a conventional Laravel monolith with server-rendered Blade
views and a MySQL database. It can run in a container on Render Free behind
Render's TLS proxy, provided that:

1. the database is TiDB Cloud Starter (MySQL protocol, port 4000, TLS), and
2. permanent uploads move to a private Cloudflare R2 bucket.

Both were prepared in the repository. The container builds and starts locally,
the HTTP smoke tests pass, and the application test suite passes. **Live TiDB,
R2 and Render checks have not been run** because no cloud resources or
credentials exist in this environment. Treat cloud compatibility as
unverified until the checks in this document are completed.

## 2. Current architecture

| Item | Finding |
|---|---|
| Framework | Laravel **13.42.0** (`laravel/framework`), standard MVC + Blade |
| Required PHP | `^8.3` in `composer.json`; platform pinned to **8.4.27**; runtime image is `php:8.4-apache` |
| Local database | MariaDB **11.8.9**, database name `hpms-db` (hyphen), 22 tables, InnoDB, `utf8mb4` / `utf8mb4_unicode_ci` |
| Frontend | Vite **8.3.8**, Tailwind CSS **4.3.5**, Alpine.js, Chart.js, Lucide; `npm run build` |
| Entry point | `hpms/public/index.php`; Nginx/Apache must point at `hpms/public` |
| Auth | Session-based email/password login, Laravel hashing, throttled login; roles `admin`, `owner`, `staff` with chambers |
| Sessions / cache | Database driver for both (`sessions`, `cache`, `cache_locks` tables) |
| Queue | Database driver by default, but **no job is dispatched anywhere**; production is set to `sync` |
| Mail / scheduler | No application mail, notifications, scheduled tasks or external API calls |
| Exports | CSV and custom XLSX (`App\Support\SimpleXlsxWriter`), ZIP business-data export (`ext-zip`), PDF via Dompdf with optional headless Chromium for shaped Bangla text |
| Uploads | Chamber logo and user avatar only, stored on the `public` disk, references kept in DB (`settings.chamber_logo`, `users.avatar_path`, `settings.chamber_brand_assets`) |

PHP requirements: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `zip` (backup ZIP), and `gd` for logo-derived brand icons. `intl` is not used. OPcache is included in the image.

## 3. Database findings

### Schema

22 tables, 10 migrations, no stored procedures, functions, triggers, events or
FULLTEXT indexes. Business tables use foreign keys and composite unique
indexes; the chamber migration also backfills data with `COALESCE(...)`.

Tables: `cache`, `cache_locks`, `chambers`, `expense_categories`, `expenses`,
`failed_jobs`, `job_batches`, `jobs`, `medicine_categories`, `medicines`,
`migrations`, `password_reset_tokens`, `purchase_items`, `purchases`,
`sale_items`, `sales`, `security_logs`, `sessions`, `settings`,
`stock_adjustments`, `stock_movements`, `users`.

### TiDB Cloud Starter compatibility

TiDB documents the following relevant limitations:

- No stored procedures/functions, triggers, events, UDFs, or FULLTEXT syntax/indexes (FULLTEXT is limited to some AWS Starter regions and is not used here).
- No descending indexes, no `SKIP LOCKED`, no `CHECK TABLE`/`CHECKSUM TABLE`, no `CREATE TABLE ... AS SELECT`.
- `utf8mb4` is supported, including `utf8mb4_unicode_ci` and `utf8mb4_0900_ai_ci`; TiDB's default is `utf8mb4_bin`. The schema's `utf8mb4_unicode_ci` is valid.
- Foreign keys are supported on current TiDB versions; verify the exact cluster version in the TiDB console.
- Default collation, `explicit_defaults_for_timestamp=ON`, and `lower_case_table_names=2` differ from a Linux MySQL server but do not affect this schema.
- **Locking:** `SELECT ... FOR UPDATE` only locks in **pessimistic** transaction mode. New TiDB clusters default to pessimistic, but this must be confirmed with `SELECT @@tidb_txn_mode`.
- TiDB has **no gap locks**. Range predicates are not fully protected. This application locks medicines by primary key only (`whereKey`, `whereIn(...)->lockForUpdate()`), so the purchase/sale stock updates use point locks and are compatible — but this must be tested under real concurrency on TiDB.
- TiDB Starter free quota (MySQL compatibility): 5 GiB row storage, 5 GiB columnar storage, **50 million request units/month**, up to 400 concurrent connections, connections idle for more than 30 minutes may be terminated, transactions cannot exceed 30 minutes. When the quota is exhausted, new connections are refused until the next month or a spending limit is configured.
- Starter does not support changefeeds, data migration tooling, audit logs, or third-party monitoring integrations.

**Verdict:** no schema change is required for the features this application uses, but TiDB compatibility must be proven by importing the dump and running the verification and concurrency checks. No code was changed to work around TiDB.

### Migrations

Production migrations are **never** run at image build or container start. The
recommended path is a full dump/import (which preserves the `migrations`
ledger). If a later deploy needs a migration, run it manually from a controlled
machine with production environment variables, never `migrate:fresh`,
`db:wipe` or `schema:drop`.

## 4. Storage findings

Permanent files:

| What | DB reference | Key format |
|---|---|---|
| Chamber logo | `settings.chamber_logo` | `branding/<random>/logo.<ext>` |
| Derived PWA icons/splash | `settings.chamber_brand_assets` (JSON) | `branding/<random>/...` |
| User avatar | `users.avatar_path` | `avatars/<random>.<ext>` |

Before this work, every upload was written to `Storage::disk('public')` and
previewed with `Storage::disk('public')->url()`, which requires
`public/storage` to be a symlink. On Render the filesystem is ephemeral, and a
private R2 bucket cannot serve `url()` links. Changes made:

- `UPLOADS_DISK` config (default `public`, production `s3`).
- A private media route (`/media/{path}`) that streams from the configured disk. Branding images are readable because the login page and PWA manifest need them; avatars require a signed-in session.
- `BrandAssets` now renders into a temporary local directory and uploads the results, instead of calling `path()` on the disk (unsupported by S3).
- Logo uploads, avatars, PDF logo embedding, deletion, and the manifest all use the configured uploads disk.
- An idempotent `uploads:migrate` command copies existing files with dry-run, no-overwrite, size verification, and leaves the source untouched.

Upload validation remains `image` + `mimes:jpg,jpeg,png,webp` + `max:2048`
(2 MB); the container raises PHP's upload limit to 10 MB, so the application
limit is the effective one.

## 5. Deployment blockers and risks

| # | Risk | Status / mitigation |
|---|---|---|
| 1 | Ephemeral Render filesystem loses uploads | **Addressed in code**: R2 disk + media route; requires R2 credentials and the migration command. Live R2 test NOT RUN. |
| 2 | Brand icons failed on non-local disks (`path()` unsupported) | **Fixed**: render to temp, upload; covered by tests with a remote fake disk. |
| 3 | R2 bucket is private, so `Storage::url()` would break previews | **Fixed**: all previews use `/media/...`; bucket must not be made public. |
| 4 | TiDB stock locking differs (no gap locks; optimistic mode ignores `FOR UPDATE`) | **Verify on TiDB**: check `@@tidb_txn_mode = pessimistic`; run concurrent sale/purchase test before real use. |
| 5 | Chromium PDF rendering in 512 MB | Container includes Chromium; `PDF_FALLBACK=true` falls back to Dompdf with unshaped Bangla. Large PDFs may be slow/OOM on Free. Test one report; optionally set `PDF_DRIVER=dompdf`. |
| 6 | Database needed during provider boot | **Fixed**: settings boot lookups now fail soft, so `/up` and `/health` respond instead of 500 when TiDB is down. |
| 7 | Render Free sleeps after 15 minutes idle, ~1 minute cold start; 750 hours/month; 0.1 CPU / 512 MB; no shell or one-off jobs | Documented. Health path is `/up`; migrations and imports are done from a local machine. |
| 8 | Free TiDB quota exhaustion refuses new connections | Monitor RU usage; keep a spending limit off for a strictly free setup and expect downtime until monthly reset. |
| 9 | No queue worker / scheduler on Free | App has neither; `QUEUE_CONNECTION=sync` prevents unprocessed jobs. Do not add queue-dependent features without a worker. |
| 10 | `route:cache` impossible (closure routes) and `config:cache` needs a bootable app | Only `config:cache` runs at container start; route/view caches are intentionally not used. |
| 11 | Uploaded file missing from R2 | `/media/...` returns 404 (no filesystem paths exposed); the page still renders using bundled icons. Re-run the migration or re-upload the logo. |
| 12 | `storage:link` no longer needed | Kept as an optional local convenience; uploads are served by the media route. |

## 6. Files changed for deployment

Created: `Dockerfile`, `.dockerignore`, `docker/apache.conf`,
`docker/apache-hardening.conf`, `docker/php-production.ini`,
`docker/entrypoint.sh`, `render.yaml`, `.env.production.example`,
`hpms/app/Support/MediaStorage.php`,
`hpms/app/Http/Controllers/MediaController.php`,
`hpms/app/Console/Commands/MigrateUploads.php`, deployment guides, and tests
`hpms/tests/Feature/MediaDeliveryTest.php`,
`hpms/tests/Feature/UploadMigrationTest.php`.

Modified: `hpms/config/filesystems.php` (uploads disk, R2 defaults),
`hpms/config/database.php` (optional TLS verification), `hpms/routes/web.php`
(media route), `hpms/app/Support/BrandAssets.php`,
`hpms/app/Http/Controllers/{Settings,AdminConsole,AdminAccount,Report}Controller.php`,
`hpms/app/Providers/AppServiceProvider.php`, `hpms/app/Models/User.php`,
`hpms/bootstrap/app.php` (trusted proxy, web-middleware-free `/health`), `hpms/.env.example`,
`hpms/docs/INSTALLATION.md`, `README.md`, `hpms/composer.json`/`composer.lock`
(added `league/flysystem-aws-s3-v3`).

## 7. Test status

Passed locally:

- Full PHPUnit suite (now 77 tests) including new media, upload-migration, health and existing business tests.
- `vendor/bin/pint --dirty`.
- `npm run build` (previous change set; no frontend code changed here).
- Docker image build (with host networking in this environment; the default build initially hit a Docker DNS failure unrelated to the repository).
- Container boot against local MariaDB: `/up` 200, `/health` 200 `{"status":"ok","database":"ok"}`, `/login` 200, `/manifest.webmanifest` 200, `/sw.js` 200, `/media/branding/...` 200 `image/png`, guest avatar request 404.
- Container boot with an unreachable database: `/up` 200 and `/health` 503 JSON (no session/stack trace).
- Apache configuration syntax, required PHP extensions, Chromium PDF generation inside the image.

NOT RUN (no resources/credentials):

- TiDB connection, dump import, migrations, Unicode verification, locking/concurrency test.
- R2 bucket access, upload, download, replacement, deletion, private-object checks.
- Render build/deploy, TLS, health check from Render, sleep/wake recovery.
- Restore drill from a production backup.
