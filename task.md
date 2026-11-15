# MASTER DEVELOPMENT PROMPT
# Homeo-Hall — Homeopathic Medicine Inventory & Financial Management System (HPMS)

## 1. Your Role

Act as a senior full-stack Laravel developer, software architect, database engineer, UI/UX designer, and QA engineer.

Your task is to inspect, plan, develop, test, and complete a production-quality web application named:

**Homeo-Hall — Homeopathic Medicine Inventory & Financial Management System**

The project directory already exists and is named:

`HPMS`

The project has already been initialized as a Laravel application. Do not create another Laravel project inside it. Work directly within the existing project.

Build a complete, functional application using PHP, Laravel, MySQL, Blade, Tailwind CSS, and JavaScript.

Do not merely create a UI prototype or static dashboard. Every major feature must be connected to a working backend, database, validation system, and business logic.

---

# 2. Project Background

This application is being developed for a private homeopathic medicine chamber in Bangladesh.

The application will be used by exactly one person: the chamber owner, who is also the doctor.

The owner needs a simple web-based system to maintain records of:

1. Homeopathic medicines.
2. Medicine purchases.
3. Medicine sales.
4. Current medicine inventory.
5. Other chamber expenses.
6. Daily, monthly, and yearly financial reports.
7. Estimated business profit.

The owner will manually enter all information.

The application is NOT an online pharmacy, e-commerce website, hospital management system, or multi-user pharmacy ERP.

The owner is not technically experienced. The interface must therefore be straightforward, readable, consistent, and easy to operate.

## Main objective

Replace manual notebooks and calculations with a reliable digital inventory and financial record-keeping system.

---

# 3. Strict Scope — Follow This Carefully

This is a single-user application.

Implement only the features necessary for the requirements below.

## Included

- Single owner login.
- Dashboard.
- Medicine inventory management.
- Medicine categories.
- Purchase recording.
- Sales recording.
- Automatic stock updates.
- Chamber expense recording.
- Stock movement history.
- Daily financial overview.
- Monthly financial reports.
- Yearly financial reports.
- Custom date-range reports.
- Medicine-wise sales reports.
- Low-stock alerts.
- PDF export.
- Excel/CSV export.
- Print-friendly reports.
- Application settings.
- Database backup guidance and export capability.

## Explicitly excluded

DO NOT implement:

- Patient management.
- Patient medical records.
- Prescriptions.
- Doctor appointment scheduling.
- Customer accounts.
- Customer registration.
- Customer phone numbers or addresses.
- Online ordering.
- E-commerce functionality.
- Delivery management.
- Payment gateways.
- bKash integration.
- Nagad integration.
- Card payment integration.
- Online payment processing.
- Payment-method management.
- Employee management.
- Salary management.
- Supplier account management.
- Multi-branch management.
- Pharmacy franchise management.
- Complex accounting or ERP features.
- Subscription management.
- Public registration.
- Multiple user roles.

A supplier name may be stored as optional plain text in a purchase record. Do not create a supplier management module.

A sale is simply a manually recorded medicine transaction. It does not require customer details or payment integration.

Do not expand the scope without explicit approval.

---

# 4. Technology Stack

Use the following stack:

| Component | Technology |
|---|---|
| Backend | PHP 8.3+ |
| Framework | Existing Laravel installation |
| Database | MySQL 8.x |
| ORM | Laravel Eloquent |
| Frontend | Laravel Blade |
| CSS | Tailwind CSS |
| JavaScript | Alpine.js |
| Charts | Chart.js |
| Icons | Lucide Icons |
| PDF | barryvdh/laravel-dompdf |
| Excel | Laravel Excel / PhpSpreadsheet |
| Authentication | Laravel authentication |
| Testing | PHPUnit or Pest |
| Version control | Git |

Use the Laravel version already installed in HPMS. Inspect its `composer.json` and installed dependencies before making changes.

Do not upgrade Laravel or replace major dependencies unnecessarily.

Use stable package versions compatible with the existing Laravel and PHP versions.

## Architecture

Use a conventional Laravel monolithic architecture.

Preferred structure:

- Laravel controllers for HTTP requests.
- Eloquent models for database entities.
- Form Request classes for validation.
- Service classes for business logic.
- Blade templates for frontend rendering.
- Middleware for authentication and security.
- Database migrations and seeders.
- Automated feature and unit tests.

