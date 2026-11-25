<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChildDoseDirectiveRequest;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\ChildDoseDirective;
use App\Models\ScheduleDose;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ChildDoseDirectiveController extends Controller
{
    public function store(StoreChildDoseDirectiveRequest $request, Child $child): RedirectResponse
    {
        $data = $request->validated();
        $dose = ScheduleDose::query()->with('schedule')->findOrFail($data['schedule_dose_id']);
        abort_unless($dose->schedule->approval_status === 'approved', 422, 'Clinical directives require an approved schedule dose.');
        DB::transaction(function () use ($request, $child, $dose, $data): void {
            $directive = ChildDoseDirective::query()->where('child_id', $child->id)
                ->where('schedule_dose_id', $dose->id)->lockForUpdate()->first();
            $before = $directive?->only(['status', 'due_date_override', 'reason', 'authorized_by']);
            $attributes = [
                'status' => $data['status'],
                'due_date_override' => $data['due_date_override'] ?? null,
                'reason' => trim($data['reason']),
                'authorized_by' => $request->user()->id,
            ];
            $directive = ChildDoseDirective::query()->updateOrCreate(
                ['child_id' => $child->id, 'schedule_dose_id' => $dose->id],
                $attributes,
            );
            AuditLog::record($request, 'child.dose_directive_updated', $directive, $before, $attributes);
        });

        return redirect()->route('due-register.index')->with('status', 'Clinician-authorized dose tracking directive saved.');
    }
}
