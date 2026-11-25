<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\Centre;
use App\Models\Child;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ChildrenIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $locality = '';

    #[Url(as: 'centre_id', except: '')]
    public string $centreId = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->hasPermission('children.view'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'locality', 'centreId'], true)) {
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
        $this->reset(['search', 'status', 'locality', 'centreId']);
        $this->resetPage();
        $this->resetValidation();
    }

    /** @return array<string, array<int, string>> */
    private function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,inactive'],
            'locality' => ['nullable', 'string', 'max:255'],
            'centreId' => ['nullable', 'integer', 'exists:centres,id'],
        ];
    }

    public function render(): View
    {
        $validator = Validator::make([
            'search' => $this->search ?: null,
            'status' => $this->status ?: null,
            'locality' => $this->locality ?: null,
            'centreId' => $this->centreId ?: null,
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
        AuditLog::record(request(), 'child.list_viewed');
        $children = Child::query()->accessibleTo($user)
            ->with(['centre:id,name', 'guardians:id,full_name,phone'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('registration_id', 'like', "%{$search}%")
                        ->orWhere('external_registration_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");

                    $phoneSearch = preg_replace('/[^0-9+]/', '', $search) ?? '';
                    if ($phoneSearch !== '') {
                        $query->orWhereHas('guardians', fn (Builder $guardians) => $guardians->where('phone', 'like', "%{$phoneSearch}%"));
                    }
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['locality'] ?? null, fn (Builder $query, string $locality) => $query->where('locality', $locality))
            ->when($filters['centreId'] ?? null, fn (Builder $query, string $centreId) => $query->where('centre_id', $centreId))
            ->orderByDesc('registered_at')->orderByDesc('id')->paginate(15);

        return view('livewire.children-index', [
            'children' => $children,
            'centres' => Centre::query()->where('is_active', true)
                ->when($user->isCentreScoped(), fn (Builder $query) => $query->whereIn('id', $user->centres()->select('centres.id')))
                ->orderBy('name')->get(['id', 'name']),
            'localities' => Child::query()->accessibleTo($user)->whereNotNull('locality')->distinct()->orderBy('locality')->pluck('locality'),
        ]);
    }
}
