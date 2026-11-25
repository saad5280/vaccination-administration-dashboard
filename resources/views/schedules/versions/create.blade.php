@extends('layouts.portal')
@section('title', 'Create schedule version')
@section('crumb', 'Immunization schedules / New version')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">{{ $sourceSchedule->name }}</p><h1>Create a new version</h1><p class="muted">Copy the current dose entries to a new draft for review.</p></div><a class="back-link" href="{{ route('schedules.show', $sourceSchedule) }}" wire:navigate>← Back to source version</a></div>
    <div class="clinical-callout"><strong>The new version starts as a draft</strong><span>Its effective dates and approval reference will be empty. Review every copied value against the authorized source before approval.</span></div>
    <div class="form-card"><form method="POST" action="{{ route('schedules.versions.store', $sourceSchedule) }}" class="form-stack">@csrf<div><label for="version">New version label</label><input id="version" name="version" value="{{ old('version') }}" maxlength="40" required><span class="field-hint">Must be unique within {{ $sourceSchedule->name }}.</span></div><div class="form-actions"><a class="button secondary" href="{{ route('schedules.show', $sourceSchedule) }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Create draft version</button></div></form></div>
@endsection
