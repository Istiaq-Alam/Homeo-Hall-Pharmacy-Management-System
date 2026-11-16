# হোমিও-হল — সিস্টেমের আচরণ ও লারাভেল বাস্তবায়ন নির্দেশিকা

এই নথিতে ব্যাখ্যা করা হয়েছে হোমিও-হল চেম্বার ম্যানেজমেন্ট অ্যাপ্লিকেশনটি বাস্তবে কীভাবে কাজ করে: প্রতিটি পেজ, তার পেছনের লারাভেল ক্লাস ও হেল্পার, হিসাবের নিয়ম এবং যেসব ডেটাবেস ট্রানজেকশন স্টক বা টাকা বদলায়।

নথিটি বর্তমান সোর্স কোড (`hpms/app`, `hpms/routes`, `hpms/database`, `hpms/resources`, `hpms/lang`) এবং `hpms/tests/Feature`-এর ফিচার টেস্ট পড়ে লেখা হয়েছে।

> **ডকুমেন্টেশনের অবস্থা।** রিপোজিটরির পুরোনো ওভারভিউ নথিগুলো এমন একটি অ্যাপ্লিকেশন বর্ণনা করে যেখানে একটিমাত্র মালিক আছেন এবং কোনো রোল নেই। কোডে এরপর চেম্বার, তিনটি রোল (Admin / Owner / Staff), বাংলা লোকালাইজেশন, থিম, আপলোড ও PWA শেল যোগ হয়েছে। অ্যাক্সেস কন্ট্রোল নিয়ে পুরোনো নথির সঙ্গে এই নির্দেশিকার মতভেদ হলে এখানে কোডকেই অনুসরণ করা হয়েছে।

---

## ১. অ্যাপ্লিকেশনটি কী

হোমিও-হল বাংলাদেশের হোমিওপ্যাথিক চেম্বারের জন্য একটি ব্যক্তিগত, শুধু লগইন করে ব্যবহারযোগ্য রেকর্ড রাখার সিস্টেম। এটি কাগজের স্টক রেজিস্টার ও হাতে লেখা লাভের হিসাবের বদলে দেয়:

- নাম + পোটেন্সি + ফর্ম + ইউনিট দিয়ে চিহ্নিত ওষুধের ইনভেন্টরি,
- এমন ক্রয় যা ওজনযুক্ত গড় (weighted-average) দামে স্টক বাড়ায়,
- এমন বিক্রয় যা স্টক কমায় এবং বিক্রয়ের মুহূর্তের ক্রয়মূল্য সংরক্ষণ করে,
- শারীরিক গণনার সংশোধনের জন্য তারিখসহ স্টক অ্যাডজাস্টমেন্ট,
- ওষুধ কেনা থেকে আলাদা রাখা চেম্বারের পরিচালন খরচ,
- ড্যাশবোর্ড কার্ড ও ছয় মাসের চার্ট,
- দৈনিক, মাসিক, বার্ষিক, কাস্টম রেঞ্জ, ইনভেন্টরি, ওষুধভিত্তিক, খরচ, লো-স্টক ও স্টক-মুভমেন্ট রিপোর্ট — PDF/XLSX/CSV/প্রিন্ট এক্সপোর্টসহ,
- প্রতি চেম্বারের আলাদা সেটিংস, ব্র্যান্ডিং, স্টাফ অ্যাকাউন্ট, থিম ও অডিট লগ।

এখানে ইচ্ছাকৃতভাবেই **নেই**: রোগী, প্রেসক্রিপশন, ক্রেতা, অনলাইন অর্ডার, পেমেন্ট গেটওয়ে, সরবরাহকারীর হিসাব, কর্মচারী/বেতন মডিউল বা পাবলিক রেজিস্ট্রেশন। সরবরাহকারী শুধু ক্রয়ের একটি ঐচ্ছিক টেক্সট ফিল্ড।

---

## ২. স্ট্যাক ও রানটাইম

| স্তর | প্রযুক্তি |
|---|---|
| ভাষা | PHP 8.4 (`composer.json`-এ প্ল্যাটফর্ম পিন 8.4.27) |
| ফ্রেমওয়ার্ক | Laravel 13 (`laravel/framework ^13.17`) |
| ডেটাবেস | প্রোডাকশনে MySQL 8; টেস্টে ইন-মেমরি SQLite |
| ভিউ | Blade + অ্যানোনিমাস কম্পোনেন্ট (`x-icon`, `x-stat-card`, `x-chart`, `x-page-heading`, `x-spinner`) |
| স্টাইলিং | Tailwind CSS 4 + `resources/css/app.css`-এর টোকেন সিস্টেম |
| ইন্টারঅ্যাকটিভিটি | Alpine.js 3 (`lineItems` কম্পোনেন্ট), চাহিদামতো লোড হওয়া Chart.js 4 |
| আইকন | `x-icon` দিয়ে ইনলাইন SVG পাথ |
| PDF | `barryvdh/laravel-dompdf`, একটি মাল্টি-ইঞ্জিন রেন্ডারারের ভেতরে |
| XLSX | `App\Support\SimpleXlsxWriter` (কোনো নির্ভরতা ছাড়া ZIP/XML রাইটার) |
| আপলোড | Laravel ফাইলসিস্টেম: লোকালে `public`, প্রোডাকশনে প্রাইভেট S3-সামঞ্জস্যপূর্ণ বাকেট (Cloudflare R2) |
| টেস্ট | PHPUnit 12 ফিচার টেস্ট |

গুরুত্বপূর্ণ এনভায়রনমেন্ট তথ্য:

- অ্যাপ্লিকেশনের টাইমজোন ডিফল্ট `Asia/Dhaka` (`config/app.php`), এবং `AppServiceProvider::boot()`-এ সংরক্ষিত চেম্বার সেটিংস থেকে আবার প্রয়োগ করা হয়।
- `config/localization.php` শুধু `en` ও `bn` অনুমোদন করে।
- `config/filesystems.php`-এ `uploads` = `UPLOADS_DISK` (ডিফল্ট `public`); প্রোডাকশনে এটি `s3` (R2)।
- `config/pdf.php` PDF ইঞ্জিনের ক্রম নির্ধারণ করে: WeasyPrint → Chromium → Dompdf (`auto`)।
- `config/themes.php`-এ দশটি বাছাইযোগ্য থিমের তালিকা আছে।

---

## ৩. রিকোয়েস্ট লাইফসাইকল

1. **Apache/PHP ফ্রন্ট কন্ট্রোলার** — `public/index.php` ফ্রেমওয়ার্ক বুট করে।
2. **রাউটিং** — `bootstrap/app.php` `routes/web.php` এবং একটি `/up` হেলথ রুট লোড করে। আলাদা `/health` রুট `web` মিডলওয়্যার গ্রুপের বাইরে নিবন্ধিত, যাতে ডেটাবেস বন্ধ থাকলে সেশন/কুকি 500-এর বদলে JSON `503` ফেরে।
3. **গ্লোবাল web মিডলওয়্যার** — কুকি, সেশন, CSRF, `SubstituteBindings` এবং শেষে যোগ করা `App\Http\Middleware\SetLocale`।
4. **রুট মিডলওয়্যার**
   - লগইন স্ক্রিনে `guest`,
   - `POST /login`-এ `throttle:10,1`,
   - প্রতিটি ব্যবসায়িক পেজে `auth`,
   - অ্যাডমিন কনসোলে `role:admin`,
   - চেম্বার সেটিংস ও ডেটা এক্সপোর্টে `role:admin,owner`,
   - স্টাফ ম্যানেজমেন্টে `role:owner`,
   - সব পরিচালনামূলক পেজে (ড্যাশবোর্ড, ওষুধ, ক্রয়, বিক্রয়, খরচ, রিপোর্ট) `chamber.selected`।
5. **কন্ট্রোলার** — `$request->validate(...)` দিয়ে যাচাই করে, স্টক/টাকার কাজ `TransactionService` বা `ReportService`-এ দেয় এবং ফ্ল্যাশ বার্তাসহ রিডাইরেক্ট করে।
6. **Blade ভিউ** — সার্ভারের ডেটা রেন্ডার করে; `AppServiceProvider` লেআউট, সেটিংস পেজ ও ওষুধ ফর্মের সঙ্গে `$appSettings` (চেম্বারের নাম, লোগো URL, ব্র্যান্ড অ্যাসেট) শেয়ার করে।
7. **রেসপন্স** — HTML, JSON (ম্যানিফেস্ট, দামের লুকআপ, হেলথ), স্ট্রিম করা CSV, তৈরি করা XLSX বা তৈরি করা PDF।

প্রতিটি ফর্ম সাধারণ HTML ফর্ম, সঙ্গে `@csrf`; ব্যবসায়িক লেখার জন্য কোনো JSON API নেই। একমাত্র JSON এন্ডপয়েন্ট:

- `GET /manifest.webmanifest` — PWA ম্যানিফেস্ট,
- `GET /sales/medicine-price` — নির্বাচিত ওষুধের বর্তমান বিক্রয়মূল্য ও স্টক,
- `GET /health` — লাইভনেস/ডেটাবেস প্রোব।

---

## ৪. রোল, চেম্বার ও ডেটা আইসোলেশন

### ৪.১ রোল

`App\Enums\UserRole` একটি backed string enum: `Admin`, `Owner`, `Staff`। `User::role` এই enum-এ cast হয়, সঙ্গে আছে `isAdmin()`, `isOwner()`, `isStaff()` ও `roleLabel()`।

| সুবিধা | Admin | Owner | Staff |
|---|---|---|---|
| সাইন ইন, নিজের পাসওয়ার্ড বদলানো | ✅ | ✅ | ✅ |
| ড্যাশবোর্ড, ওষুধ, ক্রয়, বিক্রয়, খরচ, রিপোর্ট দেখা | ✅ (চেম্বার বেছে নেওয়ার পর) | ✅ নিজের চেম্বার | ✅ নিজের চেম্বার |
| ওষুধ, ক্রয়, বিক্রয়, খরচ, অ্যাডজাস্টমেন্ট, ক্যাটাগরি তৈরি/সম্পাদনা | ✅ | ✅ | ✅ |
| স্টাফ অ্যাকাউন্ট যোগ করা | ✅ (যেকোনো চেম্বার) | ✅ নিজের চেম্বার | ❌ |
| স্টাফ অ্যাকাউন্ট সরানো | ✅ | ✅ শুধু নিজের চেম্বার | ❌ |
| চেম্বার সেটিংস, লোগো সম্পাদনা, ডেটা ZIP এক্সপোর্ট | ✅ | ✅ | ❌ |
| চেম্বার/ওনার/অ্যাডমিন তৈরি, চেম্বার বদল, অ্যাকাউন্ট মুছে ফেলা, সিকিউরিটি লগ পরিষ্কার | ✅ | ❌ | ❌ |
| থিম বদল, ভাষা বদল | ✅ | ✅ | ✅ |

