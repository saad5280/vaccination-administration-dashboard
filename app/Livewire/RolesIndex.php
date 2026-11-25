<?php

namespace App\Livewire;

use App\Models\Role;
use Illuminate\View\View;
use Livewire\Component;

class RolesIndex extends Component
{
    public function boot(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->hasPermission('roles.manage'), 403);
    }

    public function render(): View
    {
        return view('livewire.roles-index', [
            'roles' => Role::query()->withCount('users')->with('permissions')->orderBy('label')->get(),
        ]);
    }
}
