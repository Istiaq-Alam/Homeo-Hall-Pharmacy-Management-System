# Business Logic

This document is the authoritative explanation of the accounting and stock rules implemented in `App\Services\TransactionService` and `App\Services\ReportService`.

## Currency and rounding

- All money values are decimal, stored to two places for transaction amounts and four places for weighted-average unit cost.
- Each line total is calculated in integer paisa (cents) with round-half-up and stored to two decimals.
- Sale-item COGS (`line_cost_total`) is calculated from the four-decimal historical unit cost, converted to paisa, and stored to two decimals.
- Report totals sum stored transaction amounts, then round for display. Reports never recalculate historical prices from current medicine prices.

## Medicine identity

Within a chamber, a stock item is uniquely identified by:

```text
chamber + name + potency + form + unit
```

`Nux Vomica 30C Liquid Bottle` and `Nux Vomica 200C Liquid Bottle` are different rows with independent stock, cost, and prices. Editing a medicine cannot merge it with an existing identity.

## Weighted-average inventory cost

The application uses a moving weighted-average cost:

```text
New average cost =
  ((Existing quantity × Existing average cost)
   + (Purchased quantity × Purchase unit cost))
  / (Existing quantity + Purchased quantity)
```

The result is rounded to four decimals and stored in `medicines.average_cost`. The latest purchase unit cost is also copied to `medicines.purchase_price` for display and defaulting, but that field is not used for historical COGS.

When a purchase adds stock, the average is recalculated inside the same locked database transaction. When a sale is recorded, the current average is copied into `sale_items.unit_cost_price`; it is not recalculated later.

When stock reaches exactly zero, `average_cost` is reset to `0`. If zero stock is later increased by a stock adjustment, the current `purchase_price` becomes the initial average cost. The next purchase then blends normally from zero.

## Purchase recording

For each line:

```text
Line total = quantity × unit cost
Purchase total = sum of line totals
New stock = existing stock + quantity
Stock movement quantity = +quantity
```

Steps, in one transaction:

1. Create the purchase header with a generated unique number.
2. Load and lock each medicine row.
3. Validate that the resulting unsigned stock cannot overflow.
4. Create the purchase item with historical unit cost.
5. Update stock and weighted-average cost.
6. Append a `purchase` stock movement with previous and resulting stock.
7. Store the server-calculated header total.

If any line fails, the whole operation rolls back, including the header and all earlier line changes.

## Sale recording

```text
Line total = quantity × actual selling price
Line COGS = quantity × weighted-average cost at the time of sale
Sale total = sum of line totals
```

Steps, in one transaction:

1. Group duplicate medicine lines defensively and lock all affected medicine rows in ID order.
2. Reject the sale if any medicine is inactive or total requested quantity exceeds available stock.
3. Create the sale header.
4. Copy historical selling price and average cost to each sale item.
5. Reduce stock; if resulting stock is zero, reset average cost to zero.
6. Append a `sale` movement with a negative quantity.
7. Store the server-calculated total.

The form's JavaScript calculations are never trusted. The server validates quantities, prices, and stock independently.

## Purchase cancellation

A completed purchase is never deleted. Cancellation:

- Sets `status = cancelled`, stores the reason, and stores `cancelled_at`.
- Reduces stock by the original purchased quantities.
- Writes a `purchase_reversal` movement for each item.
- Removes the purchase's original cost contribution from the current inventory pool:

```text
Remaining average =
  ((Current stock × Current average)
   - (Purchased quantity × Original purchase unit cost))
  / (Current stock - Purchased quantity)
```

The result is floored at zero and rounded to four decimals.

### Safety restrictions

Cancellation is rejected when:

- The transaction is already cancelled.
- Current stock is less than the quantity being reversed.
- A **completed sale created after this purchase** used the medicine. Such a sale's COGS was based on the blended average. Reversing the purchase afterward would either distort remaining inventory cost or silently rewrite already-reported COGS. The administrator must cancel the later sale first if that is the intended correction.
- The average calculation would otherwise require removing more cost than the remaining pool holds.

A later purchase that has not been consumed does not block cancellation; the subtraction formula remains mathematically consistent. A cancelled sale does not block it because the sale's stock and cost were restored. Purchases are never partially cancelled: either every line can reverse safely, or nothing is changed.