সুরক্ষা স্তরে স্তরে সাজানো: রাউটে `role` মিডলওয়্যার; কন্ট্রোলারে `abort_unless(...)` দিয়ে গুরুত্বপূর্ণ চেকের পুনরাবৃত্তি; এবং মডেল স্কোপিং অন্য চেম্বারের সারি লুকিয়ে রাখে।

### ৪.২ চেম্বার প্রসঙ্গ

`App\Support\ChamberContext` রিকোয়েস্টের সক্রিয় চেম্বার নির্ধারণ করে:

- Admin → `session('active_chamber_id')` সেট থাকলে সেটি, নাহলে অ্যাডমিনের নিজের `chamber_id` (সাধারণত `null`)।
- Owner/Staff → তাদের `users.chamber_id`।

`App\Models\Concerns\BelongsToChamber` প্রতিটি ব্যবসায়িক মডেলে প্রয়োগ করা হয়েছে। এটি:

- একটি গ্লোবাল স্কোপ `chamber` যোগ করে যা কোয়েরিকে সক্রিয় চেম্বারে সীমাবদ্ধ করে (চেম্বার সক্রিয় না থাকলে `whereNull('chamber_id')`, যাতে গেস্টরা টেন্যান্ট ডেটা দেখতে না পারে),
- অ্যাট্রিবিউট খালি থাকলে এবং ব্যবহারকারী প্রমাণিত হলে তৈরি করার সময় `chamber_id` বসিয়ে দেয়,
- `withoutGlobalScope('chamber')` ব্যবহার করে অ্যাডমিনের স্পষ্ট কোয়েরির জন্য `scopeForChamber(int $chamberId)` দেয়।

`EnsureChamberIsSelected` সক্রিয় চেম্বার ছাড়া Admin-কে `/admin`-এ পাঠায় (চেম্বার থাকলেই বন্ধুত্বপূর্ণ ত্রুটি দেখায়) এবং চেম্বার ছাড়া Staff-এর ক্ষেত্রে `403` ফেরে। এটি সেশনের পুরোনো চেম্বার আইডিও মুছে দেয়।

পরিণতি:

- রুট-মডেল বাইন্ডিং (`Medicine $medicine`, `Sale $sale`, …) স্বয়ংক্রিয়ভাবে চেম্বার-ফিল্টার হয়, তাই অন্য চেম্বারের আইডি `404` দেয়।
- `category_id`, `medicine_id`, `expense_category_id`-এর ভ্যালিডেশন `Rule::exists(...)->where(...)` দিয়ে চেম্বারের শর্ত পুনরায় প্রয়োগ করে, তাই বানানো রিকোয়েস্ট অন্য চেম্বারের সারি উল্লেখ করতে পারে না।
- ইউনিক ইনডেক্স চেম্বারভিত্তিক: ক্যাটাগরিতে `(chamber_id, name)`, ওষুধে `(chamber_id, name, potency, form, unit)`, `(chamber_id, purchase_number)`, `(chamber_id, sale_number)`, সেটিংসে `(chamber_id, key)`।

### ৪.৩ প্রভিশনিং

`App\Services\ChamberProvisioner`:

- `createOwner($data, $chamberName)` — `DB::transaction`-এর ভেতরে: চেম্বার তৈরি, `ensureDefaults()` ডাকা, তারপর Owner ব্যবহারকারী তৈরি।
- `createStaff(Chamber $chamber, $data)` — ওই চেম্বারে Staff ব্যবহারকারী তৈরি।
- `createAdmin($data)` — চেম্বারহীন Admin তৈরি।
- `ensureDefaults()` — `firstOrCreate` দিয়ে সাতটি ওষুধ ক্যাটাগরি, নয়টি খরচ ক্যাটাগরি এবং সেটিংস (`chamber_name`, `owner_name`, `currency=BDT`, `currency_symbol=৳`, `timezone=Asia/Dhaka`, `low_stock_default=5`, `report_default_days=30`) তৈরি করে।

`Database\Seeders\DatabaseSeeder` `OWNER_EMAIL` / `OWNER_PASSWORD` / `OWNER_NAME` পড়ে। ইমেইল/পাসওয়ার্ডের একটিমাত্র পাওয়া গেলে এটি চলতে অস্বীকার করে, প্রথম চেম্বার তৈরি করে, প্রথম অ্যাকাউন্টকে **Admin** হিসেবে upsert করে (Owner নয়) এবং ডিফল্ট নিশ্চিত করে। এটি কখনো নমুনা লেনদেন তৈরি করে না।

---
## ৫. রুট ম্যাপ

সব রুট `hpms/routes/web.php`-এ আছে।

| মেথড | URI | নাম | হ্যান্ডলার | অ্যাক্সেস |
|---|---|---|---|---|
| GET | `/` | — | `/dashboard`-এ রিডাইরেক্ট | পাবলিক |
| GET | `/login` | `login` | `AuthController@create` | গেস্ট |
| POST | `/login` | `login.store` | `AuthController@store` | গেস্ট + থ্রটল |
| POST | `/logout` | `logout` | `AuthController@destroy` | লগইন আবশ্যক |
| POST | `/language` | `language.update` | `LanguageController@store` | পাবলিক |
| GET | `/manifest.webmanifest` | `manifest` | closure | পাবলিক |
| GET | `/media/{path}` | `media.show` | `MediaController@show` | পাবলিক (অ্যাভাটারে লগইন লাগে) |
| GET | `/dashboard` | `dashboard` | `DashboardController` (invokable) | লগইন + চেম্বার |
| GET/POST | `/medicines` | `medicines.index/store` | `MedicineController` | লগইন + চেম্বার |
| GET | `/medicines/create` | `medicines.create` | `MedicineController@create` | লগইন + চেম্বার |
| GET | `/medicines/categories` | `medicines.categories` | `MedicineController@categories` | লগইন + চেম্বার |
| POST | `/medicines/categories` | `medicines.categories.store` | `MedicineController@saveCategory` | লগইন + চেম্বার |
| PUT | `/medicines/categories/{category}` | `medicines.categories.update` | `MedicineController@saveCategory` | লগইন + চেম্বার |
| GET/POST | `/medicines/adjustments` | `medicines.adjustments.create/store` | `MedicineController@adjustmentForm/adjust` | লগইন + চেম্বার |
| GET | `/medicines/{medicine}` | `medicines.show` | `MedicineController@show` | লগইন + চেম্বার |
| GET/PUT | `/medicines/{medicine}/edit`, `/medicines/{medicine}` | `medicines.edit/update` | `MedicineController` | লগইন + চেম্বার |
| DELETE | `/medicines/{medicine}` | `medicines.deactivate` | `MedicineController@deactivate` | লগইন + চেম্বার |
| GET/POST | `/purchases` | `purchases.index/store` | `PurchaseController` | লগইন + চেম্বার |
| GET | `/purchases/create` | `purchases.create` | `PurchaseController@create` | লগইন + চেম্বার |
| GET | `/purchases/{purchase}` | `purchases.show` | `PurchaseController@show` | লগইন + চেম্বার |
| POST | `/purchases/{purchase}/cancel` | `purchases.cancel` | `PurchaseController@cancel` | লগইন + চেম্বার |
| GET/POST | `/sales` | `sales.index/store` | `SaleController` | লগইন + চেম্বার |
| GET | `/sales/create` | `sales.create` | `SaleController@create` | লগইন + চেম্বার |
| GET | `/sales/medicine-price` | `sales.price` | `SaleController@price` | লগইন + চেম্বার |
| GET | `/sales/{sale}` | `sales.show` | `SaleController@show` | লগইন + চেম্বার |
| POST | `/sales/{sale}/cancel` | `sales.cancel` | `SaleController@cancel` | লগইন + চেম্বার |
| GET/POST | `/expenses` | `expenses.index/store` | `ExpenseController` | লগইন + চেম্বার |
| GET | `/expenses/create` | `expenses.create` | `ExpenseController@create` | লগইন + চেম্বার |
| GET | `/expenses/categories` | `expenses.categories` | `ExpenseController@categories` | লগইন + চেম্বার |
| POST/PUT | `/expenses/categories...` | `expenses.categories.*` | `ExpenseController@saveCategory` | লগইন + চেম্বার |
| GET/PUT | `/expenses/{expense}/edit`, `/expenses/{expense}` | `expenses.edit/update` | `ExpenseController` | লগইন + চেম্বার |
| POST | `/expenses/{expense}/cancel` | `expenses.cancel` | `ExpenseController@cancel` | লগইন + চেম্বার |
| GET | `/expenses/{expense}` | `expenses.show` | `ExpenseController@show` | লগইন + চেম্বার |
| GET | `/reports` | `reports.index` | `ReportController@index` | লগইন + চেম্বার |
| GET | `/reports/daily`, `/reports/monthly`, `/reports/yearly`, `/reports/inventory`, `/reports/medicine-sales`, `/reports/expenses`, `/reports/low-stock`, `/reports/stock-movements` | `reports.*` | `ReportController` | লগইন + চেম্বার |
| GET | `/reports/export.csv`, `/reports/export.pdf`, `/reports/export.xlsx` | `reports.csv/pdf/xlsx` | `ReportController` | লগইন + চেম্বার |
| GET/PUT | `/settings` | `settings.index/update` | `SettingsController` | GET-এ লগইন; PUT-এ অ্যাডমিন+ওনার |
| PUT | `/settings/password` | `settings.password` | `SettingsController@password` | লগইন আবশ্যক |
| POST/DELETE | `/settings/staff...` | `settings.staff.*` | `AdminAccountController` | ওনার |
| GET | `/settings/backup` | `settings.backup` | `BackupController@download` | অ্যাডমিন+ওনার |
| GET | `/themes` | `themes.index` | `ThemeController@index` | লগইন আবশ্যক |
| POST | `/themes` | `themes.update` | `ThemeController@update` | লগইন + থ্রটল |
| GET | `/admin` | `admin.index` | `AdminConsoleController@index` | অ্যাডমিন |
| PUT | `/admin/profile` | `admin.profile.update` | `AdminConsoleController@updateProfile` | অ্যাডমিন |
| POST | `/admin/chambers/{chamber}/activate` | `admin.chambers.activate` | `AdminConsoleController@activate` | অ্যাডমিন |
| DELETE | `/admin/security-log` | `admin.security-log.clear` | `AdminConsoleController@clearSecurityLog` | অ্যাডমিন |
| POST/DELETE | `/admin/accounts...` | `admin.accounts.*` | `AdminAccountController` | অ্যাডমিন |

---

## ৬. মডেল ও সম্পর্ক

