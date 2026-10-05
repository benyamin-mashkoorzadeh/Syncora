<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Users\ReplaceUserAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateAvatarRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAvatarController extends Controller
{
    public function store(UpdateAvatarRequest $request, ReplaceUserAvatar $replaceUserAvatar): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $user = $replaceUserAvatar->handle($user, $request->file('avatar'));

        return (new UserResource($user))->additional(['message' => 'Profile image updated.']);
    }

    public function destroy(Request $request, ReplaceUserAvatar $replaceUserAvatar): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user = $replaceUserAvatar->remove($user);

        return response()->json([
            'data' => new UserResource($user),
            'message' => 'Profile image removed.',
        ]);
    }
}
