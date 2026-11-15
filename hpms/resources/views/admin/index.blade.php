@extends('layouts.app')
@section('title', 'Administration')
@section('content')
<x-page-heading title="Administration" description="Manage every chamber, owner, staff member, and system administrator.">
    <x-slot:action><a class="button button-secondary" href="{{ route('settings.index') }}">Chamber settings</a></x-slot:action>
</x-page-heading>

@if(session('error'))<div class="notice notice-error" role="alert">{{ session('error') }}</div>@endif

<div class="content-grid">
    <div class="card">
        <div class="section-title"><h2>Create an account</h2></div>
        <p class="report-note" style="margin-top:0">Admins manage the whole system. Chamber owners get a new chamber. Staff are assigned to an existing chamber by an owner or admin.</p>
        <form method="POST" action="{{ route('admin.accounts.store') }}" class="form-stack" x-data="{ role: '{{ old('role', 'owner') }}' }">@csrf
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
            <div><button class="button button-primary" type="submit">Create account</button></div>
        </form>
    </div>

    <div class="card">
        <div class="section-title"><h2>Chambers</h2><span class="status status-inactive">{{ $chambers->count() }} total</span></div>
        <div class="table-wrap is-card-wrap"><table class="table is-card-table"><thead><tr><th>Chamber</th><th>Owner</th><th>Accounts</th><th>Actions</th></tr></thead><tbody>
            @forelse($chambers as $chamber)
                @php($owner = $chamber->users->first(fn ($account) => $account->isOwner()))
                <tr>
                    <td data-label="Chamber"><strong>{{ $chamber->name }}</strong></td>
                    <td data-label="Owner">{{ $owner?->name ?: 'Unassigned' }}</td>
                    <td data-label="Accounts">{{ $chamber->users_count }}</td>
                    <td data-label="Actions">
                        @if($activeChamber?->is($chamber))
                            <span class="status status-completed">Managing</span>
                        @else
                            <form method="POST" action="{{ route('admin.chambers.activate', $chamber) }}">@csrf<button class="button button-secondary button-small" type="submit" aria-label="Manage {{ $chamber->name }}">Open</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" data-label="">No chambers yet. Create a chamber owner to get started.</td></tr>
            @endforelse
        </tbody></table></div>
    </div>
</div>

<div class="card">
    <div class="section-title"><h2>All accounts</h2></div>
    <div class="table-wrap is-card-wrap"><table class="table is-card-table"><thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Chamber</th><th>Actions</th></tr></thead><tbody>
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
                        <form method="POST" action="{{ route('admin.accounts.destroy', $account) }}" onsubmit="return confirmAction('Delete this account? If this is a chamber owner with no remaining accounts, the chamber and all of its data will also be permanently deleted. This cannot be undone.')">@csrf @method('DELETE')
                            <button class="button button-danger button-small" type="submit" aria-label="Delete {{ $account->name }}">Delete</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody></table></div>
</div>
@endsection
