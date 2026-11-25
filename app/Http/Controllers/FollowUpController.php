<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFollowUpRequest;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\FollowUp;
use App\Models\ScheduleDose;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    public function index(Request $request): View
    {
        if (! $request->boolean('_livewire_inner')) {
            return view('follow-ups.index');
        }

        return view('follow-ups.index', [
            'followUps' => FollowUp::query()->whereHas('child', fn ($query) => $query->accessibleTo($request->user()))->with(['child:id,registration_id,first_name,last_name', 'appointment:id,scheduled_at,status', 'scheduleDose.vaccine:id,name', 'assignedTo:id,name', 'recordedBy:id,name'])
                ->orderByDesc('created_at')->paginate(30),
            'children' => Child::query()->accessibleTo($request->user())->where('status', 'active')->orderBy('registration_id')->get(['id', 'registration_id', 'first_name', 'last_name']),
            'appointments' => Appointment::query()->where('status', '!=', 'cancelled')->whereHas('child', fn ($query) => $query->accessibleTo($request->user()))->orderByDesc('scheduled_at')->limit(100)->get(['id', 'child_id', 'scheduled_at', 'status']),
            'scheduleDoses' => ScheduleDose::query()->with(['vaccine:id,name', 'schedule:id,name,version'])->whereHas('schedule', fn ($query) => $query->where('approval_status', 'approved'))->orderBy('vaccine_id')->orderBy('dose_number')->get(),
            'staff' => User::query()->where('is_active', true)->whereHas('roles.permissions', fn ($query) => $query->where('permissions.name', 'children.view'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreFollowUpRequest $request): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data, $request): void {
            $followUp = FollowUp::query()->create([
                ...$data,
                'contacted_at' => $data['contacted_at'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);
            AuditLog::record($request, 'follow_up.recorded', $followUp, null, $followUp->only([
                'child_id', 'appointment_id', 'schedule_dose_id', 'assigned_to', 'outcome', 'contacted_at', 'next_action_at', 'notes',
            ]));
        });

        return redirect()->route('follow-ups.index')->with('status', 'Follow-up outcome recorded.');
    }
}
