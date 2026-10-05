<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Projects\CreateProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function index(Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Project::class, $workspace]);

        return ProjectResource::collection(
            $workspace->projects()->orderBy('name')->paginate(20),
        );
    }

    public function store(
        StoreProjectRequest $request,
        Workspace $workspace,
        CreateProject $createProject,
    ): JsonResponse {
        $project = $createProject->handle($workspace, $request->validated());

        return (new ProjectResource($project))
            ->additional(['message' => 'Project created.'])
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function show(Workspace $workspace, Project $project): ProjectResource
    {
        Gate::authorize('view', $project);

        return new ProjectResource($project);
    }

    public function update(
        UpdateProjectRequest $request,
        Workspace $workspace,
        Project $project,
    ): ProjectResource {
        $project->update($request->validated());

        return (new ProjectResource($project->refresh()))
            ->additional(['message' => 'Project updated.']);
    }
}
