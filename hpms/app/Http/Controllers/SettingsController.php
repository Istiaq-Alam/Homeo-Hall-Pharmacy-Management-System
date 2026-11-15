<?php

namespace App\Http\Controllers;

use App\Models\Chamber;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $settings = Setting::query()->pluck('value', 'key')->all();
        $admins = User::query()->with('chamber')->orderBy('name')->get();

        return view('settings.index', compact('settings', 'admins'));
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
            $newLogo = $request->file('chamber_logo')->store('branding', 'public');
            if (! $newLogo) {
                return back()->withErrors(['chamber_logo' => 'The logo could not be stored. Please try again.'])->withInput();
            }
            Setting::query()->updateOrCreate(['key' => 'chamber_logo'], ['value' => $newLogo]);
            $this->deleteLogo($oldLogo);
        } elseif ($request->boolean('remove_logo')) {
            Setting::query()->where('key', 'chamber_logo')->delete();
            $this->deleteLogo($oldLogo);
        }

        $user = $request->user();
        $oldAvatar = $user->avatar_path;
        if ($request->hasFile('owner_avatar')) {
            $newAvatar = $request->file('owner_avatar')->store('avatars', 'public');
            if (! $newAvatar) {
                return back()->withErrors(['owner_avatar' => 'The profile picture could not be stored. Please try again.'])->withInput();
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
        $user->name = $data['owner_name'];
        $user->save();

        if ($user->chamber_id) {
            Chamber::query()->whereKey($user->chamber_id)->update(['name' => $data['chamber_name']]);
        }

        return back()->with('success', 'Settings saved.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', 'min:12']]);
        $request->user()->update(['password' => Hash::make($data['password'])]);
        $request->session()->regenerate();

        return back()->with('success', 'Password changed successfully.');
    }

    private function deleteLogo(?string $path): void
    {
        if ($path && str_starts_with($path, 'branding/')) {
            Storage::disk('public')->delete($path);
        }
    }

    private function deleteAvatar(?string $path): void
    {
        if ($path && str_starts_with($path, 'avatars/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
