@extends('layouts.portal')
@section('title', 'Edit product presentation')
@section('crumb', 'Vaccine catalogue / Edit product')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">{{ $vaccine->code }}</p><h1>Edit product presentation</h1><p class="muted">Update product details for {{ $vaccine->name }}.</p></div><a class="back-link" href="{{ route('vaccines.show', $vaccine) }}" wire:navigate>← Back to vaccine</a></div>
    <div class="form-card"><form method="POST" action="{{ route('vaccines.products.update', [$vaccine, $product]) }}" class="form-stack">@csrf @method('PUT') @include('vaccines.products._fields')<div class="form-actions"><a class="button secondary" href="{{ route('vaccines.show', $vaccine) }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Save changes</button></div></form></div>
@endsection
