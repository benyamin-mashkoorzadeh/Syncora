<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AddWorkspaceMember
{
    public function handle(Workspace $workspace, string $email): WorkspaceMembership
    {
        $user = User::where('email', Str::lower(trim($email)))->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'No registered Syncora account matches this email address.',
            ]);
        }

        if ($workspace->memberships()->whereBelongsTo($user)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This person is already a workspace member.',
            ]);
        }

        try {
            return $workspace->memberships()->create([
                'user_id' => $user->getKey(),
                'role' => WorkspaceRole::Member,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => 'This person is already a workspace member.',
            ]);
        }
    }
}
