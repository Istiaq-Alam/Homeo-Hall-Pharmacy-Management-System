<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $businessTables = [
        'medicine_categories',
        'medicines',
        'purchases',
        'purchase_items',
        'sales',
        'sale_items',
        'expense_categories',
        'expenses',
        'stock_adjustments',
        'stock_movements',
        'settings',
    ];

    public function up(): void
    {
        Schema::create('chambers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('designation', 100)->nullable()->after('name');
            $table->foreignId('chamber_id')->nullable()->after('id')->index()->constrained()->nullOnDelete();
        });

        foreach ($this->businessTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('chamber_id')->nullable()->after('id')->index()->constrained()->nullOnDelete();
            });
        }

        if (DB::table('users')->exists() || DB::table('settings')->exists()) {
            $name = DB::table('settings')->where('key', 'chamber_name')->value('value') ?: 'Homeo-Hall';
            $chamberId = DB::table('chambers')->insertGetId([
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($this->businessTables as $tableName) {
                DB::table($tableName)->whereNull('chamber_id')->update(['chamber_id' => $chamberId]);
            }

            DB::table('users')->whereNull('chamber_id')->update([
                'chamber_id' => $chamberId,
                'designation' => DB::raw("COALESCE(designation, 'Chamber Owner')"),
            ]);
        }

        Schema::table('medicine_categories', function (Blueprint $table): void {
            $table->dropUnique('medicine_categories_name_unique');
            $table->unique(['chamber_id', 'name']);
        });

        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->dropUnique('expense_categories_name_unique');
            $table->unique(['chamber_id', 'name']);
        });

        Schema::table('medicines', function (Blueprint $table): void {
            $table->dropUnique('medicines_identity_unique');
            $table->unique(['chamber_id', 'name', 'potency', 'form', 'unit'], 'medicines_identity_unique');
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropUnique('purchases_purchase_number_unique');
            $table->unique(['chamber_id', 'purchase_number']);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropUnique('sales_sale_number_unique');
            $table->unique(['chamber_id', 'sale_number']);
        });

        Schema::table('settings', function (Blueprint $table): void {
            $table->dropUnique('settings_key_unique');
            $table->unique(['chamber_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('medicine_categories', function (Blueprint $table): void {
            $table->dropUnique(['chamber_id', 'name']);
            $table->unique('name');
        });

        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->dropUnique(['chamber_id', 'name']);
            $table->unique('name');
        });

        Schema::table('medicines', function (Blueprint $table): void {
            $table->dropUnique('medicines_identity_unique');
            $table->unique(['name', 'potency', 'form', 'unit'], 'medicines_identity_unique');
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropUnique(['chamber_id', 'purchase_number']);
            $table->unique('purchase_number');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropUnique(['chamber_id', 'sale_number']);
            $table->unique('sale_number');
        });

        Schema::table('settings', function (Blueprint $table): void {
            $table->dropUnique(['chamber_id', 'key']);
            $table->unique('key');
        });

        foreach (array_reverse($this->businessTables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['chamber_id']);
                $table->dropIndex(['chamber_id']);
                $table->dropColumn('chamber_id');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['chamber_id']);
            $table->dropIndex(['chamber_id']);
            $table->dropColumn(['chamber_id', 'designation']);
        });

        Schema::dropIfExists('chambers');
    }
};
