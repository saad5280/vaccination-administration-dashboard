@extends('layouts.portal')
@section('title', 'Edit role permissions')
@section('crumb', 'Roles & permissions / Edit')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">ACCESS CONTROL</p><h1>Edit {{ $role->label }}</h1><p class="muted">Select the permissions available to users assigned to this role.</p></div><a class="back-link" href="{{ route('roles.index') }}" wire:navigate>← Back to roles</a></div>
    <div class="form-card"><form method="POST" action="{{ route('roles.update', $role) }}" class="form-stack">@csrf @method('PUT')<fieldset><legend>Permissions</legend><div class="permission-options">@foreach ($permissions as $permission)<label class="permission-option"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains($permission->id))><span><strong>{{ $permission->label }}</strong><small>{{ $permission->name }}</small></span></label>@endforeach</div></fieldset><div class="form-actions"><a class="button secondary" href="{{ route('roles.index') }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Save permissions</button></div></form></div>
@endsection
