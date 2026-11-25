<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Child;
use App\Models\Guardian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChildGuardianController extends Controller
{
    public function store(Request $request, Child $child): RedirectResponse
    {
        $data = $request->validate([
            'guardian_id' => ['required', 'integer', 'exists:guardians,id'],
            'relationship' => ['required', 'string', 'max:50'],
            'is_primary' => ['sometimes', 'boolean'],
            'consent_status' => ['required', Rule::in(['granted', 'declined', 'unknown'])],
        ]);
        $guardian = Guardian::query()->whereKey($data['guardian_id'])
            ->where(fn ($query) => $query->whereDoesntHave('children')->orWhereHas('children', fn ($children) => $children->accessibleTo($request->user())))
            ->firstOrFail();
        abort_if($child->guardians()->whereKey($guardian->id)->exists(), 422, 'This guardian is already linked to the child.');

        DB::transaction(function () use ($child, $data, $guardian, $request): void {
            $child = Child::query()->lockForUpdate()->findOrFail($child->id);

            if ($request->boolean('is_primary')) {
                DB::table('child_guardian')->where('child_id', $child->id)->update(['is_primary' => false]);
            }

            $child->guardians()->attach($guardian->id, [
                'relationship' => $data['relationship'],
                'is_primary' => $request->boolean('is_primary'),
                'consent_status' => $data['consent_status'],
                'consent_recorded_at' => now(),
                'consent_recorded_by' => $request->user()->id,
            ]);
            AuditLog::record($request, 'child.guardian_linked', $child, null, [
                'guardian_id' => $guardian->id,
                'relationship' => $data['relationship'],
                'is_primary' => $request->boolean('is_primary'),
                'consent_status' => $data['consent_status'],
            ]);
        });

        return redirect()->route('children.show', $child)->with('status', 'Guardian linked to child.');
    }

    public function destroy(Request $request, Child $child, Guardian $guardian): RedirectResponse
    {
        abort_unless($child->guardians()->whereKey($guardian->id)->exists(), 404);
        abort_if($child->guardians()->count() <= 1, 422, 'A child profile must retain at least one linked guardian.');

        DB::transaction(function () use ($child, $guardian, $request): void {
            $pivot = DB::table('child_guardian')->where('child_id', $child->id)->where('guardian_id', $guardian->id)->first();
            $child->guardians()->detach($guardian->id);

            if ($pivot?->is_primary) {
                $replacement = $child->guardians()->orderBy('guardians.full_name')->first();

                if ($replacement) {
                    $child->guardians()->updateExistingPivot($replacement->id, ['is_primary' => true]);
                }
            }

            AuditLog::record($request, 'child.guardian_unlinked', $child, [
                'guardian_id' => $guardian->id,
                'is_primary' => (bool) ($pivot?->is_primary),
            ], null);
        });

        return redirect()->route('children.show', $child)->with('status', 'Guardian unlinked from child.');
    }

    public function updateConsent(Request $request, Child $child, Guardian $guardian): RedirectResponse
    {
        $data = $request->validate([
            'consent_status' => ['required', Rule::in(['granted', 'declined', 'unknown'])],
        ]);
        abort_unless($child->guardians()->whereKey($guardian->id)->exists(), 404);

        DB::transaction(function () use ($request, $child, $guardian, $data): void {
            $pivot = DB::table('child_guardian')->where('child_id', $child->id)->where('guardian_id', $guardian->id)->lockForUpdate()->first();
            $before = ['consent_status' => $pivot->consent_status, 'consent_recorded_at' => $pivot->consent_recorded_at, 'consent_recorded_by' => $pivot->consent_recorded_by];
            $after = ['consent_status' => $data['consent_status'], 'consent_recorded_at' => now()->toDateTimeString(), 'consent_recorded_by' => $request->user()->id, 'updated_at' => now()->toDateTimeString()];
            DB::table('child_guardian')->where('child_id', $child->id)->where('guardian_id', $guardian->id)->update($after);
            AuditLog::record($request, 'child.guardian_consent_updated', $child, ['guardian_id' => $guardian->id, ...$before], ['guardian_id' => $guardian->id, ...$after]);
        });

        return redirect()->route('children.show', $child)->with('status', 'Guardian contact consent updated.');
    }
}