| মডেল | টেবিল | মূল সম্পর্ক | ট্রেইট / কাস্ট |
|---|---|---|---|
| `Chamber` | `chambers` | `hasMany` users, settings | — |
| `User` | `users` | `belongsTo` chamber | `role` → `UserRole`, `password` → hashed, `MediaStorage` দিয়ে `avatarUrl` অ্যাক্সেসর |
| `MedicineCategory` | `medicine_categories` | `hasMany` medicines | `BelongsToChamber` |
| `Medicine` | `medicines` | `belongsTo` category; `hasMany` movements | `BelongsToChamber`, `SoftDeletes`; দাম/গড় ক্রয়ের decimal কাস্ট |
| `Purchase` | `purchases` | `hasMany` items; `belongsTo` user | `BelongsToChamber`; `purchase_date` date কাস্ট |
| `PurchaseItem` | `purchase_items` | `belongsTo` purchase, medicine | `BelongsToChamber` |
| `Sale` | `sales` | `hasMany` items; `belongsTo` user | `BelongsToChamber`; `sale_date` date কাস্ট |
| `SaleItem` | `sale_items` | `belongsTo` sale, medicine | `BelongsToChamber`; বিক্রয়মূল্য 2dp, ক্রয়মূল্য 4dp |
| `ExpenseCategory` | `expense_categories` | `hasMany` expenses | `BelongsToChamber` |
| `Expense` | `expenses` | `belongsTo` category, user | `BelongsToChamber` |
| `StockAdjustment` | `stock_adjustments` | `belongsTo` medicine | `BelongsToChamber` |
| `StockMovement` | `stock_movements` | `belongsTo` medicine | `BelongsToChamber`; সাইনযুক্ত integer quantity |
| `Setting` | `settings` | `belongsTo` chamber | `BelongsToChamber`; `key`/`value` স্টোর |
| `SecurityLog` | `security_logs` | `belongsTo` user | চেম্বার-স্কোপড নয়; static `record()`, `label()`, `badgeClass()` |

টাকা ও পরিমাণের সিদ্ধান্ত:

- টাকা `DECIMAL`, ডেটাবেসে কখনো float নয়। গড় ক্রয় ও বিক্রয়-আইটেমের ইউনিট ক্রয়মূল্য 4 দশমিক, সব টোটাল ও দাম 2 দশমিক।
- স্টক **unsigned integer** (`stock_quantity`, `minimum_stock`, মুভমেন্টের `previous_stock`/`resulting_stock`)। এই ডোমেইন গণনাযোগ্য একক বিক্রি করে (বোতল, প্যাকেট, ভায়াল, টিউব, বাক্স, পিস)।
- `stock_movements.quantity` সাইনযুক্ত integer, কারণ ফেরত ও হ্রাস ঋণাত্মক হয়।
- ঐতিহাসিক মান স্ন্যাপশট: `purchase_items.unit_cost`, `sale_items.unit_selling_price`, `sale_items.unit_cost_price`, `sale_items.line_cost_total`। রিপোর্ট কখনো পুরোনো লেনদেন বর্তমান ওষুধের দাম দিয়ে নতুন করে দাম হিসাব করে না।

---
## ৭. ব্যবহৃত লারাভেল ফিচার ও অবজেক্ট

### ৭.১ রাউটিং, মিডলওয়্যার, বুটস্ট্র্যাপ

| Laravel API | কোথায় / কেন |
|---|---|
| `Route::get/post/put/delete`, `Route::redirect`, `Route::middleware`, `Route::group` | `routes/web.php` |
| নামযুক্ত রুট + `route()` | প্রতিটি লিংক, ফর্ম অ্যাকশন ও রিডাইরেক্ট |
| রুট মডেল বাইন্ডিং (`{medicine}`, `{sale}`, `{purchase}`, `{expense}`, `{category}`, `{user}`, `{chamber}`) | implicit বাইন্ডিং, স্বয়ংক্রিয়ভাবে চেম্বার-ফিল্টারড |
| `Route::where('path', '.*')` | সব ধরার মিডিয়া পাথ |
| `->middleware('throttle:10,1')`, `throttle:30,1` | লগইন ও থিম এন্ডপয়েন্ট |
| `bootstrap/app.php`: `Application::configure()->withRouting()->withMiddleware()->withExceptions()` | আধুনিক Laravel 11+ বুটস্ট্র্যাপ |
| `$middleware->redirectGuestsTo('/login')` | লগইন না করা ব্যবহারকারীর রিডাইরেক্ট |
| `$middleware->trustProxies(at: '*')` | Render/Cloudflare TLS টার্মিনেশন |
| `$middleware->web(append: [SetLocale::class])` | প্রতিটি web রিকোয়েস্টে লোকেল |
| `$middleware->alias([...])` | `role`, `chamber.selected` |
| `withRouting(then: ...)`-এ `Route::get('/health', ...)` | সেশনের বাইরে DB প্রোব |
| কাস্টম মিডলওয়্যার `EnsureUserHasRole`, `EnsureChamberIsSelected`, `SetLocale` | অনুমোদন, চেম্বার গেট, লোকালাইজেশন |

### ৭.২ অথেন্টিকেশন ও নিরাপত্তা

| Laravel API | ব্যবহার |
|---|---|
| `Auth::attempt($credentials, $remember)` | লগইন |
| `Auth::logout()`, `$request->session()->invalidate()`, `regenerateToken()` | লগআউট |
| `$request->session()->regenerate()` | লগইনের পর ও পাসওয়ার্ড বদলের পর |
| `RateLimiter::tooManyAttempts/hit/clear/availableIn` | প্রতি ইমেইল+IP-তে ৬০ সেকেন্ডে ৫টি ব্যর্থ চেষ্টা |
| `current_password` ভ্যালিডেশন রুল | পাসওয়ার্ড বদল ও অ্যাডমিনের ইমেইল/পাসওয়ার্ড বদল |
| `Hash::make()` / `hashed` কাস্ট | পাসওয়ার্ড সংরক্ষণ |
| `@csrf`, `csrf_token()`, `@method('PUT'/'DELETE')` | CSRF + মেথড স্পুফিং |
| `abort_unless`, `abort_if` | রোল ও মালিকানা যাচাই (403) |
| `Str::limit`, `Str::headline`, `Str::random`, `Str::lower` | লগ টেক্সট, লেবেল, আপলোড ফোল্ডার, রেট-লিমিট কী |
| `SecurityLog::record()` | সাইন-ইন, ব্যর্থ সাইন-ইন, সাইন-আউট, অ্যাকাউন্ট তৈরি/মুছে ফেলা, প্রোফাইল হালনাগাদ, পাসওয়ার্ড বদল |
| `MediaController`-এ লগইন-যাচাই করা অ্যাভাটার গার্ড | অ্যাভাটার পাবলিক নয় |

### ৭.৩ ভ্যালিডেশন

প্রতিটি কন্ট্রোলারে রুল অ্যারের সঙ্গে `$request->validate([...])` ব্যবহৃত হয়। উল্লেখযোগ্য রুল:

- `Rule::exists('medicines','id')->where(chamber condition)` এবং ক্যাটাগরির জন্যও একই।
- `Rule::unique('medicine_categories','name')->where($chamber)->ignore($category?->id)`।
- `Rule::in([...])` — অ্যাডজাস্টমেন্টের ধরন, রোল, কারেন্সি, টাইমজোন, থিম, লোকেল।
- `Rule::requiredIf(...)` — Owner তৈরির সময় `chamber_name`, Staff তৈরির সময় `chamber_id`।
- `items.*.medicine_id`-তে `distinct`, যাতে একই ক্রয়/বিক্রয়ে ডুপ্লিকেট লাইন না হয়।
- লোগো/অ্যাভাটার/প্রোফাইল ছবিতে `image|mimes:jpg,jpeg,png,webp|max:2048`।
- রিপোর্টের সময়সীমায় `date_format:Y-m`, `between:2000,2100`।
- সব নতুন পাসওয়ার্ডে `min:12` + `confirmed`।

ভ্যালিডেশন ব্যর্থ হলে `$errors` নিয়ে পেছনে রিডাইরেক্ট হয়; লেআউট তালিকাটি একটি নোটিশ ব্লকে দেখায়।

### ৭.৪ Eloquent ও query builder

| API | ব্যবহার |
|---|---|
| `Model::query()->with()`, `withCount()`, `withSum()` | eager loading ও সমষ্টি (`items_count`, খরচের টোটাল) |
| `when($condition, fn ($q) => ...)` | ঐচ্ছিক ফিল্টার (সার্চ, ক্যাটাগরি, স্ট্যাটাস, তারিখের সীমা) |
| `whereHas('items', ...)` | "এই ওষুধ আছে এমন ক্রয়/বিক্রয়" ফিল্টার |
| `whereColumn('stock_quantity','<=','minimum_stock')` | লো-স্টকের সংজ্ঞা |
| `whereBetween`, `whereDate`, `latest`, `orderByDesc`, `groupBy`, `selectRaw` | রিপোর্ট ও তালিকা |
| `paginate(15)`, `withQueryString()`, `links()` | ফিল্টার ধরে রাখা পেজিনেটেড তালিকা |
| `firstOrCreate`, `updateOrCreate`, `firstOrNew` | ডিফল্ট, সেটিংস, সিডার |
| `lockForUpdate()` | স্টক ট্রানজেকশনে সারি লক |
| `forceFill()->save()` | mass-assignment গার্ড এড়িয়ে স্টক/ক্রয়মূল্যের লেখা |
| `$model->load('items.medicine')` | ডিটেইল পেজ |
| গ্লোবাল স্কোপ (`addGlobalScope('chamber', ...)`) ও `withoutGlobalScope` | টেন্যান্ট আইসোলেশন |
| `SoftDeletes` | ওষুধ নিষ্ক্রিয়/soft-delete হয়, ইতিহাস থাকে |
| কাস্ট: `decimal:2`, `decimal:4`, `date`, `datetime`, `integer`, `boolean`, enum | সামঞ্জস্যপূর্ণ টাইপ |
| `DB::raw`, `DB::table`, `DB::getSchemaBuilder()->getColumnListing()` | ভ্যালুয়েশন SQL, ব্যাকআপ CSV হেডার |

### ৭.৫ ডেটাবেস ও ট্রানজেকশন

| API | ব্যবহার |
|---|---|
| `DB::transaction(fn () => ...)` | ক্রয়, বিক্রয়, উভয় বাতিল, স্টক অ্যাডজাস্টমেন্ট, চেম্বার প্রভিশনিং, চেম্বার+অ্যাকাউন্ট ডিলিট |
| `DB::table(...)->where(...)->update/delete/insertGetId` | ডেটা মাইগ্রেশন/ব্যাকফিল, চেম্বার ডেটা ডিলিট, ব্যাকআপ এক্সপোর্ট |
| ফরেন কী: `cascadeOnDelete` (হেডারের সঙ্গে আইটেম), `restrictOnDelete` (ক্যাটাগরি, ওষুধের রেফারেন্স), `nullOnDelete` (কর্মীর user, chamber id) | রেফারেন্সিয়াল ইন্টিগ্রিটি |
| ইউনিক ও কম্পোজিট ইনডেক্স | পরিচয় ও রিপোর্টের পারফরম্যান্স |

### ৭.৬ সার্ভিস ও সাপোর্ট ক্লাস

