<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_signed_link_verifies_authenticated_user(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'api.v1.auth.verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
            absolute: false,
        );
        Event::fake([Verified::class]);

        $response = $this->actingAs($user)->getJson($url, $this->spaHeaders());

        $response->assertOk()->assertJsonPath('data.is_verified', true);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class, fn (Verified $event): bool => $event->user->is($user));
    }

    public function test_invalid_signature_returns_403_without_verifying_user(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->getJson(
            "/api/v1/auth/email/verify/{$user->id}/".sha1($user->email).'?expires=1&signature=invalid',
            $this->spaHeaders(),
        );

        $response->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_user_can_resend_verification_notification(): void
    {
        $user = User::factory()->unverified()->create();
        Notification::fake();

        $response = $this->actingAs($user)->postJson(
            '/api/v1/auth/email/verification-notification',
            [],
            $this->spaHeaders(),
        );

        $response->assertAccepted()->assertJsonPath('message', 'A fresh verification link has been sent.');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verified_user_does_not_receive_another_verification_notification(): void
    {
        $user = User::factory()->create();
        Notification::fake();

        $response = $this->actingAs($user)->postJson(
            '/api/v1/auth/email/verification-notification',
            [],
            $this->spaHeaders(),
        );

        $response->assertOk()->assertJsonPath('message', 'Your email address is already verified.');
        Notification::assertNothingSent();
    }
}
