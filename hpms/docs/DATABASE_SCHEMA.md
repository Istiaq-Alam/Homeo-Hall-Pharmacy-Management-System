# Database Schema

The schema is MySQL 8 compatible and is created by Laravel migrations. All business tables use InnoDB foreign keys and timestamps. Money is `DECIMAL`, never floating point. The only unsigned values are stock quantities and other counts where negatives are invalid by definition.

## Entity relationships

```text
medicine_categories 1 ─── * medicines
medicines           1 ─── * purchase_items     * ─── 1 purchases
medicines           1 ─── * sale_items         * ─── 1 sales
medicines           1 ─── * stock_adjustments
medicines           1 ─── * stock_movements
expense_categories  1 ─── * expenses
users               0..1 ─ * purchases / sales / expenses / stock_adjustments
```

A purchase or sale header has many line items. A line item belongs to one medicine. Completed transactions are retained; cancellation sets status, reason, and timestamp rather than deleting rows.

## Tables

### `users`

Laravel's standard owner table: `id`, `name`, unique `email`, `email_verified_at`, hashed `password`, `remember_token`, timestamps. There is no role column because the application has exactly one owner.

### `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`

Framework support tables from the default Laravel migrations. Sessions are database-backed by default. They contain no business ledger data.

### `medicine_categories`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | varchar(255) | unique |
| `is_active` | boolean | default true; deactivate instead of deleting |
| timestamps | datetime | |

### `medicines`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `category_id` | bigint FK | references `medicine_categories`, restrict on delete |
| `name` | varchar(255) | indexed |
| `potency` | varchar(255) | default empty string |
| `form` | varchar(255) | Liquid, Globules, Tablets, Powder, Ointment, Other |
| `unit` | varchar(255) | Bottle, Packet, Vial, Tube, Box, Piece |
| `purchase_price` | decimal(12,2) | latest purchase unit price for display/defaults |
| `selling_price` | decimal(12,2) | default selling price; snapshot copied to sales |
| `average_cost` | decimal(12,4) | weighted-average inventory cost |
| `stock_quantity` | unsigned int | current on-hand units; never negative |
| `minimum_stock` | unsigned int | low-stock threshold |
| `description` | text nullable | |
| `is_active` | boolean | deactivation keeps history intact |
| `timestamps`, `deleted_at` | | soft deletes available for safe lifecycle handling |

Unique identity: `(name, potency, form, unit)` via `medicines_identity_unique`. This is what keeps `Nux Vomica 30C` and `Nux Vomica 200C` as separate inventory rows. Additional indexes: `(category_id, is_active)` and `(stock_quantity, minimum_stock)`.

### `purchases`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `purchase_number` | varchar(255) | unique, generated `PUR-YYYYMMDDHHMMSS-XXXXXX` |
| `purchase_date` | date | indexed; report key |
| `supplier_name` | varchar(255) nullable | plain text only |
| `total_amount` | decimal(12,2) | server-calculated line sum |
| `status` | varchar(255) | `completed` or `cancelled`, indexed |
| `notes` | text nullable | |
| `cancellation_reason` | text nullable | audit trail |
| `cancelled_at` | timestamp nullable | |
| `user_id` | bigint FK nullable | actor; null on user deletion |
| timestamps | | |

### `purchase_items`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `purchase_id` | bigint FK | cascade delete with header |
| `medicine_id` | bigint FK | restrict delete; indexed |
| `quantity` | unsigned int | units purchased |
| `unit_cost` | decimal(12,2) | historical purchase cost |
| `line_total` | decimal(12,2) | quantity × unit cost |
| timestamps | | |

### `sales`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `sale_number` | varchar(255) | unique, generated `SAL-YYYYMMDDHHMMSS-XXXXXX` |
| `sale_date` | date | indexed; report key |
| `total_amount` | decimal(12,2) | server-calculated line sum |
| `status` | varchar(255) | `completed` or `cancelled`, indexed |
| `notes` | text nullable | |
| `cancellation_reason` | text nullable | audit trail |
| `cancelled_at` | timestamp nullable | |
| `user_id` | bigint FK nullable | actor; null on user deletion |
| timestamps | | |

### `sale_items`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `sale_id` | bigint FK | cascade delete with header |
| `medicine_id` | bigint FK | restrict delete; indexed |
| `quantity` | unsigned int | units sold |
| `unit_selling_price` | decimal(12,2) | actual price at sale time |
| `unit_cost_price` | decimal(12,4) | weighted-average cost snapshot |
| `line_total` | decimal(12,2) | quantity × selling price |
| `line_cost_total` | decimal(12,2) | quantity × historical unit cost; COGS source |
| timestamps | | |

