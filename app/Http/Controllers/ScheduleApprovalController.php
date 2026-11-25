<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveScheduleRequest;
use App\Models\AuditLog;
use App\Models\Schedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleApprovalController extends Controller
{
    public function store(ApproveScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $schedule): void {
            $schedule = Schedule::query()->lockForUpdate()->findOrFail($schedule->id);
            abort_unless($schedule->approval_status === 'draft', 422, 'Only a draft schedule can be approved.');

            if (! $schedule->doses()->exists()) {
                throw ValidationException::withMessages(['schedule' => 'Add at least one schedule dose before approval.']);
            }

            $overlappingSchedule = Schedule::query()
                ->where('name', $schedule->name)
                ->where('approval_status', 'approved')
                ->whereKeyNot($schedule->id)
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $data['effective_from']))
                ->when($data['effective_until'] ?? null, fn ($query, string $until) => $query->where(fn ($query) => $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $until)))
                ->exists();

            if ($overlappingSchedule) {
                throw ValidationException::withMessages(['effective_from' => 'The effective period overlaps another approved version of this schedule.']);
            }

            $before = $schedule->only(['approval_status', 'effective_from', 'effective_until', 'approved_by_id', 'approved_by_name', 'approved_at', 'approval_reference']);
            $schedule->update([
                'effective_from' => $data['effective_from'],
                'effective_until' => $data['effective_until'] ?? null,
                'approval_status' => 'approved',
                'approved_by_id' => $request->user()->id,
                'approved_by_name' => $request->user()->name,
                'approved_at' => now(),
                'approval_reference' => $data['approval_reference'],
            ]);
            AuditLog::record($request, 'schedule.approved', $schedule, $before, $schedule->only(array_keys($before)));
        });

        return redirect()->route('schedules.show', $schedule)->with('status', 'Schedule version approved and locked against editing.');
    }
}
