<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Chamber;
use App\Models\Setting;
use App\Models\User;
use App\Support\BrandAssets;
use App\Support\ChamberContext;
use App\Support\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $context = app(ChamberContext::class);
        $hasChamber = $context->id() !== null;
        $canManageChamber = $hasChamber && ! $user->isStaff();
        $settings = $canManageChamber ? Setting::query()->pluck('value', 'key')->all() : [];
        $staff = $user->isOwner() && $user->chamber_id !== null
            ? User::query()->where('chamber_id', $user->chamber_id)->where('role', UserRole::Staff)->orderBy('name')->get()
            : collect();
        $activeChamber = $context->chamber();

        return view('settings.index', compact('settings', 'staff', 'activeChamber', 'canManageChamber', 'hasChamber'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'chamber_name' => ['required', 'string', 'max:150'],
            'owner_name' => ['required', 'string', 'max:150'],
            'currency' => ['required', 'in:BDT'],
            'currency_symbol' => ['required', 'string', 'max:5'],
            'timezone' => ['required', 'in:Asia/Dhaka'],
            'low_stock_default' => ['required', 'integer', 'min:0', 'max:100000'],
            'report_default_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'chamber_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'owner_avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['sometimes', 'boolean'],
        ]);

        $oldLogo = Setting::query()->where('key', 'chamber_logo')->value('value');
        if ($request->hasFile('chamber_logo')) {
            $file = $request->file('chamber_logo');
            $directory = 'branding/'.Str::lower(Str::random(12));
            $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension() ?: 'png');
            $newLogo = $file->storeAs($directory, 'logo.'.$extension, config('filesystems.uploads'));
            if (! $newLogo) {
                return back()->withErrors(['chamber_logo' => __('settings.flash.logo_error')])->withInput();
            }
            Setting::query()->updateOrCreate(['key' => 'chamber_logo'], ['value' => $newLogo]);
            $this->storeBrandAssets($newLogo, $directory);
            BrandAssets::delete($oldLogo);
        } elseif ($request->boolean('remove_logo')) {
            Setting::query()->where('key', 'chamber_logo')->delete();
            Setting::query()->where('key', 'chamber_brand_assets')->delete();
            BrandAssets::delete($oldLogo);
        }

        $user = $request->user();
        $chamberId = app(ChamberContext::class)->id();
        $oldAvatar = $user->avatar_path;
        if ($request->hasFile('owner_avatar')) {
            $newAvatar = $request->file('owner_avatar')->store(MediaStorage::AVATAR_DIRECTORY, config('filesystems.uploads'));
            if (! $newAvatar) {
                return back()->withErrors(['owner_avatar' => __('settings.flash.avatar_error')])->withInput();
            }
            $user->avatar_path = $newAvatar;
            $this->deleteAvatar($oldAvatar);
        } elseif ($request->boolean('remove_avatar')) {
            $user->avatar_path = null;
            $this->deleteAvatar($oldAvatar);
        }

        unset($data['chamber_logo'], $data['remove_logo'], $data['owner_avatar'], $data['remove_avatar']);
        foreach ($data as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }
        if ($user->isOwner()) {
            $user->name = $data['owner_name'];
            $user->save();
        } elseif ($chamberId !== null) {
            User::query()
                ->where('chamber_id', $chamberId)
                ->where('role', UserRole::Owner)
                ->update(['name' => $data['owner_name']]);
        }

        if ($chamberId !== null) {
            Chamber::query()->whereKey($chamberId)->update(['name' => $data['chamber_name']]);
        }

        return back()->with('success', __('settings.flash.saved'));
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', 'min:12']]);
        $request->user()->update(['password' => Hash::make($data['password'])]);
        $request->session()->regenerate();

        return back()->with('success', __('settings.flash.password_changed'));
    }

    private function storeBrandAssets(string $logoPath, string $directory): void
    {
        $source = MediaStorage::localCopy($logoPath);

        if ($source === null) {
            Setting::query()->where('key', 'chamber_brand_assets')->delete();

            return;
        }

        try {
            $assets = BrandAssets::generate($source, $directory);
        } finally {
            MediaStorage::forgetLocalCopy($source);
        }

        if ($assets === null) {
            Setting::query()->where('key', 'chamber_brand_assets')->delete();

            return;
        }

        Setting::query()->updateOrCreate(['key' => 'chamber_brand_assets'], ['value' => json_encode($assets)]);
    }

    private function deleteAvatar(?string $path): void
    {
        if ($path && str_starts_with($path, MediaStorage::AVATAR_DIRECTORY.'/')) {
            MediaStorage::disk()->delete($path);
        }
    }
}
