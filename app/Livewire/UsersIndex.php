<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class UsersIndex extends Component
{
    use WithPagination;

    public function boot(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->hasPermission('users.manage'), 403);
    }

    public function render(): View
    {
        return view('livewire.users-index', [
            'users' => User::query()->with(['roles', 'centres'])->orderBy('name')->paginate(15),
        ]);
    }
}
