@extends('layouts.portal')
@section('title', 'Book appointment')
@section('crumb', 'Appointments / Book')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">APPOINTMENT MANAGEMENT</p><h1>Book appointment</h1><p class="muted">Choose a child, date, centre, and optional vaccinator.</p></div><a class="button secondary" href="{{ route('appointments.index') }}" wire:navigate>← Appointments</a></div>
    <form class="form-card" method="POST" action="{{ route('appointments.store') }}">@csrf @include('appointments._fields', ['appointment' => null])<div class="form-actions"><a class="button secondary" href="{{ route('appointments.index') }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Book appointment</button></div></form>
@endsection