Do not introduce unnecessary microservices, APIs, frontend frameworks, or infrastructure complexity.

---

# 5. Application Identity and Design

Application name:

**Homeo-Hall**

Short name:

**HPMS**

Application subtitle:

**Homeopathic Medicine Inventory & Financial Management System**

Target audience: a single homeopathic chamber owner in Bangladesh.

## Design direction

Create a modern, professional, clean administrative dashboard.

The design should feel like a lightweight business management application rather than a complicated enterprise ERP.

Use:

- Clean sidebar navigation.
- Responsive layout.
- White or light-neutral backgrounds.
- Subtle borders and shadows.
- Consistent spacing.
- Readable typography.
- Rounded cards and buttons.
- Clear form labels.
- Consistent status indicators.
- Lucide icons.
- Useful empty states.
- Confirmation dialogs for destructive actions.
- Toast notifications for successful operations and errors.

Avoid:

- Excessive animations.
- Overly colorful interfaces.
- Crowded dashboards.
- Tiny text.
- Complicated forms.
- Unnecessary decorative elements.

The application must work on desktop, tablet, and mobile.

## Currency and locale

Currency: Bangladeshi Taka (BDT).

Currency symbol: ৳

Display money as:

`৳1,250.00`

Use `Asia/Dhaka` as the application timezone.

Display dates in a readable Bangladesh-friendly format.

All financial values must use decimal database types, never floating-point types.

---

# 6. Main Navigation

Create a sidebar with these sections:

1. Dashboard
2. Medicines
3. Purchases
4. Sales
5. Expenses
6. Reports
7. Settings
8. Logout

Suggested routes:

- `/dashboard`
- `/medicines`
- `/purchases`
- `/sales`
- `/expenses`
- `/reports`
- `/settings`

All application pages except the login page must require authentication.

---

# 7. Module 1 — Authentication

Create a secure single-owner authentication system.

Requirements:

- Login page.
- Logout functionality.
- Secure password hashing.
- CSRF protection.
- Login throttling.
- Session security.
- No public registration.
- No user management page.
- No role management.

Create the initial owner account using a database seeder or a secure one-time setup process.

Do not hardcode real production credentials.

Use environment variables or a secure setup process for the initial account.

The application must prevent unauthorized access to all dashboard and management routes.

---

# 8. Module 2 — Dashboard

Create a useful overview of the chamber's current business condition.

## Dashboard summary cards

Display:

1. Today's Sales.
2. Today's Purchases.
3. Today's Other Expenses.
4. Today's Estimated Gross Profit.
5. Today's Estimated Net Operating Profit.
6. Total Medicines.
7. Total Stock Units.
8. Low-Stock Medicines.
9. Out-of-Stock Medicines.
10. Current Inventory Value.

## Monthly summary

Display:

- Current Month Sales.
- Current Month Purchases.
- Current Month Other Expenses.
- Current Month COGS.
- Current Month Gross Profit.
- Current Month Net Operating Profit.

## Charts

Use Chart.js.

Create:

- Monthly Sales Overview.
- Monthly Expense Overview.
- Sales vs Purchases Comparison.
- Monthly Net Profit Trend.

Use real database data. Do not hardcode chart values.

Charts should update according to available database records and selected date ranges where appropriate.

## Recent activity

Display recent:

- Sales.
- Purchases.
- Expenses.
- Stock adjustments.

Each item should link to its relevant details page.

## Quick actions

Add buttons:

- Add Medicine.
- Record Purchase.
- Record Sale.
- Add Expense.
- View Reports.

The dashboard must be responsive and must not generate misleading financial calculations.

---

# 9. Module 3 — Medicine Inventory

Create complete medicine inventory management.

## Medicine information

Each medicine should have:

- ID: automatic.
- Medicine Name.
- Potency.
- Category.
- Medicine Form.
- Unit.
- Purchase Price.
- Selling Price.
- Current Stock.
- Minimum Stock.
- Description.
- Status: Active or Inactive.
- Created At.
- Updated At.

Example medicine records:

- Nux Vomica — 30C.
- Nux Vomica — 200C.
- Arnica Montana — 30C.
- Belladonna — 200C.
- Sulphur — 1M.

IMPORTANT:

