<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Medicine;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TransactionService
{
    /** @param array<string, mixed> $data */
    public function recordPurchase(array $data, int $userId): Purchase
    {
        return DB::transaction(function () use ($data, $userId): Purchase {
            $purchase = Purchase::create([
                'purchase_number' => $this->transactionNumber('PUR'),
                'purchase_date' => $data['purchase_date'],
                'supplier_name' => $data['supplier_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'user_id' => $userId,
                'total_amount' => 0,
            ]);
            $totalCents = 0;
            $lines = $this->normalizeLines($data['items']);

            foreach ($lines as $line) {
                $medicine = Medicine::query()->lockForUpdate()->findOrFail($line['medicine_id']);
                $oldQuantity = (int) $medicine->stock_quantity;
                $oldAverage = (float) $medicine->average_cost;
                $unitCost = (float) $line['unit_price'];
                $newQuantity = $oldQuantity + $line['quantity'];
                $this->ensureStockFits($medicine, $newQuantity);
                $newAverage = round((($oldQuantity * $oldAverage) + ($line['quantity'] * $unitCost)) / $newQuantity, 4);
                $lineCents = $this->moneyCents($line['quantity'] * $unitCost);
                $totalCents += $lineCents;
                $purchase->items()->create([
                    'medicine_id' => $medicine->id,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $this->centsValue($this->moneyCents($unitCost)),
                    'line_total' => $this->centsValue($lineCents),
                ]);
                $medicine->forceFill([
                    'stock_quantity' => $newQuantity,
                    'average_cost' => $newAverage,
                    'purchase_price' => $this->centsValue($this->moneyCents($unitCost)),
                ])->save();
                $this->movement($medicine, 'purchase', $line['quantity'], $oldQuantity, $newQuantity, Purchase::class, $purchase->id, 'Purchase '.$purchase->purchase_number, $data['purchase_date']);
            }

            $purchase->update(['total_amount' => $this->centsValue($totalCents)]);

            return $purchase->load('items.medicine');
        });
    }

    /** @param array<string, mixed> $data */
    public function recordSale(array $data, int $userId): Sale
    {
        return DB::transaction(function () use ($data, $userId): Sale {
            $paymentStatus = ($data['payment_status'] ?? 'paid') === 'unpaid' ? 'unpaid' : 'paid';
            $sale = Sale::create([
                'sale_number' => $this->transactionNumber('SAL'),
                'sale_date' => $data['sale_date'],
                'notes' => $data['notes'] ?? null,
                'payment_status' => $paymentStatus,
                'paid_at' => $paymentStatus === 'paid' ? now() : null,
                'user_id' => $userId,
                'total_amount' => 0,
            ]);
            $totalCents = 0;
            $lines = $this->normalizeLines($data['items']);
            $requiredByMedicine = [];
            foreach ($lines as $line) {
                $requiredByMedicine[$line['medicine_id']] = ($requiredByMedicine[$line['medicine_id']] ?? 0) + $line['quantity'];
            }
            $medicines = Medicine::query()->whereIn('id', array_keys($requiredByMedicine))->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($requiredByMedicine as $medicineId => $quantity) {
                $medicine = $medicines->get($medicineId);
                if (! $medicine || ! $medicine->is_active || $medicine->stock_quantity < $quantity) {
                    throw ValidationException::withMessages(['items' => __('sales.insufficient_stock')]);
                }
            }

            foreach ($lines as $line) {
                $medicine = $medicines->get($line['medicine_id']);
                $oldQuantity = (int) $medicine->stock_quantity;
                $newQuantity = $oldQuantity - $line['quantity'];
                $unitSellingPrice = (float) $line['unit_price'];
                $unitCost = (float) $medicine->average_cost;
                $lineCents = $this->moneyCents($line['quantity'] * $unitSellingPrice);
                $lineCostCents = $this->moneyCents($line['quantity'] * $unitCost);
                $totalCents += $lineCents;
                $sale->items()->create([
                    'medicine_id' => $medicine->id,
                    'quantity' => $line['quantity'],
                    'unit_selling_price' => $this->centsValue($this->moneyCents($unitSellingPrice)),
                    'unit_cost_price' => round($unitCost, 4),
                    'line_total' => $this->centsValue($lineCents),
                    'line_cost_total' => $this->centsValue($lineCostCents),
                ]);
                $medicine->forceFill([
                    'stock_quantity' => $newQuantity,
                    'average_cost' => $newQuantity === 0 ? 0 : $unitCost,
                ])->save();
                $this->movement($medicine, 'sale', -$line['quantity'], $oldQuantity, $newQuantity, Sale::class, $sale->id, 'Sale '.$sale->sale_number, $data['sale_date']);
            }

            $sale->update(['total_amount' => $this->centsValue($totalCents)]);

            return $sale->load('items.medicine');
        });
    }

    public function cancelPurchase(Purchase $purchase, string $reason): Purchase
    {
        return DB::transaction(function () use ($purchase, $reason): Purchase {
            $purchase = Purchase::query()->lockForUpdate()->with('items')->findOrFail($purchase->id);
            $this->ensureCompleted($purchase);
            foreach ($purchase->items as $item) {
                $medicine = Medicine::query()->lockForUpdate()->findOrFail($item->medicine_id);
                if ($medicine->stock_quantity < $item->quantity) {
                    throw ValidationException::withMessages(['reason' => __('purchases.cancel_sold_stock')]);
                }
                $purchaseMovementId = StockMovement::query()
                    ->where('medicine_id', $medicine->id)
                    ->where('movement_type', 'purchase')
                    ->where('reference_type', Purchase::class)
                    ->where('reference_id', $purchase->id)
                    ->min('id');
                $laterSaleExists = StockMovement::query()
                    ->where('medicine_id', $medicine->id)
                    ->where('movement_type', 'sale')
                    ->when($purchaseMovementId, fn ($query) => $query->where('id', '>', $purchaseMovementId))
                    ->whereIn('reference_id', Sale::query()->where('status', 'completed')->select('id'))
                    ->exists();
                if ($laterSaleExists) {
                    throw ValidationException::withMessages(['reason' => __('purchases.cancel_used_by_sale')]);
                }
                $oldQuantity = (int) $medicine->stock_quantity;
                $newQuantity = $oldQuantity - (int) $item->quantity;
                $remainingAverage = $newQuantity === 0
                    ? 0
                    : max(0, round((($oldQuantity * (float) $medicine->average_cost) - ((int) $item->quantity * (float) $item->unit_cost)) / $newQuantity, 4));
                $medicine->forceFill([
                    'stock_quantity' => $newQuantity,
                    'average_cost' => $remainingAverage,
                ])->save();
                $this->movement($medicine, 'purchase_reversal', -(int) $item->quantity, $oldQuantity, $newQuantity, Purchase::class, $purchase->id, $reason, now());
            }
            $purchase->update(['status' => 'cancelled', 'cancellation_reason' => $reason, 'cancelled_at' => now()]);

            return $purchase;
        });
    }

    public function markSalePaid(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale): Sale {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            if ($sale->status !== 'completed') {
                throw ValidationException::withMessages(['payment_status' => __('sales.cannot_mark_cancelled')]);
            }
            if ($sale->payment_status === 'unpaid') {
                $sale->forceFill(['payment_status' => 'paid', 'paid_at' => now()])->save();
            }

            return $sale;
        });
    }

    public function cancelSale(Sale $sale, string $reason): Sale
    {
        return DB::transaction(function () use ($sale, $reason): Sale {
            $sale = Sale::query()->lockForUpdate()->with('items')->findOrFail($sale->id);
            $this->ensureCompleted($sale);
            foreach ($sale->items as $item) {
                $medicine = Medicine::query()->lockForUpdate()->findOrFail($item->medicine_id);
                $oldQuantity = (int) $medicine->stock_quantity;
                $addedQuantity = (int) $item->quantity;
                $newQuantity = $oldQuantity + $addedQuantity;
                $newAverage = round((($oldQuantity * (float) $medicine->average_cost) + ($addedQuantity * (float) $item->unit_cost_price)) / $newQuantity, 4);
                $medicine->forceFill(['stock_quantity' => $newQuantity, 'average_cost' => $newAverage])->save();
                $this->movement($medicine, 'sale_reversal', $addedQuantity, $oldQuantity, $newQuantity, Sale::class, $sale->id, $reason, now());
            }
            $sale->update(['status' => 'cancelled', 'cancellation_reason' => $reason, 'cancelled_at' => now()]);

            return $sale;
        });
    }

    /** @param array<string, mixed> $data */
    public function adjustStock(array $data, int $userId): StockAdjustment
    {
        return DB::transaction(function () use ($data, $userId): StockAdjustment {
            $medicine = Medicine::query()->lockForUpdate()->findOrFail($data['medicine_id']);
            $oldQuantity = (int) $medicine->stock_quantity;
            $quantity = (int) $data['quantity'];
            $delta = $data['adjustment_type'] === 'increase' ? $quantity : -$quantity;
            $newQuantity = $oldQuantity + $delta;
            $this->ensureStockFits($medicine, $newQuantity);
            $averageCost = (float) $medicine->average_cost;
            if ($delta > 0 && $oldQuantity === 0) {
                $averageCost = (float) $medicine->purchase_price;
            }
            if ($newQuantity === 0) {
                $averageCost = 0;
            }
            $adjustment = StockAdjustment::create([
                'medicine_id' => $medicine->id,
                'adjustment_type' => $data['adjustment_type'],
                'quantity' => $quantity,
                'reason' => $data['reason'],
                'adjustment_date' => $data['adjustment_date'],
                'notes' => $data['notes'] ?? null,
                'user_id' => $userId,
            ]);
            $medicine->forceFill(['stock_quantity' => $newQuantity, 'average_cost' => $averageCost])->save();
            $this->movement($medicine, 'adjustment_'.$data['adjustment_type'], $delta, $oldQuantity, $newQuantity, StockAdjustment::class, $adjustment->id, $data['reason'], $data['adjustment_date']);

            return $adjustment;
        });
    }

    /** @param array<string, mixed> $data */
    public function recordExpense(array $data, int $userId): Expense
    {
        return Expense::create([...$data, 'user_id' => $userId, 'status' => 'completed']);
    }

    private function movement(Medicine $medicine, string $type, int $quantity, int $previous, int $resulting, string $referenceType, int $referenceId, ?string $reason, mixed $date): void
    {
        StockMovement::create([
            'medicine_id' => $medicine->id,
            'movement_type' => $type,
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'previous_stock' => $previous,
            'resulting_stock' => $resulting,
            'reason' => $reason,
            'movement_date' => $date,
        ]);
    }

    /** @param array<int, array<string, mixed>> $items @return array<int, array{medicine_id: int, quantity: int, unit_price: float}> */
    private function normalizeLines(array $items): array
    {
        return array_map(static fn (array $item): array => [
            'medicine_id' => (int) $item['medicine_id'],
            'quantity' => (int) $item['quantity'],
            'unit_price' => (float) $item['unit_price'],
        ], $items);
    }

    private function ensureCompleted(Model $transaction): void
    {
        if ($transaction->status !== 'completed') {
            throw ValidationException::withMessages(['reason' => __('common.already_cancelled')]);
        }
    }

    private function ensureStockFits(Medicine $medicine, int $quantity): void
    {
        if ($quantity < 0) {
            throw ValidationException::withMessages(['quantity' => __('medicines.stock_never_negative')]);
        }
        if ($quantity > 4294967295) {
            throw new RuntimeException('Stock quantity is outside the supported range.');
        }
    }

    private function transactionNumber(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
    }

    private function moneyCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function centsValue(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
