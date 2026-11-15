@extends('layouts.app')
@section('title', 'Administration')
@section('content')
<x-page-heading title="Admin dashboard" description="System-wide chamber performance and account administration.">
    <x-slot:action><a class="button button-secondary" href="#admin-profile">Admin profile</a></x-slot:action>
</x-page-heading>

<div class="dashboard-hero admin-dashboard-hero">
    <div>
        <p class="hero-eyebrow">Portfolio overview · {{ $monthLabel }} to date</p>
        <h2 class="hero-title">Every chamber, at a glance.</h2>
        <p>Track sales performance, operating costs, inventory activity, and account access across your entire network.</p>
        <div class="hero-actions"><a class="button button-primary" href="#chamber-performance">View chamber performance</a><a class="button hero-outline" href="#create-account">Create an account</a></div>
    </div>
    <div class="hero-metric"><small>Total sales revenue</small><strong>৳{{ number_format((float) $overview['sales_revenue'], 2) }}</strong><span>{{ number_format((int) $overview['sales_count']) }} completed sales this month</span></div>
</div>

<div class="stat-grid admin-stat-grid">
    <x-stat-card label="Chambers" :value="number_format($overview['chambers'])" note="All active chambers" />
    <x-stat-card label="Accounts" :value="number_format($overview['accounts'])" note="Admins, owners, and staff" />
    <x-stat-card label="Sales revenue" :value="'৳'.number_format((float) $overview['sales_revenue'], 2)" :note="number_format((int) $overview['sales_count']).' completed sales · month to date'" />
    <x-stat-card label="Estimated net profit" :value="'৳'.number_format((float) $overview['net_profit'], 2)" note="Gross profit less operating expenses" />
</div>

<div class="card admin-performance-card" id="chamber-performance">
    <div class="section-title"><div><h2>Chamber performance</h2><p class="field-help">Sales, purchases, expenses, and estimated operating profit · {{ $monthLabel }} to date</p></div><span class="status status-completed">{{ $chambers->count() }} chambers</span></div>
    <div class="table-wrap is-card-wrap"><table class="table is-card-table admin-performance-table"><caption class="sr-only">Chamber performance for the current month</caption><thead><tr><th scope="col">Chamber</th><th scope="col">Owner · accounts</th><th scope="col" class="numeric">Sales</th><th scope="col" class="numeric">Revenue</th><th scope="col" class="numeric">Purchases</th><th scope="col" class="numeric">Expenses</th><th scope="col" class="numeric">Est. net profit</th><th scope="col">Inventory</th><th scope="col">Action</th></tr></thead><tbody>
        @forelse($chambers as $chamber)
            @php($owner = $chamber->users->first(fn ($account) => $account->isOwner()))
            <tr>
                <td data-label="Chamber"><strong>{{ $chamber->name }}</strong>@if($activeChamber?->is($chamber)) <span class="status status-completed">Managing</span>@endif</td>
                <td data-label="Owner · accounts">{{ $owner?->name ?: 'Unassigned' }}<br><small>{{ $chamber->users_count }} {{ \Illuminate\Support\Str::plural('account', $chamber->users_count) }}</small></td>
                <td data-label="Sales" class="numeric">{{ number_format($chamber->monthly_sales_count) }}<br><small>{{ number_format($chamber->monthly_units_sold) }} units</small></td>
                <td data-label="Revenue" class="numeric">৳{{ number_format($chamber->monthly_sales_revenue, 2) }}</td>
                <td data-label="Purchases" class="numeric">৳{{ number_format($chamber->monthly_purchases_total, 2) }}<br><small>{{ number_format($chamber->monthly_purchases_count) }} transactions</small></td>
                <td data-label="Expenses" class="numeric">৳{{ number_format($chamber->monthly_expenses_total, 2) }}<br><small>{{ number_format($chamber->monthly_expenses_count) }} transactions</small></td>
                <td data-label="Est. net profit" class="numeric"><strong>৳{{ number_format($chamber->monthly_net_profit, 2) }}</strong></td>
                <td data-label="Inventory">{{ number_format($chamber->medicine_count) }} medicines<br><small>{{ number_format($chamber->stock_units) }} units in stock</small></td>
                <td data-label="Action">
                    @if($activeChamber?->is($chamber))
                        <span class="status status-completed">Open</span>
                    @else
                        <form method="POST" action="{{ route('admin.chambers.activate', $chamber) }}">@csrf<button class="button button-secondary button-small" type="submit" aria-label="Manage {{ $chamber->name }}">Open</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9" data-label="">No chambers yet. Create a chamber owner to get started.</td></tr>
        @endforelse
    </tbody></table></div>
