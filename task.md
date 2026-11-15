# FEATURE DEVELOPMENT PROMPT
# HPMS — Complete English to Bangla Localization System

## 1. Your Role

Act as a senior Laravel developer, localization engineer, UI/UX designer, and QA engineer.

I have already completed the development of my web application:

**Homeo-Hall (HPMS) — Homeopathic Medicine Inventory & Financial Management System**

Your task is to add a complete English and Bangla language-switching feature to the existing application.

The project is already built and functional.

**IMPORTANT: Do not rebuild the application. Modify the existing project and integrate localization into its current architecture.**

The primary user is my father, who will use the application in Bangla.

The objective is to make the entire application available in natural, easy-to-understand Bangla while retaining English as an alternative.

---

# 2. Main Requirement

Add a simple language toggle button to the existing sticky header.

The toggle must allow the user to switch between:

- English
- বাংলা (Bangla)

The button must be visible and accessible on every authenticated application page.

### Expected appearance

When English is active:

`EN | বাংলা`

When Bangla is active:

`English | বাংলা`

Clearly indicate which language is currently selected.

Use a compact button that fits naturally into the existing header.

Do not create a separate language settings page.

Do not create a large dropdown or complicated language selection interface.

The language switch should be a simple, normal toggle-style control.

## Expected behavior

1. The user opens HPMS.
2. The application displays the currently selected language.
3. The user clicks the language button.
4. The entire application changes to the other language.
5. The user remains on the same page.
6. The selected language remains active while navigating between pages.
7. The preference is remembered when the user logs out and logs in again, where supported by the existing authentication architecture.

The language change must not affect any business data.

---

# 3. Technology and Localization Architecture

Use Laravel's native localization system.

Do not use Google Translate, browser translation, external translation APIs, or third-party translation services.

All translations must be stored locally in the application.

Use:

- Laravel localization.
- Laravel translation files.
- Blade translation helpers.
- Locale middleware.
- Session or persistent user locale preference.
- JavaScript translation dictionaries where required.
- Existing frontend architecture.

Supported locales:

| Language | Locale |
|---|---|
| English | en |
| Bangla | bn |

Default locale: `en`

Fallback locale: `en`

The application must work without an internet connection for translation functionality.

---

# 4. First Step — Inspect the Existing Project

Before changing any code, inspect the existing HPMS project.

Identify:

- Laravel version.
- Existing Blade layouts.
- Existing sticky header component.
- Existing sidebar component.
- Authentication implementation.
- Existing settings system.
- Existing user model.
- Existing JavaScript structure.
- Existing Chart.js implementation.
- Existing PDF export implementation.
- Existing Excel/CSV export implementation.
- Existing validation messages.
- Existing frontend components.

Locate every page and component that contains visible English text.

Do not assume that the project uses the same folder structure as a fresh Laravel installation.

Use the actual structure of the existing project.

Preserve all existing features and functionality.

---

# 5. Language Switcher in the Sticky Header

This is the most important UI requirement.

Add the language switcher to the existing sticky header.

### Design

Create a compact toggle-style control.

Example:

`EN  |  বাংলা`

Requirements:

- Small and clean.
- Consistent with the existing design.
- Clearly show the active language.
- Responsive on mobile.
- Accessible using keyboard navigation.
- Have an appropriate accessible label.
- Use existing button styling.
- Avoid unnecessary animations.
- Do not make the header significantly taller.

When clicked, switch the application locale.

The language switcher should work from every application page.

If the user is viewing:

- Dashboard: remain on dashboard.
- Medicines: remain on medicines.
- Purchases: remain on purchases.
- Sales: remain on sales.
- Expenses: remain on expenses.
- Reports: remain on reports.
- Settings: remain on settings.

Do not redirect every language change to the dashboard.

Preserve query parameters where appropriate, including search terms, pagination, selected date ranges, and report filters.

Avoid submitting the language switch as part of an existing form.

---

