<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SanctumSessionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_csrf_cookie_endpoint_returns_xsrf_cookie(): void
    {
        $response = $this->get('/sanctum/csrf-cookie', $this->spaHeaders());

        $response->assertNoContent()->assertCookie('XSRF-TOKEN');
    }

    public function test_spa_session_authenticates_subsequent_protected_request(): void
    {
        $user = User::factory()->create(['password' => 'Secure123']);
        $this->get('/sanctum/csrf-cookie', $this->spaHeaders())->assertNoContent();
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secure123',
        ], $this->spaHeaders())->assertOk();

        $this->getJson('/api/v1/auth/user', $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }
}
