<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'password'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    public function avatarUrl(): ?string
    {
        $avatarPath = $this->getRawOriginal('avatar_path');

        return $avatarPath
            ? Storage::disk('public')->url($avatarPath)
            : null;
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = Str::lower(trim($value));
    }

    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMembership::class);
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function taskComments(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'author_id');
    }

    public function taskActivities(): HasMany
    {
        return $this->hasMany(TaskActivity::class, 'actor_id');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class, 'recipient_id');
    }

    public function actedNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class, 'actor_id');
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_memberships')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
