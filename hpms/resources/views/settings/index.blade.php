@extends('layouts.app')
@section('title', 'Settings')
@section('content')
<x-page-heading title="Settings" description="Chamber details, defaults, access security, and data backup." />
<div class="content-grid">
<div class="card"><div class="section-title"><h2>General settings</h2></div><form method="POST" action="{{ route('settings.update') }}" class="form-stack">@csrf @method('PUT')
<div class="form-grid"><label>Chamber / application name<input name="chamber_name" value="{{ old('chamber_name', $settings['chamber_name'] ?? 'Homeo-Hall') }}" required maxlength="150"></label><label>Owner name<input name="owner_name" value="{{ old('owner_name', $settings['owner_name'] ?? auth()->user()->name) }}" required maxlength="150"></label><label>Currency<select name="currency"><option value="BDT">BDT — Bangladeshi Taka</option></select></label><label>Currency symbol<input name="currency_symbol" value="{{ old('currency_symbol', $settings['currency_symbol'] ?? '৳') }}" required maxlength="5"></label><label>Timezone<select name="timezone"><option value="Asia/Dhaka">Asia/Dhaka</option></select></label><label>Default low-stock level<input name="low_stock_default" type="number" min="0" max="100000" value="{{ old('low_stock_default', $settings['low_stock_default'] ?? 5) }}" required></label><label>Default report period (days)<input name="report_default_days" type="number" min="1" max="3650" value="{{ old('report_default_days', $settings['report_default_days'] ?? 30) }}" required></label></div>
<p class="report-note">All money is shown in BDT. The date range default applies when you open the custom report without dates.</p>
<div><button class="button button-primary" type="submit">Save settings</button></div></form></div>

<div class="card"><div class="section-title"><h2>Change password</h2></div><form method="POST" action="{{ route('settings.password') }}" class="form-stack">@csrf @method('PUT')
<label>Current password<input name="current_password" type="password" autocomplete="current-password" required></label><label>New password<input name="password" type="password" autocomplete="new-password" required minlength="12"></label><label>Confirm new password<input name="password_confirmation" type="password" autocomplete="new-password" required minlength="12"></label>
<p class="report-note">Use at least 12 characters. Changing your password signs out other sessions where applicable.</p><div><button class="button button-primary" type="submit">Change password</button></div></form>
<div class="section-title" style="margin-top:28px;padding-top:20px;border-top:1px solid #f0f0f0"><h2>Data backup</h2></div><p style="color:#7a7a7a;font-size:14px">Download the current business data as a ZIP of CSV files. Keep it somewhere safe. This export omits login credentials and session data; use the documented MySQL dump for a complete backup and restore.</p><a class="button button-secondary" href="{{ route('settings.backup') }}"><x-icon name="download" /> Download data export</a></div>
</div>
@endsection
