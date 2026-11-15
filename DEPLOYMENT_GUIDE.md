# Homeo-Hall (HPMS) — Manual Deployment Guide

Target: **Render Free** (Docker) + **TiDB Cloud Starter** (MySQL compatibility)
+ **Cloudflare R2** (private uploads) + GitHub.

This guide is manual by design. Nothing here has been deployed. Replace every
`<PLACEHOLDER>` and never commit real credentials. Do the steps in order; do not
import data before the backup in step 5 is verified.

---

## 0. Local container validation (optional, recommended first)

From the repository root (the directory containing `hpms/` and `Dockerfile`):

```bash
docker build -t homeo-hall:local .
docker run --rm -p 10000:10000 \
  --env-file hpms/.env \
  -e APP_ENV=production -e APP_DEBUG=false -e APP_URL=http://127.0.0.1:10000 \
  -e PORT=10000 \
  homeo-hall:local
```

Checks:

```bash
docker run --rm homeo-hall:local php -v
docker run --rm homeo-hall:local php -m | grep -E 'pdo_mysql|mbstring|bcmath|gd|zip|Zend OPcache'
docker exec <container> apache2ctl -t
curl -i http://127.0.0.1:10000/up
curl -i http://127.0.0.1:10000/health
docker logs -f <container>
```

The container binds Apache to `$PORT` (default 10000), serves only
`/var/www/html/public`, caches configuration at boot, logs to stdout/stderr,
and **never runs migrations**. `APP_KEY` must be present or startup fails with
a clear message.

> Note: Docker builds need working DNS. If your daemon cannot resolve package
> registries, use `docker build --network=host` or fix the daemon DNS.

---

## 1. Prepare the GitHub repository

1. Commit the deployment files and push to `main` (the branch Render will track):
   ```bash
   git status
   git add Dockerfile .dockerignore docker render.yaml .env.production.example \
           DEPLOYMENT_AUDIT.md DEPLOYMENT_GUIDE.md DATABASE_MIGRATION_GUIDE.md \
           FILE_STORAGE_MIGRATION_GUIDE.md BACKUP_AND_RESTORE.md \
           DEPLOYMENT_TROUBLESHOOTING.md
   git commit -m "Prepare Render, TiDB and R2 deployment configuration"
   git push origin main
   ```
2. Confirm no secrets are tracked:
   ```bash
   git ls-files | grep -E '(^|/)\.env$|\.env\.production$' || echo 'no env files tracked'
   ```
3. The Docker build context is the repository root; the application lives in
   `hpms/`. The Dockerfile expects that layout.

---

## 2. Create TiDB Cloud Starter

1. Sign in at <https://tidbcloud.com> and create a **TiDB Cloud Starter**
   (MySQL-compatible) instance. Choose the region closest to your Render region
   (for Bangladesh, Singapore is usually best).
2. Keep the free quota (50M RU/month, 5 GiB row storage). A spending limit is
   optional; with no limit the instance stops accepting **new** connections
   when the quota is exhausted.
