<?php

namespace App\Livewire;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $action = '';

    #[Url(as: 'date_from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'date_to', except: '')]
    public string $dateTo = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->hasPermission('audit.view'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['action', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function applyFilters(): void
    {
        $this->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date', 'after_or_equal:dateFrom'],
        ]);
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['action', 'dateFrom', 'dateTo']);
        $this->resetPage();
        $this->resetValidation();
    }

    public function render(): View
    {
        $validator = Validator::make([
            'action' => $this->action ?: null,
            'dateFrom' => $this->dateFrom ?: null,
            'dateTo' => $this->dateTo ?: null,
        ], [
            'action' => ['nullable', 'string', 'max:100'],
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date', 'after_or_equal:dateFrom'],
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

        AuditLog::record(request(), 'audit.viewed');
        $logs = AuditLog::query()->with('actor:id,name')
            ->when($filters['action'] ?? null, fn ($query, string $action) => $query->where('action', $action))
            ->when($filters['dateFrom'] ?? null, fn ($query, string $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when($filters['dateTo'] ?? null, fn ($query, string $date) => $query->whereDate('occurred_at', '<=', $date))
            ->orderByDesc('occurred_at')->orderByDesc('id')->paginate(30);

        return view('livewire.audit-log-index', [
            'logs' => $logs,
            'subjectName' => fn (string $type): string => in_array($type, ['system', 'authentication'], true) ? ucfirst($type) : class_basename($type),
        ]);
    }
}
