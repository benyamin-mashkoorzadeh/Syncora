<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserNotificationResource;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class UserNotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $notifications = $user->notifications()
            ->with(['actor:id,name,avatar_path', 'project:id,workspace_id,name,slug', 'project.workspace:id,slug', 'task:id,title'])
            ->latest('id')
            ->paginate(20);

        return UserNotificationResource::collection($notifications)
            ->additional(['unread_count' => $user->notifications()->whereNull('read_at')->count()]);
    }

    public function markRead(Request $request, int $notification): UserNotificationResource
    {
        /** @var User $user */
        $user = $request->user();
        $notification = $user->notifications()->findOrFail($notification);
        Gate::authorize('update', $notification);
        $notification->markAsRead();

        return (new UserNotificationResource($this->loadResource($notification)))
            ->additional(['unread_count' => $user->notifications()->whereNull('read_at')->count()]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read.',
            'unread_count' => 0,
        ]);
    }

    private function loadResource(UserNotification $notification): UserNotification
    {
        return $notification->load([
            'actor:id,name,avatar_path',
            'project:id,workspace_id,name,slug',
            'project.workspace:id,slug',
            'task:id,title',
        ]);
    }
}