| ক্লাস | দায়িত্ব |
|---|---|
| `App\Services\TransactionService` | স্টক ও লেনদেনের টোটালের একমাত্র লেখক: `recordPurchase`, `recordSale`, `cancelPurchase`, `cancelSale`, `adjustStock`, `recordExpense` |
| `App\Services\ReportService` | `summary`, `medicineSales`, `inventory`, `range`, `movements` — সব আর্থিক সমষ্টি |
| `App\Services\ChamberProvisioner` | চেম্বার + Owner/Staff/Admin তৈরি এবং ডিফল্ট ক্যাটাগরি/সেটিংস |
| `App\Services\PdfRenderer` | WeasyPrint → Chromium → Dompdf বেছে নেয়, HTML থেকে PDF বানায়, টেম্প ফাইল পরিষ্কার করে |
| `App\Support\SimpleXlsxWriter` | `ZipArchive` ও SpreadsheetML দিয়ে বৈধ একক-শীট XLSX বানায় |
| `App\Support\Localizer` | বাংলা অঙ্ক, `৳` টাকা, বাংলা তারিখ, অনূদিত সিস্টেম লেবেল/স্ট্যাটাস |
| `App\Support\helpers.php` | গ্লোবাল `money()`, `localized_number()`, `localized_date()`, `localized_date_time()`, `default_label()`, `status_label()` |
| `App\Support\MediaStorage` | আপলোড ডিস্ক নির্ধারণ, `/media/...` URL তৈরি, ইমেজ প্রসেসিংয়ের জন্য রিমোট অবজেক্ট ডাউনলোড |
| `App\Support\BrandAssets` | চেম্বার লোগো থেকে GD বা ImageMagick দিয়ে ফ্যাভিকন/PWA আইকন তৈরি; পুরোনো অ্যাসেট মোছে |
| `App\Support\ChamberContext` | সক্রিয় চেম্বার নির্ধারণ/সেশনে রাখা |
| `App\Models\SecurityLog` | অডিট ইভেন্টের ধ্রুবক, কর্মীর স্ন্যাপশট, ব্যাজ |
| `App\Providers\AppServiceProvider` | পেজিনেটর ভিউ, সেটিংস থেকে টাইমজোন, `$appSettings` ভিউ কম্পোজার |

### ৭.৭ Blade, ফ্রন্টএন্ড, লোকালাইজেশন, PWA

- লেআউট `resources/views/layouts/app.blade.php`: সাইডবার, রোল-সচেতন নেভিগেশন, ভাষা সুইচ, ফ্ল্যাশ/ত্রুটি নোটিশ, ইনস্টল-অ্যাপ ডায়ালগ, `@vite`, `@include('partials.pwa-head')`।
- অ্যানোনিমাস কম্পোনেন্ট: `x-icon`, `x-stat-card`, `x-chart`, `x-page-heading`, `x-spinner`।
- Alpine `lineItems` (`resources/js/app.js`): ওষুধের সারি যোগ/মুছুন, নির্বাচনে ডিফল্ট দাম, লাইভ লাইন ও গ্র্যান্ড টোটাল, স্টক লেবেল। **ক্লায়েন্টের টোটাল শুধু সুবিধার জন্য**; সার্ভার সব নতুন করে গণনা করে।
- Chart.js ডাইনামিকভাবে ইমপোর্ট হয়; ডেটাসেট `<script type="application/json">`-এ JSON হিসেবে বসানো হয় এবং রং CSS থিম টোকেন থেকে পড়া হয়।
- `SetLocale` + `lang/en/*.php` + `lang/bn/*.php`; `Localizer` শুধু প্রদর্শনের জন্য অঙ্ক বদলায় (`০১২৩৪৫৬৭৮৯`), তাই সংরক্ষিত ডেটা ল্যাটিনই থাকে।
- PWA: ডাইনামিক `manifest.webmanifest`, `partials/pwa-head.blade.php`-এ আইকন/স্প্ল্যাশ লিংক, `resources/js/pwa.js`-এ ইনস্টল প্রম্পট (iOS নির্দেশনাসহ) ও অফলাইন শেলের সার্ভিস-ওয়ার্কার রেজিস্ট্রেশন। ব্যবসায়িক ডেটার জন্য সার্ভার সংযোগ লাগবেই।
- থিম: `config/themes.php`-এর মেটাডেটা, `users.theme`, `<html data-theme>`, CSS-এ `[data-theme="..."]` টোকেন ব্লক, `ThemeController`-এ ভ্যালিডেশন।

### ৭.৮ ফাইল, এক্সপোর্ট, PDF ও হেলথ

- `config('filesystems.uploads')`-এ `UploadedFile::store()/storeAs()`; লোগোর জন্য র‍্যান্ডম ফোল্ডার, প্রোফাইল ছবির জন্য `avatars/`।
- `MediaStorage`-এর মাধ্যমে `Storage` ফ্যাসাড; `$disk->response()` প্রাইভেট ফাইল `X-Content-Type-Options: nosniff` দিয়ে স্ট্রিম করে।
- CSV-এর জন্য `response()->streamDownload()` (বাংলায় UTF-8 BOM যোগ করে, যাতে Excel বাংলা ঠিকভাবে পড়ে)।
- XLSX-এর জন্য `response()->download(...)->deleteFileAfterSend(true)`।
- PDF-এর জন্য `response()->make($bytes, 200, ['Content-Type' => 'application/pdf'])`।
- ম্যানিফেস্ট, দামের লুকআপ, হেলথের জন্য `response()->json(...)`।
- `AppServiceProvider` ও ম্যানিফেস্ট রুটে `Schema::hasTable/hasColumn` গার্ড, যাতে ডেটাবেস বন্ধ থাকলেও অ্যাপ বুট হতে পারে।

---
## ৮. পেজভিত্তিক আচরণ

### ৮.১ লগইন (`/login`)

বাঁ দিকে মার্কেটিং প্যানেল, ডানে ফর্ম। ইমেইল, পাসওয়ার্ড, "এই ডিভাইস মনে রাখুন"। ত্রুটি সাধারণ ("এই তথ্যগুলো মিলছে না")। গেস্টের ভাষার পছন্দ `LanguageController` সেশন + ফরএভার কুকিতে রাখে। কোনো রেজিস্ট্রেশন রুট নেই (`/register` → 404)। সফল হলে: সেশন নতুন করে তৈরি, সিকিউরিটি লগ লেখা, কাঙ্ক্ষিত URL বা `/dashboard`-এ রিডাইরেক্ট। একই ইমেইল+IP-তে এক মিনিটে পাঁচটি ব্যর্থতা হলে বাকি সেকেন্ডসহ অপেক্ষার বার্তা দেখায়।

### ৮.২ ড্যাশবোর্ড (`/dashboard`)

`DashboardController` (invokable) `CarbonImmutable::today(config('app.timezone'))` ও `ReportService` ব্যবহার করে:

- **আজ** কার্ড: বিক্রয়, ক্রয়, অন্যান্য খরচ, গ্রস লাভ, নিট লাভ।
- **ইনভেন্টরি** কার্ড: মোট ওষুধ (নিষ্ক্রিয়সহ), মোট স্টক ইউনিট, লো-স্টক (`0 < stock <= minimum_stock`), স্টক শেষ (`stock = 0`), ওজনযুক্ত গড় দামে ইনভেন্টরি ভ্যালু।
- **এই মাস** সারাংশ: আয়, ক্রয়, অন্যান্য খরচ, COGS, গ্রস লাভ, নিট পরিচালন লাভ।
- **ছয় মাসের** সিরিজ (চলতি মাস ও আগের পাঁচ) দিয়ে চারটি চার্ট: মাসিক বিক্রয়, মাসিক খরচ, বিক্রয় বনাম ক্রয়, নিট লাভের ধারা।
- **সাম্প্রতিক কার্যকলাপ**: সর্বশেষ ৫টি বিক্রয়, ৫টি ক্রয়, ৫টি খরচ, ৫টি স্টক মুভমেন্ট একত্রে, তারিখ অনুযায়ী সাজিয়ে শীর্ষ ৮টি, নিজ নিজ পেজে লিংকসহ।
- **ইনভেন্টরি নজর**: ন্যূনতম স্টকে থাকা বা তার নিচে থাকা সর্বোচ্চ ছয়টি ওষুধ, শূন্য হলে লাল বিন্দু।
- কুইক অ্যাকশন: ওষুধ যোগ, ক্রয়, বিক্রয়, খরচ, রিপোর্ট।

চেম্বার নির্বাচন না করা অ্যাডমিন এই পেজে পৌঁছায় না; `chamber.selected` তাকে `/admin`-এ পাঠায়।

### ৮.৩ ওষুধ

- `index` ফিল্টার: `q` (নাম বা পোটেন্সি, `LIKE`), `category`, `form`, `status` (`low`, `out`), `sort` (`name`, `stock`, `price`, `date`), প্রতি পেজে ১৫টি।
- `create`/`store` ও `edit`/`update` একই `validated()` রুল ব্যবহার করে। এখানে স্টক **সম্পাদনযোগ্য নয়**; ফর্ম বর্তমান স্টক দেখায় এবং অ্যাডজাস্টমেন্টে লিংক দেয়।
- `show` ক্যাটাগরি, ফর্ম/ইউনিট, বিক্রয়মূল্য, ওজনযুক্ত গড় ক্রয়, স্টক, ন্যূনতম, বিবরণ, স্টক স্ট্যাটাস এবং পেজিনেটেড মুভমেন্ট ইতিহাস (প্রতি পেজে ১০) দেখায়।
- `deactivate` (DELETE) `is_active = false` করে; সারি মোছে না। নিষ্ক্রিয় ওষুধ ক্রয়/বিক্রয়ের সিলেক্টরে আসে না, কিন্তু ইতিহাস ও রিপোর্টে থাকে।
- `categories` ওষুধের সংখ্যাসহ ক্যাটাগরি তালিকা দেখায়; `saveCategory` নাম ও সক্রিয়তা তৈরি বা হালনাগাদ করে। ক্যাটাগরি হার্ড-ডিলিট করা যায় না।
- `adjustmentForm`/`adjust` দিক, পরিমাণ, কারণ ও ঐচ্ছিক নোটসহ তারিখযুক্ত সংশোধন লিপিবদ্ধ করে।

ওষুধের পরিচয় ইউনিক টাপল `chamber + name + potency + form + unit`। `Nux Vomica 30C` ও `Nux Vomica 200C` আলাদা সারি, নিজেদের স্টক, ক্রয় ও দাম নিয়ে। ফর্ম ফিল্ড যাচাই করে, তবে ডুপ্লিকেটের আলাদা বার্তা নেই; ইউনিক ইনডেক্স শেষ রক্ষা।

### ৮.৪ ক্রয়