# 6. Language Preference Persistence

The selected language must remain consistent throughout the application.

Inspect the existing authentication and user settings architecture.

Prefer storing the owner's language preference persistently if the application already has an appropriate user-preference or settings mechanism.

If necessary, add a nullable `locale` field to the existing users table through a new migration.

Do not modify or recreate existing user records destructively.

For an authenticated user:

- Load their saved locale.
- Apply it to the application.
- Update the preference when the language is changed.

For guests, including the login page:

- Use the session locale.
- Allow language switching.
- Preserve the selected locale during navigation.

When the owner logs in, load their saved preference.

When the owner logs out, do not accidentally expose authenticated information or break the guest language preference.

Validate locale values against an explicit allowlist:

`en`, `bn`

Never accept arbitrary locale names from request parameters.

---

# 7. Laravel Localization Implementation

Use Laravel's standard translation structure.

Preferred structure:

```text
lang/
    en/
        messages.php
        navigation.php
        dashboard.php
        medicines.php
        purchases.php
        sales.php
        expenses.php
        reports.php
        settings.php
        validation.php
        common.php

    bn/
        messages.php
        navigation.php
        dashboard.php
        medicines.php
        purchases.php
        sales.php
        expenses.php
        reports.php
        settings.php
        validation.php
        common.php
```

If the existing Laravel version uses a different localization directory convention, follow the installed version.

Use readable translation keys.

Example:

```php
__('navigation.dashboard')
__('navigation.medicines')
__('dashboard.today_sales')
__('medicines.add_medicine')
__('sales.total_amount')
```

Use translation files instead of writing conditional language logic throughout Blade templates.

Avoid code such as:

```php
if (app()->getLocale() === 'bn') {
    // ...
}
```

inside every page.

Centralize translations and locale handling.

Implement a locale middleware and register it correctly according to the installed Laravel version.

Ensure that the middleware executes before localized views and validation messages are rendered.

---

# 8. Translate the Entire Application

Do not translate only the dashboard or sidebar.

Every user-visible interface element must support both languages.

## A. Authentication

Translate:

- Login.
- Email address.
- Password.
- Remember me.
- Sign in.
- Logout.
- Forgot password, if implemented.
- Validation errors.
- Login failure messages.

## B. Sidebar Navigation

Translate:

- Dashboard.
- Medicines.
- Purchases.
- Sales.
- Expenses.
- Reports.
- Settings.
- Logout.

## C. Dashboard

Translate:

- Dashboard title.
- Today's Sales.
- Today's Purchases.
- Today's Expenses.
- Gross Profit.
- Net Operating Profit.
- Total Medicines.
- Total Stock.
- Low Stock.
- Out of Stock.
- Monthly Sales.
- Monthly Expenses.
- Monthly Profit.
- Recent Activities.
- Quick Actions.
- Add Medicine.
- Record Purchase.
- Record Sale.
- Add Expense.
- View Reports.

Translate all chart titles, legends, axis labels, tooltips, and empty states.

## D. Medicines

Translate:

- Medicine Inventory.
- Add Medicine.
- Edit Medicine.
- Medicine Name.
- Potency.
- Category.
- Form.
- Unit.
- Purchase Price.
- Selling Price.
- Average Cost.
- Current Stock.
- Minimum Stock.
- Description.
- Status.
- Active.
- Inactive.
- Search.
- Filter.
- Save.
- Update.
- Cancel.
- Delete or Deactivate, if implemented.
- Stock Adjustment.
- Increase Stock.
- Decrease Stock.
- Adjustment Reason.
- In Stock.
- Low Stock.
- Out of Stock.

Translate medicine category and form labels where they are system-defined.

Examples:

- Mother Tincture.
- Dilution.
- Globules.
- Tablets.
- Biochemic.
- Ointment.
- Other.

## E. Purchases

Translate:

