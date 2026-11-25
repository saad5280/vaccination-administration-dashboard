<div>
    <div class="page-heading">
        <div><p class="eyebrow">PROGRAMME OVERVIEW</p><h1>Good day, {{ explode(' ', auth()->user()->name)[0] }}</h1><p class="muted">Operational activity for your vaccination programme.</p></div>
        <div class="today-chip"><span class="today-dot"></span> {{ now()->format('D, M j') }}</div>
    </div>

    <form class="filter-card due-filter" wire:submit="applyFilters">
        <div><label for="date_from">Activity from</label><input id="date_from" type="date" wire:model="dateFrom"></div>
        <div><label for="date_to">Activity to</label><input id="date_to" type="date" wire:model="dateTo"></div>
        <div><label for="centre_id">Centre</label><select id="centre_id" wire:model="centreId"><option value="">All centres</option>@foreach ($centres as $centre)<option value="{{ $centre->id }}">{{ $centre->name }}</option>@endforeach</select></div>
        <div><label for="vaccine_id">Vaccine</label><select id="vaccine_id" wire:model="vaccineId"><option value="">All vaccines</option>@foreach ($vaccines as $vaccine)<option value="{{ $vaccine->id }}">{{ $vaccine->name }}</option>@endforeach</select></div>
        <div><label for="age_group">Age group</label><select id="age_group" wire:model="ageGroup"><option value="">All ages</option>@foreach (['under_1' => 'Under 1 year', '1_4' => '1–4 years', '5_9' => '5–9 years', '10_17' => '10–17 years'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
        <div><label for="status">Tracking status</label><select id="status" wire:model="status"><option value="">All statuses</option>@foreach (['upcoming' => 'Upcoming', 'due' => 'Due today', 'overdue' => 'Overdue', 'unknown_history' => 'Unknown history'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
        <div class="filter-actions"><button class="button primary" type="submit">Apply filters</button><button class="clear-filter" type="button" wire:click="clearFilters">Clear</button></div>
    </form>

    <div class="section-heading"><div><h2>Service summary</h2><p>Counts reflect your selected centre, activity dates, age group, vaccine, and tracking status where applicable.</p></div></div>
    <div class="summary-grid">
        @can('children.view')<a class="summary-card" href="{{ route('children.index') }}" wire:navigate><span>REGISTERED CHILDREN</span><strong>{{ number_format($stats['children']) }}</strong></a>@else<div class="summary-card"><span>REGISTERED CHILDREN</span><strong>{{ number_format($stats['children']) }}</strong></div>@endcan
        <div class="summary-card"><span>CHILDREN WITH RECORDED DOSES</span><strong>{{ number_format($stats['vaccinated_children']) }}</strong></div>
        @can('due-register.view')<a class="summary-card" href="{{ route('due-register.index', array_filter(['centre_id' => $filters['centre_id'] ?? null, 'vaccine_id' => $filters['vaccine_id'] ?? null, 'age_group' => $filters['age_group'] ?? null, 'status' => 'upcoming'])) }}"><span>UPCOMING · 30 DAYS</span><strong>{{ number_format($stats['upcoming']) }}</strong></a><a class="summary-card" href="{{ route('due-register.index', array_filter(['centre_id' => $filters['centre_id'] ?? null, 'vaccine_id' => $filters['vaccine_id'] ?? null, 'age_group' => $filters['age_group'] ?? null, 'status' => 'due'])) }}"><span>DUE TODAY</span><strong>{{ number_format($stats['due']) }}</strong></a><a class="summary-card" href="{{ route('due-register.index', array_filter(['centre_id' => $filters['centre_id'] ?? null, 'vaccine_id' => $filters['vaccine_id'] ?? null, 'age_group' => $filters['age_group'] ?? null, 'status' => 'overdue'])) }}"><span>OVERDUE</span><strong>{{ number_format($stats['overdue']) }}</strong></a><a class="summary-card" href="{{ route('due-register.index', ['status' => 'unknown_history']) }}"><span>UNKNOWN HISTORY</span><strong>{{ number_format($stats['unknown_history']) }}</strong></a>@endcan
        @can('appointments.manage')<a class="summary-card" href="{{ route('appointments.index', ['status' => 'booked']) }}"><span>BOOKED APPOINTMENTS</span><strong>{{ number_format($stats['appointments']) }}</strong></a><a class="summary-card" href="{{ route('appointments.index', ['status' => 'missed']) }}"><span>MISSED APPOINTMENTS</span><strong>{{ number_format($stats['missed_appointments']) }}</strong></a>@else<div class="summary-card"><span>BOOKED APPOINTMENTS</span><strong>{{ number_format($stats['appointments']) }}</strong></div>@endcan
        @can('reminders.manage')<a class="summary-card" href="{{ route('reminders.index', ['status' => 'pending']) }}"><span>PENDING REMINDERS</span><strong>{{ number_format($stats['pending_reminders']) }}</strong></a>@else<div class="summary-card"><span>PENDING REMINDERS</span><strong>{{ number_format($stats['pending_reminders']) }}</strong></div>@endcan
        <div class="summary-card"><span>CHILDREN WITH SCHEDULE DOSES TO TRACK</span><strong>{{ number_format($stats['incomplete_records']) }}</strong></div>
    </div>

    @if ($alerts)
        <div class="section-heading"><div><h2>Inventory alerts</h2><p>Active stock balances requiring attention.</p></div>@can('inventory.manage')<a class="text-link" href="{{ route('inventory.index') }}" wire:navigate>Open inventory →</a>@endcan</div>
        <div class="summary-grid"><a class="summary-card" href="{{ route('inventory.index', ['state' => 'low_stock']) }}"><span>LOW OR OUT OF STOCK</span><strong>{{ number_format($alerts['low_stock']) }}</strong></a><a class="summary-card" href="{{ route('inventory.index', ['state' => 'near_expiry', 'near_expiry_days' => $nearExpiryDays]) }}"><span>NEAR EXPIRY · {{ $nearExpiryDays }} DAYS</span><strong>{{ number_format($alerts['near_expiry']) }}</strong></a><a class="summary-card" href="{{ route('inventory.index', ['state' => 'expired']) }}"><span>EXPIRED BATCHES</span><strong>{{ number_format($alerts['expired']) }}</strong></a></div>
    @endif

    @can('vaccinations.view')
        <div class="section-heading"><div><h2>Recent vaccination activity</h2><p>Latest completed dose records.</p></div></div>
        <div class="table-card"><div class="table-scroll"><table><thead><tr><th>CHILD</th><th>VACCINE</th><th>ADMINISTERED</th><th>RECORDED AT</th></tr></thead><tbody>
            @forelse ($recentActivity as $record)<tr><td><a class="table-primary-link" href="{{ route('children.show', $record->child) }}">{{ $record->child->first_name }} {{ $record->child->last_name }}</a><small class="table-secondary">{{ $record->child->registration_id }}</small></td><td>{{ $record->vaccine->name }} · dose {{ $record->dose_number ?? '—' }}</td><td>{{ $record->administered_at->format('M j, Y H:i') }}</td><td>{{ $record->created_at->format('M j, Y H:i') }}</td></tr>
            @empty<tr><td colspan="4" class="empty-state">No completed vaccination records yet.</td></tr>@endforelse
        </tbody></table></div></div>
    @endcan

    @can('reports.view')<div class="section-heading"><div><h2>Reports</h2><p>Review operational totals and export permitted summary data.</p></div><a class="button primary" href="{{ route('reports.index') }}" wire:navigate>Open reports</a></div>@endcan
    <p class="clinical-note"><span>i</span> Due and overdue figures are administrative tracking indicators. Clinical eligibility and decisions remain under authorized healthcare professionals.</p>
</div>
