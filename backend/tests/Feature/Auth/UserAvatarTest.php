<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAvatarTest extends TestCase
{
    use DatabaseMigrations;

    public function test_authenticated_user_can_upload_and_replace_their_avatar_without_orphaning_the_old_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/api/v1/auth/user/avatar', [
            'avatar' => UploadedFile::fake()->image('first.jpg', 320, 320)->size(400),
        ], $this->spaHeaders())->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('message', 'Profile image updated.')
            ->assertJson(fn ($json) => $json->whereType('data.avatar_url', 'string')->etc());

        $firstPath = $user->refresh()->avatar_path;
        Storage::disk('public')->assertExists($firstPath);

        $this->actingAs($user)->post('/api/v1/auth/user/avatar', [
            'avatar' => UploadedFile::fake()->image('replacement.png', 480, 480)->size(600),
        ], $this->spaHeaders())->assertOk();

        $replacementPath = $user->refresh()->avatar_path;
        $this->assertNotSame($firstPath, $replacementPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($replacementPath);
    }

    public function test_authenticated_user_can_remove_avatar_and_fallback_is_returned_as_null(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $path = UploadedFile::fake()->image('avatar.webp')->store('avatars', 'public');
        $user->forceFill(['avatar_path' => $path])->save();

        $this->actingAs($user)->deleteJson('/api/v1/auth/user/avatar', [], $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.avatar_url', null)
            ->assertJsonPath('message', 'Profile image removed.');

        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_avatar_upload_requires_a_supported_image_within_the_size_limit(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/api/v1/auth/user/avatar', [
            'avatar' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
        ], $this->spaHeaders())->assertUnprocessable()->assertInvalid('avatar');

        $this->actingAs($user)->post('/api/v1/auth/user/avatar', [
            'avatar' => UploadedFile::fake()->image('large.jpg')->size(2049),
        ], $this->spaHeaders())->assertUnprocessable()->assertInvalid('avatar');

        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk('public')->assertDirectoryEmpty('avatars');
    }

    public function test_avatar_endpoints_require_authentication(): void
    {
        Storage::fake('public');

        $this->post('/api/v1/auth/user/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ], $this->spaHeaders())->assertUnauthorized();
        $this->deleteJson('/api/v1/auth/user/avatar', [], $this->spaHeaders())->assertUnauthorized();
    }
}
