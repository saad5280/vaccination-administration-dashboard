<?php

namespace Tests\Feature;

use App\Models\Centre;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_can_create_a_user_with_a_role(): void
    {
        $admin = $this->userWithPermissions(['users.manage', 'roles.manage']);
        $role = Role::factory()->create(['name' => 'vaccinator', 'label' => 'Vaccinator']);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Nurse Example',
            'email' => 'nurse@example.test',
            'password' => 'Nurse-password-123',
            'password_confirmation' => 'Nurse-password-123',
            'roles' => [$role->id],
        ])->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'nurse@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Nurse-password-123', $user->password));
        $this->assertSame(['vaccinator'], $user->roles()->pluck('name')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'auditable_id' => $user->id]);
    }

    public function test_role_manager_can_assign_centres_to_centre_scoped_staff(): void
    {
        $admin = $this->userWithPermissions(['users.manage', 'roles.manage']);
        $vaccinator = Role::factory()->create(['name' => 'vaccinator', 'label' => 'Vaccinator']);
        $centre = Centre::factory()->create();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Nurse Example', 'email' => 'nurse-centre@example.test',
            'password' => 'Nurse-password-123', 'password_confirmation' => 'Nurse-password-123',
            'roles' => [$vaccinator->id], 'centre_ids' => [$centre->id],
        ])->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'nurse-centre@example.test')->firstOrFail();
        $this->assertSame([$centre->id], $user->centres()->pluck('centres.id')->all());
    }

    public function test_role_manager_can_render_user_creation_form(): void
    {
        $admin = $this->userWithPermissions(['users.manage', 'roles.manage']);
        Role::factory()->create(['name' => 'vaccinator', 'label' => 'Vaccinator']);

        $this->actingAs($admin)->get(route('users.create'))->assertOk()->assertSee('Temporary password');
    }

    public function test_user_without_user_management_permission_is_forbidden(): void
    {
        $staff = $this->userWithPermissions(['dashboard.view']);

        $this->actingAs($staff)->get(route('users.index'))->assertForbidden();
    }

    public function test_user_list_escapes_untrusted_names(): void
    {
        $admin = $this->userWithPermissions(['users.manage']);
        User::factory()->create(['name' => '<script>alert(1)</script>']);

        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_administrator_can_reactivate_an_inactive_user(): void
    {
        $admin = $this->userWithPermissions(['users.manage']);
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($admin)->post(route('users.activate', $user))->assertRedirect(route('users.index'));

        $this->assertTrue($user->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.activated', 'auditable_id' => $user->id]);
    }

    public function test_administrator_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->userWithPermissions(['users.manage', 'roles.manage'], 'administrator');

        $this->actingAs($admin)->post(route('users.deactivate', $admin))->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_last_active_administrator_cannot_be_deactivated(): void
    {
        $adminRole = Role::factory()->create(['name' => 'administrator']);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);
        $staff = $this->userWithPermissions(['users.manage'], 'account-manager');

        $this->actingAs($staff)->post(route('users.deactivate', $admin))->assertUnprocessable();

        $this->assertTrue($admin->fresh()->is_active);
    }

    private function userWithPermissions(array $permissionNames, string $roleName = 'test-role'): User
    {
        $role = Role::factory()->create(['name' => $roleName]);
        $permissions = collect($permissionNames)->map(fn (string $name) => Permission::factory()->create(['name' => $name]));
        $role->permissions()->attach($permissions->pluck('id')->all());
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
