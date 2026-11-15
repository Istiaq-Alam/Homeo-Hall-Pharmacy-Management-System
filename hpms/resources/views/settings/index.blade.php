@extends('layouts.app')
@section('title', 'Settings')
@section('content')
@php($user = auth()->user())
<x-page-heading title="Settings" description="{{ $canManageChamber ? 'Chamber details, defaults, access security, team accounts, and data backup.' : 'Manage the password for your chamber account.' }}" />

@if($user->isAdmin() && ! $hasChamber)
    <div class="notice notice-error" role="alert">Choose a chamber from the <a href="{{ route('admin.index') }}">Administration console</a> before changing chamber settings.</div>
@endif

@if($canManageChamber)
<div class="content-grid">
    <div class="card">
        <div class="section-title"><h2>General settings</h2></div>
        <form method="POST" action="{{ route('settings.update') }}" class="form-stack" enctype="multipart/form-data">@csrf @method('PUT')
            <div class="logo-setting"><div class="logo-preview">@if(! empty($appSettings['chamber_logo_url']))<img src="{{ $appSettings['chamber_logo_url'] }}" alt="Current chamber logo">@else<span class="brand-mark">H</span>@endif</div><div class="logo-copy"><label>Chamber logo<input name="chamber_logo" type="file" accept="image/png,image/jpeg,image/webp"></label><p class="field-help">PNG, JPG, or WebP up to 2 MB. A square image works best. We also build the app icon, browser favicon, and phone splash screens from this logo.</p>@if(! empty($appSettings['chamber_logo_url']))<label class="checkbox-line"><input name="remove_logo" type="checkbox" value="1">Remove the current logo</label>@endif</div></div>
            <div class="form-grid">
                <label>Chamber / application name<input name="chamber_name" value="{{ old('chamber_name', $settings['chamber_name'] ?? $activeChamber?->name ?? 'Homeo-Hall') }}" required maxlength="150"></label>
                <label>Chamber owner name<input name="owner_name" value="{{ old('owner_name', $settings['owner_name'] ?? $user->name) }}" required maxlength="150"></label>
                <label>Currency<select name="currency"><option value="BDT">BDT — Bangladeshi Taka</option></select></label>
                <label>Currency symbol<input name="currency_symbol" value="{{ old('currency_symbol', $settings['currency_symbol'] ?? '৳') }}" required maxlength="5"></label>
                <label>Timezone<select name="timezone"><option value="Asia/Dhaka">Asia/Dhaka</option></select></label>
                <label>Default low-stock level<input name="low_stock_default" type="number" min="0" max="100000" value="{{ old('low_stock_default', $settings['low_stock_default'] ?? 5) }}" required></label>
                <label>Default report period (days)<input name="report_default_days" type="number" min="1" max="3650" value="{{ old('report_default_days', $settings['report_default_days'] ?? 30) }}" required></label>
            </div>
            <p class="report-note">All money is shown in BDT. The date range default applies when you open the custom report without dates.</p>
            <div class="form-subsection"><div><h3>Your profile picture</h3><p class="field-help">This picture identifies you in the dashboard navigation.</p></div><div class="logo-setting"><div class="avatar-preview">@if($user->avatar_url)<img src="{{ $user->avatar_url }}" alt="Current profile picture">@else<span>{{ strtoupper(substr($user->name, 0, 1)) }}</span>@endif</div><div class="logo-copy"><label>Profile picture<input name="owner_avatar" type="file" accept="image/png,image/jpeg,image/webp"></label><p class="field-help">PNG, JPG, or WebP up to 2 MB. A square, well-lit portrait works best.</p>@if($user->avatar_url)<label class="checkbox-line"><input name="remove_avatar" type="checkbox" value="1">Remove the current profile picture</label>@endif</div></div></div>
            <div><button class="button button-primary" type="submit">Save settings</button></div>
        </form>
    </div>

    <div class="card">
        <div class="section-title"><h2>Change password</h2></div>
        <form method="POST" action="{{ route('settings.password') }}" class="form-stack">@csrf @method('PUT')
            <label>Current password<input name="current_password" type="password" autocomplete="current-password" required></label>
            <label>New password<input name="password" type="password" autocomplete="new-password" required minlength="12"></label>
            <label>Confirm new password<input name="password_confirmation" type="password" autocomplete="new-password" required minlength="12"></label>
            <p class="report-note">Use at least 12 characters. Changing your password signs out other sessions where applicable.</p>
            <div><button class="button button-primary" type="submit">Change password</button></div>
        </form>

        <div class="section-title" style="margin-top:28px;padding-top:20px;border-top:1px solid #f0f0f0"><h2>Data backup</h2></div>
        <p style="color:#7a7a7a;font-size:14px">Download the current chamber data as a ZIP of CSV files. Keep it somewhere safe. This export omits login credentials and session data; use the documented MySQL dump for a complete backup and restore.</p>
        <a class="button button-secondary" href="{{ route('settings.backup') }}"><x-icon name="download" /> Download data export</a>
    </div>
