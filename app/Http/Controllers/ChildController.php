<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChildRequest;
use App\Http\Requests\UpdateChildRequest;
use App\Models\AuditLog;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Schedule;
use App\Models\SystemSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use JsonException;
use Throwable;

class ChildController extends Controller
{
    public function index(): View
    {
        return view('children.index');
    }

    public function create(): View
    {
        return view('children.create', [
            'centres' => Centre::query()->where('is_active', true)->when(request()->user()->isCentreScoped(), fn (Builder $query) => $query->whereIn('id', request()->user()->centres()->select('centres.id')))->orderBy('name')->get(['id', 'name']),
            'possibleDuplicates' => session('possible_duplicate_matches', []),
            'duplicateReviewToken' => session('duplicate_review_token'),
        ]);
    }

    public function store(StoreChildRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $possibleDuplicates = $this->findPossibleDuplicates($data)->filter(fn (Child $child): bool => $request->user()->canAccessChild($child))->values();

        if ($possibleDuplicates->isNotEmpty() && ! $this->duplicateReviewConfirmed($request, $data, $possibleDuplicates)) {
            return back()
                ->withInput($request->except('photograph'))
                ->with('possible_duplicate_matches', $possibleDuplicates->map(fn (Child $child): array => [
                    'id' => $child->id,
                    'registration_id' => $child->registration_id,
                    'name' => trim($child->first_name.' '.$child->last_name),
                    'date_of_birth' => $child->date_of_birth->format('M j, Y'),
                    'locality' => $child->locality,
                ])->all())
                ->with('duplicate_review_token', $this->duplicateReviewToken($data, $possibleDuplicates, $request->user()->id));
        }

        $photoPath = $request->file('photograph')?->store('children', 'local');

        try {
            $child = DB::transaction(function () use ($data, $photoPath, $request): Child {
                $guardianData = $data['guardian'];
                $guardianPhone = $this->normalizePhone($guardianData['phone']);
                $guardian = Guardian::query()
                    ->whereRaw('LOWER(full_name) = ?', [mb_strtolower(trim($guardianData['full_name']))])
                    ->where('phone', $guardianPhone)
                    ->first();

                if (! $guardian) {
                    $guardian = Guardian::query()->create([
                        'full_name' => trim($guardianData['full_name']),
                        'phone' => $guardianPhone,
                        'alternate_phone' => isset($guardianData['alternate_phone']) ? $this->normalizePhone($guardianData['alternate_phone']) : null,
                        'email' => $guardianData['email'] ?? null,
                        'address' => $guardianData['address'] ?? null,
                        'preferred_contact_method' => $guardianData['preferred_contact_method'],
                        'preferred_language' => $guardianData['preferred_language'] ?? null,
                    ]);
                }

                $childAttributes = Arr::only($data, [
                    'external_registration_number', 'first_name', 'last_name', 'date_of_birth', 'sex', 'address', 'locality', 'centre_id', 'internal_notes',
                ]);
                $child = Child::query()->create([
                    ...$childAttributes,
                    'registration_id' => 'CH-'.Str::upper(Str::random(10)),
                    'photo_path' => $photoPath,
                    'registered_at' => today()->toDateString(),
                    'status' => 'active',
                    'created_by' => $request->user()->id,
                ]);
                $child->guardians()->attach($guardian->id, [
                    'relationship' => $guardianData['relationship'],
                    'is_primary' => true,
                    'consent_status' => $guardianData['consent_status'],
                    'consent_recorded_at' => now(),
                    'consent_recorded_by' => $request->user()->id,
                ]);
                AuditLog::record($request, 'child.created', $child, null, [
                    'registration_id' => $child->registration_id,
                    'centre_id' => $child->centre_id,
                    'primary_guardian_id' => $guardian->id,
                ]);

                return $child;
            });
        } catch (Throwable $exception) {
            if ($photoPath) {
                Storage::disk('local')->delete($photoPath);
            }

            throw $exception;
        }

        return redirect()->route('children.show', $child)->with('status', 'Child profile registered.');
    }

