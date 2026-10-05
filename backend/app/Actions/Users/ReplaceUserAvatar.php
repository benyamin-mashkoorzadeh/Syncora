<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ReplaceUserAvatar
{
    public function handle(User $user, UploadedFile $avatar): User
    {
        $newPath = $avatar->store('avatars', 'public');

        if (! $newPath) {
            throw new RuntimeException('The avatar could not be stored.');
        }

        try {
            $previousPath = DB::transaction(function () use ($user, $newPath): ?string {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
                $previousPath = $lockedUser->getRawOriginal('avatar_path');
                $lockedUser->avatar_path = $newPath;
                $lockedUser->save();

                return $previousPath;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newPath);

            throw $exception;
        }

        if ($previousPath && $previousPath !== $newPath) {
            Storage::disk('public')->delete($previousPath);
        }

        return $user->refresh();
    }

    public function remove(User $user): User
    {
        $previousPath = DB::transaction(function () use ($user): ?string {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $path = $lockedUser->getRawOriginal('avatar_path');

            if ($path) {
                $lockedUser->avatar_path = null;
                $lockedUser->save();
            }

            return $path;
        });

        if ($previousPath) {
            Storage::disk('public')->delete($previousPath);
        }

        return $user->refresh();
    }
}
