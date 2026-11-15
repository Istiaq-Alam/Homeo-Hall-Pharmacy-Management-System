# Project Overview

## Purpose

Homeo-Hall is a private record-keeping application for one homeopathic chamber owner in Bangladesh. It tracks medicine stock, medicine purchases, medicine sales, other chamber expenses, and the resulting gross and net operating profit. The owner enters every transaction manually.

The system is intentionally small. It is not an online pharmacy, hospital system, multi-user ERP, or payment platform. There are no patients, prescriptions, customer accounts, supplier accounts, employees, roles, or public registration.

## Users and access

There is exactly one authenticated owner. The owner account is provisioned from `OWNER_EMAIL`, `OWNER_PASSWORD`, and optional `OWNER_NAME` at seeding time. Every business route sits behind Laravel's `auth` middleware. The login endpoint is rate-limited; there is no registration route or user-management screen.

## Modules

### Authentication

- Login form, logout, CSRF protection, password hashing, throttling, session regeneration.
- Current-password verification and a 12-character minimum for password changes.
- No self-service account creation.

### Dashboard

- Ten live inventory and financial cards: daily sales, purchases, other expenses, gross profit, net operating profit, medicine count, stock units, low stock, out of stock, and inventory value.
- Current-month summary with revenue, purchases, COGS, other expenses, gross profit, and net operating profit.
- Four Chart.js charts backed by six months of real records.
- Recent activity links directly to sale, purchase, expense, and adjustment records.
- Quick actions for all routine recording tasks.

### Medicines and inventory

- Medicine identity is `name + potency + form + unit`; potencies are separate stock rows.
- Categories support add, edit, and deactivate. Seven default categories are seeded.
- Search by name or potency; filter by category, form, and stock status; sort by name, stock, price, or date.
- Medicine details include stock, weighted-average cost, selling price, category, description, and paginated movement history.
- Manual correction is only possible through a dated stock adjustment with a reason and notes. Every adjustment writes a ledger movement.
- Stock status is derived: out of stock at zero, low stock at or below the minimum while positive, otherwise in stock.

### Purchases

- Header plus multiple line items; line and header totals are calculated from quantity × unit cost.
- Saving locks each medicine row, increases stock, updates weighted-average cost, and writes purchase movements inside one database transaction.
- Optional supplier name is plain text; there is no supplier module.
- Cancellation is reversible, requires a reason, preserves the original record, and is rejected if purchased stock has already been sold or adjusted away.
- Completed purchases are never hard-deleted.

### Sales

- Header plus multiple line items; the owner sees stock, receives the default selling price, and may adjust it.
- Server-side stock validation runs before any line is written; row locks protect concurrent sales.
- Each sale item stores the selling price **and** the weighted-average unit cost at the moment of sale. Later price changes cannot rewrite historical profit.
- Saving reduces stock and writes sale movements atomically.
- Cancellation restores stock, writes reversal movements, records the reason and timestamp, and is rejected on a second attempt.
- Completed sales are never hard-deleted.

### Expenses

- Chamber operating expenses are separate from medicine purchases.
- Default categories: Chamber Rent, Electricity, Water, Internet, Transportation, Stationery, Cleaning, Maintenance, Other.
- Categories can be added, edited, and deactivated.
- Expenses can be created, edited, cancelled, filtered, and inspected. Cancelled expenses remain in the audit history but are excluded from financial totals.

### Reports

- Daily, monthly, yearly, and custom date-range financial reports.
- Inventory, medicine-wise sales, expense, low-stock, and stock-movement reports.
- COGS comes from stored sale-item historical cost; gross profit is revenue minus COGS; net operating profit subtracts other chamber expenses.
- Date filters include both the first and last selected day.
- PDF, XLSX, CSV, and print output use the same selected filters and date range.

### Settings and backup

- Chamber name, owner name, BDT currency and symbol, Asia/Dhaka timezone, default low-stock level, and default report period.
- Chamber logo upload (PNG/JPG/WebP, up to 2 MB) with preview, removal, and fallback monogram. The logo appears in the application navigation.
- Administration profile-picture upload (PNG/JPG/WebP, up to 2 MB) with preview, removal, and an initial-based fallback. The picture appears in the sidebar owner block.
- Changing the owner name updates the signed-in owner record so navigation and reports stay consistent.
- Password change with current-password verification.
- Authenticated business-data ZIP export; full recovery uses `mysqldump` as documented in the README.

## Architecture

```text
app/
  Http/Controllers/     HTTP orchestration and validation
  Models/               Eloquent entities and relationships
  Services/             TransactionService, ReportService, DashboardService
  Support/SimpleXlsxWriter.php
  Providers/            Settings sharing and timezone application
resources/
  css/app.css           Tailwind 4 + DESIGN.md component system
  js/app.js             Alpine line-item forms and Chart.js setup
  views/                Blade layout, components, and module pages
database/
  migrations/           MySQL-compatible schema
  seeders/              Defaults and environment-driven owner
routes/web.php          Authenticated application routes and health check
tests/Feature/          Critical workflow tests
```

`TransactionService` owns stock-changing operations. `ReportService` owns financial aggregation and historical reporting. Controllers validate requests and redirect with flash messages; Blade views do not perform writes.

## Design system

The UI follows [`../DESIGN.md`](../DESIGN.md):

- White and parchment surfaces, `#272729` dark tiles, Action Blue `#0066cc`, hairline `#e0e0e0` borders.
- SF Pro with an Inter fallback, 17px body rhythm, 14px utility text, 18px card radius, pill buttons.
- No decorative gradients or heavy shadows.
- Dark split login and dark dashboard hero, quiet white cards inside the app shell.
- Inline Lucide icons, clear focus rings, semantic stock/status chips, responsive tables, and print rules.
- Mobile navigation uses a backdrop and explicit close target; reduced-motion preferences are respected.

## Deliberate non-goals

Patients, prescriptions, appointments, customers, delivery, online ordering, payment gateways, employees, salaries, supplier accounts, multiple branches, roles, subscriptions, and public registration are out of scope. Supplier names remain optional text on purchases.
