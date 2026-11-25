<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveStockReorderLevelRequest;
use App\Models\AuditLog;
use App\Models\Centre;
use App\Models\StockBatch;
use App\Models\StockReorderLevel;
use App\Models\SystemSetting;
use App\Models\VaccineProduct;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        if (! $request->boolean('_livewire_inner')) {
            return view('inventory.index');
        }
        $filters = $request->validate([
            'centre_id' => ['nullable', 'integer', 'exists:centres,id'],
            'product_id' => ['nullable', 'integer', 'exists:vaccine_products,id'],
            'state' => ['nullable', 'in:active,quarantined,expired,near_expiry,out_of_stock,low_stock'],
            'near_expiry_days' => ['nullable', 'integer', 'in:7,14,30,60,90'],
        ]);
        $today = CarbonImmutable::today();
        $nearExpiryDays = (int) ($filters['near_expiry_days'] ?? SystemSetting::value('near_expiry_days', 30));
        $levels = $this->stockLevelSummaries($today);

        $batchQuery = StockBatch::query()
            ->with(['product.vaccine:id,name', 'centre:id,name'])
            ->when($filters['centre_id'] ?? null, fn ($query, $centreId) => $query->where('centre_id', $centreId))
            ->when($filters['product_id'] ?? null, fn ($query, $productId) => $query->where('vaccine_product_id', $productId))
            ->when(($filters['state'] ?? null) === 'active', fn ($query) => $query->where('status', 'active')->whereDate('expires_at', '>=', $today))
            ->when(($filters['state'] ?? null) === 'quarantined', fn ($query) => $query->where('status', 'quarantined'))
            ->when(($filters['state'] ?? null) === 'expired', fn ($query) => $query->whereDate('expires_at', '<', $today)->where('available_quantity', '>', 0))
            ->when(($filters['state'] ?? null) === 'near_expiry', fn ($query) => $query->where('status', 'active')->whereDate('expires_at', '>=', $today)->whereDate('expires_at', '<=', $today->addDays($nearExpiryDays))->where('available_quantity', '>', 0))
            ->when(($filters['state'] ?? null) === 'out_of_stock', fn ($query) => $query->where('status', 'active')->where('available_quantity', '<=', 0))
            ->when(($filters['state'] ?? null) === 'low_stock', function ($query) use ($levels): void {
                $ids = $levels->where('state', 'low_stock')->pluck('batch_ids')->flatten();
                $query->whereIn('id', $ids->all());
            });

        $batches = $batchQuery->orderBy('expires_at')->orderBy('id')->paginate(30)->withQueryString();
        $expiredCount = StockBatch::query()->whereDate('expires_at', '<', $today)->where('available_quantity', '>', 0)->count();
        $nearExpiryCount = StockBatch::query()->where('status', 'active')->whereDate('expires_at', '>=', $today)
            ->whereDate('expires_at', '<=', $today->addDays($nearExpiryDays))->where('available_quantity', '>', 0)->count();

        return view('inventory.index', [
            'batches' => $batches,
            'filters' => $filters,
            'centres' => Centre::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'products' => VaccineProduct::query()->with('vaccine:id,name')->where('is_active', true)->orderBy('vaccine_id')->get(),
            'levels' => $levels,
            'thresholds' => $levels->keyBy(fn (array $row): string => $row['level']->vaccine_product_id.'-'.$row['level']->centre_id),
            'fefoBatchIds' => StockBatch::query()->where('status', 'active')->whereDate('expires_at', '>=', $today)->where('available_quantity', '>', 0)
                ->orderBy('expires_at')->orderBy('id')->get(['id', 'vaccine_product_id', 'centre_id'])
                ->groupBy(fn (StockBatch $batch): string => $batch->vaccine_product_id.'-'.$batch->centre_id)->map(fn ($group) => $group->first()->id)->values(),
            'lowStockCount' => $levels->where('state', 'low_stock')->count(),
            'outOfStockCount' => $levels->where('state', 'out_of_stock')->count(),
            'expiredCount' => $expiredCount,
            'nearExpiryCount' => $nearExpiryCount,
            'nearExpiryDays' => $nearExpiryDays,
        ]);
    }

    public function reorderLevels(): View
    {
        return view('inventory.reorder-levels', [
            'levels' => StockReorderLevel::query()->with(['product.vaccine:id,name', 'centre:id,name', 'updatedBy:id,name'])->orderBy('centre_id')->orderBy('vaccine_product_id')->paginate(30),
            'products' => VaccineProduct::query()->with('vaccine:id,name')->where('is_active', true)->orderBy('vaccine_id')->get(),
            'centres' => Centre::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function saveReorderLevel(SaveStockReorderLevelRequest $request): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data, $request): void {
            VaccineProduct::query()->lockForUpdate()->findOrFail($data['vaccine_product_id']);
            Centre::query()->lockForUpdate()->findOrFail($data['centre_id']);
            $level = StockReorderLevel::query()->where('vaccine_product_id', $data['vaccine_product_id'])
                ->where('centre_id', $data['centre_id'])->lockForUpdate()->first();
            $before = $level?->only(['vaccine_product_id', 'centre_id', 'minimum_quantity']);
            $level = StockReorderLevel::query()->updateOrCreate(
                ['vaccine_product_id' => $data['vaccine_product_id'], 'centre_id' => $data['centre_id']],
                ['minimum_quantity' => number_format((float) $data['minimum_quantity'], 3, '.', ''), 'updated_by' => $request->user()->id],
            );
            AuditLog::record($request, 'stock.reorder_level_updated', $level, $before, $level->only(['vaccine_product_id', 'centre_id', 'minimum_quantity']));
        });

        return redirect()->route('inventory.reorder-levels.index')->with('status', 'Low-stock threshold saved.');
    }

    /** @return Collection<int, array<string, mixed>> */
    private function stockLevelSummaries(CarbonImmutable $today): Collection
    {
        return StockReorderLevel::query()->with(['product.vaccine:id,name', 'centre:id,name'])->get()->map(function (StockReorderLevel $level) use ($today): array {
            $eligibleBatches = StockBatch::query()->where('vaccine_product_id', $level->vaccine_product_id)
                ->where('centre_id', $level->centre_id)->where('status', 'active')->whereDate('expires_at', '>=', $today);
            $available = (float) (clone $eligibleBatches)->sum('available_quantity');
            $threshold = (float) $level->minimum_quantity;
            $state = $available <= 0 ? 'out_of_stock' : ($threshold > 0 && $available <= $threshold ? 'low_stock' : 'sufficient');

            return [
                'level' => $level,
                'available' => $available,
                'state' => $state,
                'batch_ids' => $state === 'low_stock' ? (clone $eligibleBatches)->where('available_quantity', '>', 0)->pluck('id') : collect(),
            ];
        });
    }
}
