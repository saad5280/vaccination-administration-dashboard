@extends('layouts.portal')

@section('title', 'Child profile')
@section('crumb', 'Children / Profile')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">CHILD PROFILE</p>
            <h1>{{ $child->first_name }} {{ $child->last_name }}</h1>
            <p class="muted">Registration ID <span class="mono-tag">{{ $child->registration_id }}</span></p>
        </div>
        <div class="heading-actions">
            <a class="button secondary" href="{{ route('children.index') }}" wire:navigate>← Children list</a>
            @can('appointments.manage')<a class="button secondary" href="{{ route('appointments.create', ['child_id' => $child->id]) }}">Book appointment</a>@endcan
            @can('children.manage')<a class="button primary" href="{{ route('children.edit', $child) }}" wire:navigate>Edit profile</a>@endcan
        </div>
    </div>

    <div class="profile-grid">
        <section class="profile-card">
            <div class="profile-card-heading">
                <span class="access-icon peach">♡</span>
                <div><h2>Child information</h2><p>Registration details</p></div>
                <span class="status-pill {{ $child->status === 'active' ? 'status-active' : 'status-inactive' }}"><i></i>{{ ucfirst($child->status) }}</span>
            </div>
            @if ($child->photo_path)<img class="child-photo" src="{{ route('children.photo', $child) }}" alt="Child profile photograph">@endif
            <dl class="detail-grid">
                <div><dt>Date of birth</dt><dd>{{ $child->date_of_birth->format('M j, Y') }}</dd></div>
                <div><dt>Sex</dt><dd>{{ $child->sex ?: 'Not recorded' }}</dd></div>
                <div><dt>Registration date</dt><dd>{{ $child->registered_at->format('M j, Y') }}</dd></div>
                <div><dt>External programme ID</dt><dd>{{ $child->external_registration_number ?: 'Not recorded' }}</dd></div>
                <div><dt>Vaccination centre</dt><dd>{{ $child->centre?->name ?? 'Not assigned' }}</dd></div>
                <div><dt>Area / locality</dt><dd>{{ $child->locality ?: 'Not recorded' }}</dd></div>
                <div><dt>Address</dt><dd>{{ $child->address ?: 'Not recorded' }}</dd></div>
                <div><dt>Photograph</dt><dd>{{ $child->photo_path ? 'Private photo on file' : 'Not provided' }}</dd></div>
            </dl>
            @if ($child->internal_notes)<div class="internal-note"><strong>Internal notes</strong><p>{{ $child->internal_notes }}</p></div>@endif
        </section>

        @can('schedules.assign')
            <section class="profile-card">
                <div class="profile-card-heading"><span class="access-icon teal">◷</span><div><h2>Immunization schedule assignment</h2><p>Schedule selection guides administrative due-date tracking.</p></div></div>
                @if ($scheduleAssignments->isNotEmpty())
                    <p>Current assignment: <strong>{{ $scheduleAssignments->first()->schedule->name }} · {{ $scheduleAssignments->first()->schedule->version }}</strong> — assigned {{ $scheduleAssignments->first()->assigned_at->format('M j, Y') }} by {{ $scheduleAssignments->first()->assignedBy?->name ?? 'former user' }}</p>
                    <details><summary>View assignment history</summary>@foreach ($scheduleAssignments as $assignment)<p>{{ $assignment->schedule->name }} · {{ $assignment->schedule->version }} · {{ $assignment->assigned_at->format('M j, Y') }} — {{ $assignment->reason }}</p>@endforeach</details>
                @else
                    <p class="notice warning">No child-specific schedule is assigned. If more than one approved schedule is active, due tracking will pause for this child until an authorized user assigns one.</p>
                @endif
                @if ($assignableSchedules->isNotEmpty())<form method="POST" action="{{ route('children.schedule-assignment.store', $child) }}" class="form-stack compact-form">@csrf<label for="schedule_id">Approved schedule version</label><select id="schedule_id" name="schedule_id" required>@foreach ($assignableSchedules as $schedule)<option value="{{ $schedule->id }}">{{ $schedule->name }} · {{ $schedule->version }}</option>@endforeach</select><label for="schedule_assignment_reason">Assignment reason</label><textarea id="schedule_assignment_reason" name="reason" required minlength="5" maxlength="2000"></textarea><button class="button secondary" type="submit">Assign schedule version</button></form>@else<p class="field-hint">No approved schedule is effective today.</p>@endif
            </section>
        @endcan

        <section class="profile-card">
            <div class="profile-card-heading">
                <span class="access-icon teal">♧</span>
                <div><h2>Parent / guardians</h2><p>{{ $child->guardians->count() }} linked {{ Illuminate\Support\Str::plural('guardian', $child->guardians->count()) }}</p></div>
            </div>
            @forelse ($child->guardians as $guardian)
                <div class="guardian-card">
                    <div class="guardian-card-top">
                        <div>
                            <strong>{{ $guardian->full_name }}</strong>
                            @if ($guardian->pivot->is_primary)<span class="primary-tag">PRIMARY</span>@endif
                            <small>{{ ucfirst($guardian->pivot->relationship ?: 'Relationship not recorded') }}</small>
                        </div>
                        <span class="consent-pill consent-{{ $guardian->pivot->consent_status }}">Consent {{ $guardian->pivot->consent_status }}</span>
                    </div>
                    <div class="guardian-contact">
                        <span>☎ {{ $guardian->phone }}</span>
                        @if ($guardian->alternate_phone)<span>Alt. {{ $guardian->alternate_phone }}</span>@endif
                        @if ($guardian->email)<span>{{ $guardian->email }}</span>@endif
                    </div>
                    @can('children.manage')
                        <div class="guardian-card-bottom">
                            <a href="{{ route('guardians.edit', $guardian) }}" wire:navigate>View guardian details</a>
                            <form method="POST" action="{{ route('children.guardians.consent.update', [$child, $guardian]) }}" class="inline-form">@csrf @method('PUT')<label class="sr-only" for="consent-{{ $guardian->id }}">Contact consent for {{ $guardian->full_name }}</label><select id="consent-{{ $guardian->id }}" name="consent_status"><option value="unknown" @selected($guardian->pivot->consent_status === 'unknown')>Consent unknown</option><option value="granted" @selected($guardian->pivot->consent_status === 'granted')>Consent granted</option><option value="declined" @selected($guardian->pivot->consent_status === 'declined')>Consent declined</option></select><button class="link-button" type="submit">Update consent</button></form>
                            @if ($child->guardians->count() > 1)
                                <form method="POST" action="{{ route('children.guardians.destroy', [$child, $guardian]) }}" onsubmit="return confirm('Unlink this guardian from the child?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="link-button danger-link">Unlink</button>
                                </form>
                            @endif
                        </div>
                    @endcan
                </div>
            @empty
                <div class="empty-inline">No guardians are linked to this profile.</div>
            @endforelse

            @can('children.manage')
                <details class="link-guardian">
                    <summary>＋ Link another guardian</summary>
                    @if ($availableGuardians->isEmpty())
                        <p class="field-hint">Create a guardian record first, then link it here.</p>
                    @else
                        <form method="POST" action="{{ route('children.guardians.store', $child) }}" class="form-stack compact-form">
                            @csrf
                            <label for="guardian_id">Existing guardian</label>
                            <select id="guardian_id" name="guardian_id" required><option value="">Select a guardian</option>@foreach ($availableGuardians as $guardian)<option value="{{ $guardian->id }}">{{ $guardian->full_name }} · {{ $guardian->phone }}</option>@endforeach</select>
                            <div class="form-grid"><div><label for="relationship">Relationship</label><input id="relationship" name="relationship" required></div><div><label for="consent_status">Contact consent</label><select id="consent_status" name="consent_status" required><option value="unknown">Not recorded</option><option value="granted">Granted</option><option value="declined">Declined</option></select></div></div>
                            <label class="check-line"><input type="checkbox" name="is_primary" value="1"> Set as primary guardian</label>
                            <button class="button primary" type="submit">Link guardian</button>
                        </form>
                    @endif
                </details>
            @endcan
        </section>
    </div>
    @can('vaccinations.view')
        <section class="profile-card vaccination-summary-card">
            <div class="profile-card-heading"><span class="access-icon teal">✓</span><div><h2>Vaccination history</h2><p>{{ $child->completed_vaccination_records_count }} completed record{{ $child->completed_vaccination_records_count === 1 ? '' : 's' }}</p></div><a class="button secondary" href="{{ route('children.vaccinations.index', $child) }}" wire:navigate>View history</a>@can('vaccinations.record')<a class="button primary" href="{{ route('children.vaccinations.create', $child) }}" wire:navigate>Record dose</a>@endcan</div>
        </section>
    @endcan
    <p class="clinical-note"><span>i</span> This profile is for administrative record keeping. Clinical decisions remain with authorized healthcare professionals.</p>
@endsection
