# Database Migration Guide — Local MariaDB to TiDB Cloud Starter

This guide moves the existing Homeo-Hall data into TiDB Cloud Starter. It does
not run anything automatically. **Do not delete the local database or the
pre-import dump.**

Local facts discovered during the audit:

- Database: `hpms-db` (contains a hyphen; quote it in SQL with backticks)
- Server: MariaDB 11.8.9, InnoDB, `utf8mb4` / `utf8mb4_unicode_ci`
- 22 tables, 10 migrations
- No stored procedures, functions, triggers, events or FULLTEXT indexes
- Audit-time data: 1 user, 1 security log, no purchases/sales/expenses/medicines

## A. Pre-migration checks

Run against the local database and save the output outside the repository:

```bash
mkdir -p ~/hpms-backups/pre-tidb
cd hpms

mysql -u <LOCAL_DB_USER> -p -e "SELECT VERSION(), @@character_set_database, @@collation_database;" hpms-db \
  | tee ~/hpms-backups/pre-tidb/server.txt

mysql -u <LOCAL_DB_USER> -p -N -e "SHOW TABLES;" hpms-db \
  | tee ~/hpms-backups/pre-tidb/tables.txt

mysql -u <LOCAL_DB_USER> -p -e "
  SELECT TABLE_NAME, TABLE_ROWS, ENGINE, TABLE_COLLATION
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = 'hpms-db' ORDER BY TABLE_NAME;" \
  | tee ~/hpms-backups/pre-tidb/row-estimates.txt

mysql -u <LOCAL_DB_USER> -p -e "SHOW TRIGGERS; SHOW EVENTS;" hpms-db \
  | tee ~/hpms-backups/pre-tidb/triggers-events.txt
```

Unsupported-feature check (expect no rows for procedures/functions/triggers):

```sql
SELECT ROUTINE_TYPE, ROUTINE_NAME FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = 'hpms-db';
SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = 'hpms-db';
SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA = 'hpms-db';
SELECT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'hpms-db' AND INDEX_TYPE = 'FULLTEXT';
```

Record a data baseline with real counts (not `TABLE_ROWS`, which is only an
estimate):

```sql
SELECT 'users' t, COUNT(*) n FROM users
UNION ALL SELECT 'medicines', COUNT(*) FROM medicines
UNION ALL SELECT 'medicine_categories', COUNT(*) FROM medicine_categories
UNION ALL SELECT 'purchases', COUNT(*) FROM purchases
UNION ALL SELECT 'purchase_items', COUNT(*) FROM purchase_items
UNION ALL SELECT 'sales', COUNT(*) FROM sales
UNION ALL SELECT 'sale_items', COUNT(*) FROM sale_items
UNION ALL SELECT 'expenses', COUNT(*) FROM expenses
UNION ALL SELECT 'expense_categories', COUNT(*) FROM expense_categories
UNION ALL SELECT 'stock_movements', COUNT(*) FROM stock_movements
UNION ALL SELECT 'stock_adjustments', COUNT(*) FROM stock_adjustments
UNION ALL SELECT 'settings', COUNT(*) FROM settings
UNION ALL SELECT 'chambers', COUNT(*) FROM chambers;
```

Also save the financial baseline:

```sql
SELECT
  (SELECT COALESCE(SUM(stock_quantity),0) FROM medicines) AS total_stock,
  (SELECT COALESCE(SUM(average_cost),0) FROM medicines)   AS average_cost_sum,
  (SELECT COALESCE(SUM(total_amount),0) FROM purchases)   AS purchase_total,
  (SELECT COALESCE(SUM(total_amount),0) FROM sales)       AS sales_total,
  (SELECT COALESCE(SUM(total_amount),0) FROM expenses)    AS expense_total,
  (SELECT COALESCE(SUM(line_cost_total),0) FROM sale_items) AS cost_of_goods;
```

If your schema names differ, inspect `SHOW COLUMNS FROM <table>` first and
adjust the queries — do not trust this document over the database.

## B. Backup

