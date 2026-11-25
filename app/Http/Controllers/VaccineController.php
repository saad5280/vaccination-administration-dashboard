<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVaccineRequest;
use App\Http\Requests\UpdateVaccineRequest;
use App\Models\AuditLog;
use App\Models\Vaccine;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VaccineController extends Controller
{
    public function index(): View
    {
        return view('vaccines.index');
    }

    public function create(): View
    {
        return view('vaccines.create', ['vaccine' => null]);
    }

    public function store(StoreVaccineRequest $request): RedirectResponse
    {
        $vaccine = Vaccine::query()->create($request->validated());
        AuditLog::record($request, 'vaccine.created', $vaccine, null, $vaccine->only(['code', 'name', 'target_disease', 'is_active']));

        return redirect()->route('vaccines.show', $vaccine)->with('status', 'Vaccine added to the catalogue.');
    }

    public function show(Vaccine $vaccine): View
    {
        return view('vaccines.show', [
            'vaccine' => $vaccine->load(['products' => fn ($query) => $query->orderByDesc('is_active')->orderBy('product_name')->orderBy('id')]),
            'scheduleReferenceCount' => $vaccine->scheduleDoses()->count(),
        ]);
    }

    public function edit(Vaccine $vaccine): View
    {
        return view('vaccines.edit', ['vaccine' => $vaccine]);
    }

    public function update(UpdateVaccineRequest $request, Vaccine $vaccine): RedirectResponse
    {
        $data = $request->validated();
        $before = $vaccine->only(array_keys($data));
        $identityFields = ['code', 'name', 'target_disease'];

        abort_if(
            $vaccine->scheduleDoses()->exists() && collect($identityFields)->contains(fn (string $field): bool => $vaccine->{$field} !== $data[$field]),
            422,
            'A vaccine referenced by a schedule cannot have its identifying details changed. Deactivate it and add a new catalogue item instead.',
        );

        $vaccine->update($data);
        AuditLog::record($request, 'vaccine.updated', $vaccine, $before, $vaccine->only(array_keys($before)));

        return redirect()->route('vaccines.show', $vaccine)->with('status', 'Vaccine details updated.');
    }
}
