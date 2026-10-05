<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_known_email_sends_reset_notification_without_revealing_account_state(): void
    {
        $user = User::factory()->create();
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => strtoupper($user->email),
        ], $this->spaHeaders());

        $response->assertOk()->assertJsonPath(
            'message',
            'If an account exists for that email, a password reset link is on its way.',
        );
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_returns_same_forgot_password_response(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing@example.com',
        ], $this->spaHeaders());

        $response->assertOk()->assertJsonPath(
            'message',
            'If an account exists for that email, a password reset link is on its way.',
        );
        Notification::assertNothingSent();
    }

    public function test_valid_token_resets_password_and_returns_200(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword1']);
        $token = Password::createToken($user);
        Event::fake([PasswordReset::class]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword2',
            'password_confirmation' => 'NewPassword2',
        ], $this->spaHeaders());

        $response->assertOk()->assertJsonPath('message', 'Your password has been reset.');
        $this->assertTrue(Hash::check('NewPassword2', $user->fresh()->password));
        Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event): bool => $event->user->is($user));
    }

    public function test_invalid_token_returns_422_without_changing_password(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword1']);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewPassword2',
            'password_confirmation' => 'NewPassword2',
        ], $this->spaHeaders());

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertTrue(Hash::check('OldPassword1', $user->fresh()->password));
    }
}
