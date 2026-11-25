<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduleDoseRequest;
use App\Http\Requests\UpdateScheduleDoseRequest;
use App\Models\AuditLog;
use App\Models\Schedule;
use App\Models\ScheduleDose;
use App\Models\Vaccine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleDoseController extends Controller
{
    public function create(Schedule $schedule): View
    {
        $this->ensureDraft($schedule);

        return view('schedules.doses.edit', ['schedule' => $schedule, 'scheduleDose' => null, 'vaccines' => $this->activeVaccines()]);
    }

    public function store(StoreScheduleDoseRequest $request, Schedule $schedule): RedirectResponse
    {
        $this->ensureDraft($schedule);
        $dose = $schedule->doses()->create($request->validated());
        AuditLog::record($request, 'schedule_dose.created', $dose, null, $dose->only(['schedule_id', 'vaccine_id', 'dose_number', 'recommended_age_days', 'minimum_age_days', 'interval_from_previous_days', 'minimum_interval_days']));

        return redirect()->route('schedules.show', $schedule)->with('status', 'Dose added to the schedule draft.');
    }

    public function edit(Schedule $schedule, ScheduleDose $dose): View
    {
        $this->ensureDraft($schedule);

        return view('schedules.doses.edit', ['schedule' => $schedule, 'scheduleDose' => $dose, 'vaccines' => $this->activeVaccines()]);
    }

    public function update(UpdateScheduleDoseRequest $request, Schedule $schedule, ScheduleDose $dose): RedirectResponse
    {
        $this->ensureDraft($schedule);
        $before = $dose->only(array_keys($request->validated()));
        $dose->update($request->validated());
        AuditLog::record($request, 'schedule_dose.updated', $dose, $before, $dose->only(array_keys($before)));

        return redirect()->route('schedules.show', $schedule)->with('status', 'Dose updated in the schedule draft.');
    }

    public function destroy(Request $request, Schedule $schedule, ScheduleDose $dose): RedirectResponse
    {
        $this->ensureDraft($schedule);
        AuditLog::record($request, 'schedule_dose.deleted', $dose, $dose->only(['schedule_id', 'vaccine_id', 'dose_number']), null);
        $dose->delete();

        return redirect()->route('schedules.show', $schedule)->with('status', 'Dose removed from the schedule draft.');
    }

    private function ensureDraft(Schedule $schedule): void
    {
        abort_unless($schedule->approval_status === 'draft', 422, 'Approved schedules are read-only. Create a new version to make changes.');
    }

    private function activeVaccines(): Collection
    {
        return Vaccine::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);
    }
}
