<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Sprints\UpdateSprint;
use App\Enums\SprintStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sprint\StoreSprintRequest;
use App\Http\Requests\Sprint\UpdateSprintRequest;
use App\Http\Resources\SprintResource;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SprintController extends Controller
{
    public function index(Workspace $workspace, Project $project): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Sprint::class, $project]);

        return SprintResource::collection(
            $project->sprints()
                ->orderByRaw("case status when 'active' then 0 when 'planned' then 1 else 2 end")
                ->orderBy('start_date')
                ->paginate(20),
        );
    }

    public function store(
        StoreSprintRequest $request,
        Workspace $workspace,
        Project $project,
    ): JsonResponse {
        $sprint = $project->sprints()->create([
            ...$request->validated(),
            'status' => SprintStatus::Planned,
        ]);

        return (new SprintResource($sprint))
            ->additional(['message' => 'Sprint created.'])
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function show(Workspace $workspace, Project $project, Sprint $sprint): SprintResource
    {
        Gate::authorize('view', $sprint);

        return new SprintResource($sprint);
    }

    public function update(
        UpdateSprintRequest $request,
        Workspace $workspace,
        Project $project,
        Sprint $sprint,
        UpdateSprint $updateSprint,
    ): SprintResource {
        $sprint = $updateSprint->handle($project, $sprint, $request->validated());

        return (new SprintResource($sprint))
            ->additional(['message' => 'Sprint updated.']);
    }
}
