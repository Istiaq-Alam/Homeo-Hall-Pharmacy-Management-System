# Homeo-Hall — System Behaviour & Laravel Implementation Guide

This document explains how the Homeo-Hall chamber management application actually works: every page, the Laravel classes and helpers behind it, the accounting rules, and the database transactions that change stock or money.

It is written from the current source code (`hpms/app`, `hpms/routes`, `hpms/database`, `hpms/resources`, `hpms/lang`) and the feature tests in `hpms/tests/Feature`.

> **Documentation status.** The repository's older overview documents describe a single-owner application with no roles. The code has since gained chambers, three roles (Admin / Owner / Staff), Bangla localization, themes, uploads and a PWA shell. Where the older documents disagree with this guide about access control, this guide follows the code.

---

## 1. What the application is

Homeo-Hall is a private, login-only record-keeping system for homeopathic chambers in Bangladesh. It replaces paper stock registers and manual profit arithmetic with:

- medicine inventory identified by name + potency + form + unit,
- purchases that increase stock at a weighted-average cost,
- sales that reduce stock and preserve the cost of goods at the moment of sale,
- dated stock adjustments for physical corrections,
- operating expenses kept separate from medicine purchases,
- dashboard cards and six-month charts,
- daily, monthly, yearly, custom-range, inventory, medicine-wise, expense, low-stock and stock-movement reports with PDF/XLSX/CSV/print export,
- per-chamber settings, branding, staff accounts, themes and an audit log.

It deliberately has **no** patients, prescriptions, customers, online orders, payment gateway, supplier accounts, employee/salary module, or public registration. A supplier is only an optional text field on a purchase.

---

## 2. Stack and runtime

| Layer | Technology |
|---|---|
| Language | PHP 8.4 (`composer.json` platform pin 8.4.27) |
| Framework | Laravel 13 (`laravel/framework ^13.17`) |
| Database | MySQL 8 in production; in-memory SQLite for tests |
| Views | Blade with anonymous components (`x-icon`, `x-stat-card`, `x-chart`, `x-page-heading`, `x-spinner`) |
| Styling | Tailwind CSS 4 + a token system in `resources/css/app.css` |
| Interactivity | Alpine.js 3 (`lineItems` component), Chart.js 4 loaded on demand |
| Icons | Inline SVG paths through `x-icon` |
| PDF | `barryvdh/laravel-dompdf`, wrapped by a multi-engine renderer |
| XLSX | `App\Support\SimpleXlsxWriter` (dependency-free ZIP/XML writer) |
| Uploads | Laravel filesystem: `public` locally, private S3-compatible bucket (Cloudflare R2) in production |
| Tests | PHPUnit 12 feature tests |

Important environment facts:

- Application timezone defaults to `Asia/Dhaka` (`config/app.php`), and is re-applied from the saved chamber setting in `AppServiceProvider::boot()`.
- `config/localization.php` allows only `en` and `bn`.
- `config/filesystems.php` defines `uploads` = `UPLOADS_DISK` (default `public`); production points it at `s3` (R2).
- `config/pdf.php` drives the PDF engine order: WeasyPrint → Chromium → Dompdf (`auto`).
- `config/themes.php` lists the ten selectable themes.

---

## 3. Request lifecycle

1. **Apache/PHP front controller** — `public/index.php` boots the framework.
2. **Routing** — `bootstrap/app.php` loads `routes/web.php` plus a `/up` health route. A separate `/health` route is registered outside the `web` middleware group so a database outage returns JSON `503` instead of a session/cookie 500.
3. **Global web middleware** — cookies, session, CSRF, `SubstituteBindings`, and the appended `App\Http\Middleware\SetLocale`.
4. **Route middleware**
   - `guest` on the login screen,
   - `throttle:10,1` on `POST /login`,
   - `auth` on every business page,
   - `role:admin` on the administration console,
   - `role:admin,owner` on chamber settings and data export,
   - `role:owner` on staff management,
   - `chamber.selected` on all operational pages (dashboard, medicines, purchases, sales, expenses, reports).
5. **Controller** — validates with `$request->validate(...)`, delegates stock/money work to `TransactionService` or `ReportService`, and redirects with a flash message.
6. **Blade view** — renders server data; `AppServiceProvider` shares `$appSettings` (chamber name, logo URL, brand assets) with the layout, settings page and medicine form.
7. **Response** — HTML, JSON (manifest, price lookup, health), streamed CSV, generated XLSX, or generated PDF.

Every form is a standard HTML form with `@csrf`; there is no JSON API for business writes. The only JSON endpoints are:

- `GET /manifest.webmanifest` — PWA manifest,
- `GET /sales/medicine-price` — current selling price and stock for a selected medicine,
- `GET /health` — liveness/database probe.

---

## 4. Roles, chambers and data isolation

### 4.1 Roles

`App\Enums\UserRole` is a backed string enum: `Admin`, `Owner`, `Staff`. `User::role` is cast to this enum, with helpers `isAdmin()`, `isOwner()`, `isStaff()` and `roleLabel()`.

| Capability | Admin | Owner | Staff |
|---|---|---|---|
| Sign in, change own password | ✅ | ✅ | ✅ |
| View dashboard, medicines, purchases, sales, expenses, reports | ✅ after choosing a chamber | ✅ own chamber | ✅ own chamber |
| Create/edit medicines, purchases, sales, expenses, adjustments, categories | ✅ | ✅ | ✅ |
| Add staff account | ✅ (any chamber) | ✅ own chamber | ❌ |
| Remove staff account | ✅ | ✅ own chamber only | ❌ |
| Edit chamber settings, logo, export data ZIP | ✅ | ✅ | ❌ |
| Create chambers/owners/admins, switch chamber, delete accounts, clear security log | ✅ | ❌ | ❌ |
| Switch theme, switch language | ✅ | ✅ | ✅ |

Guards are layered: routes use the `role` middleware; controllers repeat critical checks with `abort_unless(...)`; and model scoping hides other chambers' rows.

### 4.2 Chamber context

`App\Support\ChamberContext` resolves the active chamber for the request:

- Admin → `session('active_chamber_id')` if set, otherwise the admin's own `chamber_id` (usually `null`).
- Owner/Staff → their `users.chamber_id`.

`App\Models\Concerns\BelongsToChamber` is applied to every business model. It:

