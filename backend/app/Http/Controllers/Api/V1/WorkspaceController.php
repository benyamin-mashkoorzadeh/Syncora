<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\CreateWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WorkspaceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Workspace::class);

        /** @var User $user */
        $user = request()->user();
        $workspaces = $user->workspaces()
            ->with(['memberships' => fn ($query) => $query->where('user_id', $user->getKey())])
            ->withCount('memberships')
            ->orderBy('name')
            ->get();

        return WorkspaceResource::collection($workspaces);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        StoreWorkspaceRequest $request,
        CreateWorkspace $createWorkspace,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $workspace = $createWorkspace->handle($user, $request->string('name')->toString());

        return (new WorkspaceResource($this->loadContext($workspace, $user)))
            ->additional(['message' => 'Workspace created.'])
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Workspace $workspace): WorkspaceResource
    {
        Gate::authorize('view', $workspace);

        /** @var User $user */
        $user = request()->user();

        return new WorkspaceResource($this->loadContext($workspace, $user));
    }

    /**
     * Display the specified resource.
     */
    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): WorkspaceResource
    {
        $workspace->update($request->safe()->only('name'));

        /** @var User $user */
        $user = $request->user();

        return (new WorkspaceResource($this->loadContext($workspace, $user)))
            ->additional(['message' => 'Workspace updated.']);
    }

    private function loadContext(Workspace $workspace, User $user): Workspace
    {
        return $workspace->load([
            'memberships' => fn ($query) => $query->where('user_id', $user->getKey()),
        ])->loadCount('memberships');
    }
}
