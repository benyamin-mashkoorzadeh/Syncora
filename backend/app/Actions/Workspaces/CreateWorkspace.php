<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateWorkspace
{
    public function handle(User $owner, string $name): Workspace
    {
        return DB::transaction(function () use ($owner, $name): Workspace {
            $workspace = Workspace::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
            ]);

            $workspace->memberships()->create([
                'user_id' => $owner->getKey(),
                'role' => WorkspaceRole::Owner,
            ]);

            return $workspace;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';

        if (in_array($base, Workspace::RESERVED_SLUGS, true)) {
            $base = 'workspace-'.$base;
        }

        $slug = $base;
        $suffix = 2;

        while (Workspace::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
