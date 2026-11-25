<div>
    <div class="page-heading"><div><p class="eyebrow">OPERATIONAL REPORTS</p><h1>Reports and exports</h1><p class="muted">Aggregated programme information for the selected filters.</p></div></div>
    <p class="clinical-note"><span>i</span> Reports contain summary counts only and exclude child names, registration numbers, guardian contacts, and clinical notes.</p>
    <form class="filter-card due-filter" wire:submit="applyFilters">
        <div><label for="report">Report</label><select id="report" wire:model.live="report">@foreach ($reports as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
        <div><label for="date_from">From</label><input id="date_from" wire:model.live="dateFrom" type="date"></div>
        <div><label for="date_to">To</label><input id="date_to" wire:model.live="dateTo" type="date"></div>
        <div><label for="centre_id">Centre</label><select id="centre_id" wire:model.live="centreId"><option value="">All centres</option>@foreach ($centres as $centre)<option value="{{ $centre->id }}">{{ $centre->name }}</option>@endforeach</select></div>
        <div class="filter-actions"><button class="button primary" type="submit">Run report</button><button class="clear-filter" type="button" wire:click="clearFilters">Clear</button></div>
    </form>
    @foreach (['report', 'dateFrom', 'dateTo', 'centreId'] as $field)@error($field)<p class="field-error">{{ $message }}</p>@enderror @endforeach
    <div class="table-card"><div class="table-toolbar"><div><strong>{{ $reports[$type] }}</strong><span class="subtle">{{ count($rows) }} summary rows</span></div><div class="toolbar-actions"><a class="button secondary" href="{{ route('reports.index', [...$filters, 'report' => $type, 'format' => 'csv']) }}">Export CSV</a><a class="button secondary" href="{{ route('reports.index', [...$filters, 'report' => $type, 'format' => 'xlsx']) }}">Export Excel</a></div></div><div class="table-scroll"><table><thead><tr>@foreach ($headers as $header)<th>{{ mb_strtoupper($header) }}</th>@endforeach</tr></thead><tbody>
        @forelse ($rows as $row)<tr>@foreach ($row as $value)<td>{{ $value }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($headers) }}" class="empty-state">No report data matches the selected filters.</td></tr>@endforelse
    </tbody></table></div></div>
</div>
