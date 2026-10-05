<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskActivityResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskActivityController extends Controller
{
    public function index(Workspace $workspace, Project $project, Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        return TaskActivityResource::collection(
            $task->activities()->with('actor:id,name,avatar_path')->latest('id')->paginate(20),
        );
    }
}
