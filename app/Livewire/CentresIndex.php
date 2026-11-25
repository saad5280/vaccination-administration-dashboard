<?php

namespace App\Livewire;

use App\Models\Centre;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class CentresIndex extends Component
{
    use WithPagination;

    public function boot(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->hasPermission('settings.manage'), 403);
    }

    public function render(): View
    {
        return view('livewire.centres-index', [
            'centres' => Centre::query()->withCount('children')->orderBy('name')->paginate(15),
        ]);
    }
}
