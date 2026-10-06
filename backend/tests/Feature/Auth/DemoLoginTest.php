<?php

namespace Tests\Feature\Auth;

use App\Actions\Demo\ProvisionDemoEnvironment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DemoLoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('demo.enabled', true);
    }

    public function test_demo_login_uses_the_normal_session_guard_when_enabled_and_provisioned(): void
    {
        $this->get('/sanctum/csrf-cookie', $this->spaHeaders());
        $originalSessionId = session()->getId();

        $response = $this->postJson('/api/v1/auth/demo', [], $this->spaHeaders());

        $demoUser = User::query()->where('email', ProvisionDemoEnvironment::USER_EMAIL)->sole();
        $response
            ->assertOk()
            ->assertJsonPath('data.id', $demoUser->id)
            ->assertJsonPath('demo.workspace_slug', ProvisionDemoEnvironment::WORKSPACE_SLUG);
        $this->assertAuthenticatedAs($demoUser);
        $this->assertNotSame($originalSessionId, session()->getId());
    }

    public function test_demo_login_fails_safely_when_disabled(): void
    {
        config()->set('demo.enabled', false);

        $this->postJson('/api/v1/auth/demo', [], $this->spaHeaders())
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'The Syncora demo is currently unavailable. Please try again later.');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => ProvisionDemoEnvironment::USER_EMAIL]);
    }

    public function test_normal_email_and_password_authentication_remains_unchanged(): void
    {
        $user = User::factory()->create(['password' => 'Secure123']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secure123',
        ], $this->spaHeaders())
            ->assertOk();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseMissing('users', ['email' => ProvisionDemoEnvironment::USER_EMAIL]);
    }
}