- `index` ক্রয় নম্বর, কোন ওষুধ আছে, ও তারিখের সীমা দিয়ে ফিল্টার করে; প্রতি পেজে ১৫টি।
- `create` সক্রিয় ওষুধ, পুনরাবৃত্ত Alpine সারি, ঐচ্ছিক সরবরাহকারীর টেক্সট, নোট দেখায়। ওষুধ বাছলে সর্বশেষ ক্রয়মূল্য বসে। লাইন ও গ্র্যান্ড টোটাল লাইভ দেখা যায়, কিন্তু সার্ভারে নতুন করে গণনা হয়।
- `store` হেডার + অন্তত একটি আইটেম যাচাই করে `TransactionService::recordPurchase` ডাকে।
- `show` টোটাল, আইটেম সংখ্যা, মোট ইউনিট, তৈরির তারিখ, লাইন টেবিল, নোট এবং `status = completed` থাকা অবস্থায় বাতিল ফর্ম দেখায়।
- `cancel` কারণ চায় এবং `cancelPurchase` ডাকে।

### ৮.৫ বিক্রয়

- `index` বিক্রয় নম্বর, কোন ওষুধ আছে, ও তারিখের সীমা দিয়ে ফিল্টার করে।
- `create` শুধু স্টক ও ডিফল্ট বিক্রয়মূল্যসহ সক্রিয় ওষুধ দেখায়। প্রতি লাইনে দাম সম্পাদনা করা যায়; ওষুধ বদলালে স্টক লেবেল হালনাগাদ হয়।
- `store` যাচাই করে `recordSale` ডাকে, যা সারি লকের ভেতরে স্টক ও সক্রিয়তা আবার যাচাই করে।
- `show` বিক্রয় টোটাল, ঐতিহাসিক COGS, গ্রস লাভ, ইউনিট, বিক্রয়মূল্য/ঐতিহাসিক ইউনিট ক্রয়/লাইন টোটাল/লাইন COGS-সহ লাইন টেবিল, নোট ও বাতিল ফর্ম দেখায়।
- `cancel` কারণ চায় এবং স্টক ফিরিয়ে দেয়।
- `GET /sales/medicine-price` নির্বাচিত ওষুধের জন্য `{selling_price, stock, name}` ফেরে (লুকআপে ব্যবহৃত)।

সংরক্ষিত বিক্রয় **সেই মুহূর্তের** ওজনযুক্ত গড় ক্রয় ধরে রাখে, তাই পরে দাম বা ক্রয়মূল্য বদলালে ঐতিহাসিক লাভ বদলে যায় না।

### ৮.৬ খরচ

- `index` বিবরণ, ক্যাটাগরি ও তারিখের সীমা দিয়ে ফিল্টার করে।
- `create/store` ক্যাটাগরি, তারিখ, বিবরণ, ধনাত্মক টাকা, নোট সংরক্ষণ করে।
- `edit/update` শুধু `status = completed` থাকলে অনুমোদিত; বাতিল খরচে `422` ফেরে।
- `cancel` কারণ চায়, স্ট্যাটাস/কারণ/সময় বসায়, সারি রেখে দেয়।
- `categories` যোগ, নাম বদল ও সক্রিয়/নিষ্ক্রিয় করা সমর্থন করে, প্রতি ক্যাটাগরির খরচ সংখ্যাসহ।

ক্রয় হলো ইনভেন্টরি কেনা, পরিচালন খরচ নয়; এই পৃথকীকরণ ইচ্ছাকৃত এবং UI-এর লেখা ও রিপোর্টের নোটে বারবার বলা হয়।

### ৮.৭ রিপোর্ট

`ReportController` + `ReportService`:

| রিপোর্ট | আচরণ |
|---|---|
| কাস্টম রেঞ্জ (`/reports`) | ডিফল্ট শেষ `report_default_days` দিন (সাধারণত ৩০); দুই প্রান্তসহ তারিখ; সারাংশ কার্ড, ওষুধভিত্তিক বিক্রয় (শীর্ষ ৮), ক্যাটাগরিভিত্তিক খরচ, ইনভেন্টরি স্ন্যাপশট, সর্বশেষ ৫০ মুভমেন্ট (৬টি দেখায়) |
| দৈনিক | এক দিন; সারাংশ লেআউট পুনরায় ব্যবহার করে |
| মাসিক | নির্বাচিত `Y-m`; সারাংশ + ছয় মাসের তুলনামূলক সিরিজ |
| বার্ষিক | নির্বাচিত বছর; ১২ মাসের সারাংশ |
| ইনভেন্টরি | বর্তমান স্টক, গড় ক্রয়, বিক্রয়মূল্য, ভ্যালুয়েশন, স্ট্যাটাস |
| ওষুধভিত্তিক বিক্রয় | ওষুধ অনুযায়ী: পরিমাণ, আয়, ঐতিহাসিক COGS, গ্রস লাভ |
| খরচ | রেঞ্জে ক্যাটাগরিভিত্তিক টোটাল ও লেনদেন সংখ্যা |
| লো-স্টক | ন্যূনতমে বা তার নিচে থাকা সব ওষুধ (শূন্যসহ) |
| স্টক মুভমেন্ট | রেঞ্জের সম্পূর্ণ সাইনযুক্ত লেজার, প্রতি পেজে ৩০ |
| CSV | সারাংশ মেট্রিকের দুই কলাম (metric, value); বাংলায় UTF-8 BOM |
| XLSX | একটি ফরম্যাটেড সারাংশ শীট (শিরোনাম, রেঞ্জ, মেট্রিক) |
| PDF | ব্র্যান্ডেড A4 রিপোর্ট: চেম্বারের নাম/লোগো, প্রশাসক, সময়কাল, নিট লাভের হিরো, ট্রেডিং সারাংশ, কার্যকলাপের সংখ্যা, ইনভেন্টরি ভ্যালু, নোট, স্বাক্ষরের ঘর |
| প্রিন্ট | একই সার্ভার-রেন্ডারড রিপোর্ট মার্কআপ `window.print()` ও প্রিন্ট CSS দিয়ে |

তারিখের অর্থ: `from 00:00:00` থেকে `to 23:59:59`, দুই দিনই অন্তর্ভুক্ত। ব্যবহারকারী `from > to` দিলে `ReportService::range()` দুটো বদলায়। লেনদেনের তারিখ ব্যবহারকারীর দেওয়া ক্যালেন্ডার তারিখ; `created_at` কোনো লেনদেনকে অন্য সময়ে সরায় না। ইনভেন্টরি ভ্যালু সবসময় বর্তমান, রিপোর্ট রেঞ্জের উপর নির্ভর করে না।

### ৮.৮ সেটিংস

- Owner/Admin দেখেন: লোগো (PNG/JPG/WebP ≤ 2 MB) প্রিভিউ/মুছুনসহ, চেম্বারের নাম, ওনারের নাম, কারেন্সি (শুধু BDT), প্রতীক, টাইমজোন (শুধু Asia/Dhaka), ডিফল্ট লো-স্টক লেভেল, ডিফল্ট রিপোর্ট দিন, প্রোফাইল ছবি, পাসওয়ার্ড বদল, ডেটা ZIP এক্সপোর্ট।
- Staff শুধু পাসওয়ার্ড বদল ও "আপনার অ্যাক্সেস" কার্ড দেখেন; সেটিংসের ফিল্ড ও ব্যাকআপ বোতাম লুকানো থাকে এবং রুট `403` দেয়।
- সেটিংস সংরক্ষণে চেম্বার ও ওনার অ্যাকাউন্টের নামও বদলায়, যাতে নেভিগেশন ও রিপোর্ট সামঞ্জস্যপূর্ণ থাকে। Owner নিজের `users.name` বদলান; Admin কোনো চেম্বার সম্পাদনা করলে ওই চেম্বারের Owner-এর নাম বদলায়।
- লোগো আপলোড থেকে ফ্যাভিকন/PWA আইকনের মতো অ্যাসেট তৈরি হয়। GD/ImageMagick না থাকলে লোগো তবু সংরক্ষিত হয়, ডেরিভেটিভ আইকন বাদ যায় ও লগে সতর্কতা লেখা হয়।
- পাসওয়ার্ড বদলে বর্তমান পাসওয়ার্ড, নিশ্চিতকরণ ও ১২+ অক্ষর লাগে; সেশন নতুন করে তৈরি হয়।
- Staff অ্যাকাউন্ট কার্ড শুধু Owner-দের দেখায়: তৈরি (নাম, ইমেইল, পদবি, ১২+ পাসওয়ার্ড) ও সরানো (অ্যাকাউন্ট মোছে; `user_id` null হওয়ায় চেম্বারের ব্যবসায়িক ইতিহাস থাকে)।

### ৮.৯ প্রশাসন (`/admin`, শুধু Admin)

- সব চেম্বারের মাস-থেকে-তারিখ ওভারভিউ: চেম্বার সংখ্যা, অ্যাকাউন্ট সংখ্যা, বিক্রয় সংখ্যা/আয়, আনুমানিক নিট লাভ।
- প্রতি চেম্বারের পারফরম্যান্স টেবিল: ওনার, অ্যাকাউন্ট, বিক্রয় সংখ্যা/ইউনিট, আয়, ক্রয়, খরচ, আনুমানিক নিট লাভ, সক্রিয় ওষুধ/স্টক ইউনিট, ওই চেম্বার সক্রিয় করার "Open"।
- অ্যাকাউন্ট তৈরির ফর্ম: রোল Admin / Owner / Staff। Owner-এর জন্য নতুন চেম্বারের নাম লাগে; Staff-এর জন্য বিদ্যমান চেম্বার।
- অ্যাডমিন প্রোফাইল ফর্ম: নাম, ইমেইল, পদবি, ঐচ্ছিক বর্তমান পাসওয়ার্ড (ইমেইল বা পাসওয়ার্ড বদলালে আবশ্যক), নতুন পাসওয়ার্ড, প্রোফাইল ছবি।
- সব অ্যাকাউন্টের টেবিল, রোল ব্যাজ ও চেম্বারসহ, এবং ডিলিট (সাইন-ইন করা অ্যাকাউন্টে নয়)। **Owner** মুছলে তার চেম্বার, চেম্বারের অ-অ্যাডমিন অ্যাকাউন্ট, সব ব্যবসায়িক সারি, সেটিংস ও আপলোড করা ছবি মুছে যায়। Admin/Staff মুছলে শুধু ওই অ্যাকাউন্ট যায়।
- সিকিউরিটি লগ (সর্বশেষ ১৫): ইভেন্ট ব্যাজ, বিবরণ, কর্মীর স্ন্যাপশট, IP, সময়; Admin পুরো লগ পরিষ্কার করতে পারেন।

### ৮.১০ থিম ও ভাষা

- `/themes` দশটি থিম দেখায় (`apple`, `cal`, `intercom`, `linear`, `mintlify`, `notion`, `resend`, `sap`, `zapier`, `starbucks`), slug যাচাই করে `users.theme` সংরক্ষণ করে, এবং চার্ট আবার রাঙায় কারণ তারা CSS ভেরিয়েবল পড়ে।
- ভাষা সুইচ `locale` (`en`/`bn`) পোস্ট করে, তা ব্যবহারকারী, সেশন ও কুকিতে রাখে, এবং একই পেজে ফেরে (শুধু একই হোস্ট; বাইরের সাইটে নয়, `/language`-এ ফিরেও নয়)।
- বাংলায় প্রদর্শন বাংলা অঙ্ক ও অনূদিত ডিফল্ট লেবেল ব্যবহার করে; ডেটাবেসের মান ল্যাটিন/ইংরেজিই থাকে।

