<div>
    <div class="page-heading"><div><p class="eyebrow">SECURITY AND ACCOUNTABILITY</p><h1>Audit log</h1><p class="muted">Recorded account, record, schedule, appointment, and inventory actions.</p></div></div>
    <form class="filter-card due-filter" wire:submit="applyFilters">
        <div><label for="action">Action</label><input id="action" wire:model.live.debounce.300ms="action" maxlength="100" placeholder="For example: child.updated"></div>
        <div><label for="date_from">From</label><input id="date_from" wire:model.live="dateFrom" type="date"></div>
        <div><label for="date_to">To</label><input id="date_to" wire:model.live="dateTo" type="date"></div>
        <div class="filter-actions"><button class="button primary" type="submit">Apply filters</button><button class="clear-filter" type="button" wire:click="clearFilters">Clear</button></div>
    </form>
    @foreach (['action', 'dateFrom', 'dateTo'] as $field)@error($field)<p class="field-error">{{ $message }}</p>@enderror @endforeach
    <div class="table-card"><div class="table-toolbar"><div><strong>Recorded events</strong><span class="subtle">{{ $logs->total() }} entries</span></div></div><div class="table-scroll"><table><thead><tr><th>TIME</th><th>ACTOR</th><th>ACTION</th><th>AFFECTED RECORD</th><th>IP ADDRESS</th><th>BEFORE / AFTER</th></tr></thead><tbody>
        @forelse ($logs as $log)
            <tr wire:key="audit-event-{{ $log->id }}"><td>{{ $log->occurred_at->format('M j, Y H:i:s') }}</td><td>{{ $log->actor?->name ?? 'System / unavailable account' }}</td><td><span class="mono-tag">{{ $log->action }}</span></td><td>{{ $subjectName($log->auditable_type) }}{{ $log->auditable_id ? ' · '.$log->auditable_id : '' }}</td><td>{{ $log->ip_address ?: '—' }}</td><td>@if ($log->before_values)<details><summary>View change</summary><strong>Before</strong><pre>{{ json_encode($log->before_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre><strong>After</strong><pre>{{ json_encode($log->after_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>@elseif ($log->after_values)<details><summary>View details</summary><pre>{{ json_encode($log->after_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>@else — @endif</td></tr>
        @empty<tr><td colspan="6" class="empty-state">No audit events match these filters.</td></tr>@endforelse
    </tbody></table></div><div class="pagination-wrap">{{ $logs->links() }}</div></div>
    </div>
