<?php

namespace App\Livewire;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Centre;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AppointmentsIndex extends Component
{
    use WithPagination;

    #[Url(except: 'week')]
    public string $view = 'week';

    #[Url(except: '')]
    public string $appointmentDate = '';

    #[Url(as: 'centre_id', except: '')]
    public string $centreId = '';

    #[Url(as: 'vaccinator_id', except: '')]
    public string $vaccinatorId = '';

    #[Url(except: '')]
    public string $status = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->hasPermission('appointments.manage'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['view', 'appointmentDate', 'centreId', 'vaccinatorId', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function applyFilters(): void
    {
        $this->validate($this->rules());
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['view', 'appointmentDate', 'centreId', 'vaccinatorId', 'status']);
        $this->view = 'week';
        $this->appointmentDate = '';
        $this->resetPage();
        $this->resetValidation();
    }

    /** @return array<string, array<int, string>> */
    private function rules(): array
    {
        return [
            'view' => ['required', 'in:day,week'],
            'date' => ['nullable', 'date'],
            'centreId' => ['nullable', 'integer', 'exists:centres,id'],
            'vaccinatorId' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:booked,attended,missed,cancelled'],
        ];
    }

    public function render(): View
    {
        $validator = Validator::make([
            'view' => $this->view,
            'date' => $this->appointmentDate ?: null,
            'centreId' => $this->centreId ?: null,
            'vaccinatorId' => $this->vaccinatorId ?: null,
            'status' => $this->status ?: null,
        ], $this->rules());

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            $filters = ['view' => 'week'];
        } else {
            $this->resetValidation();
            $filters = $validator->validated();
        }

        $user = auth()->user();
        AuditLog::record(request(), 'appointment.list_viewed');
        $date = CarbonImmutable::parse($filters['date'] ?? today());
        $view = $filters['view'] ?? 'week';
        $from = $view === 'day' ? $date->startOfDay() : $date->startOfWeek();
        $until = $view === 'day' ? $from->addDay() : $from->addWeek();
        $appointments = Appointment::query()
            ->with(['child:id,registration_id,first_name,last_name', 'centre:id,name', 'vaccinator:id,name', 'scheduleDose.vaccine:id,name', 'scheduleDose.schedule:id,name,version'])
            ->whereHas('child', fn ($query) => $query->accessibleTo($user))
            ->where('scheduled_at', '>=', $from)->where('scheduled_at', '<', $until)
            ->when($filters['centreId'] ?? null, fn ($query, $centreId) => $query->where('centre_id', $centreId))
            ->when($filters['vaccinatorId'] ?? null, fn ($query, $vaccinatorId) => $query->where('vaccinator_id', $vaccinatorId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('scheduled_at')->paginate(30);

        return view('livewire.appointments-index', [
            'appointments' => $appointments,
            'view' => $view,
            'date' => $date,
            'centres' => Centre::query()->when($user->isCentreScoped(), fn ($query) => $query->whereIn('id', $user->centres()->select('centres.id')))->orderBy('name')->get(['id', 'name']),
            'vaccinators' => User::query()->where('is_active', true)
                ->whereHas('roles.permissions', fn ($query) => $query->where('permissions.name', 'vaccinations.record'))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
