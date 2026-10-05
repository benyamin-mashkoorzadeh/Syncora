<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_payload_creates_authenticated_user_and_returns_201(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '  Alex Morgan  ',
            'email' => '  ALEX@EXAMPLE.COM  ',
            'password' => 'Secure123',
            'password_confirmation' => 'Secure123',
        ], $this->spaHeaders());

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Alex Morgan')
            ->assertJsonPath('data.email', 'alex@example.com')
            ->assertJsonPath('data.is_verified', false)
            ->assertJsonMissingPath('data.password');
        $user = User::where('email', 'alex@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('Secure123', $user->password));
        $this->assertNotSame('Secure123', $user->password);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_empty_payload_returns_422_with_required_field_messages(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [], $this->spaHeaders());

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'The name field is required.')
            ->assertJsonPath('errors.email.0', 'The email field is required.')
            ->assertJsonPath('errors.password.0', 'The password field is required.');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_weak_or_unconfirmed_password_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Alex Morgan',
            'email' => 'alex@example.com',
            'password' => 'password',
            'password_confirmation' => 'different',
        ], $this->spaHeaders());

        $response->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_duplicate_normalized_email_returns_422(): void
    {
        User::factory()->create(['email' => 'alex@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Another Alex',
            'email' => ' ALEX@EXAMPLE.COM ',
            'password' => 'Secure123',
            'password_confirmation' => 'Secure123',
        ], $this->spaHeaders());

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The email has already been taken.');
        $this->assertDatabaseCount('users', 1);
    }
}