</div>
@else
<div class="card" style="max-width:760px">
    <div class="section-title"><h2>Change password</h2></div>
    <form method="POST" action="{{ route('settings.password') }}" class="form-stack">@csrf @method('PUT')
        <label>Current password<input name="current_password" type="password" autocomplete="current-password" required></label>
        <label>New password<input name="password" type="password" autocomplete="new-password" required minlength="12"></label>
        <label>Confirm new password<input name="password_confirmation" type="password" autocomplete="new-password" required minlength="12"></label>
        <p class="report-note">Use at least 12 characters. Changing your password signs out other sessions where applicable.</p>
        <div><button class="button button-primary" type="submit">Change password</button></div>
    </form>
    <div class="form-subsection">
        <h3>Your access</h3>
        <p class="field-help">You are signed in as <strong>{{ $user->roleLabel() }}</strong>@if($activeChamber) for <strong>{{ $activeChamber->name }}</strong>@endif. Chamber settings, backups, and account management are handled by your chamber owner or a system admin.</p>
        @if($user->isAdmin())<a class="button button-secondary" href="{{ route('admin.index') }}">Open administration</a>@endif
    </div>
</div>
@endif

@if($user->isOwner())
<div class="card" style="margin-top:18px">
    <div class="section-title"><h2>Staff accounts</h2><span class="status status-inactive">{{ $staff->count() }} staff</span></div>
    <p class="report-note" style="margin-top:0">Staff work inside your chamber with the inventory, sales, purchases, expenses, and reports. They cannot change chamber settings, download backups, or manage accounts.</p>
    <form method="POST" action="{{ route('settings.staff.store') }}" class="form-stack" style="margin-bottom:24px">@csrf
        <div class="form-grid">
            <label>Full name<input name="name" value="{{ old('name') }}" required maxlength="150" autocomplete="name"></label>
            <label>Email<input name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"></label>
            <label>Designation<input name="designation" value="{{ old('designation') }}" required maxlength="100" placeholder="e.g. Counter Staff"></label>
            <label>Password<input name="password" type="password" required minlength="12" autocomplete="new-password"></label>
            <label>Confirm password<input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></label>
        </div>
        <p class="report-note">Use at least 12 characters. The staff account is assigned to {{ $activeChamber?->name ?? 'your chamber' }} and can sign in immediately.</p>
        <div><button class="button button-primary" type="submit">Create staff account</button></div>
    </form>

    @if($staff->isEmpty())
        <div class="empty-state"><strong>No staff yet</strong>Create an account when someone joins your chamber.</div>
    @else
        <div class="table-wrap is-card-wrap"><table class="table is-card-table"><thead><tr><th>Name</th><th>Designation</th><th>Email</th><th>Actions</th></tr></thead><tbody>
            @foreach($staff as $member)
                <tr>
                    <td data-label="Name"><strong>{{ $member->name }}</strong></td>
                    <td data-label="Designation">{{ $member->designation ?: '—' }}</td>
                    <td data-label="Email">{{ $member->email }}</td>
                    <td data-label="Actions"><form method="POST" action="{{ route('settings.staff.destroy', $member) }}" onsubmit="return confirmAction('Remove this staff account from your chamber? Their account will be deleted.')">@csrf @method('DELETE')<button class="button button-danger button-small" type="submit" aria-label="Remove {{ $member->name }}">Remove</button></form></td>
                </tr>
            @endforeach
        </tbody></table></div>
    @endif
</div>
@elseif($user->isAdmin())
<div class="card" style="margin-top:18px">
    <div class="section-title"><h2>Administration</h2></div>
    <p class="field-help" style="margin-bottom:16px">Create chamber owners, add staff to any chamber, manage system admins, and switch the chamber you are currently managing.</p>
    <a class="button button-secondary" href="{{ route('admin.index') }}">Open administration console</a>
</div>
@endif
@endsection
