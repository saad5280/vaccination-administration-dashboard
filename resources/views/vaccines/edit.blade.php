@extends('layouts.portal')
@section('title', 'Edit vaccine')
@section('crumb', 'Vaccine catalogue / Edit')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">CLINICAL CONFIGURATION</p><h1>Edit vaccine</h1><p class="muted">Update {{ $vaccine->name }}’s catalogue information.</p></div><a class="back-link" href="{{ route('vaccines.show', $vaccine) }}" wire:navigate>← Back to vaccine</a></div>
    <div class="form-card"><form method="POST" action="{{ route('vaccines.update', $vaccine) }}" class="form-stack">@csrf @method('PUT') @include('vaccines._fields')<div class="form-actions"><a class="button secondary" href="{{ route('vaccines.show', $vaccine) }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Save changes</button></div></form></div>
@endsection
