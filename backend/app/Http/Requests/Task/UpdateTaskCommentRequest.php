<?php

namespace App\Http\Requests\Task;

use App\Models\TaskComment;

class UpdateTaskCommentRequest extends TaskCommentBodyRequest
{
    public function authorize(): bool
    {
        $comment = $this->route('comment');

        return $comment instanceof TaskComment
            && ($this->user()?->can('update', $comment) ?? false);
    }
}
