@extends('layouts.portal')
@section('title', $vaccine->name)
@section('crumb', 'Vaccine catalogue / '.$vaccine->code)
@section('content')
    <div class="page-heading"><div><p class="eyebrow">VACCINE CATALOGUE</p><h1>{{ $vaccine->name }}</h1><p class="muted">{{ $vaccine->target_disease ?: 'No prevention target recorded' }} · <span class="mono-tag">{{ $vaccine->code }}</span></p></div><div class="heading-actions"><a class="button secondary" href="{{ route('vaccines.index') }}" wire:navigate>Back to catalogue</a>@can('catalogue.manage')<a class="button primary" href="{{ route('vaccines.edit', $vaccine) }}" wire:navigate>Edit vaccine</a>@endcan</div></div>
    <div class="profile-grid"><section class="profile-card"><div class="profile-card-heading"><span class="access-icon">✚</span><div><h2>Catalogue details</h2><p>Vaccine identity and current availability</p></div><span class="status-pill {{ $vaccine->is_active ? 'status-active' : 'status-inactive' }}"><i></i>{{ $vaccine->is_active ? 'Active' : 'Inactive' }}</span></div><dl class="detail-grid"><div><dt>Code</dt><dd>{{ $vaccine->code }}</dd></div><div><dt>Prevention target</dt><dd>{{ $vaccine->target_disease ?: 'Not recorded' }}</dd></div><div><dt>Schedule dose references</dt><dd>{{ $scheduleReferenceCount }}</dd></div><div><dt>Last updated</dt><dd>{{ $vaccine->updated_at->format('M j, Y') }}</dd></div></dl>@if ($vaccine->notes)<div class="internal-note"><strong>Notes</strong><p>{{ $vaccine->notes }}</p></div>@endif</section>
        <section class="profile-card"><div class="profile-card-heading"><span class="access-icon">▤</span><div><h2>Product presentations</h2><p>Manufacturer, product, and unit details</p></div>@can('catalogue.manage')<a class="button secondary" href="{{ route('vaccines.products.create', $vaccine) }}" wire:navigate>＋ Add product</a>@endcan</div>
            @forelse ($vaccine->products as $product)
                <article class="catalogue-product">
                    <div class="guardian-card-top"><div><strong>{{ $product->product_name ?: 'Product name not recorded' }}</strong><small>{{ $product->manufacturer ?: 'Manufacturer not recorded' }} · {{ $product->presentation ?: 'Presentation not recorded' }}</small></div><span class="status-pill {{ $product->is_active ? 'status-active' : 'status-inactive' }}"><i></i>{{ $product->is_active ? 'Active' : 'Inactive' }}</span></div>
                    <div class="guardian-contact"><span>Route: {{ $product->route ?: 'Not recorded' }}</span><span>Stock unit: {{ $product->stock_unit }}</span>
                        @if ($product->dose_quantity)
                            <span>Unit amount: {{ $product->dose_quantity }} {{ $product->dose_unit }}</span>
                        @endif
                        @if ($product->doses_per_vial)
                            <span>Doses per vial: {{ $product->doses_per_vial }}</span>
                        @endif
                    </div>
                    @if ($product->storage_requirements)
                        <p class="product-storage">Storage: {{ $product->storage_requirements }}</p>
                    @endif
                    @can('catalogue.manage')
                        <div class="guardian-card-bottom"><span></span><a href="{{ route('vaccines.products.edit', [$vaccine, $product]) }}" wire:navigate>Edit product</a></div>
                    @endcan
                </article>
            @empty
                <div class="empty-inline">No product presentations recorded.</div>
            @endforelse
        </section>
    </div>
@endsection