3. Open the instance's **SQL Console** and create the database:
   ```sql
   CREATE DATABASE homeo_hall CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
4. Open **Connect** and copy:
   - host, e.g. `<TIDB_HOST>.tidbcloud.com`
   - port `4000`
   - username (with the generated prefix), password
   - TLS mode `REQUIRE`/`VERIFY_IDENTITY`
5. Test from your machine (the MySQL client uses the system CA bundle):
   ```bash
   mysql --host <TIDB_HOST> --port 4000 --user '<TIDB_USER>' -p \
     --ssl-mode=VERIFY_IDENTITY --default-character-set=utf8mb4 \
     -e "SELECT VERSION(), @@tidb_txn_mode, DATABASE();" homeo_hall
   ```
   `@@tidb_txn_mode` must be `pessimistic` (new clusters default to it). If it
   is not, in the SQL Console run `SET GLOBAL tidb_txn_mode = 'pessimistic';`
   and confirm again — the stock ledger uses `SELECT ... FOR UPDATE`.

Do not import data yet. Use **[`DATABASE_MIGRATION_GUIDE.md`](DATABASE_MIGRATION_GUIDE.md)**.

---

## 3. Create the Cloudflare R2 bucket

1. Cloudflare Dashboard → **R2** → **Create bucket**.
2. Name: `homeo-hall-files` (or update `AWS_BUCKET`).
3. Location: **Automatic** or the nearest region.
4. **Do not enable public access** (no `r2.dev` public URL, no public custom
   domain). The app serves files through authenticated `/media/...` routes.
5. Free tier at the time of writing: 10 GB-month storage, 1M Class A and 10M
   Class B operations/month, free egress. Recheck Cloudflare's pricing page.

## 4. Create R2 API credentials

1. R2 → **Manage API tokens** → **Create API token**.
2. Permission: **Object Read & Write**, restricted to the `homeo-hall-files`
   bucket.
3. Copy the **Access Key ID** and **Secret Access Key** once. Store them in a
   password manager; they go into Render, never into Git.
4. Endpoint: `https://<R2_ACCOUNT_ID>.r2.cloudflarestorage.com`.
5. Check access with the AWS CLI (optional but recommended):
   ```bash
   export AWS_ACCESS_KEY_ID='<R2_ACCESS_KEY_ID>'
   export AWS_SECRET_ACCESS_KEY='<R2_SECRET_ACCESS_KEY>'
   aws s3 ls s3://homeo-hall-files \
     --endpoint-url https://<R2_ACCOUNT_ID>.r2.cloudflarestorage.com
   ```
   An empty listing without an error means the credentials and endpoint work.

---

## 5. Back up local MySQL

Follow **[`DATABASE_MIGRATION_GUIDE.md`](DATABASE_MIGRATION_GUIDE.md)** section
B exactly. Minimum:

```bash
mkdir -p ~/hpms-backups
mysqldump --single-transaction --quick --routines --triggers --events \
  --default-character-set=utf8mb4 --no-tablespaces \
  -u <LOCAL_DB_USER> -p hpms-db > ~/hpms-backups/hpms-pre-tidb-$(date +%Y%m%d-%H%M).sql
```

Keep the local database and this dump untouched. Do not put dumps in Git.

## 6. Import the database into TiDB

Follow **[`DATABASE_MIGRATION_GUIDE.md`](DATABASE_MIGRATION_GUIDE.md)** sections
C–E. Import only into the empty `homeo_hall` database, capture errors to a log,
and verify counts, stock totals, reports and Bangla text before pointing the app
at it. Never use `--force` to skip failed statements.

## 7. Migrate uploaded files to R2

Follow **[`FILE_STORAGE_MIGRATION_GUIDE.md`](FILE_STORAGE_MIGRATION_GUIDE.md)**.
The dry run is mandatory first:

```bash
cd hpms
export AWS_ACCESS_KEY_ID='<R2_ACCESS_KEY_ID>'
export AWS_SECRET_ACCESS_KEY='<R2_SECRET_ACCESS_KEY>'
export AWS_DEFAULT_REGION=auto
export AWS_BUCKET=homeo-hall-files
export AWS_ENDPOINT=https://<R2_ACCOUNT_ID>.r2.cloudflarestorage.com
export AWS_USE_PATH_STYLE_ENDPOINT=true

php artisan uploads:migrate --from=public --to=s3 --dry-run
php artisan uploads:migrate --from=public --to=s3
```

Source files are never deleted. Re-running the command skips objects that
already exist; `--overwrite` is opt-in.

## 8. Test cloud database and storage

Before Render, prove both dependencies from your machine:

