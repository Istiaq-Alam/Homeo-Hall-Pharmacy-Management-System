<?php

namespace Tests\Feature;

use App\Models\ExpenseCategory;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_primary_owner_page_renders(): void
    {
        $owner = User::factory()->create();
        $medicineCategory = MedicineCategory::query()->create(['name' => 'Dilution', 'is_active' => true]);
        $expenseCategory = ExpenseCategory::query()->create(['name' => 'Electricity', 'is_active' => true]);
        $medicine = Medicine::query()->create([
            'category_id' => $medicineCategory->id,
            'name' => 'Nux Vomica',
            'potency' => '30C',
            'form' => 'Liquid',
            'unit' => 'Bottle',
            'purchase_price' => 2,
            'selling_price' => 5,
            'minimum_stock' => 2,
        ]);
        $transactions = app(TransactionService::class);
        $purchase = $transactions->recordPurchase([
            'purchase_date' => '2026-11-15',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 5, 'unit_price' => 2]],
        ], $owner->id);
        $sale = $transactions->recordSale([
            'sale_date' => '2026-11-15',
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1, 'unit_price' => 5]],
        ], $owner->id);
        $expense = $transactions->recordExpense([
            'expense_category_id' => $expenseCategory->id,
            'expense_date' => '2026-11-15',
            'description' => 'Test bill',
            'amount' => 5,
        ], $owner->id);

        $paths = [
            '/dashboard',
            '/medicines',
            '/medicines/create',
            '/medicines/categories',
            '/medicines/adjustments/create',
            '/medicines/'.$medicine->id,
            '/medicines/'.$medicine->id.'/edit',
            '/purchases',
            '/purchases/create',
            '/purchases/'.$purchase->id,
            '/sales',
            '/sales/create',
            '/sales/'.$sale->id,
            '/expenses',
            '/expenses/create',
            '/expenses/categories',
            '/expenses/'.$expense->id,
            '/expenses/'.$expense->id.'/edit',
            '/reports',
            '/reports/daily',
            '/reports/monthly',
            '/reports/yearly',
            '/reports/inventory',
            '/reports/medicine-sales',
            '/reports/expenses',
            '/reports/low-stock',
            '/reports/stock-movements',
            '/settings',
        ];

        foreach ($paths as $path) {
            $this->actingAs($owner)->get($path)->assertOk();
        }

        $this->get('/health')->assertOk()->assertJson(['status' => 'ok', 'database' => 'ok']);
    }
}