</div>

<div class="content-grid admin-management-grid">
    <div class="card" id="create-account">
        <div class="section-title"><h2>Create an account</h2></div>
        <p class="report-note" style="margin-top:0">Admins manage the whole system. Chamber owners get a new chamber. Staff are assigned to an existing chamber by an owner or admin.</p>
        <form method="POST" action="{{ route('admin.accounts.store') }}" class="form-stack" x-data="{ role: '{{ old('role', 'owner') }}', submitting: false }" @submit="if (submitting) { $event.preventDefault(); } else { submitting = true; }">@csrf
            <div class="form-grid">
                <label>Full name<input name="name" value="{{ old('name') }}" required maxlength="150" autocomplete="name"></label>
                <label>Email<input name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"></label>
                <label>Designation<input name="designation" value="{{ old('designation') }}" required maxlength="100" placeholder="e.g. Chamber Administrator"></label>
                <label>Role<select name="role" x-model="role" required><option value="admin">Admin — system-wide</option><option value="owner">Chamber Owner — new chamber</option><option value="staff">Staff — assigned chamber</option></select></label>
                <label x-show="role === 'owner'" x-cloak>New chamber name<input name="chamber_name" value="{{ old('chamber_name') }}" maxlength="150" placeholder="e.g. Oyshi Homeo Hall"></label>
                <label x-show="role === 'staff'" x-cloak>Assign to chamber<select name="chamber_id"><option value="">Choose a chamber</option>@foreach($chambers as $chamber)<option value="{{ $chamber->id }}" @selected(old('chamber_id') == $chamber->id)>{{ $chamber->name }}</option>@endforeach</select></label>
                <label>Password<input name="password" type="password" required minlength="12" autocomplete="new-password"></label>
                <label>Confirm password<input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></label>
            </div>
            <p class="report-note">Use at least 12 characters. Owners receive their own empty chamber with default categories and settings.</p>
            <p class="sr-only" role="status" x-text="submitting ? 'Creating the account…' : ''"></p>
            <div><button class="button button-primary" type="submit" x-bind:disabled="submitting" x-bind:aria-busy="submitting"><x-spinner x-show="submitting" x-cloak /><span x-text="submitting ? 'Creating account…' : 'Create account'">Create account</span></button></div>
        </form>
    </div>

    <div class="card" id="admin-profile">
        @php($admin = auth()->user())
        <div class="section-title"><div><h2>Admin profile & credentials</h2><p class="field-help">Update your own login, password, and profile image.</p></div></div>
        <form method="POST" action="{{ route('admin.profile.update') }}" class="form-stack" enctype="multipart/form-data" x-data="{ submitting: false }" @submit="if (submitting) { $event.preventDefault(); } else { submitting = true; }">@csrf @method('PUT')
            <div class="admin-profile-photo-row"><div class="avatar-preview admin-profile-avatar">@if($admin->avatar_url)<img src="{{ $admin->avatar_url }}" alt="Current Admin profile image">@else<span>{{ strtoupper(substr($admin->name, 0, 1)) }}</span>@endif</div><div class="logo-copy"><label>Profile image<input name="profile_image" type="file" accept="image/png,image/jpeg,image/webp"></label><p class="field-help">PNG, JPG, or WebP up to 2 MB.</p>@if($admin->avatar_url)<label class="checkbox-line"><input name="remove_profile_image" type="checkbox" value="1">Remove current image</label>@endif</div></div>
            <div class="form-grid">
                <label>Full name<input name="name" value="{{ old('name', $admin->name) }}" required maxlength="150" autocomplete="name"></label>
                <label>Email<input name="email" type="email" value="{{ old('email', $admin->email) }}" required maxlength="255" autocomplete="email"></label>
                <label>Designation<input name="designation" value="{{ old('designation', $admin->designation) }}" maxlength="100" placeholder="System Administrator"></label>
                <label>Current password <span class="field-help">Required to change email or password</span><input name="current_password" type="password" autocomplete="current-password"></label>
                <label>New password <span class="field-help">Leave blank to keep current password</span><input name="password" type="password" minlength="12" autocomplete="new-password"></label>
                <label>Confirm new password<input name="password_confirmation" type="password" minlength="12" autocomplete="new-password"></label>
            </div>
            <p class="sr-only" role="status" x-text="submitting ? 'Saving your profile…' : ''"></p>
            <div><button class="button button-primary" type="submit" x-bind:disabled="submitting" x-bind:aria-busy="submitting"><x-spinner x-show="submitting" x-cloak /><span x-text="submitting ? 'Saving profile…' : 'Save Admin profile'">Save Admin profile</span></button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="section-title"><h2>All accounts</h2></div>
    <div class="table-wrap is-card-wrap"><table class="table is-card-table"><caption class="sr-only">All administrator, owner and staff accounts</caption><thead><tr><th scope="col">Name</th><th scope="col">Role</th><th scope="col">Email</th><th scope="col">Chamber</th><th scope="col">Actions</th></tr></thead><tbody>
        @foreach($users as $account)
            <tr>
                <td data-label="Name"><strong>{{ $account->name }}</strong>@if($account->is(auth()->user())) <span class="status status-completed">You</span>@endif</td>
                <td data-label="Role"><span class="status {{ $account->isAdmin() ? 'status-completed' : ($account->isOwner() ? 'status-good' : 'status-inactive') }}">{{ $account->roleLabel() }}</span></td>
                <td data-label="Email">{{ $account->email }}</td>
                <td data-label="Chamber">{{ $account->chamber?->name ?: 'System-wide' }}</td>
                <td data-label="Actions">
                    @if($account->is(auth()->user()))
                        <span class="field-help">Current account</span>
                    @else
                        <form method="POST" action="{{ route('admin.accounts.destroy', $account) }}" onsubmit="return confirmAction('Delete this account? Deleting a chamber owner also permanently deletes their chamber, every staff account in it, and all of its records. Every chamber always belongs to an owner. This cannot be undone.')">@csrf @method('DELETE')
                            <button class="button button-danger button-small" type="submit" aria-label="Delete {{ $account->name }}">Delete</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody></table></div>
