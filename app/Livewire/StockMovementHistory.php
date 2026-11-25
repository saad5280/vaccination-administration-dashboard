<?php

namespace App\Livewire;

use App\Http\Controllers\StockTransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class StockMovementHistory extends Component
{
    #[Url(as: 'centre_id', except: '')]
    public string $centreId = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(as: 'date_from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'date_to', except: '')]
    public string $dateTo = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->hasPermission('inventory.manage'), 403);
    }

    public function updated(): void
    {
        $this->validateFilters();
    }

    public function clearFilters(): void
    {
        $this->reset(['centreId', 'type', 'dateFrom', 'dateTo']);
        $this->resetValidation();
    }

    private function validateFilters(): bool
    {
        $validator = Validator::make([
            'centreId' => $this->centreId ?: null, 'type' => $this->type ?: null,
            'dateFrom' => $this->dateFrom ?: null, 'dateTo' => $this->dateTo ?: null,
        ], [
            'centreId' => ['nullable', 'integer', 'exists:centres,id'],
            'type' => ['nullable', 'in:receipt,issue,return,transfer_in,transfer_out,adjustment_in,adjustment_out,wastage,disposal'],
            'dateFrom' => ['nullable', 'date'], 'dateTo' => ['nullable', 'date', 'after_or_equal:dateFrom'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return false;
        }
        $this->resetValidation();

        return true;
    }

    public function render(): View
    {
        if (! $this->validateFilters()) {
            $this->centreId = '';
            $this->type = '';
            $this->dateFrom = '';
            $this->dateTo = '';
        }
        $request = Request::create(route('inventory.transactions.index'), 'GET', array_filter([
            '_livewire_inner' => '1', 'centre_id' => $this->centreId ?: null, 'type' => $this->type ?: null,
            'date_from' => $this->dateFrom ?: null, 'date_to' => $this->dateTo ?: null,
        ], fn ($value) => $value !== null));
        $request->setUserResolver(fn () => auth()->user());
        $response = app(StockTransactionController::class)->index($request);
        abort_unless($response instanceof View, 500);

        return view('livewire.stock-movement-history', $response->getData());
    }
}
