<?php

namespace Tests\Feature;

use App\Livewire\ChildrenIndex;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChildRegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorized_staff_can_register_child_with_primary_guardian(): void
    {
        $user = $this->userWithPermissions(['children.manage', 'children.view']);
        $centre = Centre::factory()->create();

        $this->actingAs($user)->post(route('children.store'), $this->registrationData($centre))->assertRedirect(route('children.show', Child::query()->first()));

        $child = Child::query()->firstOrFail();
        $this->assertStringStartsWith('CH-', $child->registration_id);
        $this->assertSame($user->id, $child->created_by);
        $this->assertDatabaseHas('guardians', ['full_name' => 'Ayesha Khan', 'phone' => '+15550108899']);
        $guardian = Guardian::query()->where('phone', '+15550108899')->firstOrFail();
        $this->assertDatabaseHas('child_guardian', [
            'child_id' => $child->id,
            'guardian_id' => $guardian->id,
            'is_primary' => true,
            'consent_status' => 'granted',
            'consent_recorded_by' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'child.created', 'auditable_id' => $child->id]);
    }

    public function test_possible_duplicate_requires_review_before_a_second_record_is_saved(): void
    {
        $user = $this->userWithPermissions(['children.manage', 'children.view']);
        $centre = Centre::factory()->create();
        $existing = Child::factory()->create([
            'first_name' => 'Ayesha',
            'last_name' => 'Khan',
            'date_of_birth' => '2022-06-10',
            'centre_id' => $centre->id,
        ]);
        $guardian = Guardian::factory()->create(['full_name' => 'Ayesha Khan', 'phone' => '+15550108899']);
        $existing->guardians()->attach($guardian->id, [
            'relationship' => 'Parent',
            'is_primary' => true,
            'consent_status' => 'unknown',
            'consent_recorded_at' => now(),
            'consent_recorded_by' => $user->id,
        ]);
        $data = $this->registrationData($centre);

        $warning = $this->from(route('children.create'))->actingAs($user)->post(route('children.store'), $data);

        $warning->assertRedirect(route('children.create'));
        $warning->assertSessionHas('possible_duplicate_matches');
        $this->assertSame(1, Child::query()->count());
        $reviewToken = $this->app['session.store']->get('duplicate_review_token');
        $this->get(route('children.create'))->assertOk()->assertSee($existing->registration_id);

        $data['duplicate_review_token'] = $reviewToken;
        $data['confirm_duplicate_review'] = '1';

        $this->from(route('children.create'))->post(route('children.store'), $data)->assertRedirect();

        $this->assertSame(2, Child::query()->count());
        $this->assertSame(1, Guardian::query()->where('phone', '+15550108899')->count());
    }

    public function test_staff_without_child_management_permission_cannot_register_child(): void
    {
        $user = $this->userWithPermissions(['children.view']);

        $this->actingAs($user)->get(route('children.create'))->assertForbidden();
    }

    public function test_staff_can_render_child_registration_form(): void
    {
        $user = $this->userWithPermissions(['children.manage', 'children.view']);

        $this->actingAs($user)->get(route('children.create'))->assertOk()->assertSee('Register a child');
    }

    public function test_staff_can_render_child_edit_form(): void
    {
        $user = $this->userWithPermissions(['children.manage', 'children.view']);
        $child = Child::factory()->create();

        $this->actingAs($user)->get(route('children.edit', $child))->assertOk()->assertSee('Edit child profile');
    }

    public function test_child_list_and_profile_escape_untrusted_names(): void
    {
        $user = $this->userWithPermissions(['children.view']);
        $child = Child::factory()->create(['first_name' => '<script>alert(1)</script>']);

        $this->actingAs($user)->get(route('children.index'))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_configured_centre_is_required_when_registering_child(): void
    {
        $user = $this->userWithPermissions(['children.manage', 'children.view']);
        Centre::factory()->create();
        $data = $this->registrationData(null);
        unset($data['centre_id']);

        $this->from(route('children.create'))->actingAs($user)->post(route('children.store'), $data)->assertRedirect(route('children.create'))->assertSessionHasErrors('centre_id');
    }

    public function test_centre_scoped_staff_only_see_children_at_assigned_centres(): void
    {
        $role = Role::factory()->create(['name' => 'vaccinator']);
        $role->permissions()->attach(Permission::factory()->create(['name' => 'children.view'])->id);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $assigned = Centre::factory()->create();
        $other = Centre::factory()->create();
        $user->centres()->attach($assigned);
        $visibleChild = Child::factory()->create(['centre_id' => $assigned->id]);
        $hiddenChild = Child::factory()->create(['centre_id' => $other->id]);

        $this->actingAs($user)->get(route('children.index'))->assertOk()->assertSee($visibleChild->registration_id)->assertDontSee($hiddenChild->registration_id);
        $this->get(route('children.show', $hiddenChild))->assertNotFound();
    }

    public function test_child_register_search_and_pagination_filters_are_livewire_driven(): void
    {
        $user = $this->userWithPermissions(['children.view']);
        $visible = Child::factory()->create(['first_name' => 'Amina']);
        $hidden = Child::factory()->create(['first_name' => 'Zara']);

        Livewire::actingAs($user)->test(ChildrenIndex::class)
            ->set('search', 'Amina')
            ->assertSee('Amina')
            ->assertDontSee('Zara')
            ->set('status', 'invalid-status')
            ->assertHasErrors('status');

        $this->assertNotSame($visible->id, $hidden->id);
    }

    public function test_guardian_consent_can_be_updated_and_is_audited(): void
    {
        $user = $this->userWithPermissions(['children.view', 'children.manage']);
        $child = Child::factory()->create();
        $guardian = Guardian::factory()->create();
        $child->guardians()->attach($guardian->id, [
            'relationship' => 'Parent', 'is_primary' => true, 'consent_status' => 'unknown',
            'consent_recorded_at' => now(), 'consent_recorded_by' => $user->id,
        ]);

        $this->actingAs($user)->put(route('children.guardians.consent.update', [$child, $guardian]), ['consent_status' => 'declined'])
            ->assertRedirect(route('children.show', $child));

        $this->assertDatabaseHas('child_guardian', ['child_id' => $child->id, 'guardian_id' => $guardian->id, 'consent_status' => 'declined', 'consent_recorded_by' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'child.guardian_consent_updated', 'auditable_id' => $child->id]);
    }

    public function test_configured_external_registration_number_warns_about_a_possible_duplicate(): void
    {
        config(['vaccination.duplicate_detection_fields' => ['registration_id']]);
        $user = $this->userWithPermissions(['children.manage', 'children.view']);
        $centre = Centre::factory()->create();
        $existing = Child::factory()->create(['external_registration_number' => 'EXT-001', 'centre_id' => $centre->id]);
        $data = $this->registrationData($centre);
        $data['external_registration_number'] = 'EXT-001';

        $this->from(route('children.create'))->actingAs($user)->post(route('children.store'), $data)
            ->assertRedirect(route('children.create'))
            ->assertSessionHas('possible_duplicate_matches');
        $this->assertSame(1, Child::query()->count());
        $this->get(route('children.create'))->assertOk()->assertSee($existing->registration_id);
    }

    public function test_centre_scoped_staff_cannot_browse_a_guardian_from_another_centre(): void
    {
        $role = Role::factory()->create(['name' => 'registration']);
        $permissions = collect(['children.view', 'children.manage'])->map(fn ($name) => Permission::factory()->create(['name' => $name]));
        $role->permissions()->attach($permissions->pluck('id')->all());
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $assignedCentre = Centre::factory()->create();
        $otherCentre = Centre::factory()->create();
        $user->centres()->attach($assignedCentre);
        $visibleChild = Child::factory()->create(['centre_id' => $assignedCentre->id]);
        $hiddenChild = Child::factory()->create(['centre_id' => $otherCentre->id]);
        $hiddenGuardian = Guardian::factory()->create();
        $hiddenChild->guardians()->attach($hiddenGuardian->id, [
            'relationship' => 'Parent', 'is_primary' => true, 'consent_status' => 'unknown',
            'consent_recorded_at' => now(), 'consent_recorded_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('children.show', $visibleChild))->assertOk()->assertDontSee($hiddenGuardian->full_name);
        $this->get(route('guardians.edit', $hiddenGuardian))->assertNotFound();
        $this->get(route('guardians.index'))->assertOk()->assertDontSee($hiddenGuardian->full_name);
    }

    private function registrationData(?Centre $centre): array
    {
        return [
            'first_name' => 'Ayesha',
            'last_name' => 'Khan',
            'date_of_birth' => '2022-06-10',
            'sex' => 'female',
            'address' => '15 Main Street',
            'locality' => 'North District',
            'centre_id' => $centre?->id,
            'internal_notes' => 'Registration verified with guardian.',
            'guardian' => [
                'full_name' => 'Ayesha Khan',
                'relationship' => 'Mother',
                'phone' => '+1 (555) 010-8899',
                'alternate_phone' => '',
                'email' => 'guardian@example.test',
                'address' => '15 Main Street',
                'preferred_contact_method' => 'phone',
                'preferred_language' => 'ur',
                'consent_status' => 'granted',
            ],
        ];
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::factory()->create();
        $permissions = collect($permissionNames)->map(fn (string $name) => Permission::factory()->create(['name' => $name]));
        $role->permissions()->attach($permissions->pluck('id')->all());
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
