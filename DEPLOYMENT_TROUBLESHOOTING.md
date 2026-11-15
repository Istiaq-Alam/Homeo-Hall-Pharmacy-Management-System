# Deployment Troubleshooting — Homeo-Hall on Render Free

Render logs are the first place to look: **Dashboard → homeo-hall → Logs**.
The container writes PHP/Apache output and Laravel logs to stdout/stderr
(`LOG_CHANNEL=stderr`), so no filesystem access is needed.

Useful checks:

```bash
curl -i https://homeo-hall.onrender.com/up        # app booted
curl -i https://homeo-hall.onrender.com/health    # app + database
curl -i https://homeo-hall.onrender.com/login
```

`/up` is the Render health check (no database query). `/health` returns
`{"status":"ok","database":"ok"}` or HTTP 503 with
`{"status":"unavailable","database":"unavailable"}`.

## HTTP 500

- **`APP_KEY` missing**: startup logs show `FATAL: APP_KEY is not set` and the
  container exits. Add the existing `base64:...` key in Render → Environment.
- **Database errors**: check `/health`; see “Database connection refused”.
  With `APP_DEBUG=false` the browser only shows a generic error — read the logs.
- **Permissions**: `storage/` or `bootstrap/cache/` not writable. The entrypoint
  chowns them, but a broken image layer or a mounted read-only path causes
  `Permission denied`. Rebuild the image.
- **Stale config cache**: the entrypoint runs `php artisan config:cache` on every
  start. If an environment variable was changed without a restart, trigger a
  manual deploy.
- **Missing PHP extension**: see “Missing PHP extensions”.

## HTTP 502 / service unavailable

- **Port binding**: the app must listen on `$PORT` on `0.0.0.0`. The entrypoint
  rewrites Apache's `Listen` and vhost to `${PORT:-10000}`. Do not hardcode 80.
- **Apache failed to start**: check the logs for `Syntax error` or a bad
  directive. Locally: `docker exec <container> apache2ctl -t`.
- **Out of memory (512 MB)**: Chromium PDF renders and too many Apache workers
  can OOM. The image limits `MaxRequestWorkers` to 12. Retry a single report;
  if it persists, set `PDF_DRIVER=dompdf` (Bangla will not be shaped) or reduce
  concurrency.
- **Build failed**: the deploy never produced an image. Read the build log, not
  the runtime log.

## Database connection refused

- Verify host, port `4000`, database, username and password in Render.
  TiDB usernames often include a generated prefix — copy them exactly.
- Confirm the TiDB instance is running and has not exhausted its free quota
  (50M RU/month). When the quota is exhausted, **new connections are refused**
  until the monthly reset or a spending limit is added.
- Confirm the database exists: `SHOW DATABASES;` in the TiDB SQL Console.
- The public endpoint has no IP allowlist, so Render's changing egress IPs are
  not the cause. Private endpoints/VPC peering are not available on Starter.
- TiDB may terminate connections idle for more than 30 minutes. Render Free
  sleeps after 15 minutes, so the first request after sleep opens a fresh
  connection; a “server has gone away” error on a long-idle process is expected
  to be retried by a new request.

## TLS certificate errors

- `MYSQL_ATTR_SSL_CA` must point at a real CA file inside the container:
  `/etc/ssl/certs/ca-certificates.crt` (installed in the image).
- `DB_SSL_VERIFY_SERVER_CERT=true` must stay on. Do not disable verification to
  bypass an error — fix the hostname/CA instead.
- Check the container clock; a large clock skew breaks certificate validation.
- Test from your machine with `mysql --ssl-mode=VERIFY_IDENTITY`; if that fails,
  the TiDB hostname or CA is wrong, not Render.

## Missing PHP extensions

The image installs `pdo_mysql`, `mbstring`, `bcmath`, `gd`, `zip`, `opcache`,
`fileinfo`, `openssl`, `tokenizer`, `xml` and `ctype`. Check:

```bash
docker run --rm homeo-hall:local php -m
```

If Composer fails with a platform requirement, do **not** use
`--ignore-platform-reqs`; add the extension to the Dockerfile and rebuild.
`ext-gd` is used for logo-derived icons; without it the app logs a warning and
keeps bundled icons.

## “Vite manifest not found”

The runtime image must contain `public/build/manifest.json` compiled from the
current source.

- Check the build log for `npm ci` or `vite build` failures.
- Ensure `hpms/public/build` is **not** ignored by `.dockerignore` in a way that
  removes the stage output (the Dockerfile copies it from the `assets` stage).
