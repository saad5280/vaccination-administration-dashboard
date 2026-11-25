<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\Guardian;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class GuardiansIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->hasPermission('children.view'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function applySearch(): void
    {
        $this->validate(['search' => ['nullable', 'string', 'max:120']]);
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->reset('search');
        $this->resetPage();
        $this->resetValidation();
    }

    public function render(): View
    {
        $validator = Validator::make(['search' => $this->search ?: null], ['search' => ['nullable', 'string', 'max:120']]);
        if ($validator->fails()) {
            $this->addError('search', $validator->errors()->first('search'));
            $search = null;
        } else {
            $this->resetValidation('search');
            $search = $validator->validated()['search'] ?? null;
        }

        $user = auth()->user();
        AuditLog::record(request(), 'guardian.list_viewed');
        $guardians = Guardian::query()
            ->whereHas('children', fn ($query) => $query->accessibleTo($user))
            ->withCount(['children' => fn ($query) => $query->accessibleTo($user)])
            ->when($search, function ($query, string $search): void {
                $phoneSearch = preg_replace('/[^0-9+]/', '', $search) ?? '';
                $query->where(function ($query) use ($search, $phoneSearch): void {
                    $query->where('full_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                    if ($phoneSearch !== '') {
                        $query->orWhere('phone', 'like', "%{$phoneSearch}%");
                    }
                });
            })
            ->orderBy('full_name')->orderBy('id')->paginate(15);

        return view('livewire.guardians-index', ['guardians' => $guardians]);
    }
}
