@extends('layouts.app')
@section('title', __('admin.title'))
@section('content')
<x-page-heading :title="__('admin.dashboard_title')" :description="__('admin.description')">
    <x-slot:action><a class="button button-secondary" href="#admin-profile">{{ __('admin.profile') }}</a></x-slot:action>
</x-page-heading>

<div class="dashboard-hero admin-dashboard-hero">
    <div>
        <p class="hero-eyebrow">{{ __('admin.portfolio', ['month' => $monthLabel]) }}</p>
        <h2 class="hero-title">{{ __('admin.hero_title') }}</h2>
        <p>{{ __('admin.hero_copy') }}</p>
        <div class="hero-actions"><a class="button button-primary" href="#chamber-performance">{{ __('admin.view_performance') }}</a><a class="button hero-outline" href="#create-account">{{ __('admin.create_account') }}</a></div>
    </div>
    <div class="hero-metric"><small>{{ __('admin.total_sales_revenue') }}</small><strong>{{ money($overview['sales_revenue']) }}</strong><span>{{ __('admin.completed_sales', ['count' => localized_number((int) $overview['sales_count'])]) }}</span></div>
</div>

<div class="stat-grid admin-stat-grid">
    <x-stat-card :label="__('admin.chambers')" :value="localized_number($overview['chambers'])" :note="__('admin.all_chambers')" />
    <x-stat-card :label="__('admin.accounts')" :value="localized_number($overview['accounts'])" :note="__('admin.accounts_note')" />
    <x-stat-card :label="__('admin.sales_revenue')" :value="money($overview['sales_revenue'])" :note="__('admin.sales_revenue_note', ['count' => localized_number((int) $overview['sales_count'])])" />
    <x-stat-card :label="__('admin.estimated_net_profit')" :value="money($overview['net_profit'])" :note="__('admin.profit_note')" />
</div>

<div class="card admin-performance-card" id="chamber-performance">
    <div class="section-title"><div><h2>{{ __('admin.performance') }}</h2><p class="field-help">{{ __('admin.performance_note', ['month' => $monthLabel]) }}</p></div><span class="status status-completed">{{ __('admin.chamber_count', ['count' => localized_number($chambers->count())]) }}</span></div>
    <div class="table-wrap is-card-wrap"><table class="table is-card-table admin-performance-table"><caption class="sr-only">{{ __('admin.performance_caption') }}</caption><thead><tr><th scope="col">{{ __('admin.chamber') }}</th><th scope="col">{{ __('admin.owner_accounts') }}</th><th scope="col" class="numeric">{{ __('admin.sales') }}</th><th scope="col" class="numeric">{{ __('admin.revenue') }}</th><th scope="col" class="numeric">{{ __('admin.purchases') }}</th><th scope="col" class="numeric">{{ __('admin.expenses') }}</th><th scope="col" class="numeric">{{ __('admin.est_net_profit') }}</th><th scope="col">{{ __('admin.inventory') }}</th><th scope="col">{{ __('admin.action') }}</th></tr></thead><tbody>
        @forelse($chambers as $chamber)
            @php($owner = $chamber->users->first(fn ($account) => $account->isOwner()))
            <tr>
                <td data-label="{{ __('admin.chamber') }}"><strong>{{ $chamber->name }}</strong>@if($activeChamber?->is($chamber)) <span class="status status-completed">{{ __('admin.managing') }}</span>@endif</td>
                <td data-label="{{ __('admin.owner_accounts') }}">{{ $owner?->name ?: __('admin.unassigned') }}<br><small>{{ $chamber->users_count === 1 ? __('admin.account_singular', ['count' => localized_number($chamber->users_count)]) : __('admin.account_plural', ['count' => localized_number($chamber->users_count)]) }}</small></td>
                <td data-label="{{ __('admin.sales') }}" class="numeric">{{ localized_number($chamber->monthly_sales_count) }}<br><small>{{ __('admin.units', ['count' => localized_number($chamber->monthly_units_sold)]) }}</small></td>
                <td data-label="{{ __('admin.revenue') }}" class="numeric">{{ money($chamber->monthly_sales_revenue) }}</td>
                <td data-label="{{ __('admin.purchases') }}" class="numeric">{{ money($chamber->monthly_purchases_total) }}<br><small>{{ __('admin.transactions', ['count' => localized_number($chamber->monthly_purchases_count)]) }}</small></td>
                <td data-label="{{ __('admin.expenses') }}" class="numeric">{{ money($chamber->monthly_expenses_total) }}<br><small>{{ __('admin.transactions', ['count' => localized_number($chamber->monthly_expenses_count)]) }}</small></td>
                <td data-label="{{ __('admin.est_net_profit') }}" class="numeric"><strong>{{ money($chamber->monthly_net_profit) }}</strong></td>
                <td data-label="{{ __('admin.inventory') }}">{{ __('admin.medicine_count', ['count' => localized_number($chamber->medicine_count)]) }}<br><small>{{ __('admin.units_in_stock', ['count' => localized_number($chamber->stock_units)]) }}</small></td>
                <td data-label="{{ __('admin.action') }}">
                    @if($activeChamber?->is($chamber))
                        <span class="status status-completed">{{ __('admin.open') }}</span>
                    @else
                        <form method="POST" action="{{ route('admin.chambers.activate', $chamber) }}">@csrf<button class="button button-secondary button-small" type="submit" aria-label="{{ __('admin.manage_chamber', ['name' => $chamber->name]) }}">{{ __('admin.open') }}</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9" data-label="">{{ __('admin.no_chambers') }}</td></tr>
        @endforelse
    </tbody></table></div>
