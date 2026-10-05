<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Support\Str;

class CreateProject
{
    /** @param array{name: string, description?: string|null} $data */
    public function handle(Workspace $workspace, array $data): Project
    {
        return $workspace->projects()->create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($workspace, $data['name']),
            'description' => $data['description'] ?? null,
        ]);
    }

    private function uniqueSlug(Workspace $workspace, string $name): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;
        $suffix = 2;

        while ($workspace->projects()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
