<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Guardian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GuardianController extends Controller
{
    public function index(): View
    {
        return view('guardians.index');
    }

    public function create(): View
    {
        return view('guardians.create', ['guardian' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['phone'] = $this->normalizePhone($data['phone']);
        $data['alternate_phone'] = isset($data['alternate_phone']) ? $this->normalizePhone($data['alternate_phone']) : null;
        $existing = Guardian::query()
            ->whereRaw('LOWER(full_name) = ?', [mb_strtolower(trim($data['full_name']))])
            ->where('phone', $data['phone'])
            ->first();

        if ($existing) {
            return redirect()->route('guardians.edit', $existing)->with('status', 'A guardian with this name and phone is already on file. The existing record was opened.');
        }

        $guardian = DB::transaction(function () use ($data, $request): Guardian {
            $guardian = Guardian::query()->create($data);
            AuditLog::record($request, 'guardian.created', $guardian, null, ['full_name' => $guardian->full_name, 'phone' => $guardian->phone]);

            return $guardian;
        });

        return redirect()->route('guardians.edit', $guardian)->with('status', 'Guardian record created.');
    }

    public function edit(Request $request, Guardian $guardian): View
    {
        AuditLog::record($request, 'guardian.profile_viewed', $guardian);

        return view('guardians.edit', ['guardian' => $guardian->loadCount('children')]);
    }

    public function update(Request $request, Guardian $guardian): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['phone'] = $this->normalizePhone($data['phone']);
        $data['alternate_phone'] = isset($data['alternate_phone']) ? $this->normalizePhone($data['alternate_phone']) : null;

        DB::transaction(function () use ($data, $guardian, $request): void {
            $guardian = Guardian::query()->lockForUpdate()->findOrFail($guardian->id);
            $before = $guardian->only(['full_name', 'phone', 'alternate_phone', 'email', 'address', 'preferred_contact_method', 'preferred_language']);
            $guardian->update($data);
            AuditLog::record($request, 'guardian.updated', $guardian, $before, $guardian->only(array_keys($before)));
        });

        return redirect()->route('guardians.edit', $guardian)->with('status', 'Guardian record updated.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^\+?[0-9 ().-]{7,40}$/'],
            'alternate_phone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9 ().-]{7,40}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'preferred_contact_method' => ['required', Rule::in(['phone', 'sms', 'email', 'in_app'])],
            'preferred_language' => ['nullable', 'string', 'max:20'],
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';
    }
}
