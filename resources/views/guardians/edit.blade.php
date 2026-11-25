@extends('layouts.portal')
@section('title', 'Guardian details')
@section('crumb', 'Guardians / Details')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">CHILD & GUARDIAN RECORDS</p><h1>Guardian details</h1><p class="muted">{{ $guardian->children_count }} linked {{ Illuminate\Support\Str::plural('child', $guardian->children_count) }} · update shared contact details here.</p></div><a class="back-link" href="{{ route('guardians.index') }}" wire:navigate>← Back to guardians</a></div>
    <div class="form-card"><form method="POST" action="{{ route('guardians.update', $guardian) }}" class="form-stack">@csrf @method('PUT')@include('guardians._fields', ['prefix' => ''])<div class="form-actions"><a class="button secondary" href="{{ route('guardians.index') }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Save changes</button></div></form>
        @if ($guardian->children->isNotEmpty())<div class="linked-children"><h2>Linked children</h2><div>@foreach ($guardian->children as $child)<a href="{{ route('children.show', $child) }}" wire:navigate>{{ $child->first_name }} {{ $child->last_name }} <span>{{ $child->registration_id }}</span></a>@endforeach</div></div>@endif
    </div>
@endsection
