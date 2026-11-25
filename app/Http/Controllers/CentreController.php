<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Centre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CentreController extends Controller
{
    public function index(): View
    {
        return view('centres.index');
    }

    public function create(): View
    {
        return view('centres.create', ['centre' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'alpha_dash:ascii', 'unique:centres,code'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'locality' => ['nullable', 'string', 'max:255'],
        ]);
        $data['code'] = mb_strtoupper($data['code']);
        $centre = Centre::query()->create($data);
        AuditLog::record($request, 'centre.created', $centre, null, ['code' => $centre->code, 'name' => $centre->name]);

        return redirect()->route('centres.index')->with('status', 'Vaccination centre created.');
    }

    public function edit(Centre $centre): View
    {
        return view('centres.edit', ['centre' => $centre]);
    }

    public function update(Request $request, Centre $centre): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'alpha_dash:ascii', Rule::unique('centres', 'code')->ignore($centre->id)],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'locality' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);
        $before = $centre->only(['code', 'name', 'phone', 'email', 'address', 'locality', 'is_active']);
        $data['code'] = mb_strtoupper($data['code']);
        $centre->update($data);
        AuditLog::record($request, 'centre.updated', $centre, $before, $centre->only(array_keys($before)));

        return redirect()->route('centres.index')->with('status', 'Vaccination centre updated.');
    }
}