</div>

<div class="content-grid admin-management-grid">
    <div class="card" id="create-account">
        <div class="section-title"><h2>{{ __('admin.create_title') }}</h2></div>
        <p class="report-note" style="margin-top:0">{{ __('admin.create_copy') }}</p>
        <form method="POST" action="{{ route('admin.accounts.store') }}" class="form-stack" x-data="{ role: '{{ old('role', 'owner') }}', submitting: false }" @submit="if (submitting) { $event.preventDefault(); } else { submitting = true; }">@csrf
            <div class="form-grid">
                <label>{{ __('admin.full_name') }}<input name="name" value="{{ old('name') }}" required maxlength="150" autocomplete="name"></label>
                <label>{{ __('admin.email') }}<input name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"></label>
                <label>{{ __('admin.designation') }}<input name="designation" value="{{ old('designation') }}" required maxlength="100" placeholder="{{ __('admin.designation_placeholder') }}"></label>
                <label>{{ __('admin.role') }}<select name="role" x-model="role" required><option value="admin">{{ __('admin.role_admin') }}</option><option value="owner">{{ __('admin.role_owner') }}</option><option value="staff">{{ __('admin.role_staff') }}</option></select></label>
                <label x-show="role === 'owner'" x-cloak>{{ __('admin.new_chamber_name') }}<input name="chamber_name" value="{{ old('chamber_name') }}" maxlength="150" placeholder="{{ __('admin.new_chamber_placeholder') }}"></label>
                <label x-show="role === 'staff'" x-cloak>{{ __('admin.assign_chamber') }}<select name="chamber_id"><option value="">{{ __('admin.choose_chamber') }}</option>@foreach($chambers as $chamber)<option value="{{ $chamber->id }}" @selected(old('chamber_id') == $chamber->id)>{{ $chamber->name }}</option>@endforeach</select></label>
                <label>{{ __('admin.password') }}<input name="password" type="password" required minlength="12" autocomplete="new-password"></label>
                <label>{{ __('admin.confirm_password') }}<input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></label>
            </div>
            <p class="report-note">{{ __('admin.password_note') }}</p>
            <p class="sr-only" role="status" x-text="submitting ? @js(__('admin.creating_status')) : ''"></p>
            <div><button class="button button-primary" type="submit" x-bind:disabled="submitting" x-bind:aria-busy="submitting"><x-spinner x-show="submitting" x-cloak /><span x-text="submitting ? @js(__('admin.creating')) : @js(__('admin.create'))">{{ __('admin.create') }}</span></button></div>
        </form>
    </div>

    <div class="card" id="admin-profile">
        @php($admin = auth()->user())
        <div class="section-title"><div><h2>{{ __('admin.profile_title') }}</h2><p class="field-help">{{ __('admin.profile_help') }}</p></div></div>
        <form method="POST" action="{{ route('admin.profile.update') }}" class="form-stack" enctype="multipart/form-data" x-data="{ submitting: false }" @submit="if (submitting) { $event.preventDefault(); } else { submitting = true; }">@csrf @method('PUT')
            <div class="admin-profile-photo-row"><div class="avatar-preview admin-profile-avatar">@if($admin->avatar_url)<img src="{{ $admin->avatar_url }}" alt="{{ __('settings.current_picture_alt') }}">@else<span>{{ strtoupper(substr($admin->name, 0, 1)) }}</span>@endif</div><div class="logo-copy"><label>{{ __('admin.profile_image') }}<input name="profile_image" type="file" accept="image/png,image/jpeg,image/webp"></label><p class="field-help">{{ __('settings.profile_upload_help') }}</p>@if($admin->avatar_url)<label class="checkbox-line"><input name="remove_profile_image" type="checkbox" value="1">{{ __('admin.remove_image') }}</label>@endif</div></div>
            <div class="form-grid">
                <label>{{ __('admin.full_name') }}<input name="name" value="{{ old('name', $admin->name) }}" required maxlength="150" autocomplete="name"></label>
                <label>{{ __('admin.email') }}<input name="email" type="email" value="{{ old('email', $admin->email) }}" required maxlength="255" autocomplete="email"></label>
                <label>{{ __('admin.designation') }}<input name="designation" value="{{ old('designation', $admin->designation) }}" maxlength="100" placeholder="{{ __('admin.designation_placeholder') }}"></label>
                <label>{{ __('common.current_password') }} <span class="field-help">{{ __('admin.current_password_help') }}</span><input name="current_password" type="password" autocomplete="current-password"></label>
                <label>{{ __('common.new_password') }} <span class="field-help">{{ __('admin.new_password_help') }}</span><input name="password" type="password" minlength="12" autocomplete="new-password"></label>
                <label>{{ __('common.confirm_password') }}<input name="password_confirmation" type="password" minlength="12" autocomplete="new-password"></label>
            </div>
            <p class="sr-only" role="status" x-text="submitting ? @js(__('admin.saving_status')) : ''"></p>
            <div><button class="button button-primary" type="submit" x-bind:disabled="submitting" x-bind:aria-busy="submitting"><x-spinner x-show="submitting" x-cloak /><span x-text="submitting ? @js(__('admin.saving')) : @js(__('admin.save_profile'))">{{ __('admin.save_profile') }}</span></button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="section-title"><h2>{{ __('admin.all_accounts') }}</h2></div>
    <div class="table-wrap is-card-wrap"><table class="table is-card-table"><caption class="sr-only">{{ __('admin.all_accounts_caption') }}</caption><thead><tr><th scope="col">{{ __('admin.name') }}</th><th scope="col">{{ __('admin.role') }}</th><th scope="col">{{ __('admin.email_column') }}</th><th scope="col">{{ __('admin.chamber_column') }}</th><th scope="col">{{ __('admin.actions') }}</th></tr></thead><tbody>
        @foreach($users as $account)
            <tr>
                <td data-label="{{ __('admin.name') }}"><strong>{{ $account->name }}</strong>@if($account->is(auth()->user())) <span class="status status-completed">{{ __('admin.you') }}</span>@endif</td>
                <td data-label="{{ __('admin.role') }}"><span class="status {{ $account->isAdmin() ? 'status-completed' : ($account->isOwner() ? 'status-good' : 'status-inactive') }}">{{ $account->roleLabel() }}</span></td>
                <td data-label="{{ __('admin.email_column') }}">{{ $account->email }}</td>
                <td data-label="{{ __('admin.chamber_column') }}">{{ $account->chamber?->name ?: __('admin.system_wide') }}</td>
                <td data-label="{{ __('admin.actions') }}">
                    @if($account->is(auth()->user()))
                        <span class="field-help">{{ __('admin.current_account') }}</span>
                    @else
                        <form method="POST" action="{{ route('admin.accounts.destroy', $account) }}" onsubmit="return confirmAction(@js(__('admin.delete_confirm')))">@csrf @method('DELETE')
                            <button class="button button-danger button-small" type="submit" aria-label="{{ __('admin.delete_account', ['name' => $account->name]) }}">{{ __('admin.delete') }}</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody></table></div>
