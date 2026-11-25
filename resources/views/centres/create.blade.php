@extends('layouts.portal')
@section('title', 'Add centre')
@section('crumb', 'Centres / Add centre')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">SYSTEM SETTINGS</p><h1>Add vaccination centre</h1><p class="muted">Add a clinic location before assigning children to it.</p></div><a class="back-link" href="{{ route('centres.index') }}" wire:navigate>← Back to centres</a></div>
    <div class="form-card"><form method="POST" action="{{ route('centres.store') }}" class="form-stack">@csrf
        @include('centres._fields', ['centre' => null])
        <div class="form-actions"><a class="button secondary" href="{{ route('centres.index') }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Save centre</button></div></form></div>
@endsection