## Sale cancellation

A completed sale is never deleted. Cancellation:

- Sets `status = cancelled`, stores the reason, and stores `cancelled_at`.
- Restores every sold quantity.
- Writes a `sale_reversal` movement for each item.
- Re-blends the returned historical cost into current inventory:

```text
New average =
  ((Current stock × Current average)
   + (Returned quantity × Historical sale unit cost))
  / (Current stock + Returned quantity)
```

This works when current stock is zero: the returned historical cost becomes the new average. A second cancellation is rejected, and cancelled sales are excluded from revenue, COGS, and profit reports.

## Stock adjustments

An adjustment requires a medicine, direction (`increase` or `decrease`), positive quantity, reason, date, and optional notes.

```text
New stock = old stock ± adjustment quantity
```

- Decreases that would make stock negative are rejected with a validation error.
- Every adjustment creates a `stock_adjustments` header and one ledger movement:
  - `adjustment_increase` with positive signed quantity.
  - `adjustment_decrease` with negative signed quantity.
- Increasing stock from zero initializes average cost from the medicine's purchase price. Other adjustments leave average cost unchanged, except a result of zero resets it to zero.
- There is no direct stock-edit field in medicine forms, so every stock change is auditable.

## Expense recording

Medicine purchases are inventory acquisitions, not operating expenses. Expenses are separate records with a category, date, description, and positive amount. Cancelling an expense keeps the row, records the reason and timestamp, and excludes it from expense and profit totals. Cancelled expenses cannot be edited.

## Financial definitions

For the selected inclusive date range:

```text
Sales revenue = SUM(completed sale total_amount)

Purchases = SUM(completed purchase total_amount)
  (shown as inventory acquisition / cash outflow, not an operating expense)

COGS = SUM(completed sale_item line_cost_total)

Gross profit = Sales revenue - COGS

Other operating expenses = SUM(completed expense amount)

Net operating profit = Gross profit - Other operating expenses
```

Inventory value is always a point-in-time balance, independent of the report range:

```text
Inventory value = SUM(medicine.stock_quantity × medicine.average_cost)
```

Closing quantity is represented by current stock and reconciled by the stock ledger:

```text
Current stock = SUM(all signed stock movement quantities)
```

Every movement also satisfies:

```text
resulting_stock = previous_stock + signed quantity
```

## Cancellations in reports

Report queries include `status = completed` for sales, purchases, and expenses. Cancelled headers therefore contribute zero revenue, purchases, COGS, expenses, or profit. Reversal movements remain visible in the stock-movement ledger for auditability. Reversals are not counted as new sales or purchases.

## Date-range semantics

- Report ranges are inclusive of both dates.
- Queries compare date fields from `from 00:00:00` through `to 23:59:59`, avoiding off-by-one exclusions under databases that persist a time component on date values.
- Transaction dates are the administrator-entered calendar dates and drive reports; `created_at` does not move a transaction into a different report period.
- The application and display timezone is `Asia/Dhaka`.

## Concurrency and atomicity

Purchase creation, sale creation, purchase cancellation, sale cancellation, and stock adjustment each run inside `DB::transaction`.

- Medicine rows are loaded with `lockForUpdate` before their stock is changed.
- Sale lines are validated collectively, so two lines of the same medicine cannot bypass stock limits.
- Cancellation headers are locked and re-fetched before status checks, preventing two simultaneous cancellations from both succeeding.
- Any exception, including validation, rolls back every write in the unit of work.

## Audit trail

The combination of preserved transaction headers, historical line prices, cancellation reason/timestamp, stock-adjustment headers, and append-only stock movements answers:

- What changed?
- Which medicine was affected?
- When did it happen?
- What was the previous and resulting stock?
- Which transaction or adjustment caused it?
- Why was a manual correction made?

No completed purchase or sale is hard-deleted by the application.

## Export logic

PDF, XLSX, and CSV exports call the same `ReportService::summary()` used by the report screen and receive the same validated `from`/`to` range. Print uses the same server-rendered report markup. Consequently, changing the date range changes every export consistently.

The XLSX export is a standards-based ZIP/XML workbook produced by `App\Support\SimpleXlsxWriter` because PhpSpreadsheet cannot be installed without `ext-gd` here. It contains one summary worksheet; PDF and CSV are unaffected.