- Purchase Management.
- Record Purchase.
- Purchase Number.
- Purchase Date.
- Supplier Name.
- Medicine.
- Quantity.
- Unit Cost.
- Line Total.
- Purchase Total.
- Add Item.
- Remove Item.
- Purchase History.
- Purchase Details.
- Cancel Purchase.
- Cancellation Reason.

## F. Sales

Translate:

- Sales Management.
- Record Sale.
- Sale Number.
- Sale Date.
- Medicine.
- Available Stock.
- Quantity.
- Selling Price.
- Unit Cost.
- Line Total.
- Total Amount.
- Add Item.
- Remove Item.
- Sales History.
- Sale Details.
- Cancel Sale.
- Cancellation Reason.
- Insufficient Stock.

## G. Expenses

Translate:

- Expense Management.
- Add Expense.
- Expense Date.
- Expense Category.
- Description.
- Amount.
- Notes.
- Expense History.
- Category Management.

Translate default categories:

- Chamber Rent.
- Electricity.
- Water.
- Internet.
- Transportation.
- Stationery.
- Cleaning.
- Maintenance.
- Other.

## H. Reports

Translate:

- Reports & Analytics.
- Daily Report.
- Monthly Report.
- Yearly Report.
- Custom Date Range.
- Start Date.
- End Date.
- Generate Report.
- Sales Revenue.
- Purchases.
- Cost of Goods Sold.
- Gross Profit.
- Operating Expenses.
- Net Operating Profit.
- Closing Stock.
- Inventory Value.
- Total Units Sold.
- Total Units Purchased.
- Medicine-wise Sales.
- Expense Report.
- Stock Movement.
- Export PDF.
- Export Excel.
- Export CSV.
- Print.

Translate all report table headings, chart labels, filters, summaries, and empty states.

## I. Settings

Translate:

- Application Settings.
- Application Name.
- Owner Name.
- Currency.
- Timezone.
- Change Password.
- Current Password.
- New Password.
- Confirm Password.
- Save Settings.

## J. Shared UI Components

Translate:

- Save.
- Update.
- Edit.
- Delete.
- View.
- Add.
- Search.
- Filter.
- Reset.
- Submit.
- Cancel.
- Close.
- Confirm.
- Back.
- Next.
- Previous.
- Actions.
- Status.
- Date.
- Amount.
- Total.
- Loading.
- No Records Found.
- Are you sure?
- Operation successful.
- Something went wrong.

Translate:

- Toast notifications.
- Alert messages.
- Confirmation modals.
- Empty states.
- Pagination labels.
- Table messages.
- Form validation errors.
- Success and failure messages.

---

# 9. Bangla Translation Quality

Use natural, simple, professional Bangla.

The target user is a homeopathic chamber owner in Bangladesh.

Avoid overly formal, complicated, or machine-translated language.

Use familiar business terminology.

Examples:

| English | Bangla |
|---|---|
| Dashboard | ড্যাশবোর্ড |
| Medicines | ওষুধসমূহ |
| Purchases | ক্রয় |
| Sales | বিক্রয় |
| Expenses | খরচ |
| Reports | রিপোর্ট |
| Settings | সেটিংস |
| Medicine Name | ওষুধের নাম |
| Quantity | পরিমাণ |
| Purchase Price | ক্রয়মূল্য |
| Selling Price | বিক্রয়মূল্য |
| Current Stock | বর্তমান মজুত |
| Minimum Stock | সর্বনিম্ন মজুত |
| Low Stock | মজুত কম |
| Out of Stock | মজুত শেষ |
| Add Medicine | ওষুধ যোগ করুন |
| Record Purchase | ক্রয়ের তথ্য যোগ করুন |
| Record Sale | বিক্রয়ের তথ্য যোগ করুন |
| Add Expense | খরচ যোগ করুন |
| Total Sales | মোট বিক্রয় |
| Total Purchases | মোট ক্রয় |
| Gross Profit | মোট লাভ |
| Net Operating Profit | নিট পরিচালন মুনাফা |
| Cost of Goods Sold | বিক্রিত পণ্যের ক্রয়মূল্য |
| Inventory Value | মজুতের মূল্য |
| Expense Category | খরচের খাত |
| Purchase Date | ক্রয়ের তারিখ |
| Sale Date | বিক্রয়ের তারিখ |
| Save | সংরক্ষণ করুন |
| Update | হালনাগাদ করুন |
| Cancel | বাতিল করুন |
| Search | খুঁজুন |
| Print | প্রিন্ট করুন |
| Export | ডাউনলোড করুন |
| Notes | মন্তব্য |
| Description | বিবরণ |
| Supplier Name | সরবরাহকারীর নাম |

