<?php

namespace App\Livewire;

use App\Http\Controllers\ReminderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class ReminderQueue extends Component
{
    #[Url(except: '')]
    public string $status = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->hasPermission('reminders.manage'), 403);
    }

    public function updatedStatus(): void
    {
        $this->validateStatus();
    }

    public function clearFilters(): void
    {
        $this->status = '';
        $this->resetValidation();
    }

    private function validateStatus(): bool
    {
        $validator = Validator::make(['status' => $this->status ?: null], ['status' => ['nullable', 'in:pending,completed,failed']]);
        if ($validator->fails()) {
            $this->addError('status', $validator->errors()->first('status'));

            return false;
        }
        $this->resetValidation('status');

        return true;
    }

    public function render(): View
    {
        if (! $this->validateStatus()) {
            $this->status = '';
        }
        $request = Request::create(route('reminders.index'), 'GET', array_filter([
            '_livewire_inner' => '1', 'status' => $this->status ?: null,
        ], fn ($value) => $value !== null));
        $request->setUserResolver(fn () => auth()->user());
        $response = app(ReminderController::class)->index($request);
        abort_unless($response instanceof View, 500);

        return view('livewire.reminder-queue', $response->getData());
    }
}
