<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Schedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ScheduleVersionController extends Controller
{
    public function store(Request $request, Schedule $schedule): RedirectResponse
    {
        $data = $request->validate([
            'version' => ['required', 'string', 'max:40', Rule::unique('schedules', 'version')->where('name', $schedule->name)],
        ]);

        $newVersion = DB::transaction(function () use ($data, $request, $schedule): Schedule {
            $newVersion = Schedule::query()->create([
                'name' => $schedule->name,
                'version' => $data['version'],
                'approval_status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            foreach ($schedule->doses as $dose) {
                $newVersion->doses()->create($dose->only([
                    'vaccine_id', 'dose_number', 'recommended_age_days', 'minimum_age_days',
                    'interval_from_previous_days', 'minimum_interval_days', 'is_required', 'notes',
                ]));
            }

            AuditLog::record($request, 'schedule.version_created', $newVersion, null, [
                'source_schedule_id' => $schedule->id,
                'source_version' => $schedule->version,
                'version' => $newVersion->version,
            ]);

            return $newVersion;
        });

        return redirect()->route('schedules.show', $newVersion)->with('status', 'New draft version created with the source dose list. Review it before approval.');
    }
}
