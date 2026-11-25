@extends('layouts.portal')
@section('title', 'Create role')
@section('crumb', 'Roles & permissions / Create')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">ACCESS CONTROL</p><h1>Create a role</h1><p class="muted">Create a role that grants only the permissions required for its work.</p></div><a class="back-link" href="{{ route('roles.index') }}" wire:navigate>← Back to roles</a></div>
    <div class="form-card"><form method="POST" action="{{ route('roles.store') }}" class="form-stack">@csrf
        <div class="form-grid"><div><label for="label">Role name</label><input id="label" name="label" value="{{ old('label') }}" required></div><div><label for="name">Role key</label><input id="name" name="name" value="{{ old('name') }}" placeholder="e.g. outreach-team" pattern="[a-z][a-z0-9-]*" required><span class="field-hint">Lowercase letters, numbers and hyphens; must start with a letter.</span></div></div>
        <div><label for="description">Description</label><input id="description" name="description" value="{{ old('description') }}" maxlength="1000"></div>
        <fieldset><legend>Permissions</legend><div class="permission-options">@foreach ($permissions as $permission)<label class="permission-option"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, old('permissions', []), false))><span><strong>{{ $permission->label }}</strong><small>{{ $permission->name }}</small></span></label>@endforeach</div></fieldset>
        <div class="form-actions"><a class="button secondary" href="{{ route('roles.index') }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Create role</button></div>
    </form></div>
@endsection
