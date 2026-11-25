<?php

namespace Tests\Feature;

use App\Livewire\AppointmentsIndex;
use App\Models\Appointment;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\ScheduleDose;
use App\Models\User;
use App\Models\Vaccine;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AppointmentManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_appointment_calendar_filters_update_through_livewire(): void
    {
        [$staff, $child, $centre, $vaccinator] = $this->setupCase();
        $appointment = Appointment::query()->create([
            'child_id' => $child->id,
            'centre_id' => $centre->id,
            'vaccinator_id' => $vaccinator->id,
            'scheduled_at' => now()->startOfDay()->addHours(9),
            'status' => 'missed',
            'created_by' => $staff->id,
        ]);

        Livewire::actingAs($staff)->test(AppointmentsIndex::class)
            ->set('view', 'day')
            ->set('appointmentDate', now()->toDateString())
            ->set('status', 'missed')
            ->assertSee($appointment->child->registration_id)
            ->assertSee('Missed')
            ->set('status', 'invalid')
            ->assertHasErrors('status');
    }

    public function test_staff_books_appointment_with_optional_approved_dose_and_audit_entry(): void
    {
        [$staff, $child, $centre, $vaccinator, $dose] = $this->setupCase();

        $this->actingAs($staff)->get(route('appointments.create'))->assertOk()->assertSee('Book appointment');
        $this->actingAs($staff)->post(route('appointments.store'), $this->appointmentData($child, $centre, $vaccinator, $dose))
            ->assertRedirect(route('appointments.index'));

        $this->assertDatabaseHas('appointments', [
            'child_id' => $child->id,
            'centre_id' => $centre->id,
            'vaccinator_id' => $vaccinator->id,
            'schedule_dose_id' => $dose->id,
            'status' => 'booked',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.booked']);
    }

    public function test_duplicate_child_time_and_vaccinator_time_are_rejected(): void
    {
        [$staff, $child, $centre, $vaccinator, $dose] = $this->setupCase();
        $data = $this->appointmentData($child, $centre, $vaccinator, $dose);
        $this->actingAs($staff)->post(route('appointments.store'), $data)->assertRedirect();

        $this->actingAs($staff)->from(route('appointments.create'))
            ->post(route('appointments.store'), $data)
            ->assertSessionHasErrors('scheduled_at');

        $otherChild = Child::factory()->create(['date_of_birth' => '2021-01-01']);
        $this->actingAs($staff)->from(route('appointments.create'))
            ->post(route('appointments.store'), $this->appointmentData($otherChild, $centre, $vaccinator, $dose))
            ->assertSessionHasErrors('vaccinator_id');

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_staff_can_reschedule_and_mark_a_past_visit_missed(): void
    {
        [$staff, $child, $centre, $vaccinator, $dose] = $this->setupCase();
        $this->actingAs($staff)->post(route('appointments.store'), $this->appointmentData($child, $centre, $vaccinator, $dose))->assertRedirect();
        $appointment = Appointment::query()->firstOrFail();

        $this->actingAs($staff)->get(route('appointments.edit', $appointment))->assertOk()->assertSee('Reschedule appointment');
        $this->actingAs($staff)->put(route('appointments.update', $appointment), [
            'scheduled_at' => now()->addDays(2)->setTime(11, 30)->format('Y-m-d\\TH:i'),
            'centre_id' => $centre->id,
            'vaccinator_id' => $vaccinator->id,
            'notes' => 'Updated slot',
        ])->assertRedirect(route('appointments.index'));
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'notes' => 'Updated slot']);

        $appointment->update(['scheduled_at' => now()->subDay()]);
        $this->actingAs($staff)->post(route('appointments.status', $appointment), ['status' => 'missed'])
            ->assertRedirect(route('appointments.index'));
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'missed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.missed', 'auditable_id' => $appointment->id]);
    }

    public function test_cancellation_requires_a_reason_and_attended_cannot_be_marked_in_future(): void
    {
        [$staff, $child, $centre, $vaccinator, $dose] = $this->setupCase();
        $this->actingAs($staff)->post(route('appointments.store'), $this->appointmentData($child, $centre, $vaccinator, $dose))->assertRedirect();
        $appointment = Appointment::query()->firstOrFail();

        $this->actingAs($staff)->post(route('appointments.status', $appointment), ['status' => 'cancelled'])
            ->assertSessionHasErrors('cancellation_reason');
        $this->actingAs($staff)->post(route('appointments.status', $appointment), ['status' => 'attended'])
            ->assertSessionHasErrors('status');
        $this->assertSame('booked', $appointment->fresh()->status);

        $this->actingAs($staff)->post(route('appointments.status', $appointment), [
            'status' => 'cancelled', 'cancellation_reason' => 'Guardian requested cancellation',
        ])->assertRedirect();
        $this->assertSame('cancelled', $appointment->fresh()->status);
    }

    public function test_past_appointment_can_be_marked_attended_without_claiming_a_vaccination_was_administered(): void
    {
        [$staff, $child, $centre, $vaccinator, $dose] = $this->setupCase();
        $this->actingAs($staff)->post(route('appointments.store'), $this->appointmentData($child, $centre, $vaccinator, $dose))->assertRedirect();
        $appointment = Appointment::query()->firstOrFail();
        $appointment->update(['scheduled_at' => now()->subHour()]);

        $this->actingAs($staff)->post(route('appointments.status', $appointment), ['status' => 'attended'])->assertRedirect();

        $this->assertSame('attended', $appointment->fresh()->status);
        $this->assertDatabaseCount('vaccination_records', 0);
    }

    public function test_user_without_appointment_permission_is_forbidden(): void
    {
        $user = $this->userWithPermissions(['children.view']);
        $this->actingAs($user)->get(route('appointments.index'))->assertForbidden();
    }

    /** @return array{User,Child,Centre,User,ScheduleDose} */
    private function setupCase(): array
    {
        $staff = $this->userWithPermissions(['children.view', 'appointments.manage']);
        $vaccinator = $this->userWithPermissions(['children.view', 'vaccinations.view', 'vaccinations.record']);
        $child = Child::factory()->create(['date_of_birth' => '2021-01-01']);
        $centre = Centre::factory()->create(['is_active' => true]);
        $schedule = Schedule::factory()->create([
            'approval_status' => 'approved', 'effective_from' => now()->subYear()->toDateString(), 'effective_until' => null,
        ]);
        $dose = ScheduleDose::factory()->for($schedule)->for(Vaccine::factory()->create())->create(['dose_number' => 1]);

        return [$staff, $child, $centre, $vaccinator, $dose];
    }

    private function appointmentData(Child $child, Centre $centre, User $vaccinator, ScheduleDose $dose): array
    {
        return [
            'child_id' => $child->id,
            'schedule_dose_id' => $dose->id,
            'centre_id' => $centre->id,
            'vaccinator_id' => $vaccinator->id,
            'scheduled_at' => now()->addDay()->setTime(10, 0)->format('Y-m-d\\TH:i'),
            'notes' => 'Routine appointment',
        ];
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::factory()->create();
        $permissionIds = collect($permissionNames)->map(fn (string $name): int => Permission::query()->firstOrCreate(
            ['name' => $name], ['label' => str($name)->replace('.', ' ')->title()->toString()],
        )->id);
        $role->permissions()->attach($permissionIds);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
