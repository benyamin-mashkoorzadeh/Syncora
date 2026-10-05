<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && ($this->user()?->can('create', [Task::class, $project]) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $project = $this->route('project');
        $workspace = $this->route('workspace');

        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', new Enum(TaskStatus::class)],
            'priority' => ['required', new Enum(TaskPriority::class)],
            'sprint_id' => ['nullable', 'integer', Rule::exists('sprints', 'id')->where('project_id', $project instanceof Project ? $project->id : 0)],
            'assignee_id' => ['nullable', 'integer', Rule::exists('workspace_memberships', 'user_id')->where('workspace_id', $workspace instanceof Workspace ? $workspace->id : 0)],
        ];
    }
}
