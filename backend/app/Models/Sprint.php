<?php

namespace App\Models;

use App\Enums\SprintStatus;
use Database\Factories\SprintFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends Model
{
    /** @use HasFactory<SprintFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name', 'goal', 'start_date', 'end_date', 'status'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'status' => SprintStatus::class,
        ];
    }
}