You may improve the translations if a more natural Bangla expression is available.

Maintain terminology consistency throughout the application.

Do not translate proper medicine names, potency values, technical medicine identifiers, or user-entered business records automatically.

For example:

- Nux Vomica should remain Nux Vomica.
- 30C should remain 30C.
- 200C should remain 200C.
- Medicine names entered by the owner should remain unchanged.

Do not modify database records just to change the interface language.

---

# 10. Bangla Typography and Compact UI

The Bangla interface must be compact and readable.

Use an appropriate Bengali font.

Preferred font:

**Noto Sans Bengali**

Use a reliable local font asset if possible, or an existing bundled font if the project already includes one.

Avoid making the application dependent on an external font service.

Example CSS approach:

```css
body {
    font-family: "Noto Sans Bengali", "Hind Siliguri",
                 sans-serif;
}

html[lang="en"] body {
    font-family: "Inter", sans-serif;
}
```

Adapt this to the existing CSS and font setup.

Requirements:

- Proper Bangla glyph rendering.
- Correct line height.
- Readable font sizes.
- Consistent button heights.
- Compact table rows.
- Proper spacing.
- No overlapping text.
- No clipped Bengali characters.
- No unnecessary wrapping in the sidebar.
- Responsive mobile navigation.

Bangla text can be longer than English text. Adjust widths and spacing where necessary.

The Bangla version should remain visually compact without sacrificing readability.

Do not reduce text size excessively to fit the interface.

---

# 11. Date, Number, and Currency Localization

The application operates in Bangladesh.

Currency must remain:

`৳`

Do not change stored financial values.

Display financial amounts consistently in both languages.

Example:

English:

`৳1,250.00`

Bangla:

`৳১,২৫০.০০`

If Bengali numerals are implemented, use a centralized formatting helper or service.

Do not convert numbers inside the database.

Do not convert numbers before calculations.

Calculations must always use normal numeric values.

Convert to localized display strings only at the presentation layer.

Support localized date formatting.

Example:

English:

`15 November 2026`

Bangla:

`১৫ নভেম্বর ২০২৬`

Ensure that HTML date inputs, form submissions, database dates, and JavaScript date calculations continue to work correctly.

Never pass localized Bengali numerals into database queries or arithmetic operations.

The database should continue storing dates and numerical values in their existing formats.

---

# 12. JavaScript and Dynamic UI Localization

Some parts of the application may generate text dynamically through JavaScript.

These must also support both languages.

Inspect all JavaScript files and Blade scripts.

Examples:

- Dynamic purchase rows.
- Dynamic sales rows.
- Remove item confirmations.
- Stock warnings.
- Insufficient stock messages.
- Form calculations.
- Toast notifications.
- Chart.js labels.
- Chart.js tooltips.
- Modal messages.
- AJAX responses.
- Loading indicators.

Use a centralized JavaScript translation dictionary.

For example, expose a safely serialized translation object from Blade:

```javascript
window.HPMS_LOCALE = {
    locale: "bn",
    messages: {
        add_item: "...",
        remove_item: "...",
        insufficient_stock: "...",
        confirm_cancel: "..."
    }
};
```

The actual values must come from Laravel translation files.

Do not hardcode English strings throughout JavaScript.

