<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\MedicineCategory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Mother Tincture', 'Dilution', 'Globules', 'Tablets', 'Biochemic', 'Ointment', 'Other'] as $name) {
            MedicineCategory::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
        }
        foreach (['Chamber Rent', 'Electricity', 'Water', 'Internet', 'Transportation', 'Stationery', 'Cleaning', 'Maintenance', 'Other'] as $name) {
            ExpenseCategory::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
        }
        foreach (['chamber_name' => 'Homeo-Hall', 'currency' => 'BDT', 'currency_symbol' => '৳', 'timezone' => 'Asia/Dhaka', 'low_stock_default' => '5'] as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }

        $email = env('OWNER_EMAIL');
        $password = env('OWNER_PASSWORD');
        if (blank($email) !== blank($password)) {
            throw new RuntimeException('Set both OWNER_EMAIL and OWNER_PASSWORD to provision the owner account.');
        }
        if (filled($email) && ! User::query()->where('email', $email)->exists()) {
            User::create(['name' => env('OWNER_NAME', 'Chamber Owner'), 'email' => $email, 'password' => Hash::make($password)]);
        }
    }
}
