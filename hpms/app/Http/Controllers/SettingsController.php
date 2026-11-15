<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $settings = Setting::query()->pluck('value', 'key')->all();

        return view('settings.index', compact('settings'));
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
        ]);
        foreach ($data as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }
        $request->user()->update(['name' => $data['owner_name']]);

        return back()->with('success', 'Settings saved.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', 'min:12']]);
        $request->user()->update(['password' => Hash::make($data['password'])]);
        $request->session()->regenerate();

        return back()->with('success', 'Password changed successfully.');
    }
}
