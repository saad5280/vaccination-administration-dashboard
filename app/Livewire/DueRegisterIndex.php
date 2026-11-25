<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Schedule;
use App\Models\ScheduleDose;
use App\Models\Vaccine;
use App\Services\DoseStatusCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class DueRegisterIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'vaccine_id', except: '')]
    public string $vaccineId = '';

    #[Url(as: 'centre_id', except: '')]
    public string $centreId = '';

    #[Url(except: '')]
    public string $locality = '';

    #[Url(except: '')]
    public string $ageGroup = '';

    #[Url(as: 'due_from', except: '')]
    public string $dueFrom = '';

    #[Url(as: 'due_to', except: '')]
    public string $dueTo = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->hasPermission('children.view') && auth()->user()->hasPermission('due-register.view'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'vaccineId', 'centreId', 'locality', 'ageGroup', 'dueFrom', 'dueTo'], true)) {
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
        $this->reset(['status', 'vaccineId', 'centreId', 'locality', 'ageGroup', 'dueFrom', 'dueTo']);
        $this->resetPage();
        $this->resetValidation();
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'status' => ['nullable', 'in:upcoming,due,overdue,not_yet_due,unknown_history,deferred,contraindicated,not_eligible'],
            'vaccineId' => ['nullable', 'integer', 'exists:vaccines,id'],
            'centreId' => ['nullable', 'integer', 'exists:centres,id'],
            'locality' => ['nullable', 'string', 'max:120'],
            'ageGroup' => ['nullable', 'in:under_1,1_4,5_9,10_17'],
            'dueFrom' => ['nullable', 'date'],
            'dueTo' => ['nullable', 'date', 'after_or_equal:dueFrom'],
        ];
    }

    public function render(): View
    {
        $validator = Validator::make([
            'status' => $this->status ?: null,
            'vaccineId' => $this->vaccineId ?: null,
            'centreId' => $this->centreId ?: null,
            'locality' => $this->locality ?: null,
            'ageGroup' => $this->ageGroup ?: null,
            'dueFrom' => $this->dueFrom ?: null,
            'dueTo' => $this->dueTo ?: null,
        ], $this->rules());

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            $filters = [];
        } else {
            $this->resetValidation();
            $filters = $validator->validated();
        }

        $user = auth()->user();
        AuditLog::record(request(), 'due_register.viewed');
        $today = CarbonImmutable::today();
        [$minimumAgeDays, $maximumAgeDays] = match ($filters['ageGroup'] ?? null) {
            'under_1' => [0, 364], '1_4' => [365, 1825], '5_9' => [1826, 3650], '10_17' => [3651, 6570], default => [null, null],
        };
        $activeSchedules = Schedule::query()->where('approval_status', 'approved')
            ->whereDate('effective_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today))
            ->get(['id', 'name', 'version']);
        $scheduleDoses = ScheduleDose::query()->with(['vaccine:id,code,name', 'schedule:id,name,version'])
            ->where('is_required', true)->whereIn('schedule_id', $activeSchedules->modelKeys())
            ->orderBy('vaccine_id')->orderBy('dose_number')->get();
        $children = Child::query()->accessibleTo($user)->where('status', 'active')
            ->with(['centre:id,name', 'vaccinationRecords.vaccine:id,name', 'doseDirectives', 'scheduleAssignments.schedule'])
            ->with(['vaccinationRecords' => fn ($query) => $query->where('status', 'completed')->orderByDesc('administered_at')])
            ->when($filters['centreId'] ?? null, fn ($query, $centreId) => $query->where('centre_id', $centreId))
            ->when($filters['locality'] ?? null, fn ($query, $locality) => $query->where('locality', 'like', '%'.$locality.'%'))
            ->when($minimumAgeDays !== null, fn ($query) => $query->whereDate('date_of_birth', '<=', $today->subDays($minimumAgeDays)))
            ->when($maximumAgeDays !== null, fn ($query) => $query->whereDate('date_of_birth', '>=', $today->subDays($maximumAgeDays)))
            ->orderBy('registration_id')->get();
        $rows = $children->flatMap(fn (Child $child): array => app(DoseStatusCalculator::class)->forChild($child, $scheduleDoses, $today));
        if (isset($filters['vaccineId'])) {
            $rows = $rows->filter(fn (array $row): bool => $row['vaccine']?->id === (int) $filters['vaccineId']);
        }
        if (isset($filters['dueFrom']) || isset($filters['dueTo'])) {
            $rows = $rows->filter(function (array $row) use ($filters): bool {
                if (! $row['due_date']) {
                    return false;
                }

                return (! isset($filters['dueFrom']) || $row['due_date']->toDateString() >= $filters['dueFrom'])
                    && (! isset($filters['dueTo']) || $row['due_date']->toDateString() <= $filters['dueTo']);
            });
        }
        $summary = collect(['upcoming', 'due', 'overdue', 'unknown_history', 'deferred', 'contraindicated', 'not_eligible'])
            ->mapWithKeys(fn (string $state): array => [$state => $rows->where('status', $state)->count()]);
        if (isset($filters['status'])) {
            $rows = $rows->where('status', $filters['status']);
        }
        $rows = $rows->sortBy(fn (array $row): string => ($row['due_date']?->toDateString() ?? '9999-12-31').'-'.$row['child']->registration_id)->values();
        $perPage = 30;
        $page = $this->getPage();
        $register = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, [
            'path' => request()->url(), 'query' => request()->query(), 'pageName' => 'page',
        ]);

        return view('livewire.due-register-index', [
            'register' => $register,
            'summary' => $summary,
            'filters' => $filters,
            'hasActiveSchedule' => $activeSchedules->isNotEmpty(),
            'hasRequiredScheduleDoses' => $scheduleDoses->isNotEmpty(),
            'activeScheduleCount' => $activeSchedules->count(),
            'centres' => Centre::query()->when($user->isCentreScoped(), fn ($query) => $query->whereIn('id', $user->centres()->select('centres.id')))->orderBy('name')->get(['id', 'name']),
            'vaccines' => Vaccine::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