Different potencies of the same medicine must be separate inventory items.

For example:

`Nux Vomica 30C`

and

`Nux Vomica 200C`

must not share the same stock quantity.

## Medicine categories

Create default categories:

- Mother Tincture.
- Dilution.
- Globules.
- Tablets.
- Biochemic.
- Ointment.
- Other.

Allow the owner to add, edit, and deactivate categories.

## Medicine forms

Examples:

- Liquid.
- Globules.
- Tablets.
- Powder.
- Ointment.
- Other.

## Units

Examples:

- Bottle.
- Packet.
- Vial.
- Tube.
- Box.
- Piece.

Allow sensible customization if needed.

## Inventory features

Implement:

- Add medicine.
- Edit medicine information.
- View medicine details.
- Deactivate medicine.
- Search by medicine name.
- Search by potency.
- Filter by category.
- Filter by medicine form.
- Filter low-stock medicines.
- Filter out-of-stock medicines.
- Pagination.
- Sort by name, stock, price, and date.

Display stock status clearly:

- In Stock.
- Low Stock.
- Out of Stock.

## Stock rules

The current stock must be updated automatically through purchase and sale operations.

Do not allow arbitrary stock changes without a recorded stock adjustment.

If manual stock correction is required, provide a stock adjustment feature with:

- Medicine.
- Adjustment type: Increase or Decrease.
- Quantity.
- Reason.
- Date.
- Notes.

Every adjustment must create a stock movement record.

Never allow negative stock.

---

# 10. Module 4 — Purchase Management

The owner purchases medicines and manually records those purchases.

Create a purchase management system.

## Purchase entry fields

- Purchase ID: automatic.
- Purchase Number: generated automatically.
- Purchase Date.
- Medicine.
- Quantity.
- Unit Purchase Cost.
- Total Amount.
- Optional Supplier Name.
- Notes.
- Created At.

A purchase may contain multiple medicine items.

Therefore, use a purchase header and purchase item structure.

## Purchase form

Allow the owner to:

1. Create a new purchase.
2. Select an existing medicine.
3. Add multiple medicine rows.
4. Enter quantity for each medicine.
5. Enter purchase cost per unit.
6. Calculate each line total automatically.
7. Calculate the overall purchase total.
8. Save the purchase.

Formula:

`Line Total = Quantity × Unit Purchase Cost`

`Purchase Total = Sum of All Line Totals`

Show the calculated totals before submission.

## Purchase behavior

When a purchase is saved successfully:

- Increase the stock of each medicine.
- Save the purchase record.
- Save purchase item records.
- Create stock movement records.
- Preserve the purchase cost for historical reporting.

All these operations must happen inside one database transaction.

If any operation fails, roll back the complete transaction.

## Purchase history

Create:

- Purchase list.
- Purchase details.
- Search by purchase number.
- Filter by date.
- Filter by medicine.
- Monthly purchase totals.
- Purchase item history.

## Purchase corrections

Do not silently delete completed purchases.

Provide a safe cancellation or reversal process.

When a purchase is cancelled:

- Reverse its stock additions.
- Record corresponding stock movements.
- Preserve the original transaction.
- Store the cancellation reason.
- Prevent repeated cancellation.

If an incorrect purchase is edited, ensure stock quantities are reconciled correctly and atomically.

---

# 11. Module 5 — Sales Management

Create a simple manual sales recording system.

There are no customers, patient accounts, online orders, or payment integrations.

## Sale entry fields

- Sale ID: automatic.
- Sale Number: automatically generated.
- Sale Date.
- Medicine.
- Quantity.
- Unit Selling Price.
- Unit Cost Price.
- Line Total.
- Overall Total.
- Notes.
- Created At.

A sale may contain multiple medicine items.

## Sale form

Allow the owner to:

1. Create a new sale.
2. Select a medicine.
3. See available stock.
4. Enter the quantity sold.
5. Automatically retrieve the default selling price.
6. Allow the owner to adjust the actual selling price.
7. Calculate line totals.
8. Add additional medicine rows.
9. Calculate the final sale total.
10. Save the transaction.

Formula:

`Line Total = Quantity × Actual Selling Price`

`Sale Total = Sum of All Line Totals`

## Important historical price rule

When a sale is created, store both:

- Actual unit selling price.
- Unit cost price at the time of sale.

