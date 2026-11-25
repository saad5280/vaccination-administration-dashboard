<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_page_is_available_to_guests(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Sign in to your workspace');
    }

    public function test_active_user_can_sign_in_and_session_is_authenticated(): void
    {
        $user = $this->userWithPermission('dashboard.view');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Test-password-123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_can_sign_out_and_authentication_is_cleared(): void
    {
        $user = $this->userWithPermission('dashboard.view');

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_password_reset_request_does_not_disclose_unknown_email(): void
    {
        $this->post(route('password.email'), ['email' => 'unknown@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status', 'If an active account matches that email, a reset link has been sent.');
    }

    public function test_password_reset_link_is_sent_for_an_existing_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_link_can_set_a_new_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email]);
        $token = null;

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Changed-password-456',
            'password_confirmation' => 'Changed-password-456',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Changed-password-456', $user->fresh()->password));
        $passwordResetEvent = AuditLog::query()->where('action', 'auth.password_reset')->latest('id')->firstOrFail();
        $this->assertSame($user->id, $passwordResetEvent->auditable_id);
        $this->assertSame(['password_reset' => true], $passwordResetEvent->after_values);
    }

    public function test_login_attempts_are_rate_limited_after_five_failures(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => 'unknown@example.test',
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('login.store'), [
            'email' => 'unknown@example.test',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    private function userWithPermission(string $permissionName): User
    {
        $permission = Permission::factory()->create(['name' => $permissionName]);
        $role = Role::factory()->create();
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['password' => Hash::make('Test-password-123')]);
        $user->roles()->attach($role);

        return $user;
    }
}