```bash
# Database (schema, row counts, Unicode)
mysql --host <TIDB_HOST> --port 4000 --user '<TIDB_USER>' -p \
  --ssl-mode=VERIFY_IDENTITY homeo_hall \
  -e "SELECT COUNT(*) AS users FROM users; SELECT COUNT(*) AS medicines FROM medicines; SELECT name FROM medicines WHERE name REGEXP '[^ -~]' LIMIT 3;"

# R2 round trip through the app's disk configuration
cd hpms
php artisan tinker --execute="Storage::disk('s3')->put('_healthcheck/ping.txt', 'ok'); echo Storage::disk('s3')->get('_healthcheck/ping.txt'); Storage::disk('s3')->delete('_healthcheck/ping.txt');"
```

Expected: database counts match the local baseline and the round trip prints
`ok`. If this fails, stop and fix credentials/TLS before deploying.

---

## 9. Configure Render

### Option A — Blueprint (`render.yaml` in this repository)

1. Render Dashboard → **New** → **Blueprint** → connect the GitHub repository.
2. Render reads `render.yaml`: service `homeo-hall`, Docker, branch `main`,
   Dockerfile `./Dockerfile`, Free plan, health check `/up`.
3. Fill every `sync: false` variable (listed in step 10) and apply.

### Option B — Manual Web Service

Render Dashboard → **New** → **Web Service** → repository → settings:

| Setting | Value |
|---|---|
| Name | `homeo-hall` |
| Language / Runtime | Docker |
| Branch | `main` |
| Dockerfile path | `./Dockerfile` |
| Docker build context | repository root (default) |
| Instance type | Free |
| Health check path | `/up` |
| Auto-deploy | Yes (optional) |
| Persistent disk | **None** (not available on Free, not needed) |
| Pre-deploy command | **None** |
| SSH / shell | **Not available on Free** |

Do not add a `php artisan serve`, migration, or seeder command anywhere in the
Render settings.

## 10. Add environment variables

Set these in **Render → homeo-hall → Environment**. Use
[`.env.production.example`](.env.production.example) as the checklist; it
contains placeholders only.

Required values:

| Variable | Value |
|---|---|
| `APP_NAME` | `Homeo-Hall` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | the **existing** local `base64:...` key — do not regenerate |
| `APP_URL` | `https://homeo-hall.onrender.com` (or your custom domain) |
| `APP_TIMEZONE` | `Asia/Dhaka` |
| `LOG_CHANNEL` / `LOG_LEVEL` | `stderr` / `warning` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` / `DB_PORT` | TiDB host / `4000` |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | TiDB values |
| `DB_CHARSET` / `DB_COLLATION` | `utf8mb4` / `utf8mb4_unicode_ci` |
| `MYSQL_ATTR_SSL_CA` | `/etc/ssl/certs/ca-certificates.crt` |
| `DB_SSL_VERIFY_SERVER_CERT` | `true` |
| `SESSION_DRIVER` | `database` |
| `SESSION_SECURE_COOKIE` / `SESSION_ENCRYPT` | `true` / `true` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `sync` |
| `FILESYSTEM_DISK` | `local` (temporary files only) |
| `UPLOADS_DISK` | `s3` |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | R2 token values |
| `AWS_DEFAULT_REGION` | `auto` |
| `AWS_BUCKET` | `homeo-hall-files` |
| `AWS_ENDPOINT` | `https://<R2_ACCOUNT_ID>.r2.cloudflarestorage.com` |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `true` |
| `PDF_DRIVER` / `PDF_WEASYPRINT_BINARY` / `PDF_CHROME_BINARY` / `PDF_TIMEOUT` / `PDF_FALLBACK` | `auto` / `/usr/bin/weasyprint` / `/usr/bin/chromium` / `60` / `true` |
| `MAIL_MAILER` | `log` |

`APP_URL` is unknown until the service exists. Create it with a temporary value,
then update it after the first deploy and redeploy so generated `https` URLs and
the PWA manifest are correct.

Render blocks outbound SMTP ports 25/465/587 on Free. The app sends no mail, so
no SMTP provider is required; use an HTTPS API provider if mail is added later.

