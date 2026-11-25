@extends('layouts.portal')
@section('title', 'System settings')
@section('crumb', 'System settings')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">ADMINISTRATION</p><h1>System settings</h1><p class="muted">Configure operational defaults. Clinical schedules are managed and approved separately.</p></div></div>
    <form method="POST" action="{{ route('settings.update') }}" class="form-card form-stack">@csrf @method('PUT')
        <fieldset><legend>Duplicate detection</legend><p class="field-hint">Choose the fields used to warn staff about possible duplicate child records.</p><div class="role-options">@foreach (['name' => 'Child name', 'date_of_birth' => 'Date of birth', 'guardian_phone' => 'Guardian phone number', 'registration_id' => 'External programme registration number'] as $field => $label)<label class="role-option"><input type="checkbox" name="duplicate_detection_fields[]" value="{{ $field }}" @checked(in_array($field, old('duplicate_detection_fields', $settings['duplicate_detection_fields']), true))><span><strong>{{ $label }}</strong></span></label>@endforeach</div></fieldset>
        <div class="form-grid"><div><label for="duplicate_review_minutes">Duplicate review window (minutes)</label><input id="duplicate_review_minutes" name="duplicate_review_minutes" type="number" min="1" max="60" value="{{ old('duplicate_review_minutes', $settings['duplicate_review_minutes']) }}" required></div><div><label for="near_expiry_days">Near-expiry alert window (days)</label><select id="near_expiry_days" name="near_expiry_days">@foreach ([7, 14, 30, 60, 90] as $days)<option value="{{ $days }}" @selected((int) old('near_expiry_days', $settings['near_expiry_days']) === $days)>{{ $days }} days</option>@endforeach</select></div></div>
        <div class="form-actions"><button class="button primary" type="submit">Save settings</button></div>
    </form>
@endsection
