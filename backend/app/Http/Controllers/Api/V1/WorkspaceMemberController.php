<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\AddWorkspaceMember;
use App\Actions\Workspaces\RemoveWorkspaceMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\AddWorkspaceMemberRequest;
use App\Http\Resources\WorkspaceMemberResource;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WorkspaceMemberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('viewMembers', $workspace);

        $memberships = $workspace->memberships()
            ->with('user:id,name,email,email_verified_at,avatar_path')
            ->orderByRaw("case when role = 'owner' then 0 else 1 end")
            ->orderBy('created_at')
            ->get();

        return WorkspaceMemberResource::collection($memberships);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        AddWorkspaceMemberRequest $request,
        Workspace $workspace,
        AddWorkspaceMember $addWorkspaceMember,
    ): JsonResponse {
        $membership = $addWorkspaceMember
            ->handle($workspace, $request->string('email')->toString())
            ->load('user:id,name,email,email_verified_at,avatar_path');

        return (new WorkspaceMemberResource($membership))
            ->additional(['message' => 'Member added.'])
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    /**
     * Remove the specified member from the workspace.
     */
    public function destroy(
        Workspace $workspace,
        WorkspaceMembership $membership,
        RemoveWorkspaceMember $removeWorkspaceMember,
    ): JsonResponse {
        Gate::authorize('removeMember', [$workspace, $membership]);
        $removeWorkspaceMember->handle($membership);

        return response()->json([
            'message' => 'Member removed.',
        ]);
    }
}