- adds a global scope `chamber` that filters queries to the active chamber (or `whereNull('chamber_id')` when no chamber is active, so guests cannot see tenant data),
- stamps `chamber_id` on create when the attribute is empty and a user is authenticated,
- exposes `scopeForChamber(int $chamberId)` for explicit admin queries using `withoutGlobalScope('chamber')`.

`EnsureChamberIsSelected` redirects an Admin with no active chamber to `/admin` (with a friendly error only when chambers exist) and returns `403` for a Staff user with no chamber. It also clears a stale chamber id from the session.

Consequences:

- Route-model binding (`Medicine $medicine`, `Sale $sale`, …) is automatically chamber-filtered, so a foreign id returns `404`.
- Validation of `category_id`, `medicine_id`, `expense_category_id` repeats the chamber condition via `Rule::exists(...)->where(...)`, so a crafted request cannot reference another chamber's row.
- Unique indexes are per chamber: `(chamber_id, name)` for categories, `(chamber_id, name, potency, form, unit)` for medicines, `(chamber_id, purchase_number)`, `(chamber_id, sale_number)`, `(chamber_id, key)` for settings.

### 4.3 Provisioning

`App\Services\ChamberProvisioner`:

- `createOwner($data, $chamberName)` — inside `DB::transaction`: creates the chamber, calls `ensureDefaults()`, then creates the Owner user.
- `createStaff(Chamber $chamber, $data)` — creates a Staff user in that chamber.
- `createAdmin($data)` — creates a chamber-less Admin.
- `ensureDefaults()` — `firstOrCreate`s seven medicine categories, nine expense categories, and settings (`chamber_name`, `owner_name`, `currency=BDT`, `currency_symbol=৳`, `timezone=Asia/Dhaka`, `low_stock_default=5`, `report_default_days=30`).

`Database\Seeders\DatabaseSeeder` reads `OWNER_EMAIL` / `OWNER_PASSWORD` / `OWNER_NAME`. It refuses to run with only one of the email/password pair, creates the first chamber, upserts the first account as **Admin** (not Owner) and ensures defaults. It never creates sample transactions.

---

## 5. Route map

All routes live in `hpms/routes/web.php`.

| Method | URI | Name | Handler | Access |
|---|---|---|---|---|
| GET | `/` | — | redirect to `/dashboard` | public |
| GET | `/login` | `login` | `AuthController@create` | guest |
| POST | `/login` | `login.store` | `AuthController@store` | guest + throttle |
| POST | `/logout` | `logout` | `AuthController@destroy` | auth |
| POST | `/language` | `language.update` | `LanguageController@store` | public |
| GET | `/manifest.webmanifest` | `manifest` | closure | public |
| GET | `/media/{path}` | `media.show` | `MediaController@show` | public (avatars require auth) |
| GET | `/dashboard` | `dashboard` | `DashboardController` (invokable) | auth + chamber |
| GET/POST | `/medicines` | `medicines.index/store` | `MedicineController` | auth + chamber |
| GET | `/medicines/create` | `medicines.create` | `MedicineController@create` | auth + chamber |
| GET | `/medicines/categories` | `medicines.categories` | `MedicineController@categories` | auth + chamber |
| POST | `/medicines/categories` | `medicines.categories.store` | `MedicineController@saveCategory` | auth + chamber |
| PUT | `/medicines/categories/{category}` | `medicines.categories.update` | `MedicineController@saveCategory` | auth + chamber |
| GET/POST | `/medicines/adjustments` | `medicines.adjustments.create/store` | `MedicineController@adjustmentForm/adjust` | auth + chamber |
| GET | `/medicines/{medicine}` | `medicines.show` | `MedicineController@show` | auth + chamber |
| GET/PUT | `/medicines/{medicine}/edit`, `/medicines/{medicine}` | `medicines.edit/update` | `MedicineController` | auth + chamber |
| DELETE | `/medicines/{medicine}` | `medicines.deactivate` | `MedicineController@deactivate` | auth + chamber |
| GET/POST | `/purchases` | `purchases.index/store` | `PurchaseController` | auth + chamber |
| GET | `/purchases/create` | `purchases.create` | `PurchaseController@create` | auth + chamber |
| GET | `/purchases/{purchase}` | `purchases.show` | `PurchaseController@show` | auth + chamber |
| POST | `/purchases/{purchase}/cancel` | `purchases.cancel` | `PurchaseController@cancel` | auth + chamber |
| GET/POST | `/sales` | `sales.index/store` | `SaleController` | auth + chamber |
| GET | `/sales/create` | `sales.create` | `SaleController@create` | auth + chamber |
| GET | `/sales/medicine-price` | `sales.price` | `SaleController@price` | auth + chamber |
| GET | `/sales/{sale}` | `sales.show` | `SaleController@show` | auth + chamber |
| POST | `/sales/{sale}/cancel` | `sales.cancel` | `SaleController@cancel` | auth + chamber |
| GET/POST | `/expenses` | `expenses.index/store` | `ExpenseController` | auth + chamber |
| GET | `/expenses/create` | `expenses.create` | `ExpenseController@create` | auth + chamber |
| GET | `/expenses/categories` | `expenses.categories` | `ExpenseController@categories` | auth + chamber |
| POST/PUT | `/expenses/categories...` | `expenses.categories.*` | `ExpenseController@saveCategory` | auth + chamber |
| GET/PUT | `/expenses/{expense}/edit`, `/expenses/{expense}` | `expenses.edit/update` | `ExpenseController` | auth + chamber |
| POST | `/expenses/{expense}/cancel` | `expenses.cancel` | `ExpenseController@cancel` | auth + chamber |
| GET | `/expenses/{expense}` | `expenses.show` | `ExpenseController@show` | auth + chamber |
| GET | `/reports` | `reports.index` | `ReportController@index` | auth + chamber |
| GET | `/reports/daily|monthly|yearly|inventory|medicine-sales|expenses|low-stock|stock-movements` | `reports.*` | `ReportController` | auth + chamber |
| GET | `/reports/export.csv|pdf|xlsx` | `reports.csv/pdf/xlsx` | `ReportController` | auth + chamber |
| GET/PUT | `/settings` | `settings.index/update` | `SettingsController` | GET auth; PUT admin+owner |
| PUT | `/settings/password` | `settings.password` | `SettingsController@password` | auth |
| POST/DELETE | `/settings/staff...` | `settings.staff.*` | `AdminAccountController` | owner |
| GET | `/settings/backup` | `settings.backup` | `BackupController@download` | admin+owner |
| GET | `/themes` | `themes.index` | `ThemeController@index` | auth |
| POST | `/themes` | `themes.update` | `ThemeController@update` | auth + throttle |
| GET | `/admin` | `admin.index` | `AdminConsoleController@index` | admin |
| PUT | `/admin/profile` | `admin.profile.update` | `AdminConsoleController@updateProfile` | admin |
| POST | `/admin/chambers/{chamber}/activate` | `admin.chambers.activate` | `AdminConsoleController@activate` | admin |
| DELETE | `/admin/security-log` | `admin.security-log.clear` | `AdminConsoleController@clearSecurityLog` | admin |
| POST/DELETE | `/admin/accounts...` | `admin.accounts.*` | `AdminAccountController` | admin |

