<?php

namespace Tests\Feature;

use App\Livewire\GuardiansIndex;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GuardianManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guardian_directory_search_is_livewire_driven_and_keeps_centre_scope(): void
    {
        $user = $this->userWithPermissions(['children.view']);
        $child = Child::factory()->create();
        $guardian = Guardian::factory()->create(['full_name' => 'Nadia Example']);
        $child->guardians()->attach($guardian->id, [
            'relationship' => 'Parent', 'is_primary' => true, 'consent_status' => 'granted',
            'consent_recorded_at' => now(), 'consent_recorded_by' => $user->id,
        ]);

        Livewire::actingAs($user)->test(GuardiansIndex::class)
            ->set('search', 'Nadia')
            ->assertSee('Nadia Example')
            ->set('search', 'Missing')
            ->assertDontSee('Nadia Example');
    }

    public function test_staff_can_create_a_guardian_contact_record(): void
    {
        $user = $this->userWithPermissions(['children.manage', 'children.view']);

        $response = $this->actingAs($user)->post(route('guardians.store'), $this->guardianData());

        $guardian = Guardian::query()->firstOrFail();
        $response->assertRedirect(route('guardians.edit', $guardian));
        $this->assertSame('+15550108899', $guardian->phone);
        $this->assertDatabaseHas('audit_logs', ['action' => 'guardian.created', 'auditable_id' => $guardian->id]);
    }

    public function test_existing_guardian_can_be_linked_to_another_child(): void
    {
        $user = $this->userWithPermissions(['children.manage', 'children.view']);
        $guardian = Guardian::factory()->create();
        $firstChild = Child::factory()->create();
        $secondChild = Child::factory()->create();
        $firstChild->guardians()->attach($guardian->id, [
            'relationship' => 'Parent',
            'is_primary' => true,
            'consent_status' => 'granted',
            'consent_recorded_at' => now(),
            'consent_recorded_by' => $user->id,
        ]);

        $this->actingAs($user)->post(route('children.guardians.store', $secondChild), [
            'guardian_id' => $guardian->id,
            'relationship' => 'Parent',
            'consent_status' => 'unknown',
        ])->assertRedirect(route('children.show', $secondChild));

        $this->assertSame(2, $guardian->children()->count());
        $this->assertDatabaseHas('child_guardian', [
            'child_id' => $secondChild->id,
            'guardian_id' => $guardian->id,
            'is_primary' => false,
            'consent_recorded_by' => $user->id,
        ]);
    }

    public function test_staff_without_management_permission_cannot_change_guardian_contacts(): void
    {
        $user = $this->userWithPermissions(['children.view']);
        $guardian = Guardian::factory()->create();

        $this->actingAs($user)->put(route('guardians.update', $guardian), $this->guardianData())->assertForbidden();

        $this->assertSame($guardian->phone, $guardian->fresh()->phone);
    }

    public function test_guardian_directory_renders_for_registration_staff(): void
    {
        $user = $this->userWithPermissions(['children.manage', 'children.view']);
        Guardian::factory()->create();

        $this->actingAs($user)->get(route('guardians.index'))->assertOk()->assertSee('Parents & guardians', false);
    }

    public function test_guardian_creation_form_renders_for_registration_staff(): void
    {
        $user = $this->userWithPermissions(['children.manage', 'children.view']);

        $this->actingAs($user)->get(route('guardians.create'))->assertOk()->assertSee('Add a guardian');
    }

    private function guardianData(): array
    {
        return [
            'full_name' => 'Ayesha Khan',
            'phone' => '+1 (555) 010-8899',
            'alternate_phone' => '',
            'email' => 'guardian@example.test',
            'address' => '15 Main Street',
            'preferred_contact_method' => 'phone',
            'preferred_language' => 'ur',
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
