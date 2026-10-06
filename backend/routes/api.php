<?php

use App\Http\Controllers\Api\V1\Auth\CurrentUserController;
use App\Http\Controllers\Api\V1\Auth\DemoLoginController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\UserAvatarController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\ChatMessageController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\SprintController;
use App\Http\Controllers\Api\V1\TaskActivityController;
use App\Http\Controllers\Api\V1\TaskCommentController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\UserNotificationController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Controllers\Api\V1\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', function () {
    return response()->json([
        'data' => [
            'service' => 'syncora-api',
            'status' => 'ok',
        ],
    ]);
})->name('api.v1.health');

Route::prefix('/v1/auth')->name('api.v1.auth.')->group(function (): void {
    Route::middleware('guest.api')->group(function (): void {
        Route::post('/register', RegisterController::class)->name('register');
        Route::post('/login', LoginController::class)->middleware('throttle:login')->name('login');
        Route::post('/demo', DemoLoginController::class)->middleware('throttle:demo-login')->name('demo');
    });

    Route::post('/forgot-password', ForgotPasswordController::class)
        ->middleware('throttle:password-reset')
        ->name('password.forgot');
    Route::post('/reset-password', ResetPasswordController::class)
        ->middleware('throttle:password-reset')
        ->name('password.reset');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/user', CurrentUserController::class)->name('user');
        Route::post('/user/avatar', [UserAvatarController::class, 'store'])->name('user.avatar.store');
        Route::delete('/user/avatar', [UserAvatarController::class, 'destroy'])->name('user.avatar.destroy');
        Route::post('/logout', LogoutController::class)->name('logout');
        Route::post('/email/verification-notification', EmailVerificationNotificationController::class)
            ->middleware('throttle:verification')
            ->name('verification.send');
        Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed:relative', 'throttle:verification'])
            ->name('verification.verify');
    });
});

Route::prefix('/v1/notifications')
    ->name('api.v1.notifications.')
    ->middleware(['auth:sanctum', 'verified'])
    ->group(function (): void {
        Route::get('/', [UserNotificationController::class, 'index'])->name('index');
        Route::patch('/read-all', [UserNotificationController::class, 'markAllRead'])->name('read-all');
        Route::patch('/{notification}/read', [UserNotificationController::class, 'markRead'])->whereNumber('notification')->name('read');
    });

Route::prefix('/v1/workspaces')
    ->name('api.v1.workspaces.')
    ->middleware(['auth:sanctum', 'verified'])
    ->group(function (): void {
        Route::get('/', [WorkspaceController::class, 'index'])->name('index');
        Route::post('/', [WorkspaceController::class, 'store'])->name('store');

        Route::prefix('/{workspace}')
            ->middleware('workspace.member')
            ->scopeBindings()
            ->group(function (): void {
                Route::get('/', [WorkspaceController::class, 'show'])->name('show');
                Route::patch('/', [WorkspaceController::class, 'update'])->name('update');
                Route::get('/members', [WorkspaceMemberController::class, 'index'])->name('members.index');
                Route::post('/members', [WorkspaceMemberController::class, 'store'])->name('members.store');
                Route::delete('/members/{membership}', [WorkspaceMemberController::class, 'destroy'])
                    ->name('members.destroy');

                Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
                Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
                Route::prefix('/projects/{project}')->group(function (): void {
                    Route::get('/', [ProjectController::class, 'show'])->name('projects.show');
                    Route::patch('/', [ProjectController::class, 'update'])->name('projects.update');
                    Route::get('/chat/messages', [ChatMessageController::class, 'index'])->name('projects.chat.messages.index');
                    Route::post('/chat/messages', [ChatMessageController::class, 'store'])->name('projects.chat.messages.store');
                    Route::get('/sprints', [SprintController::class, 'index'])->name('projects.sprints.index');
                    Route::post('/sprints', [SprintController::class, 'store'])->name('projects.sprints.store');
                    Route::get('/sprints/{sprint}', [SprintController::class, 'show'])->name('projects.sprints.show');
                    Route::patch('/sprints/{sprint}', [SprintController::class, 'update'])->name('projects.sprints.update');
                    Route::get('/tasks', [TaskController::class, 'index'])->name('projects.tasks.index');
                    Route::post('/tasks', [TaskController::class, 'store'])->name('projects.tasks.store');
                    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('projects.tasks.show');
                    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('projects.tasks.update');
                    Route::get('/tasks/{task}/comments', [TaskCommentController::class, 'index'])->name('projects.tasks.comments.index');
                    Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('projects.tasks.comments.store');
                    Route::patch('/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'update'])->name('projects.tasks.comments.update');
                    Route::delete('/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('projects.tasks.comments.destroy');
                    Route::get('/tasks/{task}/activities', [TaskActivityController::class, 'index'])->name('projects.tasks.activities.index');
                });
            });
    });
