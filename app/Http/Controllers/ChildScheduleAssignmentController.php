<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Child;
use App\Models\ChildScheduleAssignment;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChildScheduleAssignmentController extends Controller
{
    public function store(Request $request, Child $child): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('schedules.assign'), 403);
        $data = $request->validate([
            'schedule_id' => ['required', 'integer', 'exists:schedules,id'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $today = CarbonImmutable::today();
        $schedule = Schedule::query()->whereKey($data['schedule_id'])
            ->where('approval_status', 'approved')
            ->whereDate('effective_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today))
            ->firstOrFail();

        DB::transaction(function () use ($request, $child, $schedule, $data): void {
            $previous = $child->scheduleAssignments()->lockForUpdate()->first();
            $assignment = ChildScheduleAssignment::query()->create([
                'child_id' => $child->id,
                'schedule_id' => $schedule->id,
                'assigned_by' => $request->user()->id,
                'assigned_at' => now(),
                'reason' => trim($data['reason']),
            ]);
            AuditLog::record($request, 'child.schedule_assigned', $child,
                ['schedule_id' => $previous?->schedule_id],
                ['schedule_id' => $schedule->id, 'assignment_id' => $assignment->id, 'reason' => $assignment->reason]);
        });

        return redirect()->route('children.show', $child)->with('status', 'Approved schedule version assigned to child.');
    }
}