</div>

<div class="card" id="security-log">
    <div class="section-title"><div><h2>{{ __('admin.security_log') }}</h2><p class="field-help">{{ __('admin.security_log_help') }}</p></div><div class="section-actions"><span class="status status-inactive">{{ $securityLogs->count() === 1 ? __('admin.last_event', ['count' => localized_number($securityLogs->count())]) : __('admin.last_events', ['count' => localized_number($securityLogs->count())]) }}</span>@if($securityLogs->isNotEmpty())<form method="POST" action="{{ route('admin.security-log.clear') }}" onsubmit="return confirmAction(@js(__('admin.clear_log_confirm')))">@csrf @method('DELETE')<button class="button button-danger button-icon" type="submit" aria-label="{{ __('admin.clear_log') }}" title="{{ __('admin.clear_log') }}"><x-icon name="trash" /></button></form>@endif</div></div>
    <div class="table-wrap is-card-wrap"><table class="table is-card-table"><caption class="sr-only">{{ __('admin.security_caption') }}</caption><thead><tr><th scope="col">{{ __('admin.event') }}</th><th scope="col">{{ __('admin.detail') }}</th><th scope="col">{{ __('admin.account') }}</th><th scope="col">{{ __('admin.ip') }}</th><th scope="col">{{ __('admin.when') }}</th></tr></thead><tbody>
        @forelse($securityLogs as $log)
            <tr>
                <td data-label="{{ __('admin.event') }}"><span class="status {{ $log->badgeClass() }}">{{ $log->label() }}</span></td>
                <td data-label="{{ __('admin.detail') }}">{{ $log->description }}</td>
                <td data-label="{{ __('admin.account') }}">{{ $log->actor_name ?: __('admin.unknown_actor') }}<br><small>{{ $log->actor_email ?: __('common.none') }}</small></td>
                <td data-label="{{ __('admin.ip') }}"><small>{{ $log->ip_address ?: __('common.none') }}</small></td>
                <td data-label="{{ __('admin.when') }}">{{ localized_date_time($log->created_at, 'd M Y, H:i') }}<br><small>{{ $log->created_at->diffForHumans() }}</small></td>
            </tr>
        @empty
            <tr><td colspan="5" data-label="">{{ __('admin.no_events') }}</td></tr>
        @endforelse
    </tbody></table></div>
</div>
@endsection
