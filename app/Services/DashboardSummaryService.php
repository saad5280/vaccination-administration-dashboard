<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Reminder;
use App\Models\Schedule;
use App\Models\ScheduleDose;
use App\Models\StockBatch;
use App\Models\StockReorderLevel;
use App\Models\SystemSetting;
use App\Models\VaccinationRecord;
use App\Models\Vaccine;
use Carbon\CarbonImmutable;

class DashboardSummaryService
{
    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function build(array $filters): array
    {
        $from = isset($filters['date_from']) ? CarbonImmutable::parse($filters['date_from'])->startOfDay() : null;
        $to = isset($filters['date_to']) ? CarbonImmutable::parse($filters['date_to'])->endOfDay() : null;
        $centreId = $filters['centre_id'] ?? null;
        $user = auth()->user();
        $assignedCentreIds = $user->isCentreScoped() ? $user->centres()->select('centres.id') : null;
        $today = CarbonImmutable::today();
        $nearExpiryDays = (int) SystemSetting::value('near_expiry_days', 30);
        [$minimumAgeDays, $maximumAgeDays] = match ($filters['age_group'] ?? null) {
            'under_1' => [0, 364],
            '1_4' => [365, 1825],
            '5_9' => [1826, 3650],
            '10_17' => [3651, 6570],
            default => [null, null],
        };

        $children = Child::query()->accessibleTo($user)->where('status', 'active')->when($centreId, fn ($query) => $query->where('centre_id', $centreId));
        $records = VaccinationRecord::query()->where('status', 'completed')
            ->when($from, fn ($query) => $query->where('administered_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('administered_at', '<=', $to))
            ->when($centreId, fn ($query) => $query->where('centre_id', $centreId))
            ->when($assignedCentreIds, fn ($query, $ids) => $query->whereIn('centre_id', $ids))
            ->when($filters['vaccine_id'] ?? null, fn ($query, $vaccineId) => $query->where('vaccine_id', $vaccineId));
        $appointments = Appointment::query()
            ->when($from, fn ($query) => $query->where('scheduled_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('scheduled_at', '<=', $to))
            ->when(! $from && ! $to, fn ($query) => $query->whereDate('scheduled_at', $today))
            ->when($centreId, fn ($query) => $query->where('centre_id', $centreId));
        if ($assignedCentreIds) {
            $appointments->whereIn('centre_id', $assignedCentreIds);
        }

        $stats = [
            'children' => (clone $children)->count(),
            'vaccinated_children' => (clone $records)->distinct('child_id')->count('child_id'),
            'appointments' => (clone $appointments)->where('status', 'booked')->count(),
            'missed_appointments' => (clone $appointments)->where('status', 'missed')->count(),
            'pending_reminders' => Reminder::query()->where('status', 'pending')->where('scheduled_for', '<=', now())
                ->whereHas('child', fn ($children) => $children->accessibleTo($user)->when($centreId, fn ($q) => $q->where('centre_id', $centreId)))->count(),
            'incomplete_records' => 0,
        ];

        $activeSchedules = Schedule::query()->where('approval_status', 'approved')->whereDate('effective_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today))->pluck('id');
        $scheduleDoses = ScheduleDose::query()->with(['vaccine:id,code,name', 'schedule:id,name,version'])
            ->where('is_required', true)->whereIn('schedule_id', $activeSchedules)->get();
        $trackingChildren = (clone $children)->with([
            'centre:id,name',
            'vaccinationRecords' => fn ($query) => $query->where('status', 'completed')->orderByDesc('administered_at'),
            'scheduleAssignments.schedule',
        ])
            ->when($minimumAgeDays !== null, fn ($query) => $query->whereDate('date_of_birth', '<=', $today->subDays($minimumAgeDays)))
            ->when($maximumAgeDays !== null, fn ($query) => $query->whereDate('date_of_birth', '>=', $today->subDays($maximumAgeDays)))
            ->get();
        $calculator = app(DoseStatusCalculator::class);
        $allDueRows = $trackingChildren->flatMap(fn (Child $child) => $calculator->forChild($child, $scheduleDoses, $today))
            ->when(isset($filters['vaccine_id']), fn ($rows) => $rows->filter(fn ($row) => $row['vaccine']?->id === (int) $filters['vaccine_id']));
        $stats['incomplete_records'] = $allDueRows->pluck('child.id')->unique()->count();
        $dueRows = $allDueRows->when(isset($filters['status']), fn ($rows) => $rows->where('status', $filters['status']));
        foreach (['upcoming', 'due', 'overdue', 'unknown_history'] as $trackingStatus) {
            $stats[$trackingStatus] = $dueRows->where('status', $trackingStatus)->count();
        }

        $alerts = null;
        if ($user->hasPermission('inventory.manage') || $user->hasPermission('reports.view')) {
            $alerts = [
                'near_expiry' => StockBatch::query()->where('status', 'active')->where('available_quantity', '>', 0)
                    ->whereDate('expires_at', '>=', $today)->whereDate('expires_at', '<=', $today->addDays($nearExpiryDays))
                    ->when($centreId, fn ($query) => $query->where('centre_id', $centreId))->count(),
                'expired' => StockBatch::query()->where('available_quantity', '>', 0)->whereDate('expires_at', '<', $today)
                    ->when($centreId, fn ($query) => $query->where('centre_id', $centreId))->count(),
                'low_stock' => StockReorderLevel::query()->whereHas('product')->when($centreId, fn ($query) => $query->where('centre_id', $centreId))->get()->filter(function ($level): bool {
                    $balance = StockBatch::query()->where('vaccine_product_id', $level->vaccine_product_id)->where('centre_id', $level->centre_id)
                        ->where('status', 'active')->whereDate('expires_at', '>=', today())->sum('available_quantity');

                    return (float) $balance <= (float) $level->minimum_quantity;
                })->count(),
            ];
        }

        $recentActivity = $user->hasPermission('vaccinations.view')
            ? VaccinationRecord::query()->with(['child:id,first_name,last_name,registration_id', 'vaccine:id,name'])->where('status', 'completed')
                ->whereHas('child', fn ($query) => $query->accessibleTo($user))
                ->when($from, fn ($query) => $query->where('administered_at', '>=', $from))
                ->when($to, fn ($query) => $query->where('administered_at', '<=', $to))
                ->when($centreId, fn ($query) => $query->where('centre_id', $centreId))
                ->when($filters['vaccine_id'] ?? null, fn ($query, $vaccineId) => $query->where('vaccine_id', $vaccineId))
                ->latest('administered_at')->limit(6)->get()
            : collect();

        return [
            'filters' => $filters,
            'stats' => $stats,
            'trackingRows' => $dueRows->count(),
            'alerts' => $alerts,
            'nearExpiryDays' => $nearExpiryDays,
            'recentActivity' => $recentActivity,
            'centres' => Centre::query()->when($user->isCentreScoped(), fn ($query) => $query->whereIn('id', $user->centres()->select('centres.id')))->orderBy('name')->get(['id', 'name']),
            'vaccines' => Vaccine::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