Do not calculate historical profit using the medicine's current purchase price.

The sale item must preserve the historical cost used for the profit calculation.

For the initial implementation, use a documented weighted-average inventory cost method.

When purchases increase inventory, calculate the new weighted-average cost:

`New Average Cost = ((Existing Stock × Existing Average Cost) + (Purchased Quantity × New Purchase Unit Cost)) / (Existing Stock + Purchased Quantity)`

When a sale is recorded, copy the current weighted-average cost into the sale item as its unit cost price.

When stock becomes zero, reset the inventory average cost according to a documented and consistent rule.

## Sale behavior

When a sale is successfully saved:

- Decrease medicine stock.
- Create sale record.
- Create sale item records.
- Store historical unit selling prices.
- Store historical unit cost prices.
- Create stock movement records.
- Update dashboard and reports automatically.

All operations must happen inside a database transaction.

Lock relevant medicine rows during stock validation and updates to reduce concurrency problems.

Never allow sales that exceed available stock.

## Sales history

Create:

- Sales list.
- Sale details.
- Search by sale number.
- Filter by date.
- Filter by medicine.
- Daily sales totals.
- Monthly sales totals.
- Medicine-wise sales report.

## Sale cancellation

Completed sales must not be permanently deleted.

Provide a cancellation or reversal operation.

When a sale is cancelled:

- Restore sold stock.
- Create reversal stock movements.
- Preserve the original sale record.
- Store the cancellation reason.
- Prevent repeated cancellation.

Ensure cancelled transactions are excluded from normal sales and profit reports.

---

# 12. Module 6 — Expense Management

This module is for other chamber operating expenses.

IMPORTANT:

Medicine purchases are inventory acquisitions. Do not record medicine purchases as ordinary chamber expenses.

Create a separate expense system.

## Expense fields

- Expense ID.
- Expense Date.
- Expense Category.
- Description.
- Amount.
- Notes.
- Created At.
- Updated At.

## Default expense categories

- Chamber Rent.
- Electricity.
- Water.
- Internet.
- Transportation.
- Stationery.
- Cleaning.
- Maintenance.
- Other.

Allow the owner to add, edit, and deactivate expense categories.

## Features

- Add expense.
- Edit expense.
- View expense details.
- Search expenses.
- Filter by date.
- Filter by category.
- Daily expense totals.
- Monthly expense totals.
- Category-wise expense totals.

Use decimal values for all amounts.

For incorrect expense records, provide safe editing or cancellation with an audit trail.

---

# 13. Module 7 — Reports and Analytics

This is one of the most important modules.

Create a complete reporting section with:

- Daily reports.
- Monthly reports.
- Yearly reports.
- Custom date-range reports.

The owner must be able to select a date range and generate reports from real database information.

## Financial definitions

Use these exact accounting definitions.

### Sales Revenue

Total valid sales revenue for the selected period.

### Purchases

Total amount spent acquiring medicine stock during the selected period.

Purchases are displayed as cash outflow/inventory acquisition, not automatically treated as an operating expense.

### Cost of Goods Sold (COGS)

Total historical inventory cost of the medicine quantities sold during the selected period.

Calculate it from the stored unit cost on sale items.

### Gross Profit

`Gross Profit = Sales Revenue - COGS`

### Other Operating Expenses

Total valid chamber expenses during the selected period.

### Net Operating Profit

`Net Operating Profit = Gross Profit - Other Operating Expenses`

### Inventory Quantity

`Closing Stock = Opening Stock + Purchased Quantity - Sold Quantity + Stock Adjustments`

Cancelled purchases and cancelled sales must be reversed correctly.

### Inventory Value

Calculate inventory value using the documented weighted-average inventory cost method.

Do not confuse inventory value with monthly expenses.

## Required report sections

### A. Daily Financial Report

Display:

- Total Sales.
- Total Purchases.
- COGS.
- Gross Profit.
- Other Expenses.
- Net Operating Profit.
- Number of Sales.
- Number of Purchase Records.
- Number of Expense Records.

### B. Monthly Financial Report

Display:

- Monthly Sales.
- Monthly Purchases.
- Monthly COGS.
- Monthly Gross Profit.
- Monthly Other Expenses.
- Monthly Net Operating Profit.
- Closing Inventory Value.
- Total Units Sold.
- Total Units Purchased.

