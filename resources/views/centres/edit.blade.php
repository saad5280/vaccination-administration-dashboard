@extends('layouts.portal')
@section('title', 'Edit centre')
@section('crumb', 'Centres / Edit centre')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">SYSTEM SETTINGS</p><h1>Edit vaccination centre</h1><p class="muted">Update {{ $centre->name }}’s location details.</p></div><a class="back-link" href="{{ route('centres.index') }}" wire:navigate>← Back to centres</a></div>
    <div class="form-card"><form method="POST" action="{{ route('centres.update', $centre) }}" class="form-stack">@csrf @method('PUT')
        @include('centres._fields')
        <div class="form-actions"><a class="button secondary" href="{{ route('centres.index') }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Save changes</button></div></form></div>
@endsection
