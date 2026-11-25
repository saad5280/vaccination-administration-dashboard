<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\AuditLog;
use App\Models\Schedule;
use App\Models\Vaccine;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(): View
    {
        return view('schedules.index');
    }

    public function create(): View
    {
        return view('schedules.create', ['schedule' => null]);
    }

    public function store(StoreScheduleRequest $request): RedirectResponse
    {
        $schedule = Schedule::query()->create([
            ...$request->validated(),
            'approval_status' => 'draft',
            'created_by' => $request->user()->id,
        ]);
        AuditLog::record($request, 'schedule.created', $schedule, null, $schedule->only(['name', 'version', 'approval_status']));

        return redirect()->route('schedules.show', $schedule)->with('status', 'Schedule draft created. Add its configured doses before requesting approval.');
    }

    public function show(Schedule $schedule): View
    {
        return view('schedules.show', [
            'schedule' => $schedule->load(['approvedBy', 'createdBy', 'doses' => fn ($query) => $query->with('vaccine')->orderBy('vaccine_id')->orderBy('dose_number')]),
            'vaccines' => Vaccine::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function edit(Schedule $schedule): View
    {
        $this->ensureDraft($schedule);

        return view('schedules.edit', compact('schedule'));
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        $this->ensureDraft($schedule);
        $before = $schedule->only(['name', 'version', 'effective_from', 'effective_until']);
        $schedule->update($request->validated());
        AuditLog::record($request, 'schedule.updated', $schedule, $before, $schedule->only(array_keys($before)));

        return redirect()->route('schedules.show', $schedule)->with('status', 'Schedule draft updated.');
    }

    public function createVersion(Schedule $schedule): View
    {
        return view('schedules.versions.create', ['sourceSchedule' => $schedule]);
    }

    private function ensureDraft(Schedule $schedule): void
    {
        abort_unless($schedule->approval_status === 'draft', 422, 'Approved schedules are read-only. Create a new version to make changes.');
    }
}