## 11. Build and deploy the Docker image

Click **Deploy** (or push to `main` if auto-deploy is on). The Dockerfile:

1. installs npm dependencies from `package-lock.json`, builds Vite/Tailwind;
2. installs Composer production dependencies from `composer.lock` (no dev
   packages, no scripts during build);
3. installs PHP extensions, Apache, CA certificates, WeasyPrint, Chromium and
   system fonts;
4. copies only application code, `vendor/` and `public/build`;
5. runs as Apache with only `storage/` and `bootstrap/cache/` writable.

Watch the deploy log. A successful deploy serves `/up` with HTTP 200.

## 12. Run approved production migrations (only if needed)

The recommended import already contains the `migrations` ledger, so no
migration should be needed on first deploy. If a later release adds one:

```bash
cd hpms
# Use a temporary shell environment; never commit this file.
export APP_ENV=production APP_DEBUG=false APP_URL=https://homeo-hall.onrender.com
export APP_KEY='<existing key>'
export DB_CONNECTION=mysql DB_HOST='<TIDB_HOST>' DB_PORT=4000 \
       DB_DATABASE=homeo_hall DB_USERNAME='<TIDB_USER>' DB_PASSWORD='<TIDB_PASSWORD>' \
       DB_CHARSET=utf8mb4 DB_COLLATION=utf8mb4_unicode_ci \
       MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt \
       DB_SSL_VERIFY_SERVER_CERT=true

php artisan migrate:status
php artisan migrate --pretend
php artisan migrate --force
```

Review the `--pretend` SQL first. Never run `migrate:fresh`, `db:wipe` or
`schema:drop` against production. Render Free has no shell, so these commands
run from your machine against TiDB.

## 13. Test the website

1. `https://homeo-hall.onrender.com/up` → 200.
2. `/health` → 200 with `{"status":"ok","database":"ok"}`.
3. Log in with the existing owner account.
4. Wait for the free instance to sleep (>15 min idle), then load the login page:
   the first request may take about a minute. Confirm it recovers.
5. Redeploy from Render and confirm the database data is unchanged.
6. Open Settings; upload a test logo and avatar; refresh and confirm previews
   load from `/media/...` (not `/storage/...`). Remove the test files afterwards.

## 14. Verify business records and reports

- Dashboard cards and charts load.
- Medicine list count matches the pre-migration baseline; stock quantities and average costs are unchanged.
- Purchase and sale lists show the same totals as before.
- Daily/monthly/yearly/custom reports show the same totals for a known date range.
- CSV, XLSX and PDF exports download; the PDF shows Bangla text correctly
  (WeasyPrint, then Chromium) or falls back with a logged warning.
- Bangla/English switching and Asia/Dhaka dates are correct.
- Cancel one test transaction created after go-live and confirm stock returns.

## 15. Configure backups

Follow **[`BACKUP_AND_RESTORE.md`](BACKUP_AND_RESTORE.md)**. Render Free has no
persistent disk and no shell, so backups run from your machine against TiDB and
R2. Keep several generations; never store dumps in Git.

## 16. Rollback

See **[`DATABASE_MIGRATION_GUIDE.md`](DATABASE_MIGRATION_GUIDE.md)** section F
and **[`FILE_STORAGE_MIGRATION_GUIDE.md`](FILE_STORAGE_MIGRATION_GUIDE.md)**.

Short version:

1. Stop writes (Render → Suspend service or put the app in maintenance).
2. Keep the pre-import dump and the local database. Do not drop production tables.
3. Point `DB_*` back to a known-good database (or restore the dump into a new
   TiDB database) and `UPLOADS_DISK=public` only if the local uploads are also
   preserved — on Render that disk is ephemeral, so prefer fixing R2.
4. Redeploy, verify `/health`, then re-enable the service.

Never attempt an automatic rollback that could discard transactions created
after go-live.
