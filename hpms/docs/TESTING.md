# Testing Guide

## Test setup

Feature tests use Laravel's `RefreshDatabase` and an in-memory SQLite database declared in `phpunit.xml`:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

This isolates tests from the MySQL development database. Never point the test environment at production. Production remains MySQL; SQLite is a test-only harness.

Run the suite:

```bash
php artisan test --compact
```

Run only the critical workflow suite:

```bash
php artisan test --compact tests/Feature/HpmsWorkflowTest.php
```

## Automated coverage

`tests/Feature/HpmsWorkflowTest.php` covers:

| Area | Test |
|---|---|
| Authentication | Guests redirect to login; registration routes return 404. |
| Authentication | Owner logs in and out; password is verified. |
| Medicines | Create and update a medicine; stock remains ledger-controlled. |
| Medicines | Different potencies remain separate inventory rows. |
| Medicines | Search and low-stock filtering return correct results. |
| Purchases | Multiple lines increase stock and record movements. |
| Purchases | Server-calculated totals are stored correctly. |
| Purchases | Cancellation reverses stock and weighted-average cost. |
| Purchases | Repeated cancellation is rejected. |
| Purchases | Cancellation after a completed sale is rejected to preserve reported COGS. |
| Sales | Historical unit cost is preserved after current prices change. |
| Sales | COGS remains fixed in later reports. |
| Sales | Sale cancellation restores stock and average cost. |
| Sales | Repeated cancellation is rejected. |
| Sales | Insufficient stock is rejected without partial writes. |
| Integrity | Failed multi-line purchase rolls back stock, header, and movements. |
| Adjustments | Increase/decrease is ledgered; negative stock is a validation error. |
| Expenses | Creation is included in totals; cancellation excludes it. |
| Reports | Sales, purchases, COGS, gross profit, expenses, net profit, units, and inventory value match hand-calculated values. |
| Reports | Date filters include the whole selected day. |
| Reports | Cancelled sales and purchases are excluded. |
| Ledger | Every movement satisfies `previous + quantity = resulting`, and movements reconcile to current stock. |
| Exports | CSV, XLSX, and PDF responses have correct content types and respect the range. |
| Validation | Invalid report dates produce validation errors. |
| Dashboard | Live sale and purchase data appear in the rendered dashboard. |
| Pages | Every primary owner screen renders: lists, details, create/edit forms, categories, adjustments, all report pages, settings, and the health endpoint. |
| Branding | Owner uploads a chamber logo, sees it in navigation, and can remove it with the stored file deleted. |
| Profile | Owner uploads an administration profile picture, sees it in the sidebar, and can remove it with the stored file deleted. |

A representative accounting fixture:

1. Buy 10 units at 2.50 → stock 10, average 2.50.
2. Buy 10 units at 3.50 → stock 20, average 3.00.
3. Sell 4 units at 5.00 → revenue 20.00, COGS 12.00, stock 16.
4. Add a 5.00 expense.
5. Result: gross profit 8.00, net operating profit 3.00, inventory value 48.00.

## What is verified at the database level

- Purchase/sale headers and line items exist with exact historical amounts.
- No partial state remains after a forced failure.
- Cancellation preserves the original transaction and sets status/reason/time.
- Stock movements are append-only and reconcile to medicine stock.
- Reports exclude cancelled headers.
- Current medicine price changes do not alter historical sale COGS.

## Manual QA checklist

Automated tests are necessary but not a full UX review. Before handoff, verify:

- [ ] Login succeeds with the provisioned owner; wrong credentials show a clear error; repeated attempts throttle.
- [ ] All sidebar links render without exceptions on desktop, tablet, and phone widths.
- [ ] The mobile navigation opens, overlays the page, closes via backdrop, and closes after selecting a link.
- [ ] Add a medicine; verify it appears in search, category, form, low-stock, and out-of-stock filters as appropriate.
- [ ] Record a purchase with two medicines; check detail totals, stock, average cost, and movement entries.
- [ ] Record a sale; verify stock validation, default selling price, totals, and preserved cost on the detail page.
- [ ] Attempt to oversell; confirm a validation message and no stock change.
- [ ] Cancel a sale, then a purchase; verify stock reversal, reasons, and exclusion from reports.
- [ ] Record and cancel an expense; verify totals update.
- [ ] Cycle daily, monthly, yearly, and custom reports; verify all cards and tables.
- [ ] Download PDF, XLSX, and CSV; verify the selected date range and totals.
- [ ] Print a report; confirm navigation/actions are hidden and tables fit.
- [ ] Change chamber and owner name; confirm the sidebar owner name updates.
- [ ] Change password; sign in again with the new password.
- [ ] Upload a chamber logo, confirm it appears in navigation, then remove it and confirm the monogram returns.
- [ ] Upload an administration profile picture, confirm it appears in the sidebar owner block, then remove it and confirm the initial returns.
- [ ] Download the business-data ZIP; inspect that credentials/session data are absent.
- [ ] Run `/health` with the database up and down.
- [ ] Check browser console for JavaScript errors on charts and line-item forms.
- [ ] Check keyboard focus visibility and reduced-motion behavior.

## Troubleshooting tests

| Problem | Resolution |
|---|---|
| `could not find driver` for SQLite | Enable `pdo_sqlite` in the PHP CLI. |
| Config changes ignored | `php artisan optimize:clear`. |
| Test hits MySQL | Confirm `phpunit.xml` env values and no cached config. |
| CSS build errors | Run `npm run build` directly; ensure Node dependencies are current. |
| Logo appears broken | Run `php artisan storage:link` and confirm `public/storage` exists. |
| PDF test fails | Confirm `barryvdh/laravel-dompdf` is installed and `storage/` is writable. |
| XLSX test fails | Check the temporary file directory and `App\Support\SimpleXlsxWriter`. |

## Scope of verification

The automated suite exercises the critical stock, accounting, authentication, report, and export paths against SQLite. A final MySQL deployment should additionally run `php artisan migrate:fresh --seed` on a disposable MySQL 8 database and repeat the manual purchase/sale/report smoke test. Do not run fresh migrations against production.