The historical cost columns are the authoritative COGS source. Current medicine prices are never used to recalculate past profit.

### `expense_categories`

`id`, unique `name`, `is_active`, timestamps. Defaults: Chamber Rent, Electricity, Water, Internet, Transportation, Stationery, Cleaning, Maintenance, Other.

### `expenses`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `expense_category_id` | bigint FK | restrict delete; composite index with date |
| `expense_date` | date | indexed |
| `description` | varchar(255) | |
| `amount` | decimal(12,2) | must be positive in validation |
| `notes` | text nullable | |
| `status` | varchar(255) | `completed` or `cancelled`, indexed |
| `cancellation_reason` | text nullable | |
| `cancelled_at` | timestamp nullable | |
| `user_id` | bigint FK nullable | |
| timestamps | | |

### `stock_adjustments`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `medicine_id` | bigint FK | restrict delete |
| `adjustment_type` | varchar(255) | `increase` or `decrease` |
| `quantity` | unsigned int | magnitude; signed effect is in the ledger |
| `reason` | varchar(255) | required |
| `adjustment_date` | date | indexed |
| `notes` | text nullable | |
| `user_id` | bigint FK nullable | |
| timestamps | | |

The adjustment header explains why stock changed; the corresponding `stock_movements` row records the signed quantity and before/after values.

### `stock_movements`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `medicine_id` | bigint FK | restrict delete |
| `movement_type` | varchar(255) | indexed; see values below |
| `quantity` | signed int | positive increases, negative decreases |
| `reference_type` | varchar(255) nullable | purchase, sale, or adjustment class |
| `reference_id` | unsigned bigint nullable | source row ID |
| `previous_stock` | unsigned int | stock before the change |
| `resulting_stock` | unsigned int | stock after the change |
| `reason` | varchar(255) nullable | transaction note or cancellation reason |
| `movement_date` | datetime | indexed |
| timestamps | | |

Movement types: `purchase`, `sale`, `purchase_reversal`, `sale_reversal`, `adjustment_increase`, `adjustment_decrease`. Composite indexes: `(medicine_id, movement_date)` and `(reference_type, reference_id)`.

This is an append-only ledger. Business operations write a movement for every stock change; no route writes `stock_quantity` directly.

### `settings`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `key` | varchar(255) | unique setting key |
| `value` | text nullable | string value |
| timestamps | | |

Seeded keys: `chamber_name`, `currency`, `currency_symbol`, `timezone`, `low_stock_default`, `report_default_days`. `owner_name` is written when the owner saves settings.

## Quantity and money decisions

- **Stock quantities are unsigned integers.** The chamber sells countable units (bottles, packets, vials, tubes, boxes, pieces). Integer stock gives an unambiguous ledger and makes fractional stock impossible. A future need for measured liquids would require an explicit unit conversion design, not silent decimals.
- **Money is decimal.** Selling prices, purchase costs, totals, expenses, and COGS use `decimal(12,2)` (up to 9,999,999,999.99). Average cost uses `decimal(12,4)` so repeated weighted-average calculations retain enough precision; sale-item cost also uses four decimals while its line total is rounded to cents.
- **Historical values are snapshots.** Sale items store both unit selling price and unit cost. Purchase items store unit cost. Reports aggregate those stored values.
- **Cancellation is state, not deletion.** Status, reason, and timestamp preserve the audit trail and prevent duplicate reversals.

## Date and timezone handling

- Transaction header dates are MySQL `DATE` values representing the chamber's local calendar day.
- Movement and cancellation timestamps are datetimes interpreted in `Asia/Dhaka`.
- Report ranges are inclusive. Queries use `from 00:00:00` through `to 23:59:59`, which avoids excluding same-day rows when a date column is stored with a time component by a test database.
- Laravel's configured app timezone is `Asia/Dhaka`; the saved setting is applied during boot.

## Referential integrity

- Medicine categories and expense categories restrict deletion because historical rows reference them.
- Purchase and sale items restrict medicine deletion. Medicines are deactivated or soft-deleted instead.
- Item rows cascade only with their own transaction header; completed headers are not deleted by application routes.
- Actor foreign keys are nullable with null-on-delete so history survives account maintenance.

## Migration

All application tables are created by:

```text
database/migrations/2026_11_15_111801_create_hpms_business_tables.php
```

Run `php artisan migrate` against MySQL. Do not switch production to SQLite; SQLite is used only by the isolated test suite.
