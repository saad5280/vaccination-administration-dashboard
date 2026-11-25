@extends('layouts.portal')
@section('title', 'Record vaccination')
@section('crumb', 'Children / Record vaccination')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">{{ $child->registration_id }}</p><h1>Record vaccination</h1><p class="muted">{{ $child->first_name }} {{ $child->last_name }}</p></div><a class="back-link" href="{{ route('children.vaccinations.index', $child) }}" wire:navigate>← Back to vaccination history</a></div>
    <div class="form-card"><form method="POST" action="{{ route('children.vaccinations.store', $child) }}" class="form-stack">@csrf @include('vaccinations._fields')<div class="form-actions"><a class="button secondary" href="{{ route('children.vaccinations.index', $child) }}" wire:navigate>Cancel</a><button class="button primary" type="submit" @disabled($batches->isEmpty())>Save vaccination record</button></div>@if ($batches->isEmpty())<span class="field-hint">No active batches have stock available. Ask an inventory officer to receive valid stock before recording administration.</span>@endif</form></div>
@endsection