Also show a monthly comparison chart.

### C. Yearly Report

Show:

- Sales by month.
- Purchases by month.
- COGS by month.
- Operating expenses by month.
- Gross profit by month.
- Net operating profit by month.

### D. Inventory Report

Show:

- Medicine Name.
- Potency.
- Category.
- Current Quantity.
- Minimum Stock.
- Average Unit Cost.
- Selling Price.
- Inventory Value.
- Stock Status.

### E. Medicine-wise Sales Report

Show:

- Medicine.
- Potency.
- Total Quantity Sold.
- Total Revenue.
- Total COGS.
- Gross Profit.

### F. Expense Report

Show:

- Expense Category.
- Total Amount.
- Number of Transactions.
- Date Range.

### G. Low Stock Report

Show all medicines at or below their configured minimum stock.

### H. Stock Movement Report

Show:

- Date.
- Medicine.
- Movement Type.
- Quantity.
- Reference.
- Reason.
- Previous and resulting stock where available.

## Export

Support:

- PDF.
- Excel (.xlsx).
- CSV.
- Browser print.

Exports must respect the currently selected report filters and date range.

Use clear report titles, currency formatting, date ranges, and summary totals.

---

# 14. Module 8 — Settings

Create a simple settings page.

Include:

- Chamber/Application Name.
- Owner Name.
- Currency.
- Timezone.
- Default low-stock behavior.
- Report preferences.
- Password change.

Default values:

- Application Name: Homeo-Hall.
- Currency: BDT.
- Currency Symbol: ৳.
- Timezone: Asia/Dhaka.

Do not create unnecessary settings.

---

# 15. Database Design

Use MySQL and Laravel migrations.

Design a normalized relational database with proper foreign keys, indexes, constraints, and timestamps.

Recommended core tables:

1. users
2. medicine_categories
3. medicines
4. purchases
5. purchase_items
6. sales
7. sale_items
8. expense_categories
9. expenses
10. stock_movements
11. stock_adjustments, if a separate adjustment header is needed
12. settings

You may add a small number of necessary supporting tables if justified, but avoid unnecessary complexity.

## Suggested relationships

- One category has many medicines.
- One medicine belongs to one category.
- One purchase has many purchase items.
- One purchase item belongs to one medicine.
- One sale has many sale items.
- One sale item belongs to one medicine.
- One expense category has many expenses.
- One medicine has many stock movements.
- One owner records purchases, sales, and expenses.

## Important database requirements

### Medicines

Include:

- id
- category_id
- name
- potency
- form
- unit
- purchase_price
- selling_price
- average_cost
- stock_quantity
- minimum_stock
- description
- is_active
- timestamps
- soft deletes where appropriate

Consider an appropriate uniqueness rule for the medicine identity, such as name + potency + form + unit, while allowing the owner to correct records safely.

### Purchases

Include:

- id
- purchase_number
- purchase_date
- supplier_name (nullable)
- total_amount
- status
- notes
- timestamps

### Purchase Items

Include:

- id
- purchase_id
- medicine_id
- quantity
- unit_cost
- line_total
- timestamps

### Sales

Include:

- id
- sale_number
- sale_date
- total_amount
- status
- notes
- timestamps

### Sale Items

Include:

- id
- sale_id
- medicine_id
- quantity
- unit_selling_price
- unit_cost_price
- line_total
- line_cost_total
- timestamps

### Expenses

Include:

- id
- expense_category_id
- expense_date
- description
- amount
- notes
- status
- timestamps

### Stock Movements

Include:

- id
- medicine_id
- movement_type
- quantity
- reference_type
- reference_id
- previous_stock
- resulting_stock
- reason
- movement_date
- timestamps

Use a reliable reference strategy for purchase, sale, and adjustment records.

### Database precision

Use appropriate DECIMAL columns, such as:

`DECIMAL(12,2)` for money.

Use integer or appropriately sized decimal quantity fields depending on the units supported. Document the decision.

Use foreign key constraints.

Index:

- Medicine names.
- Medicine categories.
- Purchase dates.
- Sale dates.
- Expense dates.
- Purchase and sale numbers.
- Stock movement medicine/date references.

Avoid storing calculated totals as the only source of truth. Validate totals on the server and preserve transaction-level amounts for historical accuracy.