### ৮.১১ মিডিয়া, ম্যানিফেস্ট ও ইনস্টল করা অ্যাপ

- `/media/{path}` শুধু `^(avatars|branding)/...` প্যাটার্নের পাথ দেয়, `..` ছাড়া। ব্র্যান্ডিং পাবলিক, যাতে লগইন/PWA লোগো দেখাতে পারে; অ্যাভাটারে লগইন লাগে।
- `/manifest.webmanifest` সক্রিয় চেম্বার ও ব্র্যান্ড অ্যাসেট থেকে নাম, শর্ট নাম, আইকন, শর্টকাট (নতুন বিক্রয়, ওষুধ, রিপোর্ট) তৈরি করে; ডেটাবেস না থাকলে হোমিও-হল ডিফল্টে ফেরে এবং `private, no-cache` হেডারে সার্ভ হয়।
- ব্রাউজার সমর্থন করলে লেআউট "Install app" বোতাম দেখায়, Safari/iOS-এর শেয়ার-শিট নির্দেশনাসহ; সার্ভিস ওয়ার্কার অ্যাপ শেল ক্যাশ করে। এটি ইনস্টলযোগ্যতা, অফলাইনে ডেটা এন্ট্রি নয়।

---
## ৯. বিজনেস লজিক

### ৯.১ টাকা ও রাউন্ডিং

- লাইনের টোটাল পূর্ণসংখ্যা **পয়সায়** হিসাব হয়, round-half-up নিয়মে, তারপর 2 দশমিকের স্ট্রিং হিসেবে সংরক্ষিত হয়।
- `unit_cost_price` 4 দশমিক রাখে; `line_cost_total` = `round(quantity × unit_cost × 100) / 100`।
- রিপোর্ট সংরক্ষিত অঙ্ক যোগ করে, তারপর দেখানোর সময় রাউন্ড করে।
- UI টাকা দেখায় `Localizer::money()` → `৳` + `number_format` দিয়ে, বাংলায় বাংলা অঙ্কে।

### ৯.২ ওজনযুক্ত গড় ক্রয় (weighted-average)

```text
new_average =
  ((existing_qty × existing_average) + (purchased_qty × purchase_unit_cost))
  / (existing_qty + purchased_qty)
```

- 4 দশমিকে রাউন্ড করে `medicines.average_cost`-এ রাখা হয়।
- সর্বশেষ ক্রয়ের ইউনিট দাম `medicines.purchase_price`-এও কপি হয় (ডিফল্ট ও প্রদর্শনের জন্য); এটি ঐতিহাসিক COGS-এ **ব্যবহৃত হয় না**।
- স্টক ঠিক শূন্য হলে `average_cost` আবার `0` হয়।
- শূন্য স্টক পরে অ্যাডজাস্টমেন্টে বাড়লে বর্তমান `purchase_price` প্রাথমিক গড় হয়; পরের ক্রয় সেখান থেকে মেশে।

### ৯.৩ ক্রয় রেকর্ডিং

```text
line_total   = quantity × unit_cost
purchase     = sum(line_total)
new_stock    = old_stock + quantity
movement     = +quantity   (type "purchase")
```

### ৯.৪ বিক্রয় রেকর্ডিং

```text
line_total      = quantity × actual_selling_price
line_cost_total = quantity × weighted_average_at_sale_time
sale_total      = sum(line_total)
new_stock       = old_stock - quantity
movement        = -quantity   (type "sale")
```

সার্ভার ব্রাউজারের টোটাল উপেক্ষা করে পরিমাণ, দাম ও স্টক আবার যাচাই করে। একই ওষুধের ডুপ্লিকেট লাইন স্টক যাচাইয়ের জন্য যোগ করা হয়, তাই দুই লাইন মিলে উপলব্ধ স্টকের বেশি হতে পারে না।

### ৯.৫ ক্রয় বাতিল

অনুমোদিত শুধু তখন, যখন ক্রয়টি `completed`, কোনো লাইন স্টককে ঋণাত্মক করবে না, এবং **এই ক্রয়ের মুভমেন্টের পরে তৈরি কোনো completed বিক্রয়** ওই ওষুধ ব্যবহার করেনি। শেষ নিয়মটি ইতিমধ্যে রিপোর্ট হওয়া COGS রক্ষা করে: বিক্রয়টি মিশ্র গড় থেকে খরচ ধরেছিল, তাই পরে ক্রয় সরালে বাকি ইনভেন্টরির খরচ বিকৃত হতো বা রিপোর্ট হওয়া লাভ নীরবে বদলে যেত।

সফল হলে প্রতি লাইনে:

```text
new_stock = current_stock - purchased_qty
remaining_average =
  ((current_stock × current_average) - (purchased_qty × original_unit_cost))
  / (current_stock - purchased_qty)
remaining_average = max(0, round(remaining_average, 4))   // এবং new_stock = 0 হলে 0
movement = -purchased_qty   (type "purchase_reversal")
```

স্ট্যাটাস `cancelled` হয়, কারণ ও সময়সহ। একই বাতিল আবার করলে প্রত্যাখ্যাত হয়। ক্রয় কখনো আংশিক বাতিল হয় না।

### ৯.৬ বিক্রয় বাতিল

```text
new_stock = current_stock + sold_qty
new_average =
  ((current_stock × current_average) + (sold_qty × historical_sale_unit_cost))
  / (current_stock + sold_qty)
movement = +sold_qty   (type "sale_reversal")
```

বর্তমান স্টক শূন্য হলেও এটি কাজ করে: ফেরত আসা ঐতিহাসিক খরচই নতুন গড় হয়। দ্বিতীয়বার বাতিল প্রত্যাখ্যাত হয়। বাতিল বিক্রয় আয়, COGS ও লাভ থেকে বাদ যায়।

### ৯.৭ স্টক অ্যাডজাস্টমেন্ট

```text
new_stock = old_stock ± quantity
```

- `quantity` ধনাত্মক মাত্রা; সাইনযুক্ত প্রভাব লেজারে থাকে (`adjustment_increase` বা `adjustment_decrease`)।
- শূন্যের নিচে নামানো প্রত্যাখ্যাত (`stock_never_negative`)।
- শুধু শূন্য থেকে বাড়ালে গড় ক্রয় `purchase_price` থেকে শুরু হয়; অন্য অ্যাডজাস্টমেন্ট তা বদলায় না, তবে ফল শূন্য হলে গড় শূন্য হয়।
- কারণ ও তারিখ আবশ্যক, তাই প্রতিটি হাতে করা স্টক পরিবর্তনের ব্যাখ্যা থাকে।

### ৯.৮ খরচ

```text
expense = positive amount, category, date, description
```

বাতিল করলে সারি থাকে, কারণ/সময় লেখা হয় এবং খরচ ও লাভের টোটাল থেকে বাদ যায়। বাতিল খরচ সম্পাদনা করা যায় না।

### ৯.৯ আর্থিক সংজ্ঞা

নির্বাচিত দুই প্রান্তসহ রেঞ্জে:

```text
Sales revenue          = SUM(completed sale.total_amount)
Purchases              = SUM(completed purchase.total_amount)   // inventory acquisition, not an expense
COGS                   = SUM(completed sale_item.line_cost_total)
Gross profit           = Sales revenue − COGS
Other operating costs  = SUM(completed expense.amount)
Net operating profit   = Gross profit − Other operating costs
Inventory value        = SUM(medicine.stock_quantity × medicine.average_cost)   // current, independent of range
```

লেজারের অপরিবর্তনীয় নিয়ম:

```text
current stock = SUM(all signed stock_movement.quantity)
for every movement: resulting_stock = previous_stock + signed quantity
```

সারাংশে বিক্রি/কেনা ইউনিট শুধু রেঞ্জের completed হেডার গোনে।

### ৯.১০ রিপোর্টে বাতিল

বিক্রয়/ক্রয়/খরচের প্রতিটি সমষ্টি `status = 'completed'` ফিল্টার করে, তাই বাতিল হেডার শূন্য আয়, ক্রয়, COGS, খরচ ও লাভ দেয়। রিভার্সাল মুভমেন্ট অডিটের জন্য স্টক লেজারে থাকে এবং নতুন বিক্রয়/ক্রয় হিসেবে গণ্য হয় না।

---
## ১০. ডেটাবেস রাইট ট্রানজেকশন

### ১০.১ `TransactionService::recordPurchase(array $data, int $userId)`

একটি `DB::transaction`:

1. `PUR-YYYYMMDDHHMMSS-XXXXXX` (র‍্যান্ডম হেক্স) নম্বর ও টোটাল `0` দিয়ে `Purchase::create` হেডার।
2. লাইন স্বাভাবিক করে প্রতিটি লাইনে `Medicine::lockForUpdate()->findOrFail($id)`।
3. নতুন পরিমাণ ≥ 0 এবং ≤ `4294967295` যাচাই।
4. পয়সায় লাইন টোটাল হিসাব করে চলমান টোটালে যোগ।
5. ঐতিহাসিক `unit_cost` ও `line_total` দিয়ে `purchase_items` তৈরি।
6. `forceFill` দিয়ে ওষুধ: নতুন `stock_quantity`, নতুন 4-দশমিক `average_cost`, সর্বশেষ `purchase_price`।
7. `stock_movements` সারি যোগ: type `purchase`, সাইনযুক্ত `+quantity`, আগের/পরের স্টক, purchase ক্লাস + id, কারণ `Purchase <number>`, তারিখ = ক্রয়ের তারিখ।
8. সার্ভারের যোগফল থেকে হেডারের টোটাল হালনাগাদ।

কোনো ব্যর্থতা (ওষুধ না পাওয়া, ওভারফ্লো, DB ত্রুটি) হেডার, সব আইটেম, সব স্টক পরিবর্তন ও সব মুভমেন্ট রোলব্যাক করে।

### ১০.২ `TransactionService::recordSale(array $data, int $userId)`

একটি `DB::transaction`:

1. বিক্রয় হেডার তৈরি (`SAL-...`, টোটাল `0`) — পরে ভ্যালিডেশন ব্যর্থ হলে স্বয়ংক্রিয়ভাবে রোলব্যাক হয়।
2. লাইন স্বাভাবিক করে প্রতি ওষুধের প্রয়োজনীয় পরিমাণ যোগ করা হয়।
3. জড়িত সব ওষুধ ঊর্ধ্বক্রমে id অনুযায়ী লক (`orderBy('id')->lockForUpdate()->get()->keyBy('id')`) — স্থির ক্রম ডেডলক এড়ায়।
4. কোনো ওষুধ না থাকলে, নিষ্ক্রিয় হলে, বা **মোট** চাহিদার চেয়ে কম স্টক থাকলে স্টক বদলানোর আগেই `ValidationException`।
5. প্রতি লাইনে: দেওয়া বিক্রয়মূল্য (2dp), বর্তমান গড় `unit_cost_price` (4dp), `line_total`, `line_cost_total` দিয়ে `sale_items`।
6. স্টক কমানো; শূন্য হলে গড় ক্রয় শূন্য, নাহলে অপরিবর্তিত।
7. `-quantity` দিয়ে `sale` মুভমেন্ট যোগ।
8. হেডারের টোটাল হালনাগাদ।

