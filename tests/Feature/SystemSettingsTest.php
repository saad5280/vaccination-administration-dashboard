<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_settings_manager_can_update_operational_defaults_and_audit_them(): void
    {
        $user = $this->userWithPermission('settings.manage');

        $this->actingAs($user)->put(route('settings.update'), [
            'duplicate_detection_fields' => ['name', 'date_of_birth', 'registration_id'],
            'duplicate_review_minutes' => 20,
            'near_expiry_days' => 60,
        ])->assertRedirect(route('settings.index'));

        $this->assertSame(['name', 'date_of_birth', 'registration_id'], SystemSetting::value('duplicate_detection_fields'));
        $this->assertSame(20, (int) SystemSetting::value('duplicate_review_minutes'));
        $this->assertSame(60, (int) SystemSetting::value('near_expiry_days'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated', 'actor_id' => $user->id]);
    }

    public function test_settings_manager_must_keep_name_and_date_of_birth_duplicate_checks_together(): void
    {
        $user = $this->userWithPermission('settings.manage');

        $this->actingAs($user)->from(route('settings.index'))->put(route('settings.update'), [
            'duplicate_detection_fields' => ['name'],
            'duplicate_review_minutes' => 15,
            'near_expiry_days' => 30,
        ])->assertRedirect(route('settings.index'))->assertSessionHasErrors('duplicate_detection_fields');
    }

    private function userWithPermission(string $permission): User
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::factory()->create(['name' => $permission]));
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