---

# 16. Critical Business Logic and Data Integrity

Treat this section as mandatory.

## A. Database transactions

Purchase, sale, cancellation, and stock adjustment operations must be atomic.

Use Laravel database transactions.

## B. Stock ledger

Every stock change must generate a stock movement record.

The medicine's current stock must reconcile with the stock movement history.

Do not allow direct, untracked stock modifications.

## C. No negative stock

Never allow a sale or stock decrease that results in negative stock.

Validate stock on the server, not only in JavaScript.

## D. Historical prices

Historical sales must not change when the owner updates a medicine's current purchase or selling price.

Store the actual price and cost on every sale item.

## E. Cancellation

Never permanently delete completed purchases or sales.

Use reversible cancellation records and corresponding stock movements.

Cancelled transactions must not count toward sales, COGS, purchases, expenses, or profit totals.

## F. Concurrency

Use database transactions and row-level locks where appropriate when updating inventory.

## G. Auditability

Preserve enough information to understand:

- What changed.
- Which medicine was affected.
- When it happened.
- How much stock changed.
- Which transaction caused it.
- Why a manual adjustment was made.

## H. Historical report accuracy

Reports must use transaction dates and historical cost values.

Changing a medicine's current price must not rewrite historical financial results.

## I. Date handling

Use Asia/Dhaka as the display timezone.

Use consistent database date/time storage and conversion rules.

Date-range reports must include the entire selected dates correctly.

---

# 17. User Interface and Pages

Create the following pages.

## Authentication

- Login.

## Dashboard

- Dashboard overview.

## Medicines

- Medicine list.
- Add medicine.
- Edit medicine.
- Medicine details.
- Categories.
- Stock adjustment.

## Purchases

- Purchase list.
- Create purchase.
- Purchase details.
- Purchase cancellation.

## Sales

- Sales list.
- Create sale.
- Sale details.
- Sale cancellation.

## Expenses

- Expense list.
- Add expense.
- Edit expense.
- Expense details.
- Expense categories.

## Reports

- Reports overview.
- Daily report.
- Monthly report.
- Yearly report.
- Custom date report.
- Inventory report.
- Medicine-wise sales report.
- Expense report.
- Stock movement report.

## Settings

- General settings.
- Change password.

Use reusable Blade components for:

- Sidebar.
- Header.
- Buttons.
- Form inputs.
- Tables.
- Summary cards.
- Modals.
- Alerts.
- Pagination.
- Empty states.

Do not duplicate the same UI logic across multiple pages unnecessarily.

---

# 18. Validation and User Experience

Every form must have proper server-side validation.

Provide clear validation messages.

Examples:

- Medicine name is required.
- Quantity must be greater than zero.
- Purchase price cannot be negative.
- Selling price cannot be negative.
- Expense amount must be greater than zero.
- Insufficient stock for this sale.
- Invalid purchase or sale cancellation.
- Duplicate or invalid transaction number.

Use JavaScript for user convenience, but never rely on frontend validation alone.

Implement:

- Search.
- Pagination.
- Date filters.
- Category filters.
- Confirmation dialogs.
- Success notifications.
- Error notifications.
- Loading indicators where appropriate.
- Form old-input preservation.
- Empty states.
- Responsive tables or mobile-friendly card layouts.

When creating a purchase or sale, calculate totals dynamically on the frontend, but independently calculate and validate all totals on the backend.

---

# 19. Security

Implement standard Laravel security practices.

Requirements:

- Authentication middleware.
- CSRF protection.
- Input validation.
- Output escaping.
- Password hashing.
- Login throttling.
- Secure session configuration.
- Mass-assignment protection.
- Authorization checks.
- Safe file export handling.
- Secure environment configuration.
- No exposed database credentials.
- No committed `.env` file.
- No hardcoded production secrets.

Because the application is single-user, do not create public registration or multi-role authorization systems.

All business data must be accessible only to the authenticated owner.

---

# 20. Project Structure

Follow Laravel conventions.

Suggested structure:

app/
- Models/
- Http/Controllers/
- Http/Requests/
- Services/
- Support/

app/Services/
- InventoryService.php
- PurchaseService.php
- SalesService.php
- ExpenseService.php
- ReportService.php

resources/views/
- layouts/
- components/
- auth/
- dashboard/
- medicines/
- purchases/
- sales/
- expenses/
- reports/
- settings/

