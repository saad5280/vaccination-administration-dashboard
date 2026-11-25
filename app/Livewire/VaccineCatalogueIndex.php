<?php

namespace App\Livewire;

use App\Models\Vaccine;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class VaccineCatalogueIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->hasPermission('catalogue.view'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function applyFilters(): void
    {
        $this->validate(['search' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'in:active,inactive']]);
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status']);
        $this->resetPage();
        $this->resetValidation();
    }

    public function render(): View
    {
        $validator = Validator::make(['search' => $this->search ?: null, 'status' => $this->status ?: null], [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            $filters = [];
        } else {
            $this->resetValidation();
            $filters = $validator->validated();
        }

        $vaccines = Vaccine::query()->withCount('products')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('target_disease', 'like', "%{$search}%");
                });
            })
            ->when(isset($filters['status']), fn ($query) => $query->where('is_active', $filters['status'] === 'active'))
            ->orderBy('name')->orderBy('id')->paginate(15);

        return view('livewire.vaccine-catalogue-index', ['vaccines' => $vaccines]);
    }
}
