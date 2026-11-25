<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVaccineProductRequest;
use App\Http\Requests\UpdateVaccineProductRequest;
use App\Models\AuditLog;
use App\Models\Vaccine;
use App\Models\VaccineProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VaccineProductController extends Controller
{
    public function create(Vaccine $vaccine): View
    {
        return view('vaccines.products.create', ['vaccine' => $vaccine, 'product' => null]);
    }

    public function store(StoreVaccineProductRequest $request, Vaccine $vaccine): RedirectResponse
    {
        $product = $vaccine->products()->create($request->validated());
        AuditLog::record($request, 'vaccine_product.created', $product, null, $product->only(['vaccine_id', 'manufacturer', 'product_name', 'presentation', 'route', 'stock_unit']));

        return redirect()->route('vaccines.show', $vaccine)->with('status', 'Product presentation added.');
    }

    public function edit(Vaccine $vaccine, VaccineProduct $product): View
    {
        return view('vaccines.products.edit', compact('vaccine', 'product'));
    }

    public function update(UpdateVaccineProductRequest $request, Vaccine $vaccine, VaccineProduct $product): RedirectResponse
    {
        $data = $request->validated();
        $before = $product->only(array_keys($data));
        $inventoryExists = DB::table('stock_batches')->where('vaccine_product_id', $product->id)->exists();
        $identityFields = ['manufacturer', 'product_name', 'presentation', 'route', 'doses_per_vial', 'stock_unit', 'dose_quantity', 'dose_unit'];

        abort_if(
            $inventoryExists && collect($identityFields)->contains(fn (string $field): bool => (string) $product->{$field} !== (string) ($data[$field] ?? null)),
            422,
            'A product with recorded stock cannot have its product or unit details changed. Deactivate it and add a new presentation instead.',
        );

        $product->update($data);
        AuditLog::record($request, 'vaccine_product.updated', $product, $before, $product->only(array_keys($before)));

        return redirect()->route('vaccines.show', $vaccine)->with('status', 'Product presentation updated.');
    }
}
