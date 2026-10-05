<?php

namespace App\Models;

use App\Enums\UserNotificationType;
use Database\Factories\UserNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    /** @use HasFactory<UserNotificationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'recipient_id',
        'actor_id',
        'project_id',
        'task_id',
        'type',
        'deduplication_key',
        'read_at',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => UserNotificationType::class,
            'read_at' => 'datetime',
        ];
    }
}