---

## 6. Models and relationships

| Model | Table | Key relationships | Traits / casts |
|---|---|---|---|
| `Chamber` | `chambers` | `hasMany` users, settings | — |
| `User` | `users` | `belongsTo` chamber | `role` → `UserRole`, `password` → hashed, `avatarUrl` accessor via `MediaStorage` |
| `MedicineCategory` | `medicine_categories` | `hasMany` medicines | `BelongsToChamber` |
| `Medicine` | `medicines` | `belongsTo` category; `hasMany` movements | `BelongsToChamber`, `SoftDeletes`; decimal casts for prices/average cost |
| `Purchase` | `purchases` | `hasMany` items; `belongsTo` user | `BelongsToChamber`; `purchase_date` date cast |
| `PurchaseItem` | `purchase_items` | `belongsTo` purchase, medicine | `BelongsToChamber` |
| `Sale` | `sales` | `hasMany` items; `belongsTo` user | `BelongsToChamber`; `sale_date` date cast |
| `SaleItem` | `sale_items` | `belongsTo` sale, medicine | `BelongsToChamber`; selling price 2dp, cost 4dp |
| `ExpenseCategory` | `expense_categories` | `hasMany` expenses | `BelongsToChamber` |
| `Expense` | `expenses` | `belongsTo` category, user | `BelongsToChamber` |
| `StockAdjustment` | `stock_adjustments` | `belongsTo` medicine | `BelongsToChamber` |
| `StockMovement` | `stock_movements` | `belongsTo` medicine | `BelongsToChamber`; signed integer quantity |
| `Setting` | `settings` | `belongsTo` chamber | `BelongsToChamber`; `key`/`value` store |
| `SecurityLog` | `security_logs` | `belongsTo` user | not chamber-scoped; static `record()`, `label()`, `badgeClass()` |

Money and quantity decisions:

- Money is `DECIMAL`, never float in the database. Average cost and sale-item unit cost use 4 decimals; all totals and prices use 2.
- Stock is an **unsigned integer** (`stock_quantity`, `minimum_stock`, movement `previous_stock`/`resulting_stock`). The domain sells countable units (bottle, packet, vial, tube, box, piece).
- `stock_movements.quantity` is a signed integer because reversals and decreases are negative.
- Historical values are snapshots: `purchase_items.unit_cost`, `sale_items.unit_selling_price`, `sale_items.unit_cost_price`, `sale_items.line_cost_total`. Reports never re-price past transactions from current medicine prices.

---

## 7. Laravel features and objects used

### 7.1 Routing, middleware, bootstrap

| Laravel API | Where / why |
|---|---|
| `Route::get/post/put/delete`, `Route::redirect`, `Route::middleware`, `Route::group` | `routes/web.php` |
| Named routes + `route()` | every link, form action and redirect |
| Route model binding (`{medicine}`, `{sale}`, `{purchase}`, `{expense}`, `{category}`, `{user}`, `{chamber}`) | implicit binding, automatically chamber-filtered |
| `Route::where('path', '.*')` | catch-all media path |
| `->middleware('throttle:10,1')`, `throttle:30,1` | login and theme endpoints |
| `bootstrap/app.php`: `Application::configure()->withRouting()->withMiddleware()->withExceptions()` | modern Laravel 11+ bootstrap |
| `$middleware->redirectGuestsTo('/login')` | unauthenticated redirect |
| `$middleware->trustProxies(at: '*')` | Render/Cloudflare TLS termination |
| `$middleware->web(append: [SetLocale::class])` | locale on every web request |
| `$middleware->alias([...])` | `role`, `chamber.selected` |
| `Route::get('/health', ...)` in `withRouting(then: ...)` | DB probe outside sessions |
| custom middleware `EnsureUserHasRole`, `EnsureChamberIsSelected`, `SetLocale` | authorization, chamber gate, localization |

### 7.2 Authentication and security

| Laravel API | Use |
|---|---|
| `Auth::attempt($credentials, $remember)` | login |
| `Auth::logout()`, `$request->session()->invalidate()`, `regenerateToken()` | logout |
| `$request->session()->regenerate()` | after login and password change |
| `RateLimiter::tooManyAttempts/hit/clear/availableIn` | 5 failed attempts per email+IP per 60 s |
| `current_password` validation rule | password change and admin email/password change |
| `Hash::make()` / `hashed` cast | password storage |
| `@csrf`, `csrf_token()`, `@method('PUT'/'DELETE')` | CSRF + method spoofing |
| `abort_unless`, `abort_if` | role and ownership checks (403) |
| `Str::limit`, `Str::headline`, `Str::random`, `Str::lower` | log text, labels, upload folders, rate-limit key |
| `SecurityLog::record()` | sign-in, failed sign-in, sign-out, account created/deleted, profile updated, password changed |
| signed-in avatar guard in `MediaController` | avatars are not public |

### 7.3 Validation

`$request->validate([...])` with rule arrays is used in every controller. Notable rules:

- `Rule::exists('medicines','id')->where(chamber condition)` and the same for categories.
- `Rule::unique('medicine_categories','name')->where(chamber)->ignore($category?->id)`.
- `Rule::in([...])` for adjustment type, roles, currency, timezone, themes, locales.
- `Rule::requiredIf(...)` for `chamber_name` when creating an Owner and `chamber_id` when creating Staff.
- `distinct` on `items.*.medicine_id` prevents duplicate lines in one purchase/sale.
- `image|mimes:jpg,jpeg,png,webp|max:2048` for logo/avatar/profile images.
- `date_format:Y-m`, `between:2000,2100` for report period filters.
- `min:12` + `confirmed` for all new passwords.

Validation failures redirect back with `$errors`; the layout renders the list in a notice block.

### 7.4 Eloquent and query builder

