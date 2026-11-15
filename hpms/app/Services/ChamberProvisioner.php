<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Chamber;
use App\Models\ExpenseCategory;
use App\Models\MedicineCategory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ChamberProvisioner
{
    /** @var array<int, string> */
    public const MEDICINE_CATEGORIES = ['Mother Tincture', 'Dilution', 'Globules', 'Tablets', 'Biochemic', 'Ointment', 'Other'];

    /** @var array<int, string> */
    public const EXPENSE_CATEGORIES = ['Chamber Rent', 'Electricity', 'Water', 'Internet', 'Transportation', 'Stationery', 'Cleaning', 'Maintenance', 'Other'];

    /** @param array{name: string, email: string, designation: string, password: string} $data */
    public function createOwner(array $data, string $chamberName): User
    {
        return DB::transaction(function () use ($data, $chamberName): User {
            $chamber = Chamber::query()->create(['name' => $chamberName]);

            $this->ensureDefaults($chamber, $data['name']);

            return $this->createUser($data, UserRole::Owner, $chamber->id);
        });
    }

    /** @param array{name: string, email: string, designation: string, password: string} $data */
    public function createStaff(Chamber $chamber, array $data): User
    {
        return $this->createUser($data, UserRole::Staff, $chamber->id);
    }

    /** @param array{name: string, email: string, designation: string, password: string} $data */
    public function createAdmin(array $data): User
    {
        return $this->createUser($data, UserRole::Admin, null);
    }

    public function ensureDefaults(Chamber $chamber, ?string $ownerName = null): void
    {
        $settings = [
            'chamber_name' => $chamber->name,
            'owner_name' => $ownerName ?? 'Chamber Owner',
            'currency' => 'BDT',
            'currency_symbol' => '৳',
            'timezone' => 'Asia/Dhaka',
            'low_stock_default' => '5',
            'report_default_days' => '30',
        ];

        foreach ($settings as $key => $value) {
            Setting::query()
                ->withoutGlobalScope('chamber')
                ->firstOrCreate(
                    ['chamber_id' => $chamber->id, 'key' => $key],
                    ['value' => $value],
                );
        }

        foreach (self::MEDICINE_CATEGORIES as $name) {
            MedicineCategory::query()
                ->withoutGlobalScope('chamber')
                ->firstOrCreate(
                    ['chamber_id' => $chamber->id, 'name' => $name],
                    ['is_active' => true],
                );
        }

        foreach (self::EXPENSE_CATEGORIES as $name) {
            ExpenseCategory::query()
                ->withoutGlobalScope('chamber')
                ->firstOrCreate(
                    ['chamber_id' => $chamber->id, 'name' => $name],
                    ['is_active' => true],
                );
        }
    }

    /** @param array{name: string, email: string, designation: string, password: string} $data */
    private function createUser(array $data, UserRole $role, ?int $chamberId): User
    {
        return User::query()->create([
            'chamber_id' => $chamberId,
            'role' => $role,
            'name' => $data['name'],
            'designation' => $data['designation'],
            'email' => $data['email'],
            'email_verified_at' => now(),
            'password' => Hash::make($data['password']),
        ]);
    }
}
