<div>
    <div class="page-heading">
        <div><p class="eyebrow">CHILD & GUARDIAN RECORDS</p><h1>Children</h1><p class="muted">Search registered children and open their profiles.</p></div>
        @can('children.manage')<a class="button primary" href="{{ route('children.create') }}" wire:navigate>＋ Register child</a>@endcan
    </div>
    <form wire:submit="applyFilters" class="filter-card">
        <div class="filter-search"><label for="search">Search</label><input id="search" wire:model.live.debounce.300ms="search" placeholder="Name, registration ID or guardian phone"></div>
        <div><label for="status">Status</label><select id="status" wire:model.live="status"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        <div><label for="locality">Locality</label><select id="locality" wire:model.live="locality"><option value="">All localities</option>@foreach ($localities as $locality)<option value="{{ $locality }}">{{ $locality }}</option>@endforeach</select></div>
        <div><label for="centre_id">Centre</label><select id="centre_id" wire:model.live="centreId"><option value="">All assigned centres</option>@foreach ($centres as $centre)<option value="{{ $centre->id }}">{{ $centre->name }}</option>@endforeach</select></div>
        <div class="filter-actions"><button class="button primary" type="submit">Apply filters</button><button class="clear-filter" type="button" wire:click="clearFilters">Clear</button></div>
    </form>
    @error('search')<p class="field-error">{{ $message }}</p>@enderror
    @error('status')<p class="field-error">{{ $message }}</p>@enderror
    @error('locality')<p class="field-error">{{ $message }}</p>@enderror
    @error('centreId')<p class="field-error">{{ $message }}</p>@enderror
    <div class="table-card" wire:loading.class="is-loading" wire:target="search,status,locality,centreId,applyFilters,clearFilters">
        <div class="table-toolbar"><div><strong>Registered children</strong><span class="subtle">{{ $children->total() }} records</span></div><span class="table-hint">Names and contact details are confidential</span></div>
        <div class="table-scroll"><table><thead><tr><th>CHILD</th><th>REGISTRATION ID</th><th>DATE OF BIRTH</th><th>PRIMARY GUARDIAN</th><th>CENTRE</th><th>STATUS</th></tr></thead><tbody>
            @forelse ($children as $child)<tr wire:key="child-{{ $child->id }}"><td><a class="table-primary-link" href="{{ route('children.show', $child) }}" wire:navigate>{{ $child->first_name }} {{ $child->last_name }}</a><small class="table-secondary">{{ $child->locality ?: 'Locality not set' }}</small></td><td><span class="mono-tag">{{ $child->registration_id }}</span></td><td>{{ $child->date_of_birth->format('M j, Y') }}</td><td>@php($primaryGuardian = $child->guardians->first(fn ($guardian) => $guardian->pivot->is_primary))@if ($primaryGuardian){{ $primaryGuardian->full_name }}<small class="table-secondary">{{ $primaryGuardian->phone }}</small>@else<span class="muted">None linked</span>@endif</td><td>{{ $child->centre?->name ?? 'Unassigned' }}</td><td><span class="status-pill {{ $child->status === 'active' ? 'status-active' : 'status-inactive' }}"><i></i>{{ ucfirst($child->status) }}</span></td></tr>@empty<tr><td colspan="6" class="empty-state">No child records match these filters.</td></tr>@endforelse
        </tbody></table></div><div class="pagination-wrap">{{ $children->links() }}</div>
    </div>
</div>
