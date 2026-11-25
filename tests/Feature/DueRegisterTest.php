<?php

namespace Tests\Feature;

use App\Livewire\DueRegisterIndex;
use App\Models\Child;
use App\Models\ChildDoseDirective;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\ScheduleDose;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\Vaccine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DueRegisterTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-24 12:00:00', config('app.timezone')));
    }

    public function test_register_calculates_upcoming_due_and_overdue_from_approved_schedule_age(): void
    {
        [$scheduleVaccine, $otherVaccine] = $this->scheduleWithDose(['recommended_age_days' => 60]);
        $user = $this->userWithPermissions(['children.view', 'due-register.view']);
        $upcoming = Child::factory()->create(['first_name' => 'Upcoming', 'date_of_birth' => '2026-10-10']);
        $due = Child::factory()->create(['first_name' => 'DueToday', 'date_of_birth' => '2026-09-25']);
        $overdue = Child::factory()->create(['first_name' => 'Overdue', 'date_of_birth' => '2026-08-01']);

        foreach ([$upcoming, $due, $overdue] as $child) {
            VaccinationRecord::factory()->create([
                'child_id' => $child->id,
                'vaccine_id' => $otherVaccine->id,
                'dose_number' => 1,
                'administered_at' => '2026-11-01 09:00:00',
            ]);
        }

        $this->actingAs($user)->get(route('due-register.index'))
            ->assertOk()
            ->assertSee('Upcoming · 30 days')
            ->assertSee('DueToday')
            ->assertSee('Overdue')
            ->assertSee('Nov 24, 2026')
            ->assertSee('Sep 30, 2026');
    }

    public function test_later_dose_due_date_uses_configured_interval_from_last_recorded_dose(): void
    {
        [$vaccine, $otherVaccine, $schedule] = $this->scheduleWithTwoDoses();
        $user = $this->userWithPermissions(['children.view', 'due-register.view']);
        $child = Child::factory()->create(['first_name' => 'IntervalChild', 'date_of_birth' => '2026-01-01']);
        VaccinationRecord::factory()->create([
            'child_id' => $child->id,
            'vaccine_id' => $vaccine->id,
            'schedule_dose_id' => $schedule->doses()->where('dose_number', 1)->first()->id,
            'dose_number' => 1,
            'administered_at' => '2026-11-01 09:00:00',
        ]);
        VaccinationRecord::factory()->create([
            'child_id' => $child->id,
            'vaccine_id' => $otherVaccine->id,
            'administered_at' => '2026-11-02 09:00:00',
        ]);

        $this->actingAs($user)->get(route('due-register.index', ['vaccine_id' => $vaccine->id]))
            ->assertOk()
            ->assertSee('IntervalChild')
            ->assertSee('Nov 29, 2026')
            ->assertSee('Upcoming');
    }

    public function test_minimum_interval_floor_is_not_treated_as_the_recommended_due_interval(): void
    {
        $schedule = Schedule::factory()->create([
            'name' => 'Authorized minimum interval schedule',
            'approval_status' => 'approved',
            'effective_from' => '2020-01-01',
            'effective_until' => null,
        ]);
        $vaccine = Vaccine::factory()->create();
        $otherVaccine = Vaccine::factory()->create();
        $firstDose = ScheduleDose::factory()->for($schedule)->for($vaccine)->create(['dose_number' => 1]);
        ScheduleDose::factory()->for($schedule)->for($vaccine)->create([
            'dose_number' => 2,
            'recommended_age_days' => 90,
            'interval_from_previous_days' => null,
            'minimum_interval_days' => 28,
        ]);
        $user = $this->userWithPermissions(['children.view', 'due-register.view']);
        $child = Child::factory()->create(['first_name' => 'FloorChild', 'date_of_birth' => '2026-01-01']);
        VaccinationRecord::factory()->create([
            'child_id' => $child->id,
            'vaccine_id' => $vaccine->id,
            'schedule_dose_id' => $firstDose->id,
            'dose_number' => 1,
            'administered_at' => '2026-11-01 09:00:00',
        ]);
        VaccinationRecord::factory()->create(['child_id' => $child->id, 'vaccine_id' => $otherVaccine->id]);

        $this->actingAs($user)->get(route('due-register.index', ['vaccine_id' => $vaccine->id]))
            ->assertOk()
            ->assertSee('FloorChild')
            ->assertSee('Apr 1, 2026')
            ->assertSee('Overdue');
    }

    public function test_children_with_no_completed_history_are_separated_as_unknown_history(): void
    {
        $this->scheduleWithDose(['recommended_age_days' => 60]);
        $user = $this->userWithPermissions(['children.view', 'due-register.view']);
        Child::factory()->create(['first_name' => 'UnknownChild']);

        $this->actingAs($user)->get(route('due-register.index', ['status' => 'unknown_history']))
            ->assertOk()
            ->assertSee('UnknownChild')
            ->assertSee('History review')
            ->assertSee('Not calculable');
    }

    public function test_unapproved_or_ineffective_schedule_does_not_generate_due_dates(): void
    {
        $schedule = Schedule::factory()->create([
            'approval_status' => 'draft',
            'effective_from' => '2026-01-01',
        ]);
        $vaccine = Vaccine::factory()->create();
        ScheduleDose::factory()->for($schedule)->for($vaccine)->create(['recommended_age_days' => 1]);
        $user = $this->userWithPermissions(['children.view', 'due-register.view']);

        $this->actingAs($user)->get(route('due-register.index'))
            ->assertOk()
            ->assertSee('No approved schedule is effective today.')
            ->assertDontSee('Overdue</span>');
    }

    public function test_due_register_requires_both_child_and_due_register_permissions(): void
    {
        $user = $this->userWithPermissions(['children.view']);

        $this->actingAs($user)->get(route('due-register.index'))->assertForbidden();
    }

    public function test_clinical_dose_directive_requires_reason_and_updates_tracking_date(): void
    {
        [$vaccine, $otherVaccine] = $this->scheduleWithDose(['recommended_age_days' => 60]);
        $dose = ScheduleDose::query()->where('vaccine_id', $vaccine->id)->firstOrFail();
        $child = Child::factory()->create(['date_of_birth' => '2026-08-01']);
        VaccinationRecord::factory()->create(['child_id' => $child->id, 'vaccine_id' => $otherVaccine->id]);
        $user = $this->userWithPermissions(['children.view', 'due-register.view', 'vaccinations.clinical_authorize']);

        $this->actingAs($user)->post(route('children.dose-directives.store', $child), [
            'schedule_dose_id' => $dose->id,
            'status' => 'deferred',
            'due_date_override' => '2027-01-10',
            'reason' => 'Clinician requested follow-up date.',
        ])->assertRedirect(route('due-register.index'));

        $this->assertDatabaseHas('child_dose_directives', [
            'child_id' => $child->id,
            'schedule_dose_id' => $dose->id,
            'status' => 'deferred',
            'authorized_by' => $user->id,
        ]);
        $this->assertSame('2027-01-10', ChildDoseDirective::query()->firstOrFail()->due_date_override->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'child.dose_directive_updated']);
        $this->get(route('due-register.index', ['status' => 'deferred']))->assertOk()->assertSee('Jan 10, 2027')->assertSee('deferred');
        Livewire::actingAs($user)->test(DueRegisterIndex::class)
            ->set('status', 'deferred')
            ->assertSee('Jan 10, 2027')
            ->set('status', 'invalid')
            ->assertHasErrors('status');
    }

    public function test_clinical_dose_directive_cannot_be_saved_without_reason(): void
    {
        [$vaccine] = $this->scheduleWithDose();
        $dose = ScheduleDose::query()->where('vaccine_id', $vaccine->id)->firstOrFail();
        $child = Child::factory()->create();
        $user = $this->userWithPermissions(['children.view', 'vaccinations.clinical_authorize']);

        $this->actingAs($user)->post(route('children.dose-directives.store', $child), [
            'schedule_dose_id' => $dose->id, 'status' => 'contraindicated', 'reason' => '',
        ])->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('child_dose_directives', 0);
    }

    public function test_child_can_be_assigned_an_approved_schedule_version_with_audit_history(): void
    {
        $schedule = Schedule::factory()->create(['approval_status' => 'approved', 'effective_from' => '2020-01-01']);
        $child = Child::factory()->create();
        $user = $this->userWithPermissions(['children.view', 'schedules.assign']);

        $this->actingAs($user)->post(route('children.schedule-assignment.store', $child), [
            'schedule_id' => $schedule->id,
            'reason' => 'Confirmed with the programme lead.',
        ])->assertRedirect(route('children.show', $child));

        $this->assertDatabaseHas('child_schedule_assignments', ['child_id' => $child->id, 'schedule_id' => $schedule->id, 'assigned_by' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'child.schedule_assigned', 'auditable_id' => $child->id]);
    }

    public function test_multiple_active_schedules_require_assignment_before_due_calculation(): void
    {
        [$vaccine, $otherVaccine] = $this->scheduleWithDose(['recommended_age_days' => 60]);
        $secondSchedule = Schedule::factory()->create(['approval_status' => 'approved', 'effective_from' => '2020-01-01']);
        ScheduleDose::factory()->for($secondSchedule)->for($vaccine)->create(['dose_number' => 1, 'recommended_age_days' => 120]);
        $child = Child::factory()->create(['date_of_birth' => '2026-08-01']);
        VaccinationRecord::factory()->create(['child_id' => $child->id, 'vaccine_id' => $otherVaccine->id]);
        $user = $this->userWithPermissions(['children.view', 'due-register.view']);

        $this->actingAs($user)->get(route('due-register.index'))
            ->assertOk()
            ->assertSee('Assign the approved schedule version applicable to this child');
    }

    /** @return array{Vaccine,Vaccine} */
    private function scheduleWithDose(array $attributes = []): array
    {
        $schedule = Schedule::factory()->create([
            'name' => 'Authorized test schedule',
            'approval_status' => 'approved',
            'effective_from' => '2020-01-01',
            'effective_until' => null,
        ]);
        $vaccine = Vaccine::factory()->create();
        $otherVaccine = Vaccine::factory()->create();
        ScheduleDose::factory()->for($schedule)->for($vaccine)->create([
            'dose_number' => 1,
            'is_required' => true,
            ...$attributes,
        ]);

        return [$vaccine, $otherVaccine];
    }

    /** @return array{Vaccine,Vaccine,Schedule} */
    private function scheduleWithTwoDoses(): array
    {
        $schedule = Schedule::factory()->create([
            'name' => 'Authorized interval schedule',
            'approval_status' => 'approved',
            'effective_from' => '2020-01-01',
            'effective_until' => null,
        ]);
        $vaccine = Vaccine::factory()->create();
        $otherVaccine = Vaccine::factory()->create();
        ScheduleDose::factory()->for($schedule)->for($vaccine)->create([
            'dose_number' => 1,
            'recommended_age_days' => 60,
        ]);
        ScheduleDose::factory()->for($schedule)->for($vaccine)->create([
            'dose_number' => 2,
            'recommended_age_days' => 90,
            'interval_from_previous_days' => 28,
        ]);

        return [$vaccine, $otherVaccine, $schedule];
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::factory()->create();
        $permissionIds = collect($permissionNames)->map(fn (string $name): int => Permission::query()->firstOrCreate(
            ['name' => $name],
            ['label' => str($name)->replace('.', ' ')->title()->toString()],
        )->id);
        $role->permissions()->attach($permissionIds);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
