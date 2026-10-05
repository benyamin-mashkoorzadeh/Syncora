<?php

namespace App\Http\Requests\Task;

use App\Models\Task;

class StoreTaskCommentRequest extends TaskCommentBodyRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task && ($this->user()?->can('view', $task) ?? false);
    }
}
