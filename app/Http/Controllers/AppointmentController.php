<?php

namespace App\Http\Controllers;

use App\Http\Requests\RescheduleAppointmentRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentStatusRequest;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Centre;
use App\Models\Child;
use App\Models\ScheduleDose;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(): View
    {
        return view('appointments.index');
    }

    public function create(Request $request): View
    {
        return view('appointments.create', $this->formData($request));
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $appointment = DB::transaction(function () use ($data, $request): Appointment {
            Child::query()->lockForUpdate()->findOrFail($data['child_id']);
            if (! empty($data['vaccinator_id'])) {
                User::query()->lockForUpdate()->findOrFail($data['vaccinator_id']);
            }
            $this->ensureNoConflict($data['child_id'], $data['scheduled_at'], $data['vaccinator_id'] ?? null);

            $appointment = Appointment::query()->create([
                ...$data,
                'status' => 'booked',
                'created_by' => $request->user()->id,
            ]);
            AuditLog::record($request, 'appointment.booked', $appointment, null, $this->auditValues($appointment));

            return $appointment;
        });

        return redirect()->route('appointments.index')->with('status', 'Appointment booked.');
    }

    public function edit(Request $request, Appointment $appointment): View
    {
        abort_unless($appointment->status === 'booked', 422, 'Only booked appointments can be rescheduled.');

        return view('appointments.edit', [
            ...$this->formData($request),
            'appointment' => $appointment->load(['child', 'scheduleDose']),
        ]);
    }

    public function update(RescheduleAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $appointment): void {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless($locked->status === 'booked', 422, 'Only booked appointments can be rescheduled.');
            if (! empty($data['vaccinator_id'])) {
                User::query()->lockForUpdate()->findOrFail($data['vaccinator_id']);
            }
            $this->ensureNoConflict($locked->child_id, $data['scheduled_at'], $data['vaccinator_id'] ?? null, $locked->id);

            $before = $this->auditValues($locked);
            $locked->update($data);
            AuditLog::record($request, 'appointment.rescheduled', $locked, $before, $this->auditValues($locked->fresh()));
        });

        return redirect()->route('appointments.index')->with('status', 'Appointment rescheduled.');
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment): RedirectResponse
    {
        DB::transaction(function () use ($request, $appointment): void {
            $locked = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless($locked->status === 'booked', 422, 'Only a booked appointment can be updated.');
            $before = $this->auditValues($locked);
            $locked->update([
                'status' => $request->validated('status'),
                'cancellation_reason' => $request->validated('cancellation_reason'),
            ]);
            AuditLog::record($request, 'appointment.'.$locked->status, $locked, $before, $this->auditValues($locked->fresh()));
        });

        return redirect()->route('appointments.index')->with('status', 'Appointment status updated.');
    }

    /** @return array{children: Collection, centres: Collection, vaccinators: Collection, scheduleDoses: Collection} */
    private function formData(Request $request): array
    {
        return [
            'children' => Child::query()->accessibleTo($request->user())->where('status', 'active')->orderBy('registration_id')->get(['id', 'registration_id', 'first_name', 'last_name']),
            'centres' => Centre::query()->where('is_active', true)->when($request->user()->isCentreScoped(), fn ($query) => $query->whereIn('id', $request->user()->centres()->select('centres.id')))->orderBy('name')->get(['id', 'name']),
            'vaccinators' => $this->vaccinators(),
            'scheduleDoses' => ScheduleDose::query()
                ->with(['vaccine:id,code,name', 'schedule:id,name,version,approval_status,effective_from,effective_until'])
                ->whereHas('schedule', fn ($query) => $query->where('approval_status', 'approved'))
                ->orderBy('vaccine_id')->orderBy('dose_number')->get(),
        ];
    }

    private function ensureNoConflict(int $childId, string $scheduledAt, ?int $vaccinatorId, ?int $ignoreId = null): void
    {
        $time = CarbonImmutable::parse($scheduledAt)->format('Y-m-d H:i:s');
        $base = Appointment::query()->where('status', 'booked')->where('scheduled_at', $time);
        if ($ignoreId) {
            $base->whereKeyNot($ignoreId);
        }

        if ((clone $base)->where('child_id', $childId)->exists()) {
            throw ValidationException::withMessages(['scheduled_at' => 'This child already has a booked appointment at that time.']);
        }

        if ($vaccinatorId && (clone $base)->where('vaccinator_id', $vaccinatorId)->exists()) {
            throw ValidationException::withMessages(['vaccinator_id' => 'This vaccinator already has a booked appointment at that time.']);
        }
    }

    /** @return array<string, mixed> */
    private function auditValues(Appointment $appointment): array
    {
        return $appointment->only(['child_id', 'schedule_dose_id', 'centre_id', 'vaccinator_id', 'scheduled_at', 'status', 'cancellation_reason']);
    }

    private function vaccinators(): Collection
    {
        return User::query()->where('is_active', true)
            ->whereHas('roles.permissions', fn ($query) => $query->where('permissions.name', 'vaccinations.record'))
            ->orderBy('name')->get(['id', 'name']);
    }
}
