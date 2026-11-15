@extends('layouts.app')
@section('title', __('settings.title'))
@section('content')
@php($user = auth()->user())
<x-page-heading :title="__('settings.title')" :description="$canManageChamber ? __('settings.manage_description') : __('settings.password_description')" />

@if($user->isAdmin() && ! $hasChamber)
    <div class="notice notice-error" role="alert">{{ __('settings.choose_chamber_notice') }} <a href="{{ route('admin.index') }}">{{ __('settings.administration_console') }}</a></div>
@endif

@if($canManageChamber)
<div class="content-grid">
    <div class="card">
        <div class="section-title"><h2>{{ __('settings.general') }}</h2></div>
        <form method="POST" action="{{ route('settings.update') }}" class="form-stack" enctype="multipart/form-data">@csrf @method('PUT')
            <div class="logo-setting"><div class="logo-preview">@if(! empty($appSettings['chamber_logo_url']))<img src="{{ $appSettings['chamber_logo_url'] }}" alt="{{ __('settings.current_logo_alt') }}">@else<span class="brand-mark">H</span>@endif</div><div class="logo-copy"><label>{{ __('settings.logo') }}<input name="chamber_logo" type="file" accept="image/png,image/jpeg,image/webp"></label><p class="field-help">{{ __('settings.logo_help') }}</p>@if(! empty($appSettings['chamber_logo_url']))<label class="checkbox-line"><input name="remove_logo" type="checkbox" value="1">{{ __('settings.remove_logo') }}</label>@endif</div></div>
            <div class="form-grid">
                <label>{{ __('settings.chamber_name') }}<input name="chamber_name" value="{{ old('chamber_name', $settings['chamber_name'] ?? $activeChamber?->name ?? 'Homeo-Hall') }}" required maxlength="150"></label>
                <label>{{ __('settings.owner_name') }}<input name="owner_name" value="{{ old('owner_name', $settings['owner_name'] ?? $user->name) }}" required maxlength="150"></label>
                <label>{{ __('settings.currency') }}<select name="currency"><option value="BDT">{{ __('settings.currency_bdt') }}</option></select></label>
                <label>{{ __('settings.currency_symbol') }}<input name="currency_symbol" value="{{ old('currency_symbol', $settings['currency_symbol'] ?? '৳') }}" required maxlength="5"></label>
                <label>{{ __('settings.timezone') }}<select name="timezone"><option value="Asia/Dhaka">Asia/Dhaka</option></select></label>
                <label>{{ __('settings.low_stock_default') }}<input name="low_stock_default" type="number" min="0" max="100000" value="{{ old('low_stock_default', $settings['low_stock_default'] ?? 5) }}" required></label>
                <label>{{ __('settings.report_default_days') }}<input name="report_default_days" type="number" min="1" max="3650" value="{{ old('report_default_days', $settings['report_default_days'] ?? 30) }}" required></label>
            </div>
            <p class="report-note">{{ __('settings.money_note') }}</p>
            <div class="form-subsection"><div><h3>{{ __('settings.profile_picture') }}</h3><p class="field-help">{{ __('settings.profile_help') }}</p></div><div class="logo-setting"><div class="avatar-preview">@if($user->avatar_url)<img src="{{ $user->avatar_url }}" alt="{{ __('settings.current_picture_alt') }}">@else<span>{{ strtoupper(substr($user->name, 0, 1)) }}</span>@endif</div><div class="logo-copy"><label>{{ __('settings.profile_picture') }}<input name="owner_avatar" type="file" accept="image/png,image/jpeg,image/webp"></label><p class="field-help">{{ __('settings.profile_upload_help') }}</p>@if($user->avatar_url)<label class="checkbox-line"><input name="remove_avatar" type="checkbox" value="1">{{ __('settings.remove_picture') }}</label>@endif</div></div></div>
            <div><button class="button button-primary" type="submit">{{ __('settings.save') }}</button></div>
        </form>
    </div>

    <div class="card">
        <div class="section-title"><h2>{{ __('settings.change_password') }}</h2></div>
        <form method="POST" action="{{ route('settings.password') }}" class="form-stack">@csrf @method('PUT')
            <label>{{ __('settings.current_password') }}<input name="current_password" type="password" autocomplete="current-password" required></label>
            <label>{{ __('settings.new_password') }}<input name="password" type="password" autocomplete="new-password" required minlength="12"></label>
            <label>{{ __('settings.confirm_password') }}<input name="password_confirmation" type="password" autocomplete="new-password" required minlength="12"></label>
            <p class="report-note">{{ __('settings.password_note') }}</p>
            <div><button class="button button-primary" type="submit">{{ __('settings.change_password') }}</button></div>
        </form>

        <div class="section-title" style="margin-top:28px;padding-top:20px;border-top:1px solid #f0f0f0"><h2>{{ __('settings.backup') }}</h2></div>
        <p style="color:#7a7a7a;font-size:14px">{{ __('settings.backup_copy') }}</p>
        <a class="button button-secondary" href="{{ route('settings.backup') }}"><x-icon name="download" /> {{ __('settings.download_export') }}</a>
    </div>
