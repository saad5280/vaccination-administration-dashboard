<?php

namespace App\Livewire;

use App\Models\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SchedulesIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->hasPermission('schedules.view'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function applyFilters(): void
    {
        $this->validate(['search' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'in:draft,approved']]);
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
            'search' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'in:draft,approved'],
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
        $schedules = Schedule::query()->withCount('doses')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('approval_status', $status))
            ->orderBy('name')->orderByDesc('created_at')->orderByDesc('id')->paginate(15);

        return view('livewire.schedules-index', ['schedules' => $schedules]);
    }
}
