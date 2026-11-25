<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function builtInRolePermissions(): array
    {
        return [
            'administrator' => ['administrator', ['users.manage', 'roles.manage', 'dashboard.view', 'children.manage', 'vaccinations.record', 'vaccinations.amend', 'inventory.manage', 'reports.view', 'audit.view', 'catalogue.manage', 'schedules.manage', 'schedules.approve']],
            'vaccinator' => ['vaccinator', ['dashboard.view', 'children.view', 'vaccinations.view', 'vaccinations.record', 'due-register.view', 'appointments.manage', 'catalogue.view', 'schedules.view']],
            'registration' => ['registration', ['dashboard.view', 'children.view', 'children.manage', 'appointments.manage', 'reminders.manage']],
            'inventory officer' => ['inventory-officer', ['dashboard.view', 'inventory.manage']],
            'manager' => ['manager', ['dashboard.view', 'reports.view']],
        ];
    }

    #[DataProvider('builtInRolePermissions')]
    public function test_seeded_role_grants_its_configured_permissions(string $roleName, array $expectedPermissions): void
    {
        (new RolePermissionSeeder)->run();
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());

        foreach ($expectedPermissions as $permission) {
            $this->assertTrue($user->hasPermission($permission));
        }

        $this->assertFalse($user->hasPermission('roles.manage') && $roleName !== 'administrator');
        $this->assertFalse($user->hasPermission('inventory.manage') && $roleName !== 'administrator' && $roleName !== 'inventory-officer');
        $this->assertFalse($user->hasPermission('vaccinations.record') && ! in_array($roleName, ['administrator', 'vaccinator'], true));
        $this->assertFalse($user->hasPermission('children.view') && $roleName === 'manager');
        $this->assertFalse($user->hasPermission('vaccinations.view') && $roleName === 'manager');
        $this->assertFalse($user->hasPermission('schedules.approve') && $roleName !== 'administrator');
        $this->assertFalse($user->hasPermission('schedules.manage') && $roleName !== 'administrator');
        $this->assertFalse($user->hasPermission('vaccinations.amend') && $roleName !== 'administrator');
    }

    public function test_guest_is_redirected_from_dashboard_to_sign_in(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_user_without_dashboard_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_staff_role_can_view_dashboard(): void
    {
        $dashboardPermission = Permission::factory()->create(['name' => 'dashboard.view']);
        $role = Role::factory()->create(['name' => 'manager', 'label' => 'Manager']);
        $role->permissions()->attach($dashboardPermission);
        $manager = User::factory()->create();
        $manager->roles()->attach($role);

        $this->actingAs($manager)->get(route('dashboard'))->assertOk();
    }

    public function test_staff_without_role_management_permission_is_forbidden_from_roles_page(): void
    {
        $dashboardPermission = Permission::factory()->create(['name' => 'dashboard.view']);
        $role = Role::factory()->create();
        $role->permissions()->attach($dashboardPermission);
        $staff = User::factory()->create();
        $staff->roles()->attach($role);

        $this->actingAs($staff)->get(route('roles.index'))->assertForbidden();
    }

    public function test_role_manager_can_view_role_configuration(): void
    {
        $permission = Permission::factory()->create(['name' => 'roles.manage']);
        $role = Role::factory()->create();
        $role->permissions()->attach($permission);
        $admin = User::factory()->create();
        $admin->roles()->attach($role);

        $this->actingAs($admin)->get(route('roles.index'))->assertOk()->assertSee('Roles &amp; permissions', false);
    }

    public function test_role_manager_can_render_create_screen(): void
    {
        $manager = $this->roleManager();

        $this->actingAs($manager)->get(route('roles.create'))->assertOk()->assertSee('Create a role');
    }

    public function test_role_manager_can_render_edit_screen(): void
    {
        $manager = $this->roleManager();
        $editableRole = Role::factory()->create(['name' => 'custom-nurse']);

        $this->actingAs($manager)->get(route('roles.edit', $editableRole))->assertOk()->assertSee('Edit '.$editableRole->label);
    }

    public function test_inactive_user_with_existing_session_is_logged_out(): void
    {
        $permission = Permission::factory()->create(['name' => 'dashboard.view']);
        $role = Role::factory()->create();
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['is_active' => false]);
        $user->roles()->attach($role);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_administrator_permissions_cannot_be_changed_in_the_web_interface(): void
    {
        $permission = Permission::factory()->create(['name' => 'roles.manage']);
        $adminRole = Role::factory()->create(['name' => 'administrator', 'label' => 'Administrator']);
        $adminRole->permissions()->attach($permission);
        $admin = User::factory()->create();
        $admin->roles()->attach($adminRole);

        $this->actingAs($admin)->put(route('roles.update', $adminRole), [
            'permissions' => [],
        ])->assertNotFound();

        $this->assertDatabaseHas('permission_role', [
            'role_id' => $adminRole->id,
            'permission_id' => $permission->id,
        ]);
    }

    private function roleManager(): User
    {
        $permission = Permission::factory()->create(['name' => 'roles.manage']);
        $role = Role::factory()->create(['name' => 'role-manager']);
        $role->permissions()->attach($permission);
        $manager = User::factory()->create();
        $manager->roles()->attach($role);

        return $manager;
    }
}