| API | Use |
|---|---|
| `Model::query()->with()`, `withCount()`, `withSum()` | eager loading and aggregate counts (`items_count`, expense totals) |
| `when($condition, fn ($q) => ...)` | optional filters (search, category, status, date range) |
| `whereHas('items', ...)` | "purchases/sales containing this medicine" filter |
| `whereColumn('stock_quantity','<=','minimum_stock')` | low-stock definition |
| `whereBetween`, `whereDate`, `latest`, `orderByDesc`, `groupBy`, `selectRaw` | reports and lists |
| `paginate(15)`, `withQueryString()`, `links()` | paginated lists preserving filters |
| `firstOrCreate`, `updateOrCreate`, `firstOrNew` | defaults, settings, seeder |
| `lockForUpdate()` | row locks in stock transactions |
| `forceFill()->save()` | stock/cost writes bypassing mass-assignment guards |
| `$model->load('items.medicine')` | detail pages |
| global scopes (`addGlobalScope('chamber', ...)`) and `withoutGlobalScope` | tenant isolation |
| `SoftDeletes` | medicines are deactivated/soft-deleted, history kept |
| casts: `decimal:2`, `decimal:4`, `date`, `datetime`, `integer`, `boolean`, enum | consistent types |
| `DB::raw`, `DB::table`, `DB::getSchemaBuilder()->getColumnListing()` | valuation SQL, backup CSV headers |

### 7.5 Database and transactions

| API | Use |
|---|---|
| `DB::transaction(fn () => ...)` | purchase, sale, both cancellations, stock adjustment, chamber provisioning, chamber+account deletion |
| `DB::table(...)->where(...)->update/delete/insertGetId` | data migration/backfill, chamber data deletion, backup export |
| foreign keys: `cascadeOnDelete` (items with header), `restrictOnDelete` (categories, medicine references), `nullOnDelete` (actor users, chamber ids) | referential integrity |
| unique + composite indexes | identity and report performance |

### 7.6 Services and support classes

| Class | Responsibility |
|---|---|
| `App\Services\TransactionService` | the only writer of stock and transaction totals: `recordPurchase`, `recordSale`, `cancelPurchase`, `cancelSale`, `adjustStock`, `recordExpense` |
| `App\Services\ReportService` | `summary`, `medicineSales`, `inventory`, `range`, `movements` — all financial aggregation |
| `App\Services\ChamberProvisioner` | chamber + owner/staff/admin creation and default categories/settings |
| `App\Services\PdfRenderer` | chooses WeasyPrint → Chromium → Dompdf, renders HTML to PDF, cleans temp files |
| `App\Support\SimpleXlsxWriter` | builds a valid single-sheet XLSX with `ZipArchive` and SpreadsheetML |
| `App\Support\Localizer` | Bengali digits, `৳` money, localized dates, translated system labels/statuses |
| `App\Support\helpers.php` | global `money()`, `localized_number()`, `localized_date()`, `localized_date_time()`, `default_label()`, `status_label()` |
| `App\Support\MediaStorage` | resolves the uploads disk, builds `/media/...` URLs, downloads remote objects for image processing |
| `App\Support\BrandAssets` | generates favicons/PWA icons from the chamber logo via GD or ImageMagick; deletes old assets |
| `App\Support\ChamberContext` | active chamber resolution/session storage |
| `App\Models\SecurityLog` | audit event constants, actor snapshot, badges |
| `App\Providers\AppServiceProvider` | paginator views, timezone from settings, `$appSettings` view composer |

### 7.7 Blade, frontend, localization, PWA

- Layout `resources/views/layouts/app.blade.php`: sidebar, role-aware navigation, language switch, flash/error notices, install-app dialog, `@vite`, `@include('partials.pwa-head')`.
- Anonymous components: `x-icon`, `x-stat-card`, `x-chart`, `x-page-heading`, `x-spinner`.
- Alpine `lineItems` (`resources/js/app.js`): add/remove medicine rows, default price on selection, live line and grand totals, stock label. **Client totals are convenience only**; the server recalculates everything.
- Chart.js is dynamically imported; datasets are embedded as JSON in `<script type="application/json">` and colors are read from active CSS theme tokens.
- `SetLocale` + `lang/en/*.php` + `lang/bn/*.php`; `Localizer` converts numerals for display only (`০১২৩৪৫৬৭৮৯`), so stored data stays Latin.
- PWA: dynamic `manifest.webmanifest`, `partials/pwa-head.blade.php` icon/splash links, `resources/js/pwa.js` install prompt with iOS instructions and service-worker registration for the offline shell. Business data still requires a working server connection.
- Themes: `config/themes.php` metadata, `users.theme`, `<html data-theme>`, `[data-theme="..."]` token blocks in CSS, `ThemeController` validation.

### 7.8 Files, exports, PDF and health

- `UploadedFile::store()/storeAs()` on `config('filesystems.uploads')`; random folder for logos, `avatars/` for profile images.
- `Storage` facade through `MediaStorage`; `$disk->response()` streams private files with `X-Content-Type-Options: nosniff`.
- `response()->streamDownload()` for CSV (adds UTF-8 BOM in Bangla so Excel reads Bengali correctly).
- `response()->download(...)->deleteFileAfterSend(true)` for XLSX.
- `response()->make($bytes, 200, ['Content-Type' => 'application/pdf'])` for PDF.
- `response()->json(...)` for manifest, price lookup, health.
- `Schema::hasTable/hasColumn` guards in `AppServiceProvider` and the manifest route keep the app bootable during a DB outage.

---

## 8. Page-by-page behaviour

### 8.1 Login (`/login`)

Split marketing panel + form. Email, password, "remember this device". Errors are generic ("these details do not match"). A guest's language choice is stored in session + forever cookie by `LanguageController`. No registration route exists (`/register` → 404). On success: session regenerated, security log written, redirect to the intended URL or `/dashboard`. Five failures for the same email+IP inside a minute trigger a wait message with remaining seconds.

### 8.2 Dashboard (`/dashboard`)

`DashboardController` (invokable) uses `CarbonImmutable::today(config('app.timezone'))` and `ReportService`:

- **Today** cards: sales, purchases, other expenses, gross profit, net profit.
- **Inventory** cards: total medicines (all, including inactive), total stock units, low stock (`0 < stock <= minimum_stock`), out of stock (`stock = 0`), inventory value at weighted average.
- **This month** summary: revenue, purchases, other expenses, COGS, gross profit, net operating profit.
- **Six-month** series (current month and five prior) feeding four charts: monthly sales, monthly expenses, sales vs purchases, net-profit trend.
- **Recent activity**: latest 5 sales, 5 purchases, 5 expenses, 5 stock movements merged, sorted by date, top 8, linked to their pages.
- **Inventory attention**: up to six medicines at or below minimum, red dot when zero.
- Quick actions: add medicine, record purchase, record sale, add expense, view reports.

