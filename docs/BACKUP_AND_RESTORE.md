# Backup and Restore — Homeo-Hall on Render / TiDB / R2

The production deployment has three independent pieces of state:

1. **TiDB Cloud Starter** — all business data, users, settings and sessions.
2. **Cloudflare R2** — chamber logos, derived icons and avatars.
3. **`APP_KEY`** — encrypts session payloads and signed cookies.

Render's container filesystem is ephemeral and is **not** a backup location. The
Settings → Data backup ZIP is a CSV export for inspection, not a restorable
backup (it omits credentials and sessions).

> Run backups from your own machine. Render Free has no shell or one-off jobs.
> Keep backups outside the repository. Never commit dumps, exports or `.env`
> files.

## 1. What to back up and how often

| Asset | Suggested cadence | Retention |
|---|---|---|
| TiDB logical dump | Daily or after significant data entry | 7 daily + 4 weekly + 12 monthly |
| R2 uploads | Weekly, or after changing a logo/avatar | 4 weekly + 12 monthly |
| `APP_KEY` | Once, in a password manager | Permanent |
| TiDB RU/quota notes | Monthly | 12 months |

## 2. Database backup (TiDB)

Use the MySQL client. TLS is mandatory for TiDB public endpoints.

```bash
mkdir -p ~/hpms-backups/production
STAMP=$(date +%Y%m%d-%H%M)

mysqldump \
  --host <TIDB_HOST> --port 4000 --user '<TIDB_USER>' -p \
  --ssl-mode=VERIFY_IDENTITY \
  --single-transaction --quick --no-tablespaces --set-gtid-purged=OFF \
  --default-character-set=utf8mb4 \
  homeo_hall \
  > ~/hpms-backups/production/homeo-hall-$STAMP.sql

gzip -c ~/hpms-backups/production/homeo-hall-$STAMP.sql \
  > ~/hpms-backups/production/homeo-hall-$STAMP.sql.gz

sha256sum ~/hpms-backups/production/homeo-hall-$STAMP.sql.gz \
  | tee ~/hpms-backups/production/homeo-hall-$STAMP.sql.gz.sha256
```

Verify a dump immediately:

```bash
gzip -t ~/hpms-backups/production/homeo-hall-$STAMP.sql.gz
zgrep -c '^CREATE TABLE' ~/hpms-backups/production/homeo-hall-$STAMP.sql.gz  # expect 22
```

Do not overwrite the only existing backup. If `mysqldump` reports an error,
delete the incomplete file and fix the cause before retrying.

### Restore drill (safe, local)

Restore the production dump into a **scratch** local database, never over the
application's local database:

```bash
mysql -u <LOCAL_DB_USER> -p -e "
  DROP DATABASE IF EXISTS hpms_prod_restore_check;
  CREATE DATABASE hpms_prod_restore_check CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

gunzip -c ~/hpms-backups/production/homeo-hall-$STAMP.sql.gz \
  | mysql -u <LOCAL_DB_USER> -p hpms_prod_restore_check

mysql -u <LOCAL_DB_USER> -p -N -e "
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA='hpms_prod_restore_check';"   # expect 22
```

`hpms_prod_restore_check` is disposable; dropping it does not touch real data.

### Restore to TiDB

Never import over a database that may contain newer records without explicit
approval.

1. Put the app into maintenance / suspend the Render service.
2. Create a **new** empty TiDB database (e.g. `homeo_hall_restore`) with
   `utf8mb4` / `utf8mb4_unicode_ci`.
3. Import:
   ```bash
   gunzip -c ~/hpms-backups/production/homeo-hall-$STAMP.sql.gz \
     | mysql --host <TIDB_HOST> --port 4000 --user '<TIDB_USER>' -p \
         --ssl-mode=VERIFY_IDENTITY --default-character-set=utf8mb4 \
         homeo_hall_restore
   ```
4. Verify counts and financial totals (see
   [`DATABASE_MIGRATION_GUIDE.md`](DATABASE_MIGRATION_GUIDE.md) section E).
5. Point `DB_DATABASE` in Render at `homeo_hall_restore`, redeploy, verify
   `/health` and a login, then re-enable the service.
6. Keep the old database until you are satisfied; delete it only with explicit
   approval.

## 3. Uploads backup (R2)

```bash
export AWS_ACCESS_KEY_ID='<R2_ACCESS_KEY_ID>'
export AWS_SECRET_ACCESS_KEY='<R2_SECRET_ACCESS_KEY>'
STAMP=$(date +%Y%m%d)

aws s3 sync s3://homeo-hall-files ~/hpms-backups/production/r2-$STAMP \
  --endpoint-url https://<R2_ACCOUNT_ID>.r2.cloudflarestorage.com

find ~/hpms-backups/production/r2-$STAMP -type f | wc -l
du -sb ~/hpms-backups/production/r2-$STAMP
```

Record counts/bytes alongside the database dump for the same date.

Restore (only after approval, and prefer a new prefix/key if unsure):

```bash
aws s3 sync ~/hpms-backups/production/r2-$STAMP s3://homeo-hall-files \
  --endpoint-url https://<R2_ACCOUNT_ID>.r2.cloudflarestorage.com \
  --no-progress
```

`aws s3 sync` overwrites destination objects with the same key. Confirm the
backup is the version you want, or copy to a new bucket/prefix and repoint the
app. Never delete R2 objects as part of a restore.

Local development uploads live in `hpms/storage/app/public/{branding,avatars}`;
copy that directory if you need a local-only backup.

## 4. `APP_KEY`

- Copy the existing key from the local `.env` into Render **once**, and into
  your password manager.
- Losing or changing it invalidates encrypted session payloads (users are
  logged out) and any encrypted cookies. It does not corrupt database rows, but
  keep it stable.
- Never generate a new key on container start; the image and entrypoint
  deliberately do not.

## 5. Restore checklist

1. Service suspended, no writes in progress.
2. Backup checksum verified; table count and financial totals match the
   recorded baseline.
3. New database/prefix used unless overwriting was explicitly approved.
4. `APP_KEY` unchanged.
5. `/health` returns 200 with database `ok`.
6. Login works; dashboard and a known report match expectations.
7. Uploads load through `/media/...`; one test logo/avatar upload works.
8. Old backups and the old database remain intact until sign-off.
