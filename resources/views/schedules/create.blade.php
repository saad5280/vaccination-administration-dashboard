@extends('layouts.portal')
@section('title', 'New schedule')
@section('crumb', 'Immunization schedules / New')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">SCHEDULE CONFIGURATION</p><h1>New schedule draft</h1><p class="muted">Create a named version, then enter the values provided by your clinical authority.</p></div><a class="back-link" href="{{ route('schedules.index') }}" wire:navigate>← Back to schedules</a></div>
    <div class="clinical-callout"><strong>Do not use unapproved values</strong><span>Recommended ages, intervals, and eligibility rules are not prefilled. The schedule remains a draft until authorized approval is recorded.</span></div>
    <div class="form-card"><form method="POST" action="{{ route('schedules.store') }}" class="form-stack">@csrf @include('schedules._fields')<div class="form-actions"><a class="button secondary" href="{{ route('schedules.index') }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Create draft</button></div></form></div>
@endsection