</div>
@else
<div class="card" style="max-width:760px">
    <div class="section-title"><h2>{{ __('settings.change_password') }}</h2></div>
    <form method="POST" action="{{ route('settings.password') }}" class="form-stack">@csrf @method('PUT')
        <label>{{ __('settings.current_password') }}<input name="current_password" type="password" autocomplete="current-password" required></label>
        <label>{{ __('settings.new_password') }}<input name="password" type="password" autocomplete="new-password" required minlength="12"></label>
        <label>{{ __('settings.confirm_password') }}<input name="password_confirmation" type="password" autocomplete="new-password" required minlength="12"></label>
        <p class="report-note">{{ __('settings.password_note') }}</p>
        <div><button class="button button-primary" type="submit">{{ __('settings.change_password') }}</button></div>
    </form>
    <div class="form-subsection">
        <h3>{{ __('settings.access') }}</h3>
        <p class="field-help">{{ __('settings.access_copy', ['role' => $user->roleLabel(), 'chamber' => $activeChamber?->name ?? __('settings.your_chamber')]) }}</p>
        @if($user->isAdmin())<a class="button button-secondary" href="{{ route('admin.index') }}">{{ __('settings.open_administration') }}</a>@endif
    </div>
</div>
@endif

@if($user->isOwner())
<div class="card" style="margin-top:18px">
    <div class="section-title"><h2>{{ __('settings.staff_accounts') }}</h2><span class="status status-inactive">{{ __('settings.staff_count', ['count' => localized_number($staff->count())]) }}</span></div>
    <p class="report-note" style="margin-top:0">{{ __('settings.staff_copy') }}</p>
    <form method="POST" action="{{ route('settings.staff.store') }}" class="form-stack" style="margin-bottom:24px">@csrf
        <div class="form-grid">
            <label>{{ __('settings.full_name') }}<input name="name" value="{{ old('name') }}" required maxlength="150" autocomplete="name"></label>
            <label>{{ __('common.email') }}<input name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"></label>
            <label>{{ __('settings.designation') }}<input name="designation" value="{{ old('designation') }}" required maxlength="100" placeholder="{{ __('settings.designation_placeholder') }}"></label>
            <label>{{ __('common.password') }}<input name="password" type="password" required minlength="12" autocomplete="new-password"></label>
            <label>{{ __('common.confirm_password') }}<input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></label>
        </div>
        <p class="report-note">{{ __('settings.staff_note', ['chamber' => $activeChamber?->name ?? __('settings.your_chamber')]) }}</p>
        <div><button class="button button-primary" type="submit">{{ __('settings.create_staff') }}</button></div>
    </form>

    @if($staff->isEmpty())
        <div class="empty-state"><strong>{{ __('settings.no_staff_title') }}</strong>{{ __('settings.no_staff_copy') }}</div>
    @else
        <div class="table-wrap is-card-wrap"><table class="table is-card-table"><thead><tr><th>{{ __('common.name') }}</th><th>{{ __('settings.designation') }}</th><th>{{ __('common.email') }}</th><th>{{ __('settings.actions') }}</th></tr></thead><tbody>
            @foreach($staff as $member)
                <tr>
                    <td data-label="{{ __('common.name') }}"><strong>{{ $member->name }}</strong></td>
                    <td data-label="{{ __('settings.designation') }}">{{ $member->designation ?: __('common.none') }}</td>
                    <td data-label="{{ __('common.email') }}">{{ $member->email }}</td>
                    <td data-label="{{ __('settings.actions') }}"><form method="POST" action="{{ route('settings.staff.destroy', $member) }}" onsubmit="return confirmAction(@js(__('settings.remove_staff_confirm')))">@csrf @method('DELETE')<button class="button button-danger button-small" type="submit" aria-label="{{ __('settings.remove_staff_aria', ['name' => $member->name]) }}">{{ __('common.remove') }}</button></form></td>
                </tr>
            @endforeach
        </tbody></table></div>
    @endif
</div>
@elseif($user->isAdmin())
<div class="card" style="margin-top:18px">
    <div class="section-title"><h2>{{ __('settings.administration') }}</h2></div>
    <p class="field-help" style="margin-bottom:16px">{{ __('settings.admin_copy') }}</p>
    <a class="button button-secondary" href="{{ route('admin.index') }}">{{ __('settings.open_console') }}</a>
</div>
@endif
@endsection
