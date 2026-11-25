<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\ScheduleDose;
use App\Models\User;
use App\Models\Vaccine;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_schedule_manager_can_create_draft_and_add_configured_dose(): void
    {
        $manager = $this->userWithPermissions(['schedules.view', 'schedules.manage']);
        $vaccine = Vaccine::factory()->create();

        $this->actingAs($manager)->post(route('schedules.store'), [
            'name' => 'Authority schedule',
            'version' => 'v1',
        ])->assertSessionHasNoErrors();

        $schedule = Schedule::query()->firstOrFail();
        $this->assertSame('draft', $schedule->approval_status);
        $this->assertSame($manager->id, $schedule->created_by);

        $this->actingAs($manager)->post(route('schedules.doses.store', $schedule), [
            'vaccine_id' => $vaccine->id,
            'dose_number' => 1,
            'recommended_age_days' => 84,
            'minimum_age_days' => 70,
            'interval_from_previous_days' => null,
            'minimum_interval_days' => null,
            'is_required' => '1',
            'notes' => 'Copied from authorized reference.',
        ])->assertRedirect(route('schedules.show', $schedule));

        $dose = $schedule->doses()->firstOrFail();
        $this->assertSame(84, $dose->recommended_age_days);
        $this->assertDatabaseHas('audit_logs', ['action' => 'schedule_dose.created', 'auditable_id' => $dose->id]);
    }

    public function test_authorized_approver_can_approve_and_lock_a_schedule_version(): void
    {
        $approver = $this->userWithPermissions(['schedules.view', 'schedules.approve']);
        $schedule = Schedule::factory()->create(['name' => 'Authority schedule', 'version' => 'v1']);
        $vaccine = Vaccine::factory()->create();
        ScheduleDose::factory()->for($schedule)->for($vaccine)->create();

        $this->actingAs($approver)->post(route('schedules.approval.store', $schedule), [
            'approval_confirmation' => '1',
            'effective_from' => '2027-01-01',
            'effective_until' => '2027-12-31',
            'approval_reference' => 'Authority memorandum AUTH-123',
        ])->assertRedirect(route('schedules.show', $schedule));

        $approved = $schedule->fresh();
        $this->assertSame('approved', $approved->approval_status);
        $this->assertSame($approver->id, $approved->approved_by_id);
        $this->assertSame('Authority memorandum AUTH-123', $approved->approval_reference);
        $this->assertSame('2027-01-01', $approved->effective_from->format('Y-m-d'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'schedule.approved', 'auditable_id' => $schedule->id]);
    }

    public function test_schedule_cannot_be_approved_without_dose_entries(): void
    {
        $approver = $this->userWithPermissions(['schedules.approve']);
        $schedule = Schedule::factory()->create();

        $this->actingAs($approver)->from(route('schedules.show', $schedule))->post(route('schedules.approval.store', $schedule), [
            'approval_confirmation' => '1',
            'effective_from' => '2027-01-01',
            'approval_reference' => 'Authority memorandum',
        ])->assertSessionHasErrors(['schedule' => 'Add at least one schedule dose before approval.']);

        $this->assertSame('draft', $schedule->fresh()->approval_status);
    }

    public function test_overlapping_approved_versions_are_rejected(): void
    {
        $approver = $this->userWithPermissions(['schedules.approve']);
        $vaccine = Vaccine::factory()->create();
        $existing = Schedule::factory()->create([
            'name' => 'Authority schedule',
            'version' => 'v1',
            'approval_status' => 'approved',
            'effective_from' => '2027-01-01',
            'effective_until' => '2027-06-30',
        ]);
        ScheduleDose::factory()->for($existing)->for($vaccine)->create();
        $draft = Schedule::factory()->create(['name' => 'Authority schedule', 'version' => 'v2']);
        ScheduleDose::factory()->for($draft)->for($vaccine)->create();

        $this->actingAs($approver)->from(route('schedules.show', $draft))->post(route('schedules.approval.store', $draft), [
            'approval_confirmation' => '1',
            'effective_from' => '2027-06-01',
            'effective_until' => '2027-12-31',
            'approval_reference' => 'Authority memorandum v2',
        ])->assertSessionHasErrors(['effective_from' => 'The effective period overlaps another approved version of this schedule.']);

        $this->assertSame('draft', $draft->fresh()->approval_status);
    }

    public function test_approved_schedule_and_its_doses_are_read_only(): void
    {
        $manager = $this->userWithPermissions(['schedules.view', 'schedules.manage']);
        $schedule = Schedule::factory()->create(['approval_status' => 'approved']);
        $dose = ScheduleDose::factory()->for($schedule)->create();

        $this->actingAs($manager)->put(route('schedules.update', $schedule), [
            'name' => $schedule->name,
            'version' => $schedule->version,
        ])->assertUnprocessable();
        $this->actingAs($manager)->delete(route('schedules.doses.destroy', [$schedule, $dose]))->assertUnprocessable();

        $this->assertModelExists($dose->fresh());
    }

    public function test_new_schedule_version_copies_doses_but_starts_unapproved_and_without_dates(): void
    {
        $manager = $this->userWithPermissions(['schedules.manage']);
        $source = Schedule::factory()->create([
            'name' => 'Authority schedule',
            'version' => 'v1',
            'approval_status' => 'approved',
            'effective_from' => '2026-01-01',
            'effective_until' => '2026-12-31',
        ]);
        $vaccine = Vaccine::factory()->create();
        ScheduleDose::factory()->for($source)->for($vaccine)->create([
            'dose_number' => 2,
            'recommended_age_days' => 180,
            'minimum_interval_days' => 28,
        ]);

        $this->actingAs($manager)->post(route('schedules.versions.store', $source), ['version' => 'v2'])
            ->assertSessionHasNoErrors();

        $newVersion = Schedule::query()->where('version', 'v2')->firstOrFail();
        $this->assertSame('draft', $newVersion->approval_status);
        $this->assertNull($newVersion->effective_from);
        $this->assertNull($newVersion->approved_by_id);
        $this->assertDatabaseHas('schedule_doses', [
            'schedule_id' => $newVersion->id,
            'vaccine_id' => $vaccine->id,
            'dose_number' => 2,
            'recommended_age_days' => 180,
            'minimum_interval_days' => 28,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'schedule.version_created', 'auditable_id' => $newVersion->id]);
    }

    public function test_schedule_manager_without_approval_permission_cannot_approve(): void
    {
        $manager = $this->userWithPermissions(['schedules.manage']);
        $schedule = Schedule::factory()->create();

        $this->actingAs($manager)->post(route('schedules.approval.store', $schedule), [
            'approval_confirmation' => '1',
            'effective_from' => '2027-01-01',
            'approval_reference' => 'Authority memorandum',
        ])->assertForbidden();
    }

    public function test_schedule_list_and_detail_render_to_read_only_viewer(): void
    {
        $viewer = $this->userWithPermissions(['schedules.view']);
        $schedule = Schedule::factory()->create(['name' => 'Configured test schedule']);
        ScheduleDose::factory()->for($schedule)->create();

        $this->actingAs($viewer)->get(route('schedules.index'))->assertOk()->assertSee('Configured test schedule');
        $this->actingAs($viewer)->get(route('schedules.show', $schedule))->assertOk()->assertSee('Configured test schedule');
        $this->actingAs($viewer)->get(route('schedules.create'))->assertForbidden();
    }

    public function test_schedule_manager_can_render_draft_dose_and_version_forms(): void
    {
        $manager = $this->userWithPermissions(['schedules.view', 'schedules.manage', 'schedules.approve']);
        $schedule = Schedule::factory()->create(['name' => 'Configured authority schedule']);
        $dose = ScheduleDose::factory()->for($schedule)->create();

        $this->actingAs($manager)->get(route('schedules.create'))->assertOk()->assertSee('New schedule draft');
        $this->actingAs($manager)->get(route('schedules.edit', $schedule))->assertOk()->assertSee('Edit Configured authority schedule');
        $this->actingAs($manager)->get(route('schedules.doses.create', $schedule))->assertOk()->assertSee('Add dose entry');
        $this->actingAs($manager)->get(route('schedules.doses.edit', [$schedule, $dose]))->assertOk()->assertSee('Edit dose entry');
        $this->actingAs($manager)->get(route('schedules.versions.create', $schedule))->assertOk()->assertSee('Create a new version');
        $this->actingAs($manager)->get(route('schedules.show', $schedule))->assertOk()->assertSee('Approve this version');
    }

    public function test_seeded_roles_keep_schedule_approval_restricted_to_administrators(): void
    {
        (new RolePermissionSeeder)->run();
        $vaccinator = User::factory()->create();
        $vaccinator->roles()->attach(Role::query()->where('name', 'vaccinator')->firstOrFail());

        $this->assertTrue($vaccinator->hasPermission('schedules.view'));
        $this->assertTrue($vaccinator->hasPermission('catalogue.view'));
        $this->assertFalse($vaccinator->hasPermission('schedules.manage'));
        $this->assertFalse($vaccinator->hasPermission('schedules.approve'));
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