ফর্মের JavaScript কখনো স্টক বা টোটাল নির্ধারণ করে না।

### ১০.৩ `TransactionService::cancelPurchase(Purchase $purchase, string $reason)`

1. ট্রানজেকশনের ভেতরে `findOrFail` দিয়ে আইটেমসহ ক্রয় লক ও আবার পড়া, যাতে দুই সমকালীন বাতিল দুটোই পাস করতে না পারে।
2. `status === 'completed'` না হলে প্রত্যাখ্যান।
3. প্রতি আইটেমে ওষুধ লক; বর্তমান স্টক < আইটেম পরিমাণ হলে প্রত্যাখ্যান।
4. এই ক্রয়ের সবচেয়ে আগের `purchase` মুভমেন্ট খোঁজা; একই ওষুধের কোনো `sale` মুভমেন্ট তার পরে তৈরি এবং completed বিক্রয়ের হলে প্রত্যাখ্যান।
5. স্টক ও অবশিষ্ট গড় (0-এ ন্যূনতম) আবার হিসাব করে সংরক্ষণ।
6. `-quantity`, কারণ, `now()` দিয়ে `purchase_reversal` মুভমেন্ট।
7. স্ট্যাটাস `cancelled`, `cancellation_reason`, `cancelled_at`।

সব লাইন সফল হয়, নাহলে একটিও নয়।

### ১০.৪ `TransactionService::cancelSale(Sale $sale, string $reason)`

1. আইটেমসহ বিক্রয় লক ও আবার পড়া।
2. completed না হলে প্রত্যাখ্যান।
3. প্রতি আইটেমে ওষুধ লক, পরিমাণ ফেরত যোগ, ঐতিহাসিক বিক্রয়-ইউনিট-খরচ গড়ে আবার মেশানো, সংরক্ষণ।
4. `+quantity` দিয়ে `sale_reversal` মুভমেন্ট।
5. কারণ/সময়সহ বিক্রয় বাতিল চিহ্নিত।

### ১০.৫ `TransactionService::adjustStock(array $data, int $userId)`

1. ওষুধ লক।
2. `increase`/`decrease` থেকে সাইনযুক্ত ডেল্টা।
3. ঋণাত্মক ফল প্রত্যাখ্যান; unsigned সীমার উপরে হলে প্রত্যাখ্যান।
4. শূন্য থেকে বাড়ালে `purchase_price` থেকে গড় ক্রয় শুরু; ফল শূন্য হলে গড় শূন্য।
5. `stock_adjustments` হেডার তৈরি (ধরন, মাত্রা, কারণ, তারিখ, নোট, কর্মী)।
6. ওষুধ সংরক্ষণ।
7. সাইনযুক্ত পরিমাণসহ `adjustment_increase` বা `adjustment_decrease` মুভমেন্ট।

### ১০.৬ `TransactionService::recordExpense(...)`

`status = completed` ও কর্মীসহ একটি `Expense::create`। স্টকের সঙ্গে সম্পর্ক নেই, তাই স্পষ্ট ট্রানজেকশন নেই। খরচের **আপডেট** ও **বাতিল**ও `ExpenseController`-এ একক-সারির Eloquent লেখা; বাতিল স্ট্যাটাস/কারণ/সময় বদলায়।

### ১০.৭ চেম্বার প্রভিশনিং (`ChamberProvisioner::createOwner`)

`DB::transaction`: চেম্বার তৈরি → `ensureDefaults()` (সেটিংস + `firstOrCreate` দিয়ে ৭টি ওষুধ ক্যাটাগরি + ৯টি খরচ ক্যাটাগরি) → হ্যাশ করা পাসওয়ার্ড ও `email_verified_at` দিয়ে Owner ব্যবহারকারী তৈরি। Staff/Admin তৈরি স্পষ্ট ট্রানজেকশনের বাইরে একটি ইনসার্ট।

### ১০.৮ অ্যাকাউন্ট/চেম্বার ডিলিট (`AdminAccountController`)

- Owner মুছলে একটি `DB::transaction`: চাইল্ড আইটেম সারি, মুভমেন্ট, অ্যাডজাস্টমেন্ট, হেডার, খরচ, ওষুধ, ক্যাটাগরি, সেটিংস মোছে; চেম্বারের অ-অ্যাডমিন ব্যবহারকারী মোছে; বাকি অ্যাডমিন ব্যবহারকারীদের `chamber_id` null করে; চেম্বার মোছে।
- আপলোড করা লোগো/অ্যাভাটার ট্রানজেকশনের **পরে** আপলোড ডিস্ক থেকে মোছে।
- Admin/Staff মুছলে একটি ডিলিট + অ্যাভাটার পরিষ্কার।
- Owner-এর স্টাফ সরানো শুধু একই চেম্বারের ওই Staff অ্যাকাউন্ট মোছে, রোল ও `chamber_id` যাচাই করে।

### ১০.৯ যেসব লেখা স্পষ্ট ট্রানজেকশনে মোড়া **নয়**

এগুলো একক-সারি বা সরল লেখা; atomicity আসে এক স্টেটমেন্ট হওয়া থেকে:

- ওষুধ তৈরি/আপডেট, নিষ্ক্রিয় করা,
- ওষুধ/খরচ ক্যাটাগরি তৈরি/আপডেট,
- খরচ তৈরি/আপডেট/বাতিল,
- সেটিংসে প্রতি key-তে `updateOrCreate` (একাধিক key আলাদা স্টেটমেন্ট — মাঝপথে ব্যর্থতা আংশিক আপডেট রেখে যেতে পারে),
- চেম্বার লোগো/অ্যাভাটার ফাইল সংরক্ষণ + সেটিংস লেখা,
- থিম ও লোকেল পছন্দের আপডেট,
- লগইন/লগআউটের সেশন পরিবর্তন ও সিকিউরিটি লগ ইনসার্ট।

### ১০.১০ রিড-অনলি সমষ্টি

`ReportService::summary()` কয়েকটি aggregate কোয়েরি চালায় (বিক্রয়, ক্রয়, খরচ, বিক্রয় আইটেম, ক্রয় আইটেম, ইনভেন্টরি) কিন্তু কিছু লেখে না। `medicineSales()`, `inventory()`, `movements()` ও `range()` রিড-অনলি। তাই ড্যাশবোর্ড ও রিপোর্ট পেজের কোনো ট্রানজেকশন পার্শ্বপ্রতিক্রিয়া নেই।

---
## ১১. ডেটাবেস স্কিমার সারাংশ

`2026_11_15_111801_create_hpms_business_tables.php`-এ তৈরি এবং চেম্বার মাইগ্রেশনে সম্প্রসারিত:

- `chambers(id, name)`
- `users(id, chamber_id, role, name, designation, email, password, avatar_path, theme, locale, ...)`
- `medicine_categories(id, chamber_id, name, is_active)` — ইউনিক `(chamber_id, name)`
- `medicines(id, chamber_id, category_id, name, potency, form, unit, purchase_price, selling_price, average_cost, stock_quantity, minimum_stock, description, is_active, deleted_at)` — ইউনিক পরিচয় `(chamber_id, name, potency, form, unit)`; ইনডেক্স `(category_id, is_active)`, `(stock_quantity, minimum_stock)`
- `purchases(id, chamber_id, purchase_number, purchase_date, supplier_name, total_amount, status, notes, cancellation_reason, cancelled_at, user_id)` — ইউনিক `(chamber_id, purchase_number)`
- `purchase_items(id, chamber_id, purchase_id, medicine_id, quantity, unit_cost, line_total)`
- `sales(id, chamber_id, sale_number, sale_date, total_amount, status, notes, cancellation_reason, cancelled_at, user_id)` — ইউনিক `(chamber_id, sale_number)`
- `sale_items(id, chamber_id, sale_id, medicine_id, quantity, unit_selling_price, unit_cost_price, line_total, line_cost_total)`
- `expense_categories(id, chamber_id, name, is_active)` — ইউনিক `(chamber_id, name)`
- `expenses(id, chamber_id, expense_category_id, expense_date, description, amount, notes, status, cancellation_reason, cancelled_at, user_id)`
- `stock_adjustments(id, chamber_id, medicine_id, adjustment_type, quantity, reason, adjustment_date, notes, user_id)`
- `stock_movements(id, chamber_id, medicine_id, movement_type, quantity, reference_type, reference_id, previous_stock, resulting_stock, reason, movement_date)` — ইনডেক্স `(medicine_id, movement_date)`, `(reference_type, reference_id)`
- `settings(id, chamber_id, key, value)` — ইউনিক `(chamber_id, key)`
- `security_logs(id, event, description, user_id, actor_name, actor_email, ip_address, user_agent)` — event ও created_at-এ ইনডেক্স
- লারাভেলের সাপোর্ট টেবিল: users/password resets/sessions, cache/locks, jobs/batches/failed jobs।

ডিলিটের নিয়ম: লেনদেনের আইটেম হেডারের সঙ্গে cascade; ক্যাটাগরি ও ওষুধ ডিলিটে restrict; কর্মী user ও চেম্বার রেফারেন্স ডিলিটে null। completed ব্যবসায়িক হেডার অ্যাপ্লিকেশনের কোনো রুটে কখনো হার্ড-ডিলিট হয় না।

---

## ১২. এক্সপোর্ট ও ব্যাকআপ

- **রিপোর্ট PDF/XLSX/CSV/প্রিন্ট** সবই একই যাচাই করা `from`/`to` রেঞ্জ ও একই `ReportService::summary()` সংখ্যা ব্যবহার করে, তাই রেঞ্জ বদলালে প্রতিটি এক্সপোর্ট একসঙ্গে বদলায়।
- XLSX একটি মাত্র ওয়ার্কশিট (metric/value), কারণ PhpSpreadsheet-এর জন্য `ext-gd` দরকার, যা ডিপ্লয়মেন্টে নেই; কাস্টম রাইটার তবু বৈধ `.xlsx` বানায়।
- **সেটিংস → ডেটা ব্যাকআপ** সক্রিয় চেম্বারের জন্য `medicine_categories`, `medicines`, `purchases`, `purchase_items`, `sales`, `sale_items`, `expense_categories`, `expenses`, `stock_adjustments`, `stock_movements`, `settings`-এর CSV ফাইলের ZIP স্ট্রিম করে। এতে users, পাসওয়ার্ড হ্যাশ ও সেশন বাদ যায়, তাই এটি পরিদর্শন/মাইগ্রেশনের সহায়ক — **পুনরুদ্ধারযোগ্য ডেটাবেস ব্যাকআপ নয়**। দুর্যোগ পুনরুদ্ধারের জন্য `mysqldump` ব্যবহার করুন (দেখুন `docs/BACKUP_AND_RESTORE.md`)।
- PDF ইঞ্জিন ফলব্যাক: WeasyPrint (বাংলা শেপিংয়ে সেরা) → Chromium (শেপিং) → Dompdf (জটিল লিপি শেপিং নেই; বাংলা অসম্পূর্ণ হতে পারে)। ব্যর্থতা লগ হয় এবং পরের ইঞ্জিন চেষ্টা করা হয়।

