@extends('layouts.portal')
@section('title', 'Add guardian')
@section('crumb', 'Guardians / Add guardian')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">CHILD & GUARDIAN RECORDS</p><h1>Add a guardian</h1><p class="muted">A guardian record can be shared across siblings.</p></div><a class="back-link" href="{{ route('guardians.index') }}" wire:navigate>← Back to guardians</a></div>
    <div class="form-card"><form method="POST" action="{{ route('guardians.store') }}" class="form-stack">@csrf
        @include('guardians._fields', ['guardian' => null, 'prefix' => ''])
        <div class="form-actions"><a class="button secondary" href="{{ route('guardians.index') }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Save guardian</button></div></form></div>
@endsection
