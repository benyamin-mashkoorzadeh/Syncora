<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tasks\AddTaskComment;
use App\Actions\Tasks\DeleteTaskComment;
use App\Actions\Tasks\UpdateTaskComment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskCommentRequest;
use App\Http\Requests\Task\UpdateTaskCommentRequest;
use App\Http\Resources\TaskCommentResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskCommentController extends Controller
{
    public function index(Workspace $workspace, Project $project, Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        return TaskCommentResource::collection(
            $task->comments()->with('author:id,name,avatar_path')->latest('id')->paginate(20),
        );
    }

    public function store(
        StoreTaskCommentRequest $request,
        Workspace $workspace,
        Project $project,
        Task $task,
        AddTaskComment $addTaskComment,
    ): JsonResponse {
        /** @var User $author */
        $author = $request->user();
        $comment = $addTaskComment->handle($task, $author, $request->string('body')->toString())
            ->load('author:id,name,avatar_path');

        return (new TaskCommentResource($comment))
            ->additional(['message' => 'Comment added.'])
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(
        UpdateTaskCommentRequest $request,
        Workspace $workspace,
        Project $project,
        Task $task,
        TaskComment $comment,
        UpdateTaskComment $updateTaskComment,
    ): TaskCommentResource {
        /** @var User $actor */
        $actor = $request->user();
        $comment = $updateTaskComment
            ->handle($task, $comment, $actor, $request->string('body')->toString())
            ->load('author:id,name,avatar_path');

        return (new TaskCommentResource($comment))
            ->additional(['message' => 'Comment updated.']);
    }

    public function destroy(
        Request $request,
        Workspace $workspace,
        Project $project,
        Task $task,
        TaskComment $comment,
        DeleteTaskComment $deleteTaskComment,
    ): JsonResponse {
        Gate::authorize('delete', $comment);
        /** @var User $actor */
        $actor = $request->user();
        $deleteTaskComment->handle($task, $comment, $actor);

        return response()->json(['message' => 'Comment deleted.']);
    }
}
