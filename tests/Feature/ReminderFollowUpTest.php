<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Permission;
use App\Models\Reminder;
use App\Models\ReminderRule;
use App\Models\ReminderTemplate;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\ScheduleDose;
use App\Models\StockBatch;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\Vaccine;
use App\Models\VaccineProduct;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReminderFollowUpTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reminder_rule_and_generic_in_app_template_can_be_created_and_updated(): void
    {
        $manager = $this->userWithPermissions(['children.view', 'reminders.manage']);
        $this->actingAs($manager)->get(route('reminders.rules.index'))->assertOk()->assertSee('Reminder rules');
        $this->actingAs($manager)->post(route('reminders.rules.store'), [
            'name' => 'Upcoming dose task',
            'trigger_type' => 'before_due',
            'offset_days' => 7,
            'template_name' => 'Upcoming follow-up',
            'template_body' => 'Please review the upcoming task and guardian contact preference.',
            'language' => 'en',
        ])->assertRedirect(route('reminders.rules.index'));

        $rule = ReminderRule::query()->with('template')->firstOrFail();
        $this->assertSame('in_app', $rule->channel);
        $this->assertSame('in_app', $rule->template->channel);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reminder.rule_created', 'auditable_id' => $rule->id]);

        $this->actingAs($manager)->put(route('reminders.rules.update', $rule), [
            'name' => 'Updated upcoming task',
            'trigger_type' => 'before_due',
            'offset_days' => 5,
            'template_name' => 'Updated generic guidance',
            'template_body' => 'Please review contact preferences before outreach.',
            'language' => 'ur',
            'is_active' => 0,
        ])->assertRedirect(route('reminders.rules.index'));

        $this->assertSame('Updated upcoming task', $rule->fresh()->name);
        $this->assertFalse($rule->fresh()->is_active);
        $this->assertSame('ur', $rule->template->fresh()->language);
    }

    public function test_before_due_rule_requires_a_positive_offset(): void
    {
        $manager = $this->userWithPermissions(['children.view', 'reminders.manage']);

        $this->actingAs($manager)->from(route('reminders.rules.index'))->post(route('reminders.rules.store'), [
            'name' => 'Invalid before rule',
            'trigger_type' => 'before_due',
            'offset_days' => 0,
            'template_name' => 'Generic task',
            'template_body' => 'Please review this task.',
        ])->assertSessionHasErrors('offset_days');

        $this->assertDatabaseCount('reminder_rules', 0);
        $this->assertDatabaseCount('reminder_templates', 0);
    }

    public function test_due_reminder_generation_is_idempotent_and_attaches_only_consented_primary_guardian(): void
    {
        $today = today();
        [$child, $scheduleDose, $otherVaccine] = $this->childDueForDose($today->toDateString());
        $guardian = Guardian::factory()->create();
        $child->guardians()->attach($guardian->id, [
            'relationship' => 'parent', 'is_primary' => true, 'consent_status' => 'granted',
        ]);
        $template = ReminderTemplate::query()->create([
            'name' => 'Generic due task', 'channel' => 'in_app', 'body' => 'Please review contact preferences.', 'is_active' => true,
        ]);
        $rule = ReminderRule::query()->create([
            'name' => 'On due date', 'trigger_type' => 'on_due', 'offset_days' => 0,
            'template_id' => $template->id, 'channel' => 'in_app', 'is_active' => true,
        ]);

        $this->artisan('reminders:generate')->assertSuccessful();
        $this->artisan('reminders:generate')->assertSuccessful();

        $this->assertDatabaseCount('reminders', 1);
        $reminder = Reminder::query()->firstOrFail();
        $this->assertSame($child->id, $reminder->child_id);
        $this->assertSame($guardian->id, $reminder->guardian_id);
        $this->assertSame($scheduleDose->id, $reminder->schedule_dose_id);
        $this->assertSame($rule->id, $reminder->reminder_rule_id);
        $this->assertSame('in_app', $reminder->channel);
        $this->assertNotNull($reminder->idempotency_key);
        $this->assertSame($template->body, $reminder->template_body_snapshot);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reminder.generated', 'auditable_id' => $reminder->id]);
        $this->assertNotSame($otherVaccine->id, $scheduleDose->vaccine_id);
    }

    public function test_missed_appointment_reminder_is_generated_without_contacting_declined_guardian(): void
    {
        $staff = $this->userWithPermissions(['children.view', 'appointments.manage', 'reminders.manage']);
        $child = Child::factory()->create(['date_of_birth' => '2020-01-01']);
        $guardian = Guardian::factory()->create();
        $child->guardians()->attach($guardian->id, ['relationship' => 'parent', 'is_primary' => true, 'consent_status' => 'declined']);
        $centre = Centre::factory()->create();
        $appointment = Appointment::query()->create([
            'child_id' => $child->id,
            'centre_id' => $centre->id,
            'scheduled_at' => now()->subDays(2),
            'status' => 'missed',
            'created_by' => $staff->id,
        ]);
        ReminderRule::query()->create([
            'name' => 'Missed visit follow-up',
            'trigger_type' => 'after_missed_appointment',
            'offset_days' => 2,
            'channel' => 'in_app',
            'is_active' => true,
        ]);

        $this->artisan('reminders:generate')->assertSuccessful();

        $this->assertDatabaseHas('reminders', [
            'child_id' => $child->id,
            'appointment_id' => $appointment->id,
            'guardian_id' => null,
            'channel' => 'in_app',
            'status' => 'pending',
        ]);
    }

    public function test_staff_can_create_and_complete_manual_in_dashboard_reminder_task(): void
    {
        $manager = $this->userWithPermissions(['children.view', 'reminders.manage']);
        $child = Child::factory()->create();
        $this->actingAs($manager)->get(route('reminders.index'))->assertOk()->assertSee('Reminder queue');

        $this->actingAs($manager)->post(route('reminders.store'), [
            'child_id' => $child->id,
            'scheduled_for' => now()->addHour()->format('Y-m-d\\TH:i'),
            'outcome_notes' => 'Check consent before any contact.',
        ])->assertRedirect(route('reminders.index'));
        $reminder = Reminder::query()->firstOrFail();
        $this->actingAs($manager)->put(route('reminders.update', $reminder), [
            'status' => 'completed',
            'outcome_notes' => 'Guardian contacted and appointment booked.',
        ])->assertRedirect(route('reminders.index'));

        $this->assertSame('completed', $reminder->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reminder.completed', 'auditable_id' => $reminder->id]);
    }

    public function test_declined_guardian_cannot_be_attached_to_a_manual_reminder(): void
    {
        $manager = $this->userWithPermissions(['children.view', 'reminders.manage']);
        $child = Child::factory()->create();
        $guardian = Guardian::factory()->create();
        $child->guardians()->attach($guardian->id, ['relationship' => 'parent', 'is_primary' => true, 'consent_status' => 'declined']);

        $this->actingAs($manager)->from(route('reminders.index'))->post(route('reminders.store'), [
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'scheduled_for' => now()->addHour()->format('Y-m-d\\TH:i'),
        ])->assertSessionHasErrors('guardian_id');
        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_follow_up_outcomes_are_linked_to_child_and_audited(): void
    {
        $staff = $this->userWithPermissions(['children.view', 'appointments.manage', 'reminders.manage']);
        $child = Child::factory()->create();
        $appointment = Appointment::query()->create([
            'child_id' => $child->id,
            'centre_id' => Centre::factory()->create()->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'booked',
        ]);

        $this->actingAs($staff)->get(route('follow-ups.index'))->assertOk()->assertSee('Follow-ups');

        $this->actingAs($staff)->post(route('follow-ups.store'), [
            'child_id' => $child->id,
            'appointment_id' => $appointment->id,
            'outcome' => 'contacted',
            'contacted_at' => now()->format('Y-m-d\\TH:i'),
            'next_action_at' => now()->addDays(3)->toDateString(),
            'assigned_to' => $staff->id,
            'notes' => 'Guardian asked for a follow-up call.',
        ])->assertRedirect(route('follow-ups.index'));

        $this->assertDatabaseHas('follow_ups', ['child_id' => $child->id, 'appointment_id' => $appointment->id, 'outcome' => 'contacted']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'follow_up.recorded']);
    }

    /** @return array{Child,ScheduleDose,Vaccine} */
    private function childDueForDose(string $dueDate): array
    {
        $vaccine = Vaccine::factory()->create();
        $otherVaccine = Vaccine::factory()->create();
        $schedule = Schedule::factory()->create([
            'name' => 'Authorized reminder schedule',
            'approval_status' => 'approved',
            'effective_from' => now()->subYear()->toDateString(),
            'effective_until' => null,
        ]);
        $dose = ScheduleDose::factory()->for($schedule)->for($vaccine)->create([
            'dose_number' => 1,
            'recommended_age_days' => 60,
            'is_required' => true,
        ]);
        $child = Child::factory()->create([
            'date_of_birth' => now()->parse($dueDate)->subDays(60)->toDateString(),
        ]);
        $product = VaccineProduct::factory()->for($otherVaccine)->create();
        $batch = StockBatch::factory()->for($product, 'product')->for(Centre::factory())->create();
        VaccinationRecord::factory()->create([
            'child_id' => $child->id,
            'vaccine_id' => $otherVaccine->id,
            'stock_batch_id' => $batch->id,
            'administered_at' => now()->subDays(20),
        ]);

        return [$child, $dose, $otherVaccine];
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