```bash
mkdir -p ~/hpms-backups
STAMP=$(date +%Y%m%d-%H%M)
mysqldump --single-transaction --quick --routines --triggers --events \
  --default-character-set=utf8mb4 --no-tablespaces \
  -u <LOCAL_DB_USER> -p hpms-db \
  > ~/hpms-backups/hpms-pre-tidb-$STAMP.sql

gzip -c ~/hpms-backups/hpms-pre-tidb-$STAMP.sql \
  > ~/hpms-backups/hpms-pre-tidb-$STAMP.sql.gz
sha256sum ~/hpms-backups/hpms-pre-tidb-$STAMP.sql* \
  | tee ~/hpms-backups/hpms-pre-tidb-$STAMP.sha256
```

Verify the dump before trusting it:

```bash
grep -c '^CREATE TABLE' ~/hpms-backups/hpms-pre-tidb-$STAMP.sql   # expect 22

mysql -u <LOCAL_DB_USER> -p -e "
  DROP DATABASE IF EXISTS hpms_restore_check;
  CREATE DATABASE hpms_restore_check CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

mysql -u <LOCAL_DB_USER> -p hpms_restore_check < ~/hpms-backups/hpms-pre-tidb-$STAMP.sql

mysql -u <LOCAL_DB_USER> -p -N -e "
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA='hpms_restore_check';"                       # expect 22
```

`hpms_restore_check` is a throwaway local database; dropping it is safe and
does not touch the application database. Keep at least one older dump. Never
put dumps, `.env` files or backups in Git.

## C. Prepare the cloud database

1. Create the TiDB Starter instance and database as described in
   [`DEPLOYMENT_GUIDE.md`](DEPLOYMENT_GUIDE.md) step 2.
2. Test connectivity **before** importing:
   ```bash
   mysql --host <TIDB_HOST> --port 4000 --user '<TIDB_USER>' -p \
     --ssl-mode=VERIFY_IDENTITY \
     -e "SELECT VERSION(), @@tidb_txn_mode; SHOW DATABASES;" 
   ```
3. Confirm the target is empty or explicitly approved:
   ```bash
   mysql --host <TIDB_HOST> --port 4000 --user '<TIDB_USER>' -p \
     --ssl-mode=VERIFY_IDENTITY -N -e "SHOW TABLES;" homeo_hall
   ```
   It must print nothing for a first import. If tables exist, **stop** and get
   explicit approval before importing. Do not drop them.
4. Confirm pessimistic transactions (`pessimistic`) and the collation:
   ```sql
   SELECT @@tidb_txn_mode;
   SHOW CREATE DATABASE homeo_hall;
   ```

## D. Import

Import without `--force`, so the first failed statement stops the import:

```bash
mysql --host <TIDB_HOST> --port 4000 --user '<TIDB_USER>' -p \
  --ssl-mode=VERIFY_IDENTITY --default-character-set=utf8mb4 \
  homeo_hall < ~/hpms-backups/hpms-pre-tidb-$STAMP.sql \
  2> ~/hpms-backups/import-errors-$STAMP.log

echo "exit=$?"
cat ~/hpms-backups/import-errors-$STAMP.log
```

If the import fails:

- Do not re-run with `--force` and do not skip statements.
- Read the error. Typical TiDB differences: descending index, FULLTEXT,
  unsupported `ALTER` combination, or a collation not supported by your region.
- Fix the dump or schema explicitly, document it, and retry into an empty
  database. This application's dump does not use those features.

The dump includes the `migrations` table, so Laravel sees the schema as already
migrated. Do not run `php artisan migrate` afterwards unless
`migrate:status` shows a genuinely missing migration.

Optional cleanup: the dump also contains local `sessions`, `cache`, `jobs`,
`job_batches` and `failed_jobs` rows. They are harmless, but if you want a clean
start, delete **only those rows in the new TiDB database** after confirming the
import (never `TRUNCATE` business tables):

```sql
DELETE FROM sessions;
DELETE FROM cache;
DELETE FROM cache_locks;
DELETE FROM jobs;
DELETE FROM job_batches;
DELETE FROM failed_jobs;
```

This is destructive to session/cache rows only; ask for approval before running
it.

