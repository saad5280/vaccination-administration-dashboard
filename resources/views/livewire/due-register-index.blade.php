<div>
    <div class="page-heading"><div><p class="eyebrow">FOLLOW-UP WORKLIST</p><h1>Due and overdue register</h1><p class="muted">Administrative tracking based on the currently effective, approved schedule.</p></div></div>
    <p class="clinical-note"><span>i</span> Due status is an administrative reminder. It does not determine medical eligibility; authorized healthcare professionals make clinical decisions.</p>

    @unless ($hasActiveSchedule)
        <div class="notice warning"><strong>No approved schedule is effective today.</strong> Due dates cannot be calculated until authorized staff configure and approve an effective schedule.</div>
    @endunless
    @if ($hasActiveSchedule && ! $hasRequiredScheduleDoses)
        <div class="notice warning"><strong>No required doses are configured in the effective schedule.</strong> Authorized schedule staff should review the schedule configuration.</div>
    @endif
    @if ($activeScheduleCount > 1)
        <div class="notice warning"><strong>More than one approved schedule is effective today.</strong> The register evaluates each schedule; confirm the schedule applicable to each child with authorized programme staff.</div>
    @endif

    <div class="summary-grid">
        <button class="summary-card" type="button" wire:click="$set('status', 'upcoming')"><span>UPCOMING · 30 DAYS</span><strong>{{ $summary['upcoming'] }}</strong></button>
        <button class="summary-card" type="button" wire:click="$set('status', 'due')"><span>DUE TODAY</span><strong>{{ $summary['due'] }}</strong></button>
        <button class="summary-card" type="button" wire:click="$set('status', 'overdue')"><span>OVERDUE</span><strong>{{ $summary['overdue'] }}</strong></button>
        <button class="summary-card" type="button" wire:click="$set('status', 'unknown_history')"><span>UNKNOWN HISTORY</span><strong>{{ $summary['unknown_history'] }}</strong></button>
    </div>

    <form class="filter-card due-filter" wire:submit="applyFilters">
        <div><label for="status">Status</label><select id="status" wire:model.live="status"><option value="">All tracking states</option>@foreach (['upcoming' => 'Upcoming · 30 days', 'due' => 'Due today', 'overdue' => 'Overdue', 'not_yet_due' => 'Not yet due', 'unknown_history' => 'Unknown history / dates', 'deferred' => 'Deferred', 'contraindicated' => 'Contraindicated', 'not_eligible' => 'Not eligible'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
        <div><label for="vaccine_id">Vaccine</label><select id="vaccine_id" wire:model.live="vaccineId"><option value="">All vaccines</option>@foreach ($vaccines as $vaccine)<option value="{{ $vaccine->id }}">{{ $vaccine->name }}</option>@endforeach</select></div>
        <div><label for="centre_id">Centre</label><select id="centre_id" wire:model.live="centreId"><option value="">All assigned centres</option>@foreach ($centres as $centre)<option value="{{ $centre->id }}">{{ $centre->name }}</option>@endforeach</select></div>
        <div><label for="age_group">Age group</label><select id="age_group" wire:model.live="ageGroup"><option value="">All ages</option>@foreach (['under_1' => 'Under 1 year', '1_4' => '1–4 years', '5_9' => '5–9 years', '10_17' => '10–17 years'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
        <div><label for="locality">Locality</label><input id="locality" wire:model.live.debounce.300ms="locality" placeholder="Search locality"></div>
        <div><label for="due_from">Due from</label><input id="due_from" wire:model.live="dueFrom" type="date"></div>
        <div><label for="due_to">Due to</label><input id="due_to" wire:model.live="dueTo" type="date"></div>
        <div class="filter-actions"><button class="button primary" type="submit">Apply filters</button><button class="clear-filter" type="button" wire:click="clearFilters">Clear</button></div>
    </form>
    @foreach (['status', 'vaccineId', 'centreId', 'locality', 'ageGroup', 'dueFrom', 'dueTo'] as $field)@error($field)<p class="field-error">{{ $message }}</p>@enderror @endforeach

    <div class="table-card"><div class="table-toolbar"><div><strong>Scheduled follow-up items</strong><span class="subtle">{{ $register->total() }} items</span></div></div><div class="table-scroll"><table><thead><tr><th>CHILD</th><th>VACCINE</th><th>DOSE</th><th>CALCULATED DATE</th><th>DAYS OVERDUE</th><th>LAST DOSE</th><th>LOCALITY / CENTRE</th><th>STATUS</th><th>NEXT ACTION</th></tr></thead><tbody>
        @forelse ($register as $row)
            <tr wire:key="due-row-{{ $row['child']->id }}-{{ $row['schedule_dose']?->id ?? 'history' }}">
                <td><a class="table-primary-link" href="{{ route('children.show', $row['child']) }}" wire:navigate>{{ $row['child']->first_name }} {{ $row['child']->last_name }}</a><small class="table-secondary">{{ $row['child']->registration_id }}</small></td>
                <td>{{ $row['vaccine']?->name ?? 'History review' }}@if ($row['schedule'])<small class="table-secondary">{{ $row['schedule']->name }} · {{ $row['schedule']->version }}</small>@endif</td>
                <td>{{ $row['dose_number'] ?? '—' }}</td>
                <td>{{ $row['due_date']?->format('M j, Y') ?? 'Not calculable' }}</td>
                <td>{{ $row['days_overdue'] ?? '—' }}</td>
                <td>@if ($row['last_dose']){{ $row['last_dose']->administered_at->format('M j, Y') }}<small class="table-secondary">{{ $row['last_dose']->vaccine->name }} · dose {{ $row['last_dose']->dose_number }}</small>@else — @endif</td>
                <td>{{ $row['child']->locality ?: '—' }}<small class="table-secondary">{{ $row['child']->centre?->name ?? 'Unassigned centre' }}</small></td>
                <td><span class="status-pill {{ in_array($row['status'], ['overdue', 'unknown_history', 'contraindicated', 'not_eligible']) ? 'status-inactive' : 'status-active' }}"><i></i>{{ str($row['status'])->replace('_', ' ')->title() }}</span>@if ($row['directive'])<small class="table-secondary">{{ $row['directive']->reason }}</small>@endif</td>
                <td>{{ $row['next_action'] }}
                    @can('vaccinations.clinical_authorize')
                        @if ($row['schedule_dose'])<details class="inline-details"><summary>Clinical tracking</summary><form method="POST" action="{{ route('children.dose-directives.store', $row['child']) }}" class="form-stack compact-form">@csrf<input type="hidden" name="schedule_dose_id" value="{{ $row['schedule_dose']->id }}"><label for="directive-status-{{ $row['child']->id }}-{{ $row['schedule_dose']->id }}">Tracking status</label><select id="directive-status-{{ $row['child']->id }}-{{ $row['schedule_dose']->id }}" name="status"><option value="active">Standard schedule</option><option value="deferred">Deferred</option><option value="contraindicated">Contraindicated</option><option value="not_eligible">Not eligible</option></select><label for="directive-date-{{ $row['child']->id }}-{{ $row['schedule_dose']->id }}">Adjusted planned date (optional)</label><input id="directive-date-{{ $row['child']->id }}-{{ $row['schedule_dose']->id }}" name="due_date_override" type="date" value="{{ $row['directive']?->due_date_override?->toDateString() }}"><label for="directive-reason-{{ $row['child']->id }}-{{ $row['schedule_dose']->id }}">Clinical authorization reason</label><textarea id="directive-reason-{{ $row['child']->id }}-{{ $row['schedule_dose']->id }}" name="reason" required minlength="5" maxlength="2000">{{ $row['directive']?->reason }}</textarea><button class="button secondary" type="submit">Save directive</button></form></details>@endif
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty-state">No follow-up items match these filters.</td></tr>
        @endforelse
    </tbody></table></div><div class="pagination-wrap">{{ $register->links() }}</div></div>
    </div>
