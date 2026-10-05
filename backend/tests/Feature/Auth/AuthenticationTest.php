<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_credentials_authenticate_user_rotate_session_and_return_200(): void
    {
        $user = User::factory()->create(['password' => 'Secure123']);
        $this->get('/sanctum/csrf-cookie', $this->spaHeaders());
        $originalSessionId = session()->getId();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => strtoupper($user->email),
            'password' => 'Secure123',
        ], $this->spaHeaders());

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($originalSessionId, session()->getId());
    }

    public function test_invalid_credentials_return_422_without_authenticating(): void
    {
        $user = User::factory()->create(['password' => 'Secure123']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Incorrect123',
        ], $this->spaHeaders());

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');
        $this->assertGuest();
    }

    public function test_authenticated_current_user_returns_safe_resource(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/auth/user', $this->spaHeaders());

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.is_verified', true)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_unauthenticated_current_user_returns_401(): void
    {
        $this->getJson('/api/v1/auth/user', $this->spaHeaders())
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_logout_invalidates_session_and_returns_user_to_guest_state(): void
    {
        $user = User::factory()->create(['password' => 'Secure123']);
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secure123',
        ], $this->spaHeaders())->assertOk();
        $authenticatedSessionId = session()->getId();

        $response = $this->postJson('/api/v1/auth/logout', [], $this->spaHeaders());

        $response->assertOk()->assertJsonPath('message', 'You have been signed out.');
        $this->assertGuest('web');
        $this->assertNotSame($authenticatedSessionId, session()->getId());
    }

    public function test_authenticated_user_receives_409_from_guest_only_endpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'Password1',
            ], $this->spaHeaders())
            ->assertConflict()
            ->assertJsonPath('message', 'You are already signed in.');
    }
}
