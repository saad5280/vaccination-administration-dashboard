<?php

namespace App\Livewire;

use App\Http\Controllers\InventoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class InventoryIndex extends Component
{
    #[Url(as: 'centre_id', except: '')]
    public string $centreId = '';

    #[Url(as: 'product_id', except: '')]
    public string $productId = '';

    #[Url(except: '')]
    public string $state = '';

    #[Url(as: 'near_expiry_days', except: '')]
    public string $nearExpiryDays = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->hasPermission('inventory.manage'), 403);
    }

    public function updated(): void
    {
        $this->validateFilters();
    }

    public function applyFilters(): void
    {
        $this->validateFilters();
    }

    public function clearFilters(): void
    {
        $this->reset(['centreId', 'productId', 'state', 'nearExpiryDays']);
        $this->resetValidation();
    }

    private function validateFilters(): bool
    {
        $validator = Validator::make([
            'centreId' => $this->centreId ?: null,
            'productId' => $this->productId ?: null,
            'state' => $this->state ?: null,
            'nearExpiryDays' => $this->nearExpiryDays ?: null,
        ], [
            'centreId' => ['nullable', 'integer', 'exists:centres,id'],
            'productId' => ['nullable', 'integer', 'exists:vaccine_products,id'],
            'state' => ['nullable', 'in:active,quarantined,expired,near_expiry,out_of_stock,low_stock'],
            'nearExpiryDays' => ['nullable', 'integer', 'in:7,14,30,60,90'],
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
            $this->productId = '';
            $this->state = '';
        }
        $request = Request::create(route('inventory.index'), 'GET', array_filter([
            '_livewire_inner' => '1',
            'centre_id' => $this->centreId ?: null,
            'product_id' => $this->productId ?: null,
            'state' => $this->state ?: null,
            'near_expiry_days' => $this->nearExpiryDays ?: null,
        ], fn ($value) => $value !== null));
        $request->setUserResolver(fn () => auth()->user());
        $response = app(InventoryController::class)->index($request);
        abort_unless($response instanceof View, 500);

        return view('livewire.inventory-index', $response->getData());
    }
}