Admins with no selected chamber never reach this page; `chamber.selected` redirects them to `/admin`.

### 8.3 Medicines

- `index` filters: `q` (name or potency, `LIKE`), `category`, `form`, `status` (`low`, `out`), `sort` (`name`, `stock`, `price`, `date`), 15 per page.
- `create`/`store` and `edit`/`update` use the same `validated()` rules. Stock is **not** editable here; the form shows the current stock and links to adjustment.
- `show` displays category, form/unit, selling price, weighted average cost, stock, minimum, description, stock status and paginated movement history (10/page).
- `deactivate` (DELETE) sets `is_active = false`; it does not delete rows. Inactive medicines disappear from purchase/sale selectors but remain in history and reports.
- `categories` lists categories with medicine counts; `saveCategory` creates or updates name and active flag. Categories cannot be hard-deleted.
- `adjustmentForm`/`adjust` records a dated correction with direction, quantity, reason and optional notes.

Medicine identity is the unique tuple `chamber + name + potency + form + unit`. `Nux Vomica 30C` and `Nux Vomica 200C` are independent rows with their own stock, cost and prices. The form validates fields but has no custom duplicate message; the unique index is the final guard.

### 8.4 Purchases

- `index` filters by purchase number, medicine contained, and date range; 15 per page.
- `create` shows active medicines, a repeating Alpine row set, optional supplier text, notes. Selecting a medicine fills the last purchase price. Line totals and grand total are displayed live but recalculated on the server.
- `store` validates header + at least one item, then calls `TransactionService::recordPurchase`.
- `show` displays total, item count, total units, created date, line table, notes, and a cancel form while `status = completed`.
- `cancel` requires a reason and calls `cancelPurchase`.

### 8.5 Sales

- `index` filters by sale number, medicine contained, and date range.
- `create` lists only active medicines with stock and default selling price. The price can be edited per line; the stock label updates as the medicine changes.
- `store` validates then calls `recordSale`, which re-checks stock and active status under row locks.
- `show` shows sale total, historical COGS, gross profit, units, line table with selling price, historical unit cost, line total and line COGS, notes, and a cancel form.
- `cancel` requires a reason and restores stock.
- `GET /sales/medicine-price` returns `{selling_price, stock, name}` for the selected medicine (used for lookups).

A recorded sale stores the weighted-average cost **at that moment**, so later price or cost edits cannot rewrite historical profit.

### 8.6 Expenses

- `index` filters by description, category and date range.
- `create/store` records category, date, description, positive amount, notes.
- `edit/update` is allowed only while `status = completed`; cancelled expenses return `422`.
- `cancel` requires a reason, sets status/reason/timestamp, keeps the row.
- `categories` supports add, rename and activate/deactivate, with per-category expense counts.

Purchases are inventory acquisition, not operating expense; this separation is deliberate and is repeated in UI copy and report notes.

### 8.7 Reports

`ReportController` + `ReportService`:

| Report | Behaviour |
|---|---|
| Custom range (`/reports`) | default last `report_default_days` days (default 30); inclusive dates; summary cards, medicine-wise sales (top 8), expense by category, inventory snapshot, latest 50 movements (shows 6) |
| Daily | one day; reuses the summary layout |
| Monthly | selected `Y-m`; summary + six-month comparison series |
| Yearly | selected year; all 12 months summarized |
| Inventory | current stock, average cost, selling price, valuation, status |
| Medicine-wise sales | grouped by medicine: quantity, revenue, historical COGS, gross profit |
| Expenses | category-wise totals and transaction counts for the range |
| Low stock | all medicines at or below minimum (including zero) |
| Stock movements | full signed ledger for the range, 30/page |
| CSV | two columns (metric, value) for the summary metrics; UTF-8 BOM in Bangla |
| XLSX | one formatted summary sheet (title, range, metrics) |
| PDF | branded A4 report: chamber name/logo, administrator, period, net-profit hero, trading summary, activity counts, inventory value, note, signature block |
| Print | same server-rendered report markup via `window.print()` and print CSS |

Date semantics: `from 00:00:00` through `to 23:59:59`, both days included. If the user submits `from > to`, `ReportService::range()` swaps them. Transaction dates are the user-entered calendar dates; `created_at` does not move a transaction into another period. Inventory value is always current, independent of the report range.

### 8.8 Settings

- Owner/Admin see: logo (PNG/JPG/WebP ≤ 2 MB) with preview/remove, chamber name, owner name, currency (BDT only), symbol, timezone (Asia/Dhaka only), default low-stock level, default report days, profile picture, password change, data ZIP export.
- Staff see only password change and an "your access" card; the setting fields and backup button are withheld and the routes return `403`.
- Saving settings also renames the chamber and the owner account so navigation and reports stay consistent. Owner changes their own `users.name`; an Admin editing a chamber updates that chamber's Owner name.
- Logo upload creates derived brand assets (favicon/PWA icons). If GD/ImageMagick is unavailable, the logo is still saved and derived icons are skipped with a log warning.
- Password change requires the current password, confirmation and 12+ characters; the session is regenerated.
- Staff accounts card appears only for Owners: create (name, email, designation, 12+ password) and remove (deletes the account; chamber business history remains because `user_id` is nulled).

### 8.9 Administration (`/admin`, Admin only)

- Month-to-date overview across all chambers: chamber count, account count, sales count/revenue, estimated net profit.
- Per-chamber performance table: owner, accounts, sales count/units, revenue, purchases, expenses, estimated net profit, active medicines/stock units, "Open" to make that chamber active.
- Create account form: role Admin / Owner / Staff. Owner requires a new chamber name; Staff requires an existing chamber.
- Admin profile form: name, email, designation, optional current password (required when email or password changes), new password, profile image.
- All-accounts table with role badges, chamber, and delete (not for the signed-in account). Deleting an **Owner** deletes the chamber, its non-admin accounts, all business rows, settings and uploaded images. Deleting Admin/Staff removes just that account.
- Security log (latest 15): event badge, description, actor snapshot, IP, time; Admin can clear the whole log.

### 8.10 Themes and language

