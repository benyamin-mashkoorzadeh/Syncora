<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\UpdateTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function index(Workspace $workspace, Project $project): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Task::class, $project]);

        return TaskResource::collection(
            $project->tasks()
                ->with(['sprint:id,name,status', 'assignee:id,name,avatar_path'])
                ->orderByRaw("case status when 'todo' then 0 when 'in_progress' then 1 else 2 end")
                ->orderBy('position')
                ->orderBy('id')
                ->get(),
        );
    }

    public function store(
        StoreTaskRequest $request,
        Workspace $workspace,
        Project $project,
        CreateTask $createTask,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $task = $createTask->handle($project, $actor, $request->validated())->load(['sprint:id,name,status', 'assignee:id,name,avatar_path']);

        return (new TaskResource($task))
            ->additional(['message' => 'Task created.'])
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function show(Workspace $workspace, Project $project, Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return new TaskResource($task->load(['project:id,name,slug', 'sprint:id,name,status', 'assignee:id,name,avatar_path']));
    }

    public function update(
        UpdateTaskRequest $request,
        Workspace $workspace,
        Project $project,
        Task $task,
        UpdateTask $updateTask,
    ): TaskResource {
        /** @var User $actor */
        $actor = $request->user();
        $task = $updateTask->handle($project, $task, $actor, $request->validated())
            ->load(['sprint:id,name,status', 'assignee:id,name,avatar_path']);

        return (new TaskResource($task))
            ->additional(['message' => 'Task updated.']);
    }
}
