<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Child;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_audit_log_is_forbidden_without_audit_permission(): void
    {
        $user = $this->userWithPermissions(['dashboard.view']);

        $this->actingAs($user)->get(route('audit.index'))->assertForbidden();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'audit.viewed']);
    }

    public function test_audit_permission_can_view_filtered_events_and_the_access_is_recorded(): void
    {
        $user = $this->userWithPermissions(['audit.view']);
        AuditLog::query()->create([
            'actor_id' => $user->id,
            'action' => 'child.updated',
            'auditable_type' => Child::class,
            'auditable_id' => 41,
            'before_values' => ['locality' => 'North'],
            'after_values' => ['locality' => 'South'],
            'occurred_at' => now(),
        ]);

        $this->actingAs($user)->get(route('audit.index', ['action' => 'child.updated']))
            ->assertOk()
            ->assertSee('child.updated')
            ->assertSee('Child · 41')
            ->assertSee('North')
            ->assertSee('South');

        $this->assertDatabaseHas('audit_logs', ['action' => 'audit.viewed', 'actor_id' => $user->id, 'auditable_type' => 'system']);
    }

    public function test_child_profile_access_is_written_to_the_audit_log(): void
    {
        $user = $this->userWithPermissions(['children.view']);
        $child = Child::factory()->create();

        $this->actingAs($user)->get(route('children.show', $child))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'child.profile_viewed',
            'auditable_type' => Child::class,
            'auditable_id' => $child->id,
        ]);
    }

    private function userWithPermissions(array $names): User
    {
        $role = Role::factory()->create();
        foreach ($names as $name) {
            $role->permissions()->attach(Permission::factory()->create(['name' => $name]));
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