- `/themes` shows ten themes (`apple`, `cal`, `intercom`, `linear`, `mintlify`, `notion`, `resend`, `sap`, `zapier`, `starbucks`), validates the slug, saves `users.theme`, and repaints charts because they read CSS variables.
- Language switch posts `locale` (`en`/`bn`), stores it on the user and in session/cookie, and returns to the same page (same host only; never external, never back to `/language`).
- Bangla display uses Bengali numerals and translated default labels; database values remain Latin/English.

### 8.11 Media, manifest and installed app

- `/media/{path}` only serves paths matching `^(avatars|branding)/...` without `..`. Branding is readable so the login/PWA can show the logo; avatars require authentication.
- `/manifest.webmanifest` builds name, short name, icons, shortcuts (new sale, medicines, reports) from the active chamber and brand assets; it falls back to Homeo-Hall defaults if the DB is unavailable and is served `private, no-cache`.
- The layout exposes an "Install app" button when the browser supports it, with Safari/iOS share-sheet instructions; a service worker caches the app shell. This is installability, not offline data entry.

---

## 9. Business logic

### 9.1 Money and rounding

- Line totals are computed in integer **paisa** with round-half-up, then stored as 2-decimal strings.
- `unit_cost_price` keeps 4 decimals; `line_cost_total` is `round(quantity × unit_cost × 100) / 100`.
- Report totals sum stored amounts, then round for display.
- The UI formats money through `Localizer::money()` → `৳` + `number_format`, with Bengali digits in Bangla.

### 9.2 Weighted-average cost

```text
new_average =
  ((existing_qty × existing_average) + (purchased_qty × purchase_unit_cost))
  / (existing_qty + purchased_qty)
```

- Rounded to 4 decimals, stored in `medicines.average_cost`.
- The latest purchase unit cost is also copied to `medicines.purchase_price` for defaults/display; it is **not** used for historical COGS.
- When stock hits exactly zero, `average_cost` resets to `0`.
- If zero stock is later increased by an adjustment, the current `purchase_price` becomes the initial average; the next purchase blends from there.

### 9.3 Purchase recording

```text
line_total   = quantity × unit_cost
purchase     = sum(line_total)
new_stock    = old_stock + quantity
movement     = +quantity   (type "purchase")
```

### 9.4 Sale recording

```text
line_total      = quantity × actual_selling_price
line_cost_total = quantity × weighted_average_at_sale_time
sale_total      = sum(line_total)
new_stock       = old_stock - quantity
movement        = -quantity   (type "sale")
```

The server ignores the browser totals and revalidates quantity, price and stock. Duplicate lines for the same medicine are summed for the stock check, so two lines cannot jointly exceed available stock.

### 9.5 Purchase cancellation

Allowed only when the purchase is `completed`, no line would drive stock negative, and no **completed sale created after this purchase's movement** used that medicine. The last rule protects already-reported COGS: the sale was costed from the blended average, so removing the purchase afterwards would distort remaining inventory cost or silently rewrite reported profit.

On success, per line:

```text
new_stock = current_stock - purchased_qty
remaining_average =
  ((current_stock × current_average) - (purchased_qty × original_unit_cost))
  / (current_stock - purchased_qty)
remaining_average = max(0, round(remaining_average, 4))   // and 0 when new_stock = 0
movement = -purchased_qty   (type "purchase_reversal")
```

Status becomes `cancelled` with reason and timestamp. Repeating the cancellation is rejected. Purchases are never partially cancelled.

### 9.6 Sale cancellation

```text
new_stock = current_stock + sold_qty
new_average =
  ((current_stock × current_average) + (sold_qty × historical_sale_unit_cost))
  / (current_stock + sold_qty)
movement = +sold_qty   (type "sale_reversal")
```

This also works when current stock is zero: the returned historical cost becomes the new average. A second cancellation is rejected. Cancelled sales are excluded from revenue, COGS and profit.

### 9.7 Stock adjustments

```text
new_stock = old_stock ± quantity
```

- `quantity` is a positive magnitude; the signed effect is stored in the ledger (`adjustment_increase` or `adjustment_decrease`).
- A decrease below zero is rejected (`stock_never_negative`).
- Only an increase from zero initializes average cost (from `purchase_price`); other adjustments leave it unchanged, except a result of zero resets it to zero.
- A reason and date are mandatory, so every manual stock change is explainable.

### 9.8 Expenses

```text
expense = positive amount, category, date, description
```

Cancelling keeps the row, records reason/timestamp and removes it from expense and profit totals. Cancelled expenses cannot be edited.

### 9.9 Financial definitions

For the selected inclusive range:

```text
Sales revenue          = SUM(completed sale.total_amount)
Purchases              = SUM(completed purchase.total_amount)   // inventory acquisition, not an expense
COGS                   = SUM(completed sale_item.line_cost_total)
Gross profit           = Sales revenue − COGS
Other operating costs  = SUM(completed expense.amount)
Net operating profit   = Gross profit − Other operating costs
Inventory value        = SUM(medicine.stock_quantity × medicine.average_cost)   // current, independent of range
```

Ledger invariants:

```text
current stock = SUM(all signed stock_movement.quantity)
for every movement: resulting_stock = previous_stock + signed quantity
```

Units sold/purchased in the summary count only completed headers in the range.

### 9.10 Cancellations in reports

Every sales/purchases/expenses aggregate filters `status = 'completed'`, so cancelled headers contribute zero revenue, purchases, COGS, expenses and profit. Reversal movements remain in the stock ledger for audit and are not treated as new sales/purchases.

---

## 10. Database write transactions

### 10.1 `TransactionService::recordPurchase(array $data, int $userId)`

One `DB::transaction`:

1. `Purchase::create` a header with `PUR-YYYYMMDDHHMMSS-XXXXXX` (random hex) and total `0`.
2. Normalize lines; for each line `Medicine::lockForUpdate()->findOrFail($id)`.
3. Validate new quantity is ≥ 0 and ≤ `4294967295`.
4. Compute line total in paisa and add it to the running total.
5. Create `purchase_items` with historical `unit_cost` and `line_total`.
6. `forceFill` medicine: new `stock_quantity`, new 4-decimal `average_cost`, latest `purchase_price`.
7. Append a `stock_movements` row: type `purchase`, signed `+quantity`, previous/resulting stock, purchase class + id, reason `Purchase <number>`, date = purchase date.
8. Update the header total from the server-side sum.

Any failure (missing medicine, overflow, DB error) rolls back the header, all items, all stock edits and all movements.

