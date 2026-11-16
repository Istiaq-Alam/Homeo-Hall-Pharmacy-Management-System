<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ReportService;
use App\Services\TransactionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HpmsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function medicine(string $potency = '30C', array $attributes = []): Medicine
    {
        $category = MedicineCategory::query()->firstOrCreate(['name' => 'Dilution'], ['is_active' => true]);

        return Medicine::query()->create(array_merge([
            'category_id' => $category->id,
            'name' => 'Nux Vomica',
            'potency' => $potency,
            'form' => 'Liquid',
            'unit' => 'Bottle',
            'purchase_price' => 2.50,
            'selling_price' => 5.00,
            'minimum_stock' => 2,
        ], $attributes));
    }

    private function expenseCategory(): ExpenseCategory
    {
        return ExpenseCategory::query()->firstOrCreate(['name' => 'Electricity'], ['is_active' => true]);
    }

    public function test_guest_is_redirected_to_login_and_registration_is_unavailable(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/medicines')->assertRedirect('/login');
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_owner_can_log_in_and_log_out_without_public_registration(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com', 'password' => Hash::make('correct-password')]);

        $this->post('/login', ['email' => $owner->email, 'password' => 'correct-password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_owner_can_create_and_update_a_medicine(): void
    {
        $owner = User::factory()->create();
        $category = MedicineCategory::query()->create(['name' => 'Dilution', 'is_active' => true]);

        $this->actingAs($owner)->post('/medicines', [
            'category_id' => $category->id,
            'name' => 'Arnica Montana',
            'potency' => '200C',
            'form' => 'Liquid',
            'unit' => 'Bottle',
            'purchase_price' => 10,
            'selling_price' => 18,
            'minimum_stock' => 3,
        ])->assertRedirect();

        $medicine = Medicine::query()->firstOrFail();
        $this->actingAs($owner)->put('/medicines/'.$medicine->id, [
            'category_id' => $category->id,
            'name' => 'Arnica Montana',
            'potency' => '1M',
            'form' => 'Liquid',
            'unit' => 'Bottle',
            'purchase_price' => 12,
            'selling_price' => 20,
            'minimum_stock' => 4,
        ])->assertRedirect(route('medicines.show', $medicine));

        $this->assertDatabaseHas('medicines', ['id' => $medicine->id, 'potency' => '1M', 'selling_price' => 20]);
        $this->assertSame(0, $medicine->fresh()->stock_quantity);
    }

    public function test_different_potencies_are_separate_inventory_items(): void
    {
        $this->medicine('30C');
        $this->medicine('200C');

        $this->assertSame(2, Medicine::query()->count());
        $this->assertNotSame(
            Medicine::query()->where('potency', '30C')->value('id'),
            Medicine::query()->where('potency', '200C')->value('id'),
        );
    }

    public function test_medicine_search_and_low_stock_filter_work(): void
    {
        $owner = User::factory()->create();
        $low = $this->medicine('30C');
        $low->forceFill(['stock_quantity' => 1])->save();
        $healthy = $this->medicine('200C');
        $healthy->forceFill(['stock_quantity' => 20])->save();

        $this->actingAs($owner)->get('/medicines?status=low')
            ->assertOk()
            ->assertSee('Nux Vomica')
            ->assertSee('1');
        $this->actingAs($owner)->get('/medicines?q=200C')
            ->assertOk()
            ->assertSee('200C');
    }

    public function test_purchase_increases_stock_and_records_multiple_items_and_movements(): void
    {
        $owner = User::factory()->create();
        $first = $this->medicine('30C');
        $second = $this->medicine('200C');
        $service = app(TransactionService::class);

        $purchase = $service->recordPurchase([
            'purchase_date' => '2026-11-15',
            'supplier_name' => 'Local supplier',
            'items' => [
                ['medicine_id' => $first->id, 'quantity' => 10, 'unit_price' => 2.50],
                ['medicine_id' => $second->id, 'quantity' => 5, 'unit_price' => 4.00],
            ],
        ], $owner->id);

        $this->assertSame('45.00', $purchase->total_amount);
        $this->assertSame(10, $first->fresh()->stock_quantity);
        $this->assertSame(5, $second->fresh()->stock_quantity);
        $this->assertSame(2, $purchase->items()->count());
        $this->assertSame(2, StockMovement::query()->where('movement_type', 'purchase')->count());
        $this->assertSame(2.5000, (float) $first->fresh()->average_cost);
    }

    public function test_purchase_cancellation_reverses_stock_cost_and_rejects_repeat(): void
    {
        $owner = User::factory()->create();
        $medicine = $this->medicine();
        $service = app(TransactionService::class);
        $first = $service->recordPurchase([
            'purchase_date' => '2026-11-14',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 10, 'unit_price' => 2]],
        ], $owner->id);
        $second = $service->recordPurchase([
            'purchase_date' => '2026-11-15',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 10, 'unit_price' => 4]],
        ], $owner->id);

        $this->assertSame(3.0, (float) $medicine->fresh()->average_cost);
        $service->cancelPurchase($second, 'Supplier issued a return');

        $medicine->refresh();
        $this->assertSame(10, $medicine->stock_quantity);
        $this->assertSame(2.0, (float) $medicine->average_cost);
        $this->assertSame('cancelled', $second->fresh()->status);
        $this->assertSame('Supplier issued a return', $second->fresh()->cancellation_reason);
        $this->assertSame(1, StockMovement::query()->where('movement_type', 'purchase_reversal')->count());

        $this->expectException(ValidationException::class);
        $service->cancelPurchase($second, 'Again');
    }

    public function test_purchase_cannot_be_cancelled_after_a_completed_sale_used_its_stock(): void
    {
        $owner = User::factory()->create();
        $medicine = $this->medicine();
        $service = app(TransactionService::class);
        $purchase = $service->recordPurchase([
            'purchase_date' => '2026-11-15',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 10, 'unit_price' => 2]],
        ], $owner->id);
        $service->recordSale([
            'sale_date' => '2026-11-15',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1, 'unit_price' => 5]],
        ], $owner->id);
        $service->recordPurchase([
            'purchase_date' => '2026-11-15',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1, 'unit_price' => 2]],
        ], $owner->id);

        try {
            $service->cancelPurchase($purchase, 'Cannot reverse reported COGS');
            $this->fail('Expected the purchase cancellation to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('historical cost of goods sold', $exception->getMessage());
            $this->assertSame('completed', $purchase->fresh()->status);
            $this->assertSame(10, $medicine->fresh()->stock_quantity);
            $this->assertSame(2.0, (float) $medicine->fresh()->average_cost);
        }
    }

    public function test_sale_uses_historical_cost_and_sale_cancellation_restores_stock(): void
    {
        $owner = User::factory()->create();
        $medicine = $this->medicine();
        $service = app(TransactionService::class);
        $service->recordPurchase([
            'purchase_date' => '2026-11-01',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 10, 'unit_price' => 2.50]],
        ], $owner->id);
        $service->recordPurchase([
            'purchase_date' => '2026-11-02',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 10, 'unit_price' => 3.50]],
        ], $owner->id);

        $sale = $service->recordSale([
            'sale_date' => '2026-11-15',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 4, 'unit_price' => 5]],
        ], $owner->id);

        $item = $sale->items()->firstOrFail();
        $this->assertSame('20.00', $sale->total_amount);
        $this->assertSame('3.0000', (string) $item->unit_cost_price);
        $this->assertSame('12.00', $item->line_cost_total);
        $this->assertSame(16, $medicine->fresh()->stock_quantity);

        $medicine->update(['purchase_price' => 99, 'selling_price' => 99]);
        $this->assertSame(12.0, app(ReportService::class)->summary('2026-11-15', '2026-11-15')['cogs']);

        $service->cancelSale($sale, 'Incorrect quantity');
        $medicine->refresh();
        $this->assertSame(20, $medicine->stock_quantity);
        $this->assertSame(3.0, (float) $medicine->average_cost);
        $this->assertSame('cancelled', $sale->fresh()->status);

        $this->expectException(ValidationException::class);
        $service->cancelSale($sale, 'Again');
    }

    public function test_insufficient_stock_is_rejected_without_partial_sale(): void
    {
        $owner = User::factory()->create();
        $medicine = $this->medicine();
        $medicine->forceFill(['stock_quantity' => 2])->save();
        $service = app(TransactionService::class);

        try {
            $service->recordSale([
                'sale_date' => '2026-11-15',
                'items' => [['medicine_id' => $medicine->id, 'quantity' => 3, 'unit_price' => 5]],
            ], $owner->id);
            $this->fail('Expected an insufficient-stock validation error.');
        } catch (ValidationException) {
            $this->assertSame(2, $medicine->fresh()->stock_quantity);
            $this->assertSame(0, Sale::query()->count());
            $this->assertSame(0, StockMovement::query()->count());
        }
    }

    public function test_failed_purchase_rolls_back_all_stock_changes(): void
    {
        $owner = User::factory()->create();
        $medicine = $this->medicine();

        try {
            app(TransactionService::class)->recordPurchase([
                'purchase_date' => '2026-11-15',
                'items' => [
                    ['medicine_id' => $medicine->id, 'quantity' => 5, 'unit_price' => 2],
                    ['medicine_id' => 999999, 'quantity' => 2, 'unit_price' => 2],
                ],
            ], $owner->id);
            $this->fail('Expected the invalid medicine to fail the purchase.');
        } catch (ModelNotFoundException) {
            $this->assertSame(0, $medicine->fresh()->stock_quantity);
            $this->assertSame(0, Purchase::query()->count());
            $this->assertSame(0, StockMovement::query()->count());
        }
    }

    public function test_stock_adjustments_are_ledgered_and_cannot_go_negative(): void
    {
        $owner = User::factory()->create();
        $medicine = $this->medicine();
        $medicine->forceFill(['stock_quantity' => 5])->save();
        $service = app(TransactionService::class);

        $service->adjustStock([
            'medicine_id' => $medicine->id,
            'adjustment_type' => 'decrease',
            'quantity' => 2,
            'reason' => 'Damaged during counting',
            'adjustment_date' => '2026-11-15',
        ], $owner->id);

        $this->assertSame(3, $medicine->fresh()->stock_quantity);
        $movement = StockMovement::query()->firstOrFail();
        $this->assertSame('adjustment_decrease', $movement->movement_type);
        $this->assertSame(5, $movement->previous_stock);
        $this->assertSame(3, $movement->resulting_stock);

        $this->expectException(ValidationException::class);
        $service->adjustStock([
            'medicine_id' => $medicine->id,
            'adjustment_type' => 'decrease',
            'quantity' => 4,
            'reason' => 'Negative test',
            'adjustment_date' => '2026-11-15',
        ], $owner->id);
    }

    public function test_expenses_are_recorded_and_cancelled_expenses_are_excluded(): void
    {
        $owner = User::factory()->create();
        $category = $this->expenseCategory();
        $service = app(TransactionService::class);
        $expense = $service->recordExpense([
            'expense_category_id' => $category->id,
            'expense_date' => '2026-11-15',
            'description' => 'Electricity bill',
            'amount' => '50.00',
        ], $owner->id);

        $this->assertSame(50.0, (float) $expense->amount);
        $this->assertSame(50.0, app(ReportService::class)->summary('2026-11-15', '2026-11-15')['expenses']);

        $this->actingAs($owner)->post('/expenses/'.$expense->id.'/cancel', ['reason' => 'Duplicate entry'])
            ->assertRedirect();
        $this->assertSame('cancelled', $expense->fresh()->status);
        $this->assertSame(0.0, app(ReportService::class)->summary('2026-11-15', '2026-11-15')['expenses']);
    }

    public function test_financial_report_formulas_date_filters_and_inventory_value_are_correct(): void
    {
        $owner = User::factory()->create();
        $medicine = $this->medicine();
        $service = app(TransactionService::class);
        $service->recordPurchase([
            'purchase_date' => '2026-11-01',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 10, 'unit_price' => 2.50]],
        ], $owner->id);
        $service->recordPurchase([
            'purchase_date' => '2026-11-02',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 10, 'unit_price' => 3.50]],
        ], $owner->id);
        $service->recordSale([
            'sale_date' => '2026-11-15',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 4, 'unit_price' => 5]],
        ], $owner->id);
        $service->recordExpense([
            'expense_category_id' => $this->expenseCategory()->id,
            'expense_date' => '2026-11-15',
            'description' => 'Chamber internet',
            'amount' => 5,
        ], $owner->id);

        $summary = app(ReportService::class)->summary('2026-11-15', '2026-11-15');
        $this->assertSame(20.0, $summary['sales']);
        $this->assertSame(0.0, $summary['purchases']);
        $this->assertSame(12.0, $summary['cogs']);
        $this->assertSame(8.0, $summary['gross_profit']);
        $this->assertSame(5.0, $summary['expenses']);
        $this->assertSame(3.0, $summary['net_profit']);
        $this->assertSame(4, $summary['units_sold']);
        $this->assertSame(48.0, $summary['inventory_value']);

        $month = app(ReportService::class)->summary('2026-11-01', '2026-11-30');
        $this->assertSame(60.0, $month['purchases']);
        $this->assertSame(20, $month['units_purchased']);

        $this->actingAs($owner)->get('/reports?from=2026-11-15&to=2026-11-15')
            ->assertOk()
            ->assertSee('Sales revenue')
            ->assertSee('৳20.00')
            ->assertSee('৳12.00');
    }

    public function test_cancelled_transactions_are_excluded_from_reports(): void
    {
        $owner = User::factory()->create();
        $medicine = $this->medicine();
        $service = app(TransactionService::class);
        $purchase = $service->recordPurchase([
            'purchase_date' => '2026-11-15',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 5, 'unit_price' => 2]],
        ], $owner->id);
        $sale = $service->recordSale([
            'sale_date' => '2026-11-15',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1, 'unit_price' => 5]],
        ], $owner->id);

        $service->cancelSale($sale, 'Cancelled sale');
        $service->cancelPurchase($purchase, 'Cancelled purchase');

        $summary = app(ReportService::class)->summary('2026-11-15', '2026-11-15');
        $this->assertSame(0.0, $summary['sales']);
        $this->assertSame(0.0, $summary['purchases']);
        $this->assertSame(0.0, $summary['cogs']);
        $this->assertSame(0.0, $summary['gross_profit']);
        $this->assertSame(0.0, $summary['net_profit']);
        $this->assertSame(0.0, $summary['inventory_value']);
    }

    public function test_stock_movements_match_current_stock_and_each_ledger_step(): void
    {
        $owner = User::factory()->create();
        $medicine = $this->medicine();
        $service = app(TransactionService::class);
        $service->recordPurchase([
            'purchase_date' => '2026-11-15',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 8, 'unit_price' => 2]],
        ], $owner->id);
        $service->recordSale([
            'sale_date' => '2026-11-15',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 2, 'unit_price' => 5]],
        ], $owner->id);
        $service->adjustStock([
            'medicine_id' => $medicine->id,
            'adjustment_type' => 'increase',
            'quantity' => 1,
            'reason' => 'Physical count',
            'adjustment_date' => '2026-11-15',
        ], $owner->id);

        $movements = $medicine->movements()->orderBy('id')->get();
        $this->assertSame($medicine->fresh()->stock_quantity, $movements->sum('quantity'));
        foreach ($movements as $movement) {
            $this->assertSame($movement->resulting_stock, $movement->previous_stock + $movement->quantity);
        }
    }

    public function test_pdf_csv_and_xlsx_exports_respect_the_date_range(): void
    {
        $owner = User::factory()->create();
        $this->medicine();

        $this->actingAs($owner)->get('/reports/export.csv?from=2026-11-01&to=2026-11-30')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($owner)->get('/reports/export.xlsx?from=2026-11-01&to=2026-11-30')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->actingAs($owner)->get('/reports/export.pdf?from=2026-11-01&to=2026-11-30')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_invalid_report_dates_are_rejected(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->get('/reports?from=not-a-date&to=2026-11-30')
            ->assertSessionHasErrors('from');
    }

    public function test_medicine_can_be_created_with_opening_stock_at_a_per_unit_cost(): void
    {
        $owner = User::factory()->create();
        $category = MedicineCategory::query()->create(['name' => 'Dilution', 'is_active' => true]);

        $this->actingAs($owner)->post('/medicines', [
            'category_id' => $category->id,
            'name' => 'Arnica Montana',
            'potency' => '30C',
            'form' => 'Liquid',
            'unit' => 'Bottle',
            'purchase_price' => 2,
            'selling_price' => 5,
            'minimum_stock' => 1,
            'opening_stock' => 5,
        ])->assertRedirect();

        $medicine = Medicine::query()->where('name', 'Arnica Montana')->firstOrFail();
        $this->assertSame(5, $medicine->stock_quantity);
        $this->assertSame(2.0, (float) $medicine->average_cost);

        $sale = app(TransactionService::class)->recordSale([
            'sale_date' => '2026-11-16',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1, 'unit_price' => 5]],
        ], $owner->id);

        $summary = app(ReportService::class)->summary('2026-11-16', '2026-11-16');
        $this->assertSame(5.0, $summary['sales']);
        $this->assertSame(2.0, $summary['cogs']);
        $this->assertSame(3.0, $summary['gross_profit']);
        $this->assertSame(1, $sale->items()->value('quantity'));
        $this->assertSame(4, $medicine->fresh()->stock_quantity);
    }

    public function test_editing_purchase_price_updates_cost_basis_when_stock_has_no_purchase_ledger(): void
    {
        $owner = User::factory()->create();
        $medicine = $this->medicine('30C', ['purchase_price' => 600, 'selling_price' => 395]);
        $this->actingAs($owner);

        app(TransactionService::class)->adjustStock([
            'medicine_id' => $medicine->id,
            'adjustment_type' => 'increase',
            'quantity' => 5,
            'reason' => 'Opening count',
            'adjustment_date' => '2026-11-16',
        ], $owner->id);
        $this->assertSame(600.0, (float) $medicine->fresh()->average_cost);

        $this->put('/medicines/'.$medicine->id, [
            'category_id' => $medicine->category_id,
            'name' => $medicine->name,
            'potency' => $medicine->potency,
            'form' => $medicine->form,
            'unit' => $medicine->unit,
            'purchase_price' => 100,
            'selling_price' => 150,
            'minimum_stock' => 1,
        ])->assertRedirect(route('medicines.show', $medicine));

        $this->assertSame(100.0, (float) $medicine->fresh()->average_cost);

        app(TransactionService::class)->recordSale([
            'sale_date' => '2026-11-17',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1, 'unit_price' => 150]],
        ], $owner->id);

        $summary = app(ReportService::class)->summary('2026-11-17', '2026-11-17');
        $this->assertSame(150.0, $summary['sales']);
        $this->assertSame(100.0, $summary['cogs']);
        $this->assertSame(50.0, $summary['gross_profit']);
    }

    public function test_editing_purchase_price_keeps_weighted_average_when_purchases_exist(): void
    {
        $owner = User::factory()->create();
        $medicine = $this->medicine('30C', ['purchase_price' => 100, 'selling_price' => 150]);
        $this->actingAs($owner);

        app(TransactionService::class)->recordPurchase([
            'purchase_date' => '2026-11-16',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 5, 'unit_price' => 100]],
        ], $owner->id);

        $this->put('/medicines/'.$medicine->id, [
            'category_id' => $medicine->category_id,
            'name' => $medicine->name,
            'potency' => $medicine->potency,
            'form' => $medicine->form,
            'unit' => $medicine->unit,
            'purchase_price' => 120,
            'selling_price' => 150,
            'minimum_stock' => 1,
        ])->assertRedirect(route('medicines.show', $medicine));

        $this->assertSame(100.0, (float) $medicine->fresh()->average_cost);
    }

    public function test_a_sale_can_be_kept_unpaid_and_marked_paid_later(): void
    {
        $owner = User::factory()->create();
        $medicine = $this->medicine();
        $medicine->forceFill(['stock_quantity' => 5, 'average_cost' => 2])->save();

        $this->actingAs($owner)->post('/sales', [
            'sale_date' => '2026-11-16',
            'payment_status' => 'unpaid',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1, 'unit_price' => 5]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame('unpaid', $sale->payment_status);
        $this->assertNull($sale->paid_at);
        $this->actingAs($owner)->get('/sales/'.$sale->id)->assertOk()->assertSee('Unpaid');
        $this->actingAs($owner)->get('/sales?payment=unpaid')->assertOk()->assertSee($sale->sale_number);

        $this->actingAs($owner)->post('/sales/'.$sale->id.'/mark-paid')
            ->assertRedirect()
            ->assertSessionHas('success', __('sales.flash.marked_paid'));

        $sale->refresh();
        $this->assertSame('paid', $sale->payment_status);
        $this->assertNotNull($sale->paid_at);
        $this->actingAs($owner)->get('/sales?payment=unpaid')->assertOk()->assertDontSee($sale->sale_number);
    }

    public function test_dashboard_displays_live_financial_data(): void
    {
        $owner = User::factory()->create();
        $medicine = $this->medicine();
        $service = app(TransactionService::class);
        $service->recordPurchase([
            'purchase_date' => now()->toDateString(),
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 10, 'unit_price' => 2]],
        ], $owner->id);
        $service->recordSale([
            'sale_date' => now()->toDateString(),
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1, 'unit_price' => 5]],
        ], $owner->id);

        $this->actingAs($owner)->get('/dashboard')
            ->assertOk()
            ->assertSee("Today's sales")
            ->assertSee('৳5.00')
            ->assertSee('Total medicines');
    }
}
