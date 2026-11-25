<?php

namespace App\Livewire;

use App\Http\Controllers\ReportsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class ReportsIndex extends Component
{
    #[Url(except: 'vaccinations')]
    public string $report = 'vaccinations';

    #[Url(as: 'date_from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'date_to', except: '')]
    public string $dateTo = '';

    #[Url(as: 'centre_id', except: '')]
    public string $centreId = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->hasPermission('reports.view'), 403);
    }

    public function updated(): void
    {
        $this->validateFilters();
    }

    public function applyFilters(): void
    {
        $this->validateFilters();
    }

    public function clearFilters(): void
    {
        $this->reset(['report', 'dateFrom', 'dateTo', 'centreId']);
        $this->report = 'vaccinations';
        $this->resetValidation();
    }

    /** @return array<string, array<int, string>> */
    private function rules(): array
    {
        return [
            'report' => ['required', 'in:registrations,coverage,vaccinations,due,appointments,follow-ups,stock,movements,wastage'],
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date', 'after_or_equal:dateFrom'],
            'centreId' => ['nullable', 'integer', 'exists:centres,id'],
        ];
    }

    private function validateFilters(): bool
    {
        $validator = Validator::make([
            'report' => $this->report,
            'dateFrom' => $this->dateFrom ?: null,
            'dateTo' => $this->dateTo ?: null,
            'centreId' => $this->centreId ?: null,
        ], $this->rules());

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return false;
        }

        $this->resetValidation();

        return true;
    }

    public function render(): View
    {
        if (! $this->validateFilters()) {
            $this->report = in_array($this->report, ['registrations', 'coverage', 'vaccinations', 'due', 'appointments', 'follow-ups', 'stock', 'movements', 'wastage'], true) ? $this->report : 'vaccinations';
            $this->dateFrom = '';
            $this->dateTo = '';
            $this->centreId = '';
        }

        $request = Request::create(route('reports.index'), 'GET', array_filter([
            'report' => $this->report,
            'date_from' => $this->dateFrom ?: null,
            'date_to' => $this->dateTo ?: null,
            'centre_id' => $this->centreId ?: null,
        ], fn ($value) => $value !== null));
        $response = app(ReportsController::class)->index($request);
        abort_unless($response instanceof View, 500);

        return view('livewire.reports-index', $response->getData());
    }
}