database/
- migrations/
- seeders/
- factories/

routes/
- web.php

tests/
- Feature/
- Unit/

You may improve the structure when appropriate, but keep it understandable and maintainable.

---

# 21. Testing Requirements

Do not consider the project complete just because the pages render.

Write automated tests for critical business logic.

At minimum, test:

## Authentication

- Guest cannot access dashboard.
- Owner can log in.
- Owner can log out.
- Public registration is unavailable.

## Medicine

- Create medicine.
- Update medicine.
- Search medicine.
- Different potencies remain separate.
- Low-stock calculation works.

## Purchases

- Purchase increases stock.
- Purchase totals are correct.
- Multiple purchase items work.
- Purchase cancellation reverses stock.
- Repeated cancellation is rejected.

## Sales

- Sale decreases stock.
- Sale totals are correct.
- Insufficient stock is rejected.
- Historical unit cost is preserved.
- Sale cancellation restores stock.
- Repeated cancellation is rejected.

## Expenses

- Expense creation works.
- Expense category totals are correct.
- Cancelled expenses are excluded.

## Reports

- COGS is correct.
- Gross profit is correct.
- Net operating profit is correct.
- Date filters work.
- Cancelled transactions are excluded.
- Inventory valuation is correct.

## Data integrity

- Failed purchase transactions do not partially update stock.
- Failed sales do not partially update stock.
- Stock movement records match stock changes.
- Historical reports remain stable after medicine prices change.

Use a dedicated testing database. Never run destructive test commands against production data.

---

# 22. Deployment Readiness — Do Not Deploy Yet

I may deploy this application later using a free hosting platform.

Possible options include Render or another platform capable of running Laravel.

Do not perform deployment now.

Do not assume Vercel is suitable for a conventional Laravel application.

Make the project deployment-ready while keeping local development straightforward.

Requirements:

- Correct Laravel production configuration.
- Environment-based database configuration.
- No hardcoded hostnames.
- No local-only filesystem assumptions.
- Correct storage configuration.
- Correct asset build process.
- Production-safe error handling.
- Database migration support.
- Health-check route if useful.
- A documented deployment process.
- A documented backup and restore process.

Keep the database layer compatible with MySQL.

Do not silently replace MySQL with PostgreSQL or SQLite.

Do not use local SQLite as the production database.

Do not store important business data in temporary filesystem storage.

Avoid requiring paid third-party services for core application functionality.

Do not promise that free hosting will provide permanent database storage or production-grade availability. Hosting limitations must be reviewed separately before deployment.

---

# 23. Documentation

Create a detailed `README.md`.

Include:

- Project name and description.
- Main features.
- Technology stack.
- Requirements.
- Installation instructions.
- Environment configuration.
- Database creation.
- Migration commands.
- Seeder commands.
- Local development commands.
- Build commands.
- Test commands.
- Troubleshooting.
- Security notes.
- Backup and restore instructions.
- Future deployment notes.

Also create:

`docs/PROJECT_OVERVIEW.md`

`docs/DATABASE_SCHEMA.md`

`docs/BUSINESS_LOGIC.md`

`docs/INSTALLATION.md`

`docs/TESTING.md`

Explain the accounting formulas and inventory valuation method clearly.

---

# 24. Development Workflow — Follow These Phases

Do not attempt to write the entire application blindly in one operation.

Work incrementally.

## Phase 1 — Inspect and Plan

First inspect the existing HPMS directory.

Check:

- Laravel version.
- PHP version requirements.
- Composer dependencies.
- Node dependencies.
- Existing migrations.
- Existing models.
- Existing routes.
- Existing frontend assets.
- Current project structure.

Do not overwrite existing working code without understanding it.

Then produce:

1. Project inspection summary.
2. Implementation plan.
3. Proposed database schema.
4. Relationship overview.
5. List of required packages.
6. Potential compatibility issues.

If the existing project is a fresh Laravel installation, proceed with the implementation.

If something important is missing or incompatible, explain it before making a destructive change.

## Phase 2 — Foundation

Implement:

- Authentication.
- Application layout.
- Sidebar.
- Header.
- Responsive design.
- Database migrations.
- Models.
- Seeders.
- Default categories.
- Initial owner setup.

