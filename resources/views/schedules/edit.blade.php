@extends('layouts.portal')
@section('title', 'Edit schedule draft')
@section('crumb', 'Immunization schedules / Edit draft')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">SCHEDULE DRAFT</p><h1>Edit {{ $schedule->name }}</h1><p class="muted">Update the version details before approval.</p></div><a class="back-link" href="{{ route('schedules.show', $schedule) }}" wire:navigate>← Back to schedule</a></div>
    <div class="form-card"><form method="POST" action="{{ route('schedules.update', $schedule) }}" class="form-stack">@csrf @method('PUT') @include('schedules._fields')<div class="form-actions"><a class="button secondary" href="{{ route('schedules.show', $schedule) }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Save draft</button></div></form></div>
@endsection
