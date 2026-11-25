@extends('layouts.portal')

@section('title', 'Register child')
@section('crumb', 'Children / Register child')

@section('content')
    <div class="page-heading"><div><p class="eyebrow">CHILD & GUARDIAN RECORDS</p><h1>Register a child</h1><p class="muted">Check for a possible existing profile before saving a new record.</p></div><a class="back-link" href="{{ route('children.index') }}" wire:navigate>← Back to children</a></div>
    @if ($centres->isEmpty())<div class="notice warning">No vaccination centres are configured yet. The profile can be saved unassigned. @can('settings.manage')<a href="{{ route('centres.create') }}" wire:navigate>Add a centre</a> first to assign a location.@endcan</div>@endif
    @if ($possibleDuplicates)<div class="duplicate-panel"><div class="duplicate-heading"><span>!</span><div><strong>Possible matching records found</strong><p>Review these profiles before continuing. This is an administrative duplicate warning.</p></div></div><div class="duplicate-list">@foreach ($possibleDuplicates as $match)<a href="{{ route('children.show', $match['id']) }}" class="duplicate-row" wire:navigate><span><strong>{{ $match['name'] }}</strong><small>{{ $match['date_of_birth'] }} · {{ $match['locality'] ?: 'Locality not listed' }}</small></span><span class="mono-tag">{{ $match['registration_id'] }}</span><span class="card-arrow">↗</span></a>@endforeach</div><label class="check-line confirm-review"><input type="checkbox" form="child-registration-form" name="confirm_duplicate_review" value="1" required><span>I reviewed the possible matches and need to create a separate record.</span></label><p class="field-hint">If a profile is the same child, open it and update its guardian or contact details instead.</p></div>@endif
    <div class="form-card"><form id="child-registration-form" method="POST" action="{{ route('children.store') }}" enctype="multipart/form-data" class="form-stack">@csrf
        @if ($duplicateReviewToken)<input type="hidden" name="duplicate_review_token" value="{{ $duplicateReviewToken }}">@endif
        @include('children._fields', ['child' => null])
        <fieldset><legend>Primary parent or guardian</legend><p class="field-hint">A guardian with the same name and phone may be linked to their other children.</p>
            @include('guardians._fields', ['guardian' => null, 'prefix' => 'guardian'])
            <div class="form-grid"><div><label for="guardian_relationship">Relationship to child</label><input id="guardian_relationship" name="guardian[relationship]" value="{{ old('guardian.relationship') }}" placeholder="e.g. Parent, aunt or legal guardian" required></div><div><label for="guardian_consent_status">Contact consent</label><select id="guardian_consent_status" name="guardian[consent_status]" required><option value="unknown" @selected(old('guardian.consent_status', 'unknown') === 'unknown')>Not recorded</option><option value="granted" @selected(old('guardian.consent_status') === 'granted')>Granted</option><option value="declined" @selected(old('guardian.consent_status') === 'declined')>Declined</option></select></div></div>
        </fieldset>
        <div class="form-actions"><a class="button secondary" href="{{ route('children.index') }}" wire:navigate>Cancel</a><button class="button primary" type="submit">{{ $possibleDuplicates ? 'Continue after review' : 'Check matches and save' }}</button></div>
    </form></div>
@endsection
