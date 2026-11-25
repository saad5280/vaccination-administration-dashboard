<?php

namespace App\Livewire;

use App\Http\Controllers\FollowUpController;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Livewire\Component;

class FollowUpHistory extends Component
{
    public function boot(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->hasPermission('reminders.manage'), 403);
    }

    public function render(): View
    {
        $request = Request::create(route('follow-ups.index'), 'GET', ['_livewire_inner' => '1']);
        $request->setUserResolver(fn () => auth()->user());
        $response = app(FollowUpController::class)->index($request);
        abort_unless($response instanceof View, 500);

        return view('livewire.follow-up-history', $response->getData());
    }
}