### 10.2 `TransactionService::recordSale(array $data, int $userId)`

One `DB::transaction`:

1. Create the sale header (`SAL-...`, total `0`) — rolled back automatically if validation fails later.
2. Normalize lines and aggregate the required quantity per medicine.
3. Lock all involved medicines in ascending id order (`orderBy('id')->lockForUpdate()->get()->keyBy('id')`) — a stable order avoids deadlocks.
4. If any medicine is missing, inactive, or has less stock than the **total** requested, throw `ValidationException` before any stock change.
5. Per line: create `sale_items` with the entered selling price (2dp), the current average as `unit_cost_price` (4dp), `line_total`, and `line_cost_total`.
6. Reduce stock; if it becomes zero, average cost becomes zero; otherwise it is unchanged.
7. Append a `sale` movement with `-quantity`.
8. Update the header total.

The form's JavaScript never decides stock or totals.

### 10.3 `TransactionService::cancelPurchase(Purchase $purchase, string $reason)`

1. Lock and re-fetch the purchase with items (`findOrFail` inside the transaction), so two simultaneous cancels cannot both pass.
2. Reject unless `status === 'completed'`.
3. Per item: lock the medicine; reject if current stock < item quantity.
4. Find the earliest `purchase` movement for this purchase; reject if any `sale` movement for the same medicine was created after it and belongs to a completed sale.
5. Recompute stock and remaining average (floored at 0), save.
6. Append `purchase_reversal` movement with `-quantity`, reason, `now()`.
7. Set status `cancelled`, `cancellation_reason`, `cancelled_at`.

All lines succeed or none do.

### 10.4 `TransactionService::cancelSale(Sale $sale, string $reason)`

1. Lock and re-fetch the sale with items.
2. Reject unless completed.
3. Per item: lock medicine, add quantity back, re-blend the historical sale unit cost into the average, save.
4. Append `sale_reversal` movement with `+quantity`.
5. Mark the sale cancelled with reason/timestamp.

### 10.5 `TransactionService::adjustStock(array $data, int $userId)`

1. Lock the medicine.
2. Compute signed delta from `increase`/`decrease`.
3. Reject a negative result; reject above the unsigned range.
4. Initialize average cost from `purchase_price` when increasing from zero; reset to zero if the result is zero.
5. Create the `stock_adjustments` header (type, magnitude, reason, date, notes, actor).
6. Save the medicine.
7. Append `adjustment_increase` or `adjustment_decrease` movement with the signed quantity.

### 10.6 `TransactionService::recordExpense(...)`

A single `Expense::create` with `status = completed` and the acting user. No stock involvement, so no explicit transaction. Expense **updates** and **cancellations** are also single-row Eloquent writes in `ExpenseController`; cancellation changes status/reason/timestamp.

### 10.7 Chamber provisioning (`ChamberProvisioner::createOwner`)

`DB::transaction`: create chamber → `ensureDefaults()` (settings + 7 medicine categories + 9 expense categories via `firstOrCreate`) → create Owner user with hashed password and `email_verified_at`. Staff/Admin creation is a single insert outside an explicit transaction.

### 10.8 Account/chamber deletion (`AdminAccountController`)

- Deleting an Owner runs one `DB::transaction`: delete child item rows, movements, adjustments, headers, expenses, medicines, categories, settings; delete non-admin users of the chamber; null `chamber_id` on any remaining admin users; delete the chamber.
- Uploaded logo/avatars are deleted from the uploads disk **after** the transaction.
- Deleting an Admin/Staff is a single delete plus avatar cleanup.
- Staff removal by an Owner deletes only that Staff account in the same chamber, validated by role and `chamber_id`.

### 10.9 Writes that are *not* wrapped in an explicit transaction

These are single-row or simple writes; atomicity comes from being one statement:

- medicine create/update, deactivate,
- medicine/expense category create/update,
- expense create/update/cancel,
- settings `updateOrCreate` per key (multiple keys are separate statements — a mid-save failure could leave a partially updated settings set),
- chamber logo/avatar file store + settings write,
- theme and locale preference updates,
- login/logout session changes and security-log inserts.

### 10.10 Read-only aggregation

`ReportService::summary()` performs several aggregate queries (sales, purchases, expenses, sale items, purchase items, inventory) but writes nothing. `medicineSales()`, `inventory()`, `movements()` and `range()` are read-only. Dashboard and report pages therefore have no transaction side effects.

---

## 11. Database schema summary

Created by `2026_11_15_111801_create_hpms_business_tables.php` and extended by the chamber migration:

- `chambers(id, name)`
- `users(id, chamber_id, role, name, designation, email, password, avatar_path, theme, locale, ...)`
- `medicine_categories(id, chamber_id, name, is_active)` — unique `(chamber_id, name)`
- `medicines(id, chamber_id, category_id, name, potency, form, unit, purchase_price, selling_price, average_cost, stock_quantity, minimum_stock, description, is_active, deleted_at)` — unique identity `(chamber_id, name, potency, form, unit)`; indexes `(category_id, is_active)`, `(stock_quantity, minimum_stock)`
- `purchases(id, chamber_id, purchase_number, purchase_date, supplier_name, total_amount, status, notes, cancellation_reason, cancelled_at, user_id)` — unique `(chamber_id, purchase_number)`
- `purchase_items(id, chamber_id, purchase_id, medicine_id, quantity, unit_cost, line_total)`
- `sales(id, chamber_id, sale_number, sale_date, total_amount, status, notes, cancellation_reason, cancelled_at, user_id)` — unique `(chamber_id, sale_number)`
- `sale_items(id, chamber_id, sale_id, medicine_id, quantity, unit_selling_price, unit_cost_price, line_total, line_cost_total)`
- `expense_categories(id, chamber_id, name, is_active)` — unique `(chamber_id, name)`
- `expenses(id, chamber_id, expense_category_id, expense_date, description, amount, notes, status, cancellation_reason, cancelled_at, user_id)`
- `stock_adjustments(id, chamber_id, medicine_id, adjustment_type, quantity, reason, adjustment_date, notes, user_id)`
- `stock_movements(id, chamber_id, medicine_id, movement_type, quantity, reference_type, reference_id, previous_stock, resulting_stock, reason, movement_date)` — indexes `(medicine_id, movement_date)`, `(reference_type, reference_id)`
- `settings(id, chamber_id, key, value)` — unique `(chamber_id, key)`
- `security_logs(id, event, description, user_id, actor_name, actor_email, ip_address, user_agent)` — indexes on event and created_at
- Laravel support tables: users/password resets/sessions, cache/locks, jobs/batches/failed jobs.