    public function show(Request $request, Child $child): View
    {
        AuditLog::record($request, 'child.profile_viewed', $child);
        if ($request->user()->hasPermission('vaccinations.view')) {
            $child->loadCount(['vaccinationRecords as completed_vaccination_records_count' => fn (Builder $query) => $query->where('status', 'completed')]);
        }

        return view('children.show', [
            'child' => $child->load(['centre', 'guardians', 'createdBy']),
            'scheduleAssignments' => $request->user()->hasPermission('schedules.assign') ? $child->scheduleAssignments()->with(['schedule', 'assignedBy'])->limit(10)->get() : collect(),
            'assignableSchedules' => Schedule::query()->where('approval_status', 'approved')->whereDate('effective_from', '<=', today())
                ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))
                ->orderBy('name')->orderByDesc('version')->get(),
            'availableGuardians' => $request->user()->hasPermission('children.manage')
                ? Guardian::query()->whereDoesntHave('children', fn (Builder $query) => $query->whereKey($child->id))
                    ->where(fn (Builder $query) => $query->whereDoesntHave('children')->orWhereHas('children', fn (Builder $children) => $children->accessibleTo($request->user())))
                    ->orderBy('full_name')->get(['id', 'full_name', 'phone'])
                : collect(),
        ]);
    }

    public function edit(Child $child): View
    {
        return view('children.edit', [
            'child' => $child,
            'centres' => Centre::query()->where('is_active', true)->when(request()->user()->isCentreScoped(), fn (Builder $query) => $query->whereIn('id', request()->user()->centres()->select('centres.id')))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateChildRequest $request, Child $child): RedirectResponse
    {
        $data = $request->validated();
        $newPhotoPath = $request->file('photograph')?->store('children', 'local');
        $oldPhotoPath = $child->photo_path;

        try {
            DB::transaction(function () use ($data, $newPhotoPath, $request, $child): void {
                $child = Child::query()->lockForUpdate()->findOrFail($child->id);
                $before = Arr::only($child->getAttributes(), [
                    'external_registration_number', 'first_name', 'last_name', 'date_of_birth', 'sex', 'address', 'locality', 'centre_id', 'status', 'photo_path',
                ]);
                $child->fill(Arr::only($data, [
                    'external_registration_number', 'first_name', 'last_name', 'date_of_birth', 'sex', 'address', 'locality', 'centre_id', 'internal_notes',
                ]));

                if ($newPhotoPath) {
                    $child->photo_path = $newPhotoPath;
                }

                $child->save();
                AuditLog::record($request, 'child.updated', $child, $before, Arr::only($child->getAttributes(), array_keys($before)));
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath) {
                Storage::disk('local')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if ($newPhotoPath && $oldPhotoPath) {
            Storage::disk('local')->delete($oldPhotoPath);
        }

        return redirect()->route('children.show', $child)->with('status', 'Child profile updated.');
    }

    /** @return Collection<int, Child> */
    private function findPossibleDuplicates(array $data): Collection
    {
        $fields = SystemSetting::value('duplicate_detection_fields', config('vaccination.duplicate_detection_fields'));
        $guardianPhone = $this->normalizePhone($data['guardian']['phone']);
        $checksConfigured = (in_array('name', $fields, true) && in_array('date_of_birth', $fields, true))
            || in_array('guardian_phone', $fields, true)
            || ((in_array('registration_id', $fields, true) || in_array('external_registration_number', $fields, true)) && filled($data['external_registration_number'] ?? null));

        if (! $checksConfigured) {
            return Child::query()->whereKey([])->get();
        }

        return Child::query()->accessibleTo(request()->user())
            ->where(function (Builder $query) use ($data, $fields, $guardianPhone): void {
                if (in_array('name', $fields, true) && in_array('date_of_birth', $fields, true)) {
                    $query->where(function (Builder $query) use ($data): void {
                        $query->whereRaw('LOWER(first_name) = ?', [mb_strtolower(trim($data['first_name']))])
                            ->whereDate('date_of_birth', $data['date_of_birth']);

                        if (filled($data['last_name'] ?? null)) {
                            $query->whereRaw('LOWER(COALESCE(last_name, ?)) = ?', ['', mb_strtolower(trim($data['last_name']))]);
                        }
                    });
                }

                if (in_array('guardian_phone', $fields, true)) {
                    $query->orWhereHas('guardians', fn (Builder $guardians) => $guardians->where('phone', $guardianPhone));
                }

                if ((in_array('registration_id', $fields, true) || in_array('external_registration_number', $fields, true)) && filled($data['external_registration_number'] ?? null)) {
                    $query->orWhere('external_registration_number', $data['external_registration_number']);
                }
            })
            ->orderBy('registration_id')
            ->limit(10)
            ->get(['id', 'registration_id', 'first_name', 'last_name', 'date_of_birth', 'locality']);
    }

    private function duplicateReviewConfirmed(StoreChildRequest $request, array $data, Collection $matches): bool
    {
        if (! $request->boolean('confirm_duplicate_review') || ! $request->filled('duplicate_review_token')) {
            return false;
        }

        try {
            $review = json_decode(Crypt::decryptString($request->string('duplicate_review_token')->toString()), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return false;
        }

        return ($review['expires_at'] ?? 0) >= now()->timestamp
            && ($review['actor_id'] ?? null) === $request->user()->id
            && ($review['fingerprint'] ?? null) === $this->duplicateFingerprint($data)
            && ($review['match_ids'] ?? []) === $matches->modelKeys();
    }

    private function duplicateReviewToken(array $data, Collection $matches, int $actorId): string
    {
        return Crypt::encryptString(json_encode([
            'actor_id' => $actorId,
            'fingerprint' => $this->duplicateFingerprint($data),
            'match_ids' => $matches->modelKeys(),
            'expires_at' => now()->addMinutes(SystemSetting::value('duplicate_review_minutes', config('vaccination.duplicate_review_minutes', 15)))->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    private function duplicateFingerprint(array $data): string
    {
        return hash('sha256', json_encode([
            'first_name' => mb_strtolower(trim($data['first_name'])),
            'last_name' => mb_strtolower(trim($data['last_name'] ?? '')),
            'date_of_birth' => $data['date_of_birth'],
            'external_registration_number' => mb_strtolower(trim($data['external_registration_number'] ?? '')),
            'guardian_name' => mb_strtolower(trim($data['guardian']['full_name'])),
            'guardian_phone' => $this->normalizePhone($data['guardian']['phone']),
        ], JSON_THROW_ON_ERROR));
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';
    }
}