Ensure Chart.js charts update their labels when the language changes or are regenerated after the page reload.

Avoid duplicating translation logic between PHP and JavaScript.

---

# 13. PDF, Excel, CSV, and Print Localization

Localization must apply to exported documents too.

When Bangla is selected:

- PDF titles must be Bangla.
- PDF table headings must be Bangla.
- Report summary labels must be Bangla.
- Excel column headings must be Bangla.
- CSV headings must be Bangla where applicable.
- Print reports must be Bangla.

When English is selected, exports should remain in English.

## PDF font support

Inspect the existing PDF package and configuration.

Ensure that the PDF renderer supports Bengali Unicode characters.

Use an appropriate embedded Bengali font, preferably Noto Sans Bengali, if required.

Do not assume that the default PDF font supports Bengali.

Test actual PDF output for:

- Bangla headings.
- Bangla table columns.
- Currency symbols.
- Bengali numerals if enabled.
- Long Bengali text.

Avoid broken glyphs, missing characters, or disconnected Bengali letters.

Keep exported documents readable and professional.

---

# 14. Backend and Validation Localization

All backend-generated user-facing messages must follow the selected locale.

Translate:

- Validation errors.
- Authentication errors.
- Stock validation messages.
- Purchase success messages.
- Sale success messages.
- Expense success messages.
- Cancellation messages.
- Database operation errors where shown to users.
- Custom exception messages.
- API/AJAX response messages, if applicable.

Use Laravel's validation localization capabilities.

Create or update the Bangla validation language file.

Do not expose raw SQL errors or stack traces to users.

Keep detailed technical errors in application logs.

---

# 15. Translation Rules for User-Entered Data

This requirement is very important.

Only translate the application interface.

Do not automatically translate information manually entered by the owner.

Examples of data that must remain unchanged:

- Medicine names.
- Potency.
- Supplier names.
- Medicine descriptions.
- Purchase notes.
- Sale notes.
- Expense descriptions.
- Custom category names.
- Stock adjustment reasons.

Do not modify existing database values.

Do not run database-wide translation scripts.

Do not overwrite manually entered records.

If a user creates a custom category in Bangla, display it exactly as entered.

If a user creates a custom category in English, display it exactly as entered.

System-defined labels should use translation files; user-created content should remain untouched.

---

# 16. Routes and Middleware

Implement a secure language-switching route.

For example:

```php
POST /language
```

or an equivalent route following the existing route structure.

The route should:

1. Accept a locale.
2. Validate it against `en` and `bn`.
3. Update the authenticated owner's preference where supported.
4. Update the session locale.
5. Redirect back to the previous page safely.
6. Preserve relevant query parameters where possible.

Prevent open redirects.

Implement locale middleware to apply the selected locale on subsequent requests.

Register middleware using the correct mechanism for the installed Laravel version.

Do not break existing authentication middleware, route groups, or CSRF protection.

---

# 17. Performance and Maintainability

The localization feature must not significantly slow down the application.

Requirements:

- Load translation files efficiently.
- Avoid unnecessary database queries on every page.
- Cache stable translations if appropriate.
- Do not request translations from external APIs.
- Avoid duplicating translation strings.
- Keep translation files organized.
- Keep Blade templates readable.
- Use reusable components.

Do not create two separate copies of the entire website for English and Bangla.

Maintain one application with one shared layout and translated strings.

---

# 18. Testing Requirements

After implementation, test both languages thoroughly.

## A. Language Switching

- English to Bangla works.
- Bangla to English works.
- Current page is preserved.
- Current query parameters are preserved where applicable.
- Language preference survives navigation.
- Language preference persists after logout/login for the owner.
- Invalid locale values are rejected.
- Guest login page can switch language.

## B. Page Coverage

Check:

- Login.
- Dashboard.
- Medicines.
- Medicine creation/editing.
- Categories.
- Stock adjustment.
- Purchases.
- Purchase creation/details.
- Sales.
- Sale creation/details.
- Expenses.
- Expense creation/editing.
- Reports.
- PDF export.
- Excel export.
- CSV export.
- Settings.