## E. Verification

Compare every count against the baseline from section A:

```sql
SELECT 'users' t, COUNT(*) n FROM users
UNION ALL SELECT 'medicines', COUNT(*) FROM medicines
UNION ALL SELECT 'medicine_categories', COUNT(*) FROM medicine_categories
UNION ALL SELECT 'purchases', COUNT(*) FROM purchases
UNION ALL SELECT 'purchase_items', COUNT(*) FROM purchase_items
UNION ALL SELECT 'sales', COUNT(*) FROM sales
UNION ALL SELECT 'sale_items', COUNT(*) FROM sale_items
UNION ALL SELECT 'expenses', COUNT(*) FROM expenses
UNION ALL SELECT 'expense_categories', COUNT(*) FROM expense_categories
UNION ALL SELECT 'stock_movements', COUNT(*) FROM stock_movements
UNION ALL SELECT 'stock_adjustments', COUNT(*) FROM stock_adjustments
UNION ALL SELECT 'settings', COUNT(*) FROM settings
UNION ALL SELECT 'chambers', COUNT(*) FROM chambers;
```

Financials:

```sql
SELECT
  (SELECT COALESCE(SUM(stock_quantity),0) FROM medicines) AS total_stock,
  (SELECT COALESCE(SUM(average_cost),0) FROM medicines)   AS average_cost_sum,
  (SELECT COALESCE(SUM(total_amount),0) FROM purchases)   AS purchase_total,
  (SELECT COALESCE(SUM(total_amount),0) FROM sales)       AS sales_total,
  (SELECT COALESCE(SUM(total_amount),0) FROM expenses)    AS expense_total,
  (SELECT COALESCE(SUM(line_cost_total),0) FROM sale_items) AS cost_of_goods;
```

Integrity and Unicode:

```sql
-- Foreign keys exist
SELECT COUNT(*) AS foreign_keys
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = 'homeo_hall';

-- Indexes per business table
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'homeo_hall'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- Bangla/Unicode survived
SELECT id, name FROM medicines WHERE name REGEXP '[^ -~]' LIMIT 5;
SELECT id, value FROM settings WHERE value REGEXP '[^ -~]' LIMIT 5;

-- Login data
SELECT COUNT(*) FROM users;
```

Then test the application itself:

1. Log in with the owner account (password hashes are included in the dump).
2. Open the dashboard and compare cards/charts.
3. Compare a known date range in daily/monthly/yearly reports with the local app.
4. Confirm stock quantities, average costs, purchase/sale totals and profit.
5. Export CSV, XLSX and PDF; confirm Bangla text and BDT formatting.
6. Create one test sale and one test purchase, then cancel both; confirm stock
   and the stock-movement ledger return to the baseline.
7. Concurrency check (the TiDB-specific risk): from two sessions, run two
   simultaneous sales of the last units of the same medicine and confirm only
   one succeeds. Use the application UI or a load tool; the database must not
   allow negative stock. If this fails, stop and report it — do not "fix" it by
   removing the stock check.

Record all outputs in your own notes, outside Git.

## F. Rollback

There is no automatic rollback. TiDB and the local MariaDB are independent.

1. **Stop writes:** Render Dashboard → suspend the service (or put the app into
   maintenance) so no new transactions are created.
2. **Keep evidence:** preserve the pre-import dump, the local database, and the
   import error log. Do not drop or truncate TiDB business tables.
3. **Choose a target:**
   - If the import failed: leave the TiDB database in place, create a **new**
     empty database, fix the dump, and import there. Never overwrite data that
     may already be valid.
   - If the app ran and data diverged: export the new TiDB data first
     (`mysqldump` against TiDB), then restore the pre-import dump into a new
     database and repoint `DB_*` in Render.
4. Update Render's `DB_*` variables and redeploy. Render restarts the container
   with the new configuration.
5. Verify `/health`, log in, and compare reports with the baseline.
6. Only after confirming the rollback target should you consider removing the
   failed database — and only with explicit approval.

Never restore over a live database, and never attempt an automated rollback that
could destroy records created after go-live.
