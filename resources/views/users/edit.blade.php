@extends('layouts.portal')
@section('title', 'Edit user')
@section('crumb', 'User management / Edit user')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">ACCESS CONTROL</p><h1>Edit user</h1><p class="muted">Update {{ $user->name }}’s account details and access.</p></div><a class="back-link" href="{{ route('users.index') }}" wire:navigate>← Back to users</a></div>
    <div class="form-card"><form method="POST" action="{{ route('users.update', $user) }}" class="form-stack">@csrf @method('PUT')
        @include('users._fields', ['selectedRoles' => $user->roles->modelKeys()])
        @if ($user->is_active)<label class="check-line"><input type="checkbox" checked disabled> Account is active (use Deactivate from the user list to suspend access)</label>@else<div class="notice danger">This account is inactive. An administrator can activate it from the user list.</div>@endif
        <div class="form-actions"><a class="button secondary" href="{{ route('users.index') }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Save changes</button></div>
    </form></div>
@endsection
