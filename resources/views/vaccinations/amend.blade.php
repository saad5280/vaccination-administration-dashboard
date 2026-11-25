@extends('layouts.portal')
@section('title', 'Correct vaccination record')
@section('crumb', 'Children / Correct vaccination')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">CONTROLLED AMENDMENT</p><h1>Correct vaccination record</h1><p class="muted">{{ $child->first_name }} {{ $child->last_name }} · {{ $record->vaccine->name }} · {{ $record->administered_at->format('M j, Y') }}</p></div><a class="back-link" href="{{ route('children.vaccinations.index', $child) }}" wire:navigate>← Back to history</a></div>
    <div class="clinical-callout"><strong>The original record will be retained</strong><span>Saving creates a replacement entry, marks the original as superseded, records your reason, and reconciles the linked stock movement.</span></div>
    <div class="form-card"><form method="POST" action="{{ route('children.vaccinations.amend.store', [$child, $record]) }}" class="form-stack">@csrf @include('vaccinations._fields')<div><label for="correction_reason">Reason for correction</label><textarea id="correction_reason" name="correction_reason" rows="3" minlength="5" maxlength="1000" required>{{ old('correction_reason') }}</textarea></div><div class="form-actions"><a class="button secondary" href="{{ route('children.vaccinations.index', $child) }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Save corrected record</button></div></form></div>
@endsection
