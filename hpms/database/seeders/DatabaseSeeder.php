<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Chamber;
use App\Models\User;
use App\Services\ChamberProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(ChamberProvisioner $chambers): void
    {
        $email = env('OWNER_EMAIL');
        $password = env('OWNER_PASSWORD');
        if (blank($email) !== blank($password)) {
            throw new RuntimeException('Set both OWNER_EMAIL and OWNER_PASSWORD to provision the owner account.');
        }

        $ownerName = env('OWNER_NAME', 'Chamber Owner');
        $chamber = Chamber::query()->orderBy('id')->first()
            ?? Chamber::query()->create(['name' => env('CHAMBER_NAME', 'Homeo-Hall')]);

        if (filled($email)) {
            $admin = User::query()->firstOrNew(['email' => $email]);
            $admin->fill([
                'chamber_id' => $admin->chamber_id ?? $chamber->id,
                'role' => UserRole::Admin,
                'name' => $ownerName,
                'designation' => 'System Administrator',
                'password' => $admin->exists ? $admin->password : Hash::make($password),
            ])->save();
        }

        $chambers->ensureDefaults($chamber, $ownerName);
    }
}