Verify login and navigation.

## Phase 3 — Medicine Inventory

Implement:

- Medicine CRUD.
- Category management.
- Search and filters.
- Stock status.
- Stock adjustments.
- Stock movement ledger.

Test all operations.

## Phase 4 — Purchases

Implement:

- Purchase form.
- Multiple purchase items.
- Automatic totals.
- Stock additions.
- Purchase history.
- Purchase details.
- Purchase cancellation.

Test stock calculations and reversals.

## Phase 5 — Sales

Implement:

- Sales form.
- Multiple sale items.
- Automatic price retrieval.
- Dynamic totals.
- Weighted-average cost calculations.
- Stock deduction.
- Historical price preservation.
- Sale history.
- Sale cancellation.

Test all accounting and inventory calculations.

## Phase 6 — Expenses

Implement:

- Expense categories.
- Expense CRUD.
- Expense history.
- Category filters.
- Expense totals.

## Phase 7 — Dashboard and Reports

Implement:

- Dashboard metrics.
- Chart.js charts.
- Daily reports.
- Monthly reports.
- Yearly reports.
- Custom date reports.
- Inventory reports.
- Sales reports.
- Expense reports.
- Stock movement reports.
- PDF and Excel/CSV exports.

Verify the report calculations against manually calculated test examples.

## Phase 8 — Quality Assurance

Perform:

- Automated tests.
- Manual workflow checks.
- Responsive layout checks.
- Validation checks.
- Authentication checks.
- Database integrity checks.
- Error handling checks.
- Performance review.

Fix discovered problems before declaring the application complete.

## Phase 9 — Documentation and Handoff

Finish:

- README.
- Technical documentation.
- Database documentation.
- Business logic documentation.
- Installation guide.
- Testing guide.

Provide a final implementation summary.

---

# 25. Acceptance Criteria

The application is complete only when all of the following are true:

1. The owner can log in securely.
2. The owner can add and manage medicines.
3. Different potencies are stored as separate inventory items.
4. The owner can record a purchase.
5. A purchase automatically increases stock.
6. The owner can record a sale.
7. A sale automatically decreases stock.
8. The system prevents negative stock.
9. The system records every stock movement.
10. Historical purchase and sale prices are preserved.
11. The owner can record other chamber expenses.
12. The dashboard displays real financial data.
13. Daily reports work.
14. Monthly reports work.
15. Yearly reports work.
16. Custom date-range reports work.
17. COGS is calculated correctly.
18. Gross profit is calculated correctly.
19. Net operating profit is calculated correctly.
20. Inventory valuation is calculated correctly.
21. Cancelled transactions are reversed correctly.
22. PDF exports work.
23. Excel/CSV exports work.
24. The application is responsive.
25. Automated tests cover the critical workflows.
26. Documentation is complete.
27. The project remains compatible with MySQL.
28. No excluded features have been introduced.

---

# 26. Important Instructions to the Coding Agent

- Work directly inside the existing `HPMS` Laravel project.
- Do not create a nested Laravel project.
- Do not replace Laravel with another framework.
- Do not change the database from MySQL.
- Do not introduce patient management.
- Do not introduce online payments.
- Do not introduce customer management.
- Do not introduce employee or salary management.
- Do not introduce unnecessary enterprise features.
- Do not use hardcoded dashboard statistics.
- Do not leave nonfunctional buttons.
- Do not create fake sample transactions in production.
- Do not silently modify historical financial records.
- Do not silently alter stock.
- Do not permanently delete completed transactions.
- Do not skip backend validation.
- Do not skip database transactions.
- Do not skip automated tests.
- Do not expose credentials.
- Do not deploy the application without my separate instruction.

Prioritize correctness of inventory and financial calculations over visual complexity.

The owner should be able to operate the application with minimal technical knowledge.

If a requirement is ambiguous, choose the simplest reliable implementation that preserves the stated business rules. Document the decision rather than introducing unnecessary features.

When you finish each phase, summarize:

1. What you implemented.
2. Which files you created or modified.
3. Which migrations were added.
4. Which tests were executed.
5. Whether those tests passed.
6. Any remaining issues.

Do not claim a feature is complete unless it has actually been implemented and verified.

**Start now with Phase 1: inspect the existing HPMS project and produce the implementation plan. Then continue through the phases systematically.**
