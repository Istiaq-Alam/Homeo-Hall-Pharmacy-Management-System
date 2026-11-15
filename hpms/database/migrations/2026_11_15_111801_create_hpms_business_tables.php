<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('medicine_categories')->restrictOnDelete();
            $table->string('name')->index();
            $table->string('potency')->default('');
            $table->string('form');
            $table->string('unit');
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('average_cost', 12, 4)->default(0);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['name', 'potency', 'form', 'unit'], 'medicines_identity_unique');
            $table->index(['category_id', 'is_active']);
            $table->index(['stock_quantity', 'minimum_stock']);
        });

        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->string('purchase_number')->unique();
            $table->date('purchase_date')->index();
            $table->string('supplier_name')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status')->default('completed')->index();
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
            $table->index('medicine_id');
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->string('sale_number')->unique();
            $table->date('sale_date')->index();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status')->default('completed')->index();
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_selling_price', 12, 2);
            $table->decimal('unit_cost_price', 12, 4);
            $table->decimal('line_total', 12, 2);
            $table->decimal('line_cost_total', 12, 2);
            $table->timestamps();
            $table->index('medicine_id');
        });

        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->date('expense_date')->index();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->string('status')->default('completed')->index();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['expense_category_id', 'expense_date']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->string('adjustment_type');
            $table->unsignedInteger('quantity');
            $table->string('reason');
            $table->date('adjustment_date')->index();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->string('movement_type')->index();
            $table->integer('quantity');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedInteger('previous_stock');
            $table->unsignedInteger('resulting_stock');
            $table->string('reason')->nullable();
            $table->dateTime('movement_date')->index();
            $table->timestamps();
            $table->index(['medicine_id', 'movement_date']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('medicines');
        Schema::dropIfExists('medicine_categories');
    }
};
