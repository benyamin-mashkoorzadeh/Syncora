<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Chat\SendProjectMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StoreChatMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ChatMessageController extends Controller
{
    public function index(Workspace $workspace, Project $project): AnonymousResourceCollection
    {
        Gate::authorize('view', $project);

        return ChatMessageResource::collection(
            $project->chatMessages()->with('sender:id,name,avatar_path')->latest('id')->paginate(30),
        );
    }

    public function store(
        StoreChatMessageRequest $request,
        Workspace $workspace,
        Project $project,
        SendProjectMessage $sendProjectMessage,
    ): JsonResponse {
        /** @var User $sender */
        $sender = $request->user();
        $message = $sendProjectMessage->handle(
            $project,
            $sender,
            $request->string('body')->toString(),
        );

        return (new ChatMessageResource($message))
            ->additional(['message' => 'Message sent.'])
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