</div>

<div class="card" id="security-log">
    <div class="section-title"><div><h2>Security log</h2><p class="field-help">Sign-ins, sign-outs, and account or credential changes · newest first</p></div><span class="status status-inactive">Last {{ $securityLogs->count() }} {{ \Illuminate\Support\Str::plural('event', $securityLogs->count()) }}</span></div>
    <div class="table-wrap is-card-wrap"><table class="table is-card-table"><caption class="sr-only">Security events, newest first</caption><thead><tr><th scope="col">Event</th><th scope="col">Detail</th><th scope="col">Account</th><th scope="col">IP address</th><th scope="col">When</th></tr></thead><tbody>
        @forelse($securityLogs as $log)
            <tr>
                <td data-label="Event"><span class="status {{ $log->badgeClass() }}">{{ $log->label() }}</span></td>
                <td data-label="Detail">{{ $log->description }}</td>
                <td data-label="Account">{{ $log->actor_name ?: 'Unknown actor' }}<br><small>{{ $log->actor_email ?: '—' }}</small></td>
                <td data-label="IP address"><small>{{ $log->ip_address ?: '—' }}</small></td>
                <td data-label="When">{{ $log->created_at->format('d M Y, H:i') }}<br><small>{{ $log->created_at->diffForHumans() }}</small></td>
            </tr>
        @empty
            <tr><td colspan="5" data-label="">No security events recorded yet.</td></tr>
        @endforelse
    </tbody></table></div>
</div>
@endsection