## C. UI Quality

Check:

- Bangla font renders correctly.
- Sidebar fits.
- Header fits.
- Buttons fit.
- Tables remain readable.
- Modals fit.
- Mobile navigation works.
- No text overlaps.
- No English interface strings remain unintentionally.

## D. Functional Regression

Ensure that the language feature does not break:

- Login/logout.
- CRUD operations.
- Purchase calculations.
- Sales calculations.
- Inventory updates.
- Stock movements.
- Financial reports.
- Date filtering.
- Search and pagination.
- PDF/Excel exports.

## E. Automated Tests

Add tests for:

- Locale switching.
- Locale persistence.
- Invalid locale rejection.
- Guest language switching.
- Translation rendering.
- Business operations remaining functional in both locales.

Run the existing test suite and fix any regressions.

---

# 19. Acceptance Criteria

The feature is complete only when:

1. A compact language toggle exists in the sticky header.
2. The toggle supports English and Bangla.
3. The entire application interface changes language.
4. All sidebar items are translated.
5. All dashboard labels are translated.
6. All forms are translated.
7. All tables are translated.
8. All buttons and modals are translated.
9. All notifications are translated.
10. Validation messages are translated.
11. Charts are translated.
12. Reports are translated.
13. PDF exports support Bangla.
14. Excel and CSV exports support Bangla headings.
15. Print views support Bangla.
16. The user's language preference is remembered.
17. Switching languages does not change the current page.
18. Existing business data remains unchanged.
19. Existing business calculations continue to work.
20. The Bangla UI is compact, readable, and responsive.
21. The application works without external translation services.
22. All relevant regression tests pass.

---

# 20. Development Workflow

Follow these steps.

### Phase 1 — Project Inspection

Inspect the complete existing HPMS project.

Identify every source of visible text.

Report the files and components that need localization.

### Phase 2 — Localization Foundation

Implement:

- Locale configuration.
- English translations.
- Bangla translations.
- Locale middleware.
- Language switching route.
- Persistent language preference.
- Guest locale support.

### Phase 3 — Header Integration

Add the compact language toggle to the existing sticky header.

Verify switching from multiple pages.

### Phase 4 — Complete UI Translation

Translate all application pages, shared components, forms, tables, alerts, modals, and notifications.

### Phase 5 — Dynamic Content

Translate JavaScript-generated messages, chart labels, tooltips, and dynamic form controls.

### Phase 6 — Export Localization

Implement and test Bangla support for PDF, Excel, CSV, and print output.

### Phase 7 — Typography and Responsiveness

Integrate Bengali fonts and adjust the UI for compact, readable Bangla rendering.

### Phase 8 — Testing

Test both locales and all existing application workflows.

Fix every regression.

### Phase 9 — Final Review

Provide a summary of:

1. Files created.
2. Files modified.
3. Migrations added.
4. Translation coverage.
5. Language persistence implementation.
6. PDF font configuration.
7. Automated tests executed.
8. Test results.
9. Any remaining untranslated strings or limitations.

---

# FINAL INSTRUCTIONS

- Work inside the existing HPMS Laravel project.
- Do not rebuild the application.
- Do not replace the existing design.
- Do not create separate English and Bangla websites.
- Do not use Google Translate or external translation APIs.
- Do not translate user-entered medicine or financial records.
- Do not change existing database business data.
- Do not break the existing inventory, sales, purchases, expenses, or reports.
- Do not remove existing functionality.
- Do not add unnecessary language settings pages.
- Put the language toggle in the existing sticky header.
- Make the Bangla interface compact and easy for a non-technical user.
- Keep the application fully functional in both languages.
- Do not deploy the application.

**Start by inspecting the existing HPMS project and then implement the localization feature systematically. Do not stop after creating translation files; integrate and verify translations throughout the entire application.**
