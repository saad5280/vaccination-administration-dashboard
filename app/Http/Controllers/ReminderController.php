<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteReminderRequest;
use App\Http\Requests\StoreReminderRequest;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\Reminder;
use App\Models\ReminderTemplate;
use App\Models\ScheduleDose;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReminderController extends Controller
{
    public function index(Request $request): View
    {
        if (! $request->boolean('_livewire_inner')) {
            return view('reminders.index');
        }
        $filters = $request->validate(['status' => ['nullable', 'in:pending,completed,failed']]);
        $reminders = Reminder::query()
            ->with(['child:id,registration_id,first_name,last_name', 'guardian:id,full_name,preferred_contact_method,preferred_language', 'scheduleDose.vaccine:id,name', 'appointment:id,scheduled_at,status', 'rule:id,name'])
            ->whereHas('child', fn ($query) => $query->accessibleTo($request->user()))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('scheduled_for')
            ->paginate(30)
            ->withQueryString();

        return view('reminders.index', [
            'reminders' => $reminders,
            'filters' => $filters,
            'children' => Child::query()->accessibleTo($request->user())->where('status', 'active')->orderBy('registration_id')->get(['id', 'registration_id', 'first_name', 'last_name']),
            'appointments' => Appointment::query()->whereIn('status', ['booked', 'missed'])->whereHas('child', fn ($query) => $query->accessibleTo($request->user()))->with('child:id,registration_id,first_name,last_name')->orderByDesc('scheduled_at')->limit(100)->get(['id', 'child_id', 'scheduled_at', 'status']),
            'scheduleDoses' => ScheduleDose::query()->with(['vaccine:id,name', 'schedule:id,name,version'])->whereHas('schedule', fn ($query) => $query->where('approval_status', 'approved'))->orderBy('vaccine_id')->orderBy('dose_number')->get(),
            'templates' => ReminderTemplate::query()->where('is_active', true)->where('channel', 'in_app')->orderBy('name')->get(),
            'pendingCount' => Reminder::query()->where('status', 'pending')->where('scheduled_for', '<=', now())->count(),
        ]);
    }

    public function store(StoreReminderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $template = ReminderTemplate::query()->find($data['template_id'] ?? null);
        DB::transaction(function () use ($data, $request, $template): void {
            $reminder = Reminder::query()->create([
                ...$data,
                'channel' => 'in_app',
                'status' => 'pending',
                'created_by' => $request->user()->id,
                'template_name_snapshot' => $template?->name,
                'template_body_snapshot' => $template?->body,
            ]);
            AuditLog::record($request, 'reminder.created', $reminder, null, $this->auditValues($reminder));
        });

        return redirect()->route('reminders.index')->with('status', 'In-dashboard follow-up task created. Check consent and contact preferences before any external contact.');
    }

    public function update(CompleteReminderRequest $request, Reminder $reminder): RedirectResponse
    {
        abort_unless($request->user()->canAccessChild($reminder->child), 404);
        abort_unless($reminder->status === 'pending', 422, 'Only pending reminder tasks can be completed.');

        DB::transaction(function () use ($request, $reminder): void {
            $locked = Reminder::query()->lockForUpdate()->findOrFail($reminder->id);
            abort_unless($locked->status === 'pending', 422, 'Only pending reminder tasks can be completed.');
            $before = $this->auditValues($locked);
            $locked->update($request->validated());
            AuditLog::record($request, 'reminder.'.$locked->status, $locked, $before, $this->auditValues($locked->fresh()));
        });

        return redirect()->route('reminders.index')->with('status', 'Reminder task outcome saved.');
    }

    /** @return array<string, mixed> */
    private function auditValues(Reminder $reminder): array
    {
        return $reminder->only(['child_id', 'guardian_id', 'schedule_dose_id', 'appointment_id', 'template_id', 'reminder_rule_id', 'channel', 'status', 'scheduled_for', 'failure_reason', 'outcome_notes']);
    }
}
