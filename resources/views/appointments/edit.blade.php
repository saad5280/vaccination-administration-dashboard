@extends('layouts.portal')
@section('title', 'Reschedule appointment')
@section('crumb', 'Appointments / Reschedule')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">APPOINTMENT MANAGEMENT</p><h1>Reschedule appointment</h1><p class="muted">{{ $appointment->child->first_name }} {{ $appointment->child->last_name }} · {{ $appointment->child->registration_id }}</p></div><a class="button secondary" href="{{ route('appointments.index') }}" wire:navigate>← Appointments</a></div>
    <form class="form-card" method="POST" action="{{ route('appointments.update', $appointment) }}">@csrf @method('PUT') @include('appointments._fields')<div class="form-actions"><a class="button secondary" href="{{ route('appointments.index') }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Save new time</button></div></form>
@endsection
