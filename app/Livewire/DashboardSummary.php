<?php

namespace App\Livewire;

use App\Services\DashboardSummaryService;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class DashboardSummary extends Component
{
    #[Url(as: 'date_from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'date_to', except: '')]
    public string $dateTo = '';

    #[Url(as: 'centre_id', except: '')]
    public string $centreId = '';

    #[Url(as: 'vaccine_id', except: '')]
    public string $vaccineId = '';

    #[Url(as: 'age_group', except: '')]
    public string $ageGroup = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->hasPermission('dashboard.view'), 403);
    }

    public function applyFilters(): void
    {
        $this->validatedFilters();
    }

    public function clearFilters(): void
    {
        $this->reset(['dateFrom', 'dateTo', 'centreId', 'vaccineId', 'ageGroup', 'status']);
        $this->resetValidation();
    }

    public function render(): View
    {
        $validator = $this->filterValidator();
        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            $filters = [];
        } else {
            $this->resetValidation();
            $filters = $validator->validated();
        }

        return view('livewire.dashboard-summary', app(DashboardSummaryService::class)->build($filters));
    }

    /** @return array<string, mixed> */
    private function validatedFilters(): array
    {
        return $this->filterValidator()->validate();
    }

    private function filterValidator(): \Illuminate\Validation\Validator
    {
        return Validator::make([
            'date_from' => $this->dateFrom ?: null,
            'date_to' => $this->dateTo ?: null,
            'centre_id' => $this->centreId ?: null,
            'vaccine_id' => $this->vaccineId ?: null,
            'age_group' => $this->ageGroup ?: null,
            'status' => $this->status ?: null,
        ], [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'centre_id' => ['nullable', 'integer', 'exists:centres,id'],
            'vaccine_id' => ['nullable', 'integer', 'exists:vaccines,id'],
            'age_group' => ['nullable', 'in:under_1,1_4,5_9,10_17'],
            'status' => ['nullable', 'in:upcoming,due,overdue,unknown_history'],
        ]);
    }
}
