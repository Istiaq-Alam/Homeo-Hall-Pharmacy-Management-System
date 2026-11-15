<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Chamber;
use App\Models\SecurityLog;
use App\Models\User;
use App\Services\ChamberProvisioner;
use App\Support\ChamberContext;
use App\Support\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminAccountController extends Controller
{
    public function store(Request $request, ChamberProvisioner $chambers): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(array_map(fn (UserRole $role): string => $role->value, UserRole::cases()))],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'designation' => ['required', 'string', 'max:100'],
            'password' => ['required', 'confirmed', 'min:12'],
            'chamber_id' => ['nullable', 'integer', Rule::requiredIf($request->input('role') === UserRole::Staff->value), Rule::exists('chambers', 'id')],
            'chamber_name' => ['nullable', 'string', 'max:150', Rule::requiredIf($request->input('role') === UserRole::Owner->value)],
        ]);

        $role = UserRole::from($data['role']);
        $user = match ($role) {
            UserRole::Admin => $chambers->createAdmin($data),
            UserRole::Owner => $chambers->createOwner($data, trim($data['chamber_name'])),
            UserRole::Staff => $chambers->createStaff(Chamber::query()->findOrFail($data['chamber_id']), $data),
        };

        $message = __('settings.account_created', ['role' => $role->label(), 'email' => $user->email]);
        SecurityLog::record(SecurityLog::ACCOUNT_CREATED, $message, $request->user());

        return redirect()->route('admin.index')->with('success', $message);
    }

    public function storeStaff(Request $request, ChamberProvisioner $chambers): RedirectResponse
    {
        $owner = $request->user();
        abort_unless($owner->isOwner() && $owner->chamber_id !== null, 403, 'Only a chamber owner can add staff.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'designation' => ['required', 'string', 'max:100'],
            'password' => ['required', 'confirmed', 'min:12'],
        ]);

        $staff = $chambers->createStaff($owner->chamber, $data);
        $message = __('settings.staff_created', ['email' => $staff->email, 'chamber' => $owner->chamber->name]);
        SecurityLog::record(SecurityLog::ACCOUNT_CREATED, $message, $owner);

        return back()->with('success', $message);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin->isAdmin(), 403, 'Only a system admin can delete this account.');
        abort_if($user->is($admin), 403, 'You cannot delete the account you are currently signed in with.');

        $removeChamber = $user->isOwner() && $user->chamber_id !== null;
        $chamberId = $removeChamber ? $user->chamber_id : null;
        $logoPath = $removeChamber
            ? DB::table('settings')->where('chamber_id', $chamberId)->where('key', 'chamber_logo')->value('value')
            : null;
        $avatarPaths = $removeChamber
            ? User::query()->where('chamber_id', $chamberId)->whereNotNull('avatar_path')->pluck('avatar_path')->all()
            : array_filter([$user->avatar_path]);
        $removedAccounts = $removeChamber
            ? User::query()->where('chamber_id', $chamberId)->where('role', '!=', UserRole::Admin)->count()
            : 1;

        DB::transaction(function () use ($user, $chamberId, $removeChamber): void {
            if ($removeChamber) {
                // A chamber cannot exist without its owner, so the owner's chamber
                // is removed together with every account and record that belongs to it.
                $this->deleteChamberData($chamberId);
                User::query()->where('chamber_id', $chamberId)->where('role', '!=', UserRole::Admin)->delete();
                User::query()->where('chamber_id', $chamberId)->update(['chamber_id' => null]);
                Chamber::query()->whereKey($chamberId)->delete();

                return;
            }

            $user->delete();
        });

        if ($removeChamber && app(ChamberContext::class)->id() === $chamberId) {
            app(ChamberContext::class)->forget();
        }

        $this->deleteImage($logoPath, 'branding/');
        foreach ($avatarPaths as $avatarPath) {
            $this->deleteImage($avatarPath, 'avatars/');
        }

        $message = $removeChamber
            ? __('settings.account_deleted_with_chamber', ['email' => $user->email, 'count' => localized_number($removedAccounts - 1)])
            : __('settings.account_deleted', ['email' => $user->email]);

        SecurityLog::record(SecurityLog::ACCOUNT_DELETED, $message, $admin);

        return redirect()->route('admin.index')->with('success', $message);
    }

    public function destroyStaff(Request $request, User $user): RedirectResponse
    {
        $owner = $request->user();
        abort_unless($owner->isOwner() && $owner->chamber_id !== null, 403, 'Only a chamber owner can remove staff.');
        abort_unless($user->isStaff() && $user->chamber_id === $owner->chamber_id, 403, 'That staff account is not part of your chamber.');

        $avatarPath = $user->avatar_path;
        $user->delete();
        $this->deleteImage($avatarPath, 'avatars/');
        $message = __('settings.staff_removed', ['email' => $user->email, 'chamber' => $owner->chamber->name]);
        SecurityLog::record(SecurityLog::ACCOUNT_DELETED, $message, $owner);

        return back()->with('success', $message);
    }

    private function deleteChamberData(?int $chamberId): void
    {
        if ($chamberId === null) {
            return;
        }

        DB::table('sale_items')->where('chamber_id', $chamberId)->delete();
        DB::table('purchase_items')->where('chamber_id', $chamberId)->delete();
        DB::table('stock_movements')->where('chamber_id', $chamberId)->delete();
        DB::table('stock_adjustments')->where('chamber_id', $chamberId)->delete();
        DB::table('sales')->where('chamber_id', $chamberId)->delete();
        DB::table('purchases')->where('chamber_id', $chamberId)->delete();
        DB::table('expenses')->where('chamber_id', $chamberId)->delete();
        DB::table('medicines')->where('chamber_id', $chamberId)->delete();
        DB::table('medicine_categories')->where('chamber_id', $chamberId)->delete();
        DB::table('expense_categories')->where('chamber_id', $chamberId)->delete();
        DB::table('settings')->where('chamber_id', $chamberId)->delete();
    }

    private function deleteImage(?string $path, string $directory): void
    {
        if ($path && str_starts_with($path, $directory)) {
            MediaStorage::disk()->delete($path);
        }
    }
}
