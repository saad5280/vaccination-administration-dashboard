<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Centre;
use App\Models\Child;
use App\Models\FollowUp;
use App\Models\Schedule;
use App\Models\ScheduleDose;
use App\Models\StockBatch;
use App\Models\StockTransaction;
use App\Models\VaccinationRecord;
use App\Services\DoseStatusCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportsController extends Controller
{
    private const REPORTS = [
        'registrations' => 'Child registrations',
        'coverage' => 'Vaccination coverage',
        'vaccinations' => 'Completed vaccinations',
        'due' => 'Due and overdue tracking',
        'appointments' => 'Appointments',
        'follow-ups' => 'Follow-up activities',
        'stock' => 'Stock balances and expiry',
        'movements' => 'Stock movements',
        'wastage' => 'Vaccine wastage',
    ];

    public function index(Request $request): View|Response
    {
        $filters = $request->validate([
            'report' => ['nullable', 'in:registrations,coverage,vaccinations,due,appointments,follow-ups,stock,movements,wastage'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'centre_id' => ['nullable', 'integer', 'exists:centres,id'],
            'format' => ['nullable', 'in:csv,xlsx'],
        ]);
        $type = $filters['report'] ?? 'vaccinations';
        [$headers, $rows] = $this->rows($type, $filters);
        if (($filters['format'] ?? null) === 'csv') {
            return response()->streamDownload(function () use ($headers, $rows): void {
                $output = fopen('php://output', 'w');
                fputcsv($output, $headers);
                foreach ($rows as $row) {
                    fputcsv($output, array_map(fn ($value) => $this->spreadsheetSafe((string) $value), $row));
                }
                fclose($output);
            }, 'vaccination-report-'.$type.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        if (($filters['format'] ?? null) === 'xlsx') {
            abort_unless(class_exists(\ZipArchive::class), 503, 'XLSX export requires the PHP zip extension.');
            $path = tempnam(storage_path('app'), 'report-');
            $zip = new \ZipArchive;
            abort_unless($zip->open($path, \ZipArchive::OVERWRITE) === true, 500, 'Unable to prepare the Excel report.');
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $xmlRows = '';
            foreach ([$headers, ...$rows] as $rowNumber => $row) {
                $cells = '';
                foreach (array_values($row) as $column => $value) {
                    $letters = '';
                    for ($n = $column + 1; $n > 0; $n = intdiv($n - 1, 26)) {
                        $letters = chr(65 + (($n - 1) % 26)).$letters;
                    }
                    $text = htmlspecialchars($this->spreadsheetSafe((string) $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $cells .= '<c r="'.$letters.($rowNumber + 1).'" t="inlineStr"><is><t>'.$text.'</t></is></c>';
                }
                $xmlRows .= '<row r="'.($rowNumber + 1).'">'.$cells.'</row>';
            }
            $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xmlRows.'</sheetData></worksheet>');
            $zip->close();

            return response()->download($path, 'vaccination-report-'.$type.'-'.now()->format('Ymd-His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
        }

        return view('reports.index', ['reports' => self::REPORTS, 'type' => $type, 'filters' => $filters, 'headers' => $headers, 'rows' => $rows, 'centres' => Centre::query()->orderBy('name')->get(['id', 'name'])]);
    }

    private function rows(string $type, array $filters): array
    {
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;
        $centreId = $filters['centre_id'] ?? null;

        return match ($type) {
            'registrations' => $this->aggregate(Child::query()->with('centre:id,name')->when($from, fn ($q) => $q->whereDate('registered_at', '>=', $from))->when($to, fn ($q) => $q->whereDate('registered_at', '<=', $to))->when($centreId, fn ($q) => $q->where('centre_id', $centreId))->get()->groupBy(fn ($r) => ($r->centre?->name ?? 'Unassigned')."\0".$r->status), ['Centre', 'Registration status', 'Children'], fn ($key, $group) => [...explode("\0", $key), $group->count()]),
            'coverage' => $this->coverageRows($from, $to, $centreId),
            'vaccinations' => $this->aggregate(VaccinationRecord::query()->with(['centre:id,name', 'vaccine:id,name'])->where('status', 'completed')->when($from, fn ($q) => $q->whereDate('administered_at', '>=', $from))->when($to, fn ($q) => $q->whereDate('administered_at', '<=', $to))->when($centreId, fn ($q) => $q->where('centre_id', $centreId))->get()->groupBy(fn ($r) => ($r->centre?->name ?? 'Unassigned')."\0".$r->vaccine->name), ['Centre', 'Vaccine', 'Completed doses'], fn ($key, $group) => [...explode("\0", $key), $group->count()]),
            'due' => $this->dueRows($centreId),
            'appointments' => $this->aggregate(Appointment::query()->with('centre:id,name')->when($from, fn ($q) => $q->whereDate('scheduled_at', '>=', $from))->when($to, fn ($q) => $q->whereDate('scheduled_at', '<=', $to))->when($centreId, fn ($q) => $q->where('centre_id', $centreId))->get()->groupBy(fn ($r) => ($r->centre?->name ?? 'Unassigned')."\0".$r->status), ['Centre', 'Status', 'Appointments'], fn ($key, $group) => [...explode("\0", $key), $group->count()]),
            'follow-ups' => $this->aggregate(FollowUp::query()->with('child:id,centre_id')->when($from, fn ($q) => $q->whereDate('contacted_at', '>=', $from))->when($to, fn ($q) => $q->whereDate('contacted_at', '<=', $to))->when($centreId, fn ($q) => $q->whereHas('child', fn ($c) => $c->where('centre_id', $centreId)))->get()->groupBy('outcome'), ['Outcome', 'Follow-ups'], fn ($key, $group) => [$key ?: 'Not recorded', $group->count()]),
            'stock' => $this->aggregate(StockBatch::query()->with(['centre:id,name', 'product.vaccine:id,name'])->when($centreId, fn ($q) => $q->where('centre_id', $centreId))->get()->groupBy(fn ($r) => ($r->centre?->name ?? 'Unassigned')."\0".$r->product->vaccine->name."\0".$r->status), ['Centre', 'Vaccine', 'Batch status', 'Batches', 'Available quantity'], fn ($key, $group) => [...explode("\0", $key), $group->count(), (float) $group->sum('available_quantity')]),
            'movements', 'wastage' => $this->movementRows($type, $from, $to, $centreId),
        };
    }

    private function coverageRows(?string $from, ?string $to, ?int $centreId): array
    {
        $asOf = $to ? CarbonImmutable::parse($to) : CarbonImmutable::today();
        $activeSchedules = Schedule::query()->where('approval_status', 'approved')
            ->whereDate('effective_from', '<=', $asOf)
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $asOf))
            ->get(['id', 'name', 'version']);
        $scheduleDoses = ScheduleDose::query()->with(['vaccine:id,name', 'schedule:id,name,version'])
            ->where('is_required', true)->whereIn('schedule_id', $activeSchedules->modelKeys())
            ->orderBy('schedule_id')->orderBy('vaccine_id')->orderBy('dose_number')->get();
        $children = Child::query()->where('status', 'active')->with(['scheduleAssignments' => fn ($query) => $query->latest('assigned_at')])
            ->when($centreId, fn ($query) => $query->where('centre_id', $centreId))->get();
        $records = VaccinationRecord::query()->where('status', 'completed')
            ->whereDate('administered_at', '<=', $asOf)
            ->when($from, fn ($query) => $query->whereDate('administered_at', '>=', $from))
            ->get();
        $rows = [];
        $centres = Centre::query()->when($centreId, fn ($query) => $query->whereKey($centreId))->orderBy('name')->get();
        foreach ($scheduleDoses as $dose) {
            if ($dose->recommended_age_days === null) {
                continue;
            }

            foreach ($centres as $centre) {
                $eligibleChildren = $children->filter(function (Child $child) use ($dose, $activeSchedules, $asOf, $centre): bool {
                    if ($child->centre_id !== $centre->id || CarbonImmutable::parse($child->date_of_birth)->gt($asOf->subDays($dose->recommended_age_days))) {
                        return false;
                    }
                    $assignment = $child->scheduleAssignments->first();

                    return $assignment
                        ? $assignment->schedule_id === $dose->schedule_id
                        : $activeSchedules->count() === 1 && $activeSchedules->first()->id === $dose->schedule_id;
                });
                $recordedChildren = $records->filter(fn (VaccinationRecord $record): bool => $eligibleChildren->contains('id', $record->child_id)
                    && (int) $record->vaccine_id === (int) $dose->vaccine_id
                    && (int) $record->dose_number === (int) $dose->dose_number
                    && ((int) $record->schedule_dose_id === (int) $dose->id || $record->schedule_dose_id === null))
                    ->pluck('child_id')->unique()->count();
                $denominator = $eligibleChildren->count();
                $rows[] = [$centre->name, $dose->schedule->name.' · '.$dose->schedule->version, $dose->vaccine->name, $dose->dose_number, $recordedChildren, $denominator, $denominator ? round(100 * $recordedChildren / $denominator, 1).'%' : '—'];
            }
        }

        return [['Centre', 'Schedule version', 'Vaccine', 'Dose', 'Children with dose in period', 'Age-eligible assigned children', 'Recorded-dose coverage'], $rows];
    }

    private function dueRows(?int $centreId): array
    {
        $today = CarbonImmutable::today();
        $scheduleIds = Schedule::query()->where('approval_status', 'approved')->whereDate('effective_from', '<=', $today)->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today))->pluck('id');
        $doses = ScheduleDose::query()->with(['vaccine:id,code,name', 'schedule:id,name,version'])->where('is_required', true)->whereIn('schedule_id', $scheduleIds)->get();
        $children = Child::query()->where('status', 'active')->with(['centre:id,name', 'vaccinationRecords' => fn ($q) => $q->where('status', 'completed')->orderByDesc('administered_at')])->when($centreId, fn ($q) => $q->where('centre_id', $centreId))->get();
        $grouped = $children->flatMap(fn (Child $child) => app(DoseStatusCalculator::class)->forChild($child, $doses, $today))->groupBy(fn ($row) => ($row['child']->centre?->name ?? 'Unassigned')."\0".($row['vaccine']?->name ?? 'History review')."\0".$row['status']);

        return $this->aggregate($grouped, ['Centre', 'Vaccine', 'Tracking status', 'Follow-up items'], fn ($key, $group) => [...explode("\0", $key), $group->count()]);
    }

    private function movementRows(string $type, ?string $from, ?string $to, ?int $centreId): array
    {
        $query = StockTransaction::query()->with(['centre:id,name', 'stockBatch.product.vaccine:id,name'])->when($from, fn ($q) => $q->whereDate('occurred_at', '>=', $from))->when($to, fn ($q) => $q->whereDate('occurred_at', '<=', $to))->when($centreId, fn ($q) => $q->where('centre_id', $centreId));
        if ($type === 'wastage') {
            $query->where('type', 'wastage');
        }

        return $this->aggregate($query->get()->groupBy(fn ($r) => ($r->centre?->name ?? 'Unassigned')."\0".$r->stockBatch->product->vaccine->name."\0".$r->type), ['Centre', 'Vaccine', 'Movement type', 'Transactions', 'Quantity'], fn ($key, $group) => [...explode("\0", $key), $group->count(), (float) $group->sum('quantity')]);
    }

    private function aggregate($groups, array $headers, callable $map): array
    {
        return [$headers, $groups->map(fn ($group, $key) => $map($key, $group))->values()->all()];
    }

    private function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