- Locally: `npm ci && npm run build`.
- After changing CSS/JS, rebuild and redeploy; the image is immutable.

## Permission denied

Only `storage/` and `bootstrap/cache/` need to be writable (owned by
`www-data`). The Dockerfile sets this, and the entrypoint re-applies it. If you
add a new writable directory, update both. Never `chmod -R 777`.

## R2 `AccessDenied` / `SignatureDoesNotMatch`

- Token permission must include **Object Read & Write** for the bucket.
- `AWS_ENDPOINT` must be `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`
  (no bucket name in the endpoint).
- `AWS_DEFAULT_REGION=auto` and `AWS_USE_PATH_STYLE_ENDPOINT=true`.
- `AWS_BUCKET` must match the real bucket name exactly.
- Secrets often contain characters that break shell quoting; paste them into
  Render directly, never into a committed file.
- Check the container clock; skewed time invalidates signatures.
- Test with `aws s3 ls s3://<bucket> --endpoint-url <endpoint>` using the same
  credentials.

## Missing uploaded files / broken previews

- The bucket must remain **private**. Previews go through `/media/branding/...`
  and `/media/avatars/...`; `/storage/...` URLs are no longer used.
- A `404` from `/media/...` means the object is absent or the key differs.
  Re-run `php artisan uploads:migrate --from=public --to=s3 --dry-run` (it
  reports `skip` for present files) and check the R2 listing.
- File keys are case-sensitive. Preserve the relative path exactly.
- `settings.chamber_logo` and `users.avatar_path` store relative keys; do not
  change them to full URLs.
- If the logo is missing, pages still render with bundled icons. Re-upload the
  logo in Settings to create a new key.

## `APP_KEY` / encryption errors

- `No application encryption key has been specified` → set `APP_KEY`.
- `The payload is invalid` / users suddenly logged out → the key changed between
  deploys. Restore the original key; sessions are encrypted with it.
- Never run `php artisan key:generate` in production and never bake a key into
  the image.

## Session / login problems

- `SESSION_SECURE_COOKIE=true` requires HTTPS. Use the `https://` Render URL,
  not an `http://` preview.
- `APP_URL` must match the public URL (including scheme); update it after the
  first deploy and redeploy.
- Sessions use the `sessions` table; confirm TiDB connectivity and that the
  table exists after import.
- A `419 Page Expired` usually means an expired session/CSRF token after a long
  sleep — reload the login page.
- The app trusts Render's proxy headers to detect HTTPS; if redirects loop,
  verify the request reaches the app through Render, not directly.

## Blank pages

- Set `APP_DEBUG=false` (production) and read the Render logs; blank pages are
  usually a PHP fatal error hidden by `display_errors=Off`.
- Check `storage/logs` is not used in production — `LOG_CHANNEL=stderr` sends
  errors to the Render log stream.
- If OPcache serves stale code, redeploy; `opcache.validate_timestamps=0` is
  intentional for immutable images.
- Confirm `public/index.php` is the document root entry and Apache's rewrite
  module is enabled.

## PDF / Bangla text problems

- `PDF_DRIVER=auto` uses Chromium when `/usr/bin/chromium` is present, otherwise
  Dompdf.
- A logged warning `Chromium PDF rendering failed; falling back to Dompdf`
  means the report was delivered with unshaped Bangla. Check memory and retry;
  on a 512 MB instance large reports may need `PDF_DRIVER=dompdf` or a paid
  instance.
- Missing fonts: Bengali fonts are self-hosted under `hpms/public/fonts`; do not
  remove them from the image.
- `PDF_CHROME_BINARY` must be an absolute path in the container.

## Slow first request after inactivity

Render Free spins down after ~15 minutes idle; the first request takes roughly a
minute. This is expected and not a bug. Keeping it awake with synthetic traffic
consumes the 750 free instance hours/month; do not add a keep-alive unless you
accept that trade-off.

## Free-tier limits to remember

- 750 instance hours/month, 512 MB RAM, ~0.1 CPU, no shell, no one-off jobs, no
  persistent disk.
- Render explicitly does not recommend Free for production; if the chamber
  depends on this system daily, plan an upgrade.
- TiDB Starter: 50M RU/month, 5 GiB row storage, 400 connections; quota
  exhaustion blocks new connections.
- R2: 10 GB-month storage, 1M Class A / 10M Class B operations per month (check
  current Cloudflare pricing).
- Render blocks outbound SMTP ports 25/465/587 on Free; the app sends no email.
  If email is added, use an HTTPS API provider.
