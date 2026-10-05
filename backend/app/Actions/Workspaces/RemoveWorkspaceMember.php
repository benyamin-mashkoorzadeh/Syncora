<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\WorkspaceMembership;
use Illuminate\Validation\ValidationException;

class RemoveWorkspaceMember
{
    public function handle(WorkspaceMembership $membership): void
    {
        if ($membership->role === WorkspaceRole::Owner) {
            throw ValidationException::withMessages([
                'member' => 'The workspace owner cannot be removed.',
            ]);
        }

        $membership->delete();
    }
}
