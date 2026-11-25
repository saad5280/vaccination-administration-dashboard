<?php

namespace Tests\Feature;

use App\Models\Centre;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CentreManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_can_create_centre_for_child_assignment(): void
    {
        $admin = $this->userWithPermissions(['settings.manage']);

        $this->actingAs($admin)->post(route('centres.store'), [
            'code' => 'north-clinic',
            'name' => 'North Clinic',
            'phone' => '+15550101111',
            'email' => 'north@example.test',
            'address' => '15 Main Street',
            'locality' => 'North District',
        ])->assertRedirect(route('centres.index'));

        $centre = Centre::query()->firstOrFail();
        $this->assertSame('NORTH-CLINIC', $centre->code);
        $this->assertDatabaseHas('audit_logs', ['action' => 'centre.created', 'auditable_id' => $centre->id]);
    }

    public function test_user_without_settings_permission_cannot_manage_centres(): void
    {
        $user = $this->userWithPermissions(['children.manage']);

        $this->actingAs($user)->get(route('centres.index'))->assertForbidden();
    }

    public function test_administrator_can_render_centre_list(): void
    {
        $admin = $this->userWithPermissions(['settings.manage']);

        $this->actingAs($admin)->get(route('centres.index'))->assertOk()->assertSee('Vaccination centres');
    }

    public function test_administrator_can_render_centre_edit_screen(): void
    {
        $admin = $this->userWithPermissions(['settings.manage']);
        $centre = Centre::factory()->create();

        $this->actingAs($admin)->get(route('centres.edit', $centre))->assertOk()->assertSee('Edit vaccination centre');
    }

    public function test_administrator_can_deactivate_centre_without_deleting_history(): void
    {
        $admin = $this->userWithPermissions(['settings.manage']);
        $centre = Centre::factory()->create();

        $this->actingAs($admin)->put(route('centres.update', $centre), [
            'code' => $centre->code,
            'name' => $centre->name,
            'phone' => $centre->phone,
            'email' => $centre->email,
            'address' => $centre->address,
            'locality' => $centre->locality,
            'is_active' => '0',
        ])->assertRedirect(route('centres.index'));

        $this->assertFalse($centre->fresh()->is_active);
        $this->assertDatabaseHas('centres', ['id' => $centre->id]);
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
