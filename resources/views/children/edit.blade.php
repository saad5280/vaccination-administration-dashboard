@extends('layouts.portal')

@section('title', 'Edit child profile')
@section('crumb', 'Children / Edit profile')

@section('content')
    <div class="page-heading"><div><p class="eyebrow">CHILD & GUARDIAN RECORDS</p><h1>Edit child profile</h1><p class="muted">Registration ID <span class="mono-tag">{{ $child->registration_id }}</span></p></div><a class="back-link" href="{{ route('children.show', $child) }}" wire:navigate>← Back to profile</a></div>
    <div class="form-card"><form method="POST" action="{{ route('children.update', $child) }}" enctype="multipart/form-data" class="form-stack">@csrf @method('PUT')
        @include('children._fields', ['child' => $child])
        @if ($child->photo_path)<p class="field-hint">A private photograph is on file. Upload a new image to replace it.</p>@endif
        <div class="form-actions"><a class="button secondary" href="{{ route('children.show', $child) }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Save changes</button></div>
    </form></div>
@endsection
