@extends('layouts.portal')
@section('title', 'Add user')
@section('crumb', 'User management / Add user')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">ACCESS CONTROL</p><h1>Add staff user</h1><p class="muted">Create an account and assign the appropriate role.</p></div><a class="back-link" href="{{ route('users.index') }}" wire:navigate>← Back to users</a></div>
    <div class="form-card"><form method="POST" action="{{ route('users.store') }}" class="form-stack">@csrf
        @include('users._fields', ['user' => null, 'selectedRoles' => []])
        <div class="form-actions"><a class="button secondary" href="{{ route('users.index') }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Create user</button></div>
    </form></div>
@endsection