Deletion rules: transaction items cascade with their header; categories and medicines restrict deletion; actor users and chamber references null on delete. Completed business headers are never hard-deleted by application routes.

---

## 12. Exports and backup

- **Report PDF/XLSX/CSV/print** all use the same validated `from`/`to` range and the same `ReportService::summary()` numbers, so changing the range changes every export consistently.
- XLSX is a single worksheet (metric/value) because PhpSpreadsheet needs `ext-gd`, which the deployment lacks; the custom writer still produces a valid `.xlsx`.
- **Settings → Data backup** streams a ZIP of CSV files for `medicine_categories`, `medicines`, `purchases`, `purchase_items`, `sales`, `sale_items`, `expense_categories`, `expenses`, `stock_adjustments`, `stock_movements`, `settings`, scoped to the active chamber. It omits users, password hashes and sessions, so it is an inspection/migration aid — **not** a restorable database backup. Use `mysqldump` for disaster recovery (see `docs/BACKUP_AND_RESTORE.md`).
- PDF engine fallback: WeasyPrint (best Bengali shaping) → Chromium (shaping) → Dompdf (no complex-script shaping; output may have imperfect Bengali). Failures are logged and the next engine is tried.

---

## 13. Security posture

- Login-only; no public registration. Passwords hashed (`Hash::make`, `hashed` cast) and minimum 12 characters on every set/reset path.
- CSRF on every form; method spoofing for PUT/DELETE.
- Login throttled twice: 5 attempts/minute per email+IP in `AuthController`, plus route `throttle:10,1`.
- Session regenerated on login and password change; invalidated on logout; secure cookie/session env flags documented for HTTPS.
- Role middleware + controller `abort_unless` + chamber global scope + chamber-aware `Rule::exists` = layered isolation.
- Uploaded avatars require authentication; media paths are pattern-checked and traversal is blocked; responses send `nosniff`.
- Mass assignment limited by `$fillable` / `#[Fillable]`; stock and cost writes use `forceFill` inside services only.
- Security log snapshots actor name/email/IP/user-agent so entries remain readable after account changes.
- Backup ZIP excludes credentials; database dumps must be protected like credentials.
- `APP_DEBUG=false` in production; `/health` and `/up` support monitoring without exposing data.

---

## 14. Verification and tests

Run from `hpms/`:

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent   # after PHP edits
```

The feature suite (`hpms/tests/Feature`) covers, among others:

- `HpmsWorkflowTest` — login/logout, no registration, medicine create/update and separate potencies, purchase totals/stock/movements, weighted average, purchase and sale cancellation (including repeat and "used by later sale" rejection), historical COGS immunity to later price edits, insufficient stock with no partial sale, failed purchase rollback, stock adjustment ledger and negative guard, expense exclusion, report formulas, cancelled-transaction exclusion, ledger invariants, PDF/CSV/XLSX headers, invalid dates, dashboard values.
- `RbacTest` — owner/staff/admin boundaries, chamber switching, admin month-to-date totals, profile/password rules, chamber-owner deletion cascade.
- `ChamberIsolationTest` — cross-chamber reads/writes/route bindings, backup scoping, guest data leakage, manifest privacy, per-chamber unique categories.
- `LocalizationTest` — locale persistence, guest cookie, redirect safety, Bengali digits/labels, business writes in Bangla.
- `PageSmokeTest`, `ThemeTest`, `SecurityLogTest`, `MediaDeliveryTest`, `PdfRenderingTest`, `UploadMigrationTest` — page availability, themes, audit log, media authorization, PDF engines, upload migration command.

Tests use in-memory SQLite from `phpunit.xml` and never touch the MySQL development/production database.

---

## 15. Limitations and observations

- XLSX is a single summary sheet, not a multi-sheet workbook (no `ext-gd` for PhpSpreadsheet).
- The ZIP export is not restorable; only `mysqldump` is a full backup.
- One chamber per Owner/Staff account; Admins switch chambers via session rather than a per-page selector.
- Stock is integer-only by design; fractional liquids would need an explicit unit-conversion design.
- Purchase cancellation is intentionally strict: after any later completed sale of that medicine, the purchase cannot be reversed until that sale is cancelled.
- Duplicate medicine identity is enforced by the database unique index but has no dedicated inline validation message.
- Settings are saved key-by-key outside a transaction, so a mid-save failure can leave a partially updated set.
- Bangla PDF quality depends on the available engine; Dompdf fallback does not shape complex scripts correctly.
- Installability/offline shell exists, but business data entry still needs a live server and database.
- The application is a chamber tool, not a pharmacy ERP: no patients, prescriptions, suppliers, customers, payments or multi-branch consolidation.

---

## Appendix A — Which page writes which table

| Action | Tables written |
|---|---|
| Login / logout | `sessions`, `security_logs` |
| Add/edit medicine | `medicines` |
| Deactivate medicine | `medicines.is_active` |
| Add/rename category | `medicine_categories` / `expense_categories` |
| Record purchase | `purchases`, `purchase_items`, `medicines` (stock, average, purchase price), `stock_movements` |
| Record sale | `sales`, `sale_items`, `medicines` (stock, average reset at zero), `stock_movements` |
| Cancel purchase | `purchases` (status/reason/time), `medicines` (stock, average), `stock_movements` |
| Cancel sale | `sales` (status/reason/time), `medicines` (stock, average), `stock_movements` |
| Stock adjustment | `stock_adjustments`, `medicines` (stock, average), `stock_movements` |
| Expense create/update/cancel | `expenses` |
| Settings save | `settings`, `chambers`, `users` (owner name), uploads disk |
| Staff/account management | `users`, `chambers`, business tables on chamber deletion, `security_logs`, uploads disk |
| Theme / language | `users.theme`, `users.locale` |
| Data export | no writes (streams a ZIP) |

## Appendix B — Glossary

- **Potency** — dilution strength (30C, 200C, 1M); part of a medicine's identity.
- **Weighted-average cost** — blended purchase cost per unit held in stock.
- **COGS** — cost of goods sold; the historical cost of the units actually sold.
- **Gross profit** — sales revenue minus COGS.
- **Net operating profit** — gross profit minus other chamber operating expenses.
- **Stock movement** — one signed ledger entry recording why stock changed.
- **Chamber** — one clinic's isolated data space (owner, staff, medicines, transactions, settings).
