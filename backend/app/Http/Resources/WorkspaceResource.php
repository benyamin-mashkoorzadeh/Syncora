<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $membership = $this->relationLoaded('memberships')
            ? $this->memberships->first()
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'membership' => $membership ? [
                'role' => $membership->role->value,
                'joined_at' => $membership->created_at?->toISOString(),
            ] : null,
            'member_count' => $this->whenCounted('memberships'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
