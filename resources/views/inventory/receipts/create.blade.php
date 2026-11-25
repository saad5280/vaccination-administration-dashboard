@extends('layouts.portal')
@section('title', 'Receive stock')
@section('crumb', 'Inventory / Receive stock')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">STOCK RECEIPT</p><h1>Receive vaccine stock</h1><p class="muted">A batch and its receipt ledger entry are saved together.</p></div><a class="button secondary" href="{{ route('inventory.index') }}" wire:navigate>← Inventory</a></div>
    <form class="form-card" method="POST" action="{{ route('inventory.receipts.store') }}">@csrf
        <div class="form-grid"><div><label for="vaccine_product_id">Vaccine product / presentation</label><select id="vaccine_product_id" name="vaccine_product_id" required><option value="">Choose product</option>@foreach ($products as $product)<option value="{{ $product->id }}">{{ $product->vaccine->name }} · {{ $product->product_name ?: $product->manufacturer ?: 'Product' }} · {{ $product->stock_unit }}</option>@endforeach</select></div><div><label for="centre_id">Receiving centre</label><select id="centre_id" name="centre_id" required><option value="">Choose centre</option>@foreach ($centres as $centre)<option value="{{ $centre->id }}">{{ $centre->name }}</option>@endforeach</select></div></div>
        <div class="form-grid"><div><label for="batch_number">Batch / lot number</label><input id="batch_number" name="batch_number" maxlength="100" required></div><div><label for="supplier">Supplier</label><input id="supplier" name="supplier" maxlength="255"></div></div>
        <div class="form-grid"><div><label for="received_at">Received date</label><input id="received_at" name="received_at" type="date" max="{{ today()->toDateString() }}" value="{{ today()->toDateString() }}" required></div><div><label for="expires_at">Expiry date</label><input id="expires_at" name="expires_at" type="date" min="{{ today()->addDay()->toDateString() }}" required></div></div>
        <div class="form-grid"><div><label for="quantity_received">Quantity received</label><input id="quantity_received" name="quantity_received" type="number" min="0.001" step="0.001" required><span class="field-hint">Enter the quantity in the selected product's stock unit.</span></div><div><label for="storage_location">Storage location</label><input id="storage_location" name="storage_location" maxlength="255"></div></div>
        <div class="form-actions"><a class="button secondary" href="{{ route('inventory.index') }}" wire:navigate>Cancel</a><button class="button primary" type="submit">Receive batch</button></div>
    </form>
@endsection
