@extends('layouts.portal')
@section('title', 'Add vaccine')
@section('crumb', 'Vaccine catalogue / Add')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">CLINICAL CONFIGURATION</p><h1>Add vaccine</h1><p class="muted">Enter the product identity supplied by the responsible programme.</p></div><a class="back-link" href="{{ route('vaccines.index') }}" wire:navigate>← Back to catalogue</a></div>
    <div class="clinical-callout"><strong>No clinical schedule is created here</strong><span>Schedule values must come from the responsible health authority or authorized clinical personnel.</span></div>
    <div class="form-card"><form method="POST" action="{{ route('vaccines.store') }}" class="form-stack">@csrf @include('vaccines._fields')<div class="form-actions"><a class="button secondary" href="{{ route('vaccines.index') }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Save vaccine</button></div></form></div>
@endsection