---

## ১৩. নিরাপত্তা অবস্থা

- শুধু লগইন; পাবলিক রেজিস্ট্রেশন নেই। পাসওয়ার্ড হ্যাশ করা (`Hash::make`, `hashed` কাস্ট) এবং প্রতিটি সেট/রিসেট পথে ন্যূনতম ১২ অক্ষর।
- প্রতিটি ফর্মে CSRF; PUT/DELETE-এর জন্য মেথড স্পুফিং।
- লগইন দুই স্তরে থ্রটল: `AuthController`-এ প্রতি ইমেইল+IP-তে মিনিটে ৫টি চেষ্টা, সঙ্গে রুটে `throttle:10,1`।
- লগইনে ও পাসওয়ার্ড বদলে সেশন নতুন; লগআউটে বাতিল; HTTPS-এর জন্য সিকিউর কুকি/সেশন env ফ্ল্যাগ নথিভুক্ত।
- রোল মিডলওয়্যার + কন্ট্রোলারের `abort_unless` + চেম্বার গ্লোবাল স্কোপ + চেম্বার-সচেতন `Rule::exists` = স্তরে স্তরে আইসোলেশন।
- আপলোড করা অ্যাভাটারে লগইন লাগে; মিডিয়া পাথ প্যাটার্ন-যাচাই করা ও ট্রাভার্সাল ব্লকড; রেসপন্সে `nosniff`।
- `$fillable` / `#[Fillable]` দিয়ে mass assignment সীমিত; স্টক ও ক্রয়মূল্যের লেখা শুধু সার্ভিসের ভেতরে `forceFill`।
- সিকিউরিটি লগ কর্মীর নাম/ইমেইল/IP/user-agent স্ন্যাপশট রাখে, তাই অ্যাকাউন্ট বদলালেও এন্ট্রি পড়া যায়।
- ব্যাকআপ ZIP ক্রেডেনশিয়াল বাদ দেয়; ডেটাবেস ডাম্পও ক্রেডেনশিয়ালের মতো সুরক্ষিত রাখতে হবে।
- প্রোডাকশনে `APP_DEBUG=false`; `/health` ও `/up` ডেটা ফাঁস না করে মনিটরিং সমর্থন করে।

---

## ১৪. যাচাই ও টেস্ট

`hpms/` থেকে চালান:

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent   # PHP সম্পাদনার পর
```

ফিচার স্যুট (`hpms/tests/Feature`) যা যা কভার করে, তার মধ্যে:

- `HpmsWorkflowTest` — লগইন/লগআউট, রেজিস্ট্রেশন নেই, ওষুধ তৈরি/আপডেট ও আলাদা পোটেন্সি, ক্রয়ের টোটাল/স্টক/মুভমেন্ট, ওজনযুক্ত গড়, ক্রয় ও বিক্রয় বাতিল (পুনরাবৃত্তি এবং "পরের বিক্রয়ে ব্যবহৃত" প্রত্যাখ্যানসহ), পরে দাম বদলালেও ঐতিহাসিক COGS অপরিবর্তিত, অপর্যাপ্ত স্টকে আংশিক বিক্রয় নেই, ব্যর্থ ক্রয়ের রোলব্যাক, স্টক অ্যাডজাস্টমেন্ট লেজার ও ঋণাত্মক গার্ড, খরচ বাদ, রিপোর্টের সূত্র, বাতিল লেনদেন বাদ, লেজারের অপরিবর্তনীয় নিয়ম, PDF/CSV/XLSX হেডার, অবৈধ তারিখ, ড্যাশবোর্ডের মান।
- `RbacTest` — owner/staff/admin সীমানা, চেম্বার বদল, অ্যাডমিনের মাস-থেকে-তারিখ টোটাল, প্রোফাইল/পাসওয়ার্ড নিয়ম, চেম্বার-ওনার ডিলিটের cascade।
- `ChamberIsolationTest` — চেম্বার-পারাপার পড়া/লেখা/রুট বাইন্ডিং, ব্যাকআপ স্কোপিং, গেস্টের কাছে ডেটা ফাঁস, ম্যানিফেস্টের গোপনীয়তা, প্রতি চেম্বারে ইউনিক ক্যাটাগরি।
- `LocalizationTest` — লোকেল স্থায়িত্ব, গেস্ট কুকি, রিডাইরেক্টের নিরাপত্তা, বাংলা অঙ্ক/লেবেল, বাংলায় ব্যবসায়িক লেখা।
- `PageSmokeTest`, `ThemeTest`, `SecurityLogTest`, `MediaDeliveryTest`, `PdfRenderingTest`, `UploadMigrationTest` — পেজের প্রাপ্যতা, থিম, অডিট লগ, মিডিয়া অনুমোদন, PDF ইঞ্জিন, আপলোড মাইগ্রেশন কমান্ড।

টেস্ট `phpunit.xml`-এর ইন-মেমরি SQLite ব্যবহার করে এবং MySQL ডেভেলপমেন্ট/প্রোডাকশন ডেটাবেস কখনো স্পর্শ করে না।

---

## ১৫. সীমাবদ্ধতা ও পর্যবেক্ষণ

- XLSX একটি সারাংশ শীট, মাল্টি-শীট ওয়ার্কবুক নয় (PhpSpreadsheet-এর জন্য `ext-gd` নেই)।
- ZIP এক্সপোর্ট পুনরুদ্ধারযোগ্য নয়; শুধু `mysqldump` পূর্ণ ব্যাকআপ।
- প্রতি Owner/Staff অ্যাকাউন্টে একটি চেম্বার; অ্যাডমিন পেজভিত্তিক সিলেক্টরের বদলে সেশন দিয়ে চেম্বার বদলায়।
- স্টক ডিজাইন অনুযায়ী শুধু integer; ভগ্নাংশের তরল পদার্থের জন্য আলাদা ইউনিট-কনভার্শন ডিজাইন লাগবে।
- ক্রয় বাতিল ইচ্ছাকৃতভাবে কঠোর: ওই ওষুধের পরে কোনো completed বিক্রয় হলে সেই বিক্রয় বাতিল না হওয়া পর্যন্ত ক্রয় ফেরানো যায় না।
- ডুপ্লিকেট ওষুধের পরিচয় ডেটাবেসের ইউনিক ইনডেক্স নিশ্চিত করে, কিন্তু আলাদা ইনলাইন ভ্যালিডেশন বার্তা নেই।
- সেটিংস ট্রানজেকশনের বাইরে key ধরে ধরে সংরক্ষিত হয়, তাই মাঝপথে ব্যর্থতায় আংশিক আপডেট থাকতে পারে।
- বাংলা PDF-এর গুণ উপলব্ধ ইঞ্জিনের উপর নির্ভর করে; Dompdf ফলব্যাক জটিল লিপি ঠিকভাবে শেপ করে না।
- ইনস্টলযোগ্যতা/অফলাইন শেল আছে, তবে ব্যবসায়িক ডেটা এন্ট্রির জন্য সক্রিয় সার্ভার ও ডেটাবেস লাগে।
- অ্যাপ্লিকেশনটি চেম্বারের টুল, ফার্মেসি ERP নয়: রোগী, প্রেসক্রিপশন, সরবরাহকারী, ক্রেতা, পেমেন্ট বা মাল্টি-ব্রাঞ্চ সমন্বয় নেই।

---

## পরিশিষ্ট ক — কোন পেজ কোন টেবিলে লেখে

| কাজ | লেখা হয় যে টেবিলে |
|---|---|
| লগইন / লগআউট | `sessions`, `security_logs` |
| ওষুধ যোগ/সম্পাদনা | `medicines` |
| ওষুধ নিষ্ক্রিয় | `medicines.is_active` |
| ক্যাটাগরি যোগ/নাম বদল | `medicine_categories` / `expense_categories` |
| ক্রয় রেকর্ড | `purchases`, `purchase_items`, `medicines` (স্টক, গড়, ক্রয়মূল্য), `stock_movements` |
| বিক্রয় রেকর্ড | `sales`, `sale_items`, `medicines` (স্টক, শূন্যে গড় রিসেট), `stock_movements` |
| ক্রয় বাতিল | `purchases` (স্ট্যাটাস/কারণ/সময়), `medicines` (স্টক, গড়), `stock_movements` |
| বিক্রয় বাতিল | `sales` (স্ট্যাটাস/কারণ/সময়), `medicines` (স্টক, গড়), `stock_movements` |
| স্টক অ্যাডজাস্টমেন্ট | `stock_adjustments`, `medicines` (স্টক, গড়), `stock_movements` |
| খরচ তৈরি/আপডেট/বাতিল | `expenses` |
| সেটিংস সংরক্ষণ | `settings`, `chambers`, `users` (ওনারের নাম), আপলোড ডিস্ক |
| স্টাফ/অ্যাকাউন্ট ম্যানেজমেন্ট | `users`, `chambers`, চেম্বার ডিলিটে ব্যবসায়িক টেবিল, `security_logs`, আপলোড ডিস্ক |
| থিম / ভাষা | `users.theme`, `users.locale` |
| ডেটা এক্সপোর্ট | কোনো লেখা নেই (ZIP স্ট্রিম করে) |

## পরিশিষ্ট খ — শব্দকোষ

- **পোটেন্সি** — মিশ্রণের শক্তি (30C, 200C, 1M); ওষুধের পরিচয়ের অংশ।
- **ওজনযুক্ত গড় ক্রয়** — স্টকে থাকা প্রতি ইউনিটের মিশ্র ক্রয়মূল্য।
- **COGS** — বিক্রীত পণ্যের খরচ; প্রকৃতপক্ষে বিক্রি হওয়া ইউনিটের ঐতিহাসিক ক্রয়মূল্য।
- **গ্রস লাভ** — বিক্রয় আয় বিয়োগ COGS।
- **নিট পরিচালন লাভ** — গ্রস লাভ বিয়োগ চেম্বারের অন্যান্য পরিচালন খরচ।
- **স্টক মুভমেন্ট** — স্টক কেন বদলাল তা লেখা একটি সাইনযুক্ত লেজার এন্ট্রি।
- **চেম্বার** — একটি ক্লিনিকের আলাদা ডেটা জগৎ (ওনার, স্টাফ, ওষুধ, লেনদেন, সেটিংস)।

---

*ইংরেজি সংস্করণ: `docs/SYSTEM_GUIDE.md`।*
