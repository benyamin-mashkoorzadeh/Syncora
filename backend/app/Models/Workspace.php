<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name', 'slug'];

    /** @var list<string> */
    public const RESERVED_SLUGS = [
        'account',
        'forgot-password',
        'login',
        'register',
        'reset-password',
        'verify-email',
        'workspaces',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMembership::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_memberships')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function isOwner(User $user): bool
    {
        return $this->memberships()
            ->whereBelongsTo($user)
            ->where('role', WorkspaceRole::Owner)
            ->exists();
    }
}
