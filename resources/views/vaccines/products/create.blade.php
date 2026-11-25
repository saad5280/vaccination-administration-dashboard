@extends('layouts.portal')
@section('title', 'Add product presentation')
@section('crumb', 'Vaccine catalogue / Add product')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">{{ $vaccine->code }}</p><h1>Add product presentation</h1><p class="muted">Describe a specific manufacturer and product form for {{ $vaccine->name }}.</p></div><a class="back-link" href="{{ route('vaccines.show', $vaccine) }}" wire:navigate>← Back to vaccine</a></div>
    <div class="form-card"><form method="POST" action="{{ route('vaccines.products.store', $vaccine) }}" class="form-stack">@csrf @include('vaccines.products._fields')<div class="form-actions"><a class="button secondary" href="{{ route('vaccines.show', $vaccine) }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Save product</button></div></form></div>
@endsection
