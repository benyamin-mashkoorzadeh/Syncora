<?php

namespace Tests\Feature\Notification;

use App\Actions\Notifications\CreateUserNotification;
use App\Enums\TaskPriority;
use App\Enums\UserNotificationType;
use App\Enums\WorkspaceRole;
use App\Events\UserNotificationCreated;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UserNotificationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_task_assignment_creates_one_notification_for_another_current_member(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $recipient = $this->addUser($workspace);
        $task = Task::factory()->for($project)->create();
        Event::fake([UserNotificationCreated::class]);

        $this->actingAs($owner)->patchJson($this->taskUrl($workspace, $project, $task), [
            'assignee_id' => $recipient->id,
        ], $this->spaHeaders())->assertOk();

        $notification = UserNotification::query()->sole();
        $this->assertSame($recipient->id, $notification->recipient_id);
        $this->assertSame($owner->id, $notification->actor_id);
        $this->assertSame($task->id, $notification->task_id);
        $this->assertSame(UserNotificationType::TaskAssigned, $notification->type);
        Event::assertDispatched(UserNotificationCreated::class, 1);
        Event::assertDispatched(UserNotificationCreated::class, fn (UserNotificationCreated $event): bool => $event->recipientId === $recipient->id
            && $event->notificationId === $notification->id);

        $this->actingAs($owner)->patchJson($this->taskUrl($workspace, $project, $task), [
            'assignee_id' => $recipient->id,
        ], $this->spaHeaders())->assertOk();
        $this->assertDatabaseCount('user_notifications', 1);
        Event::assertDispatched(UserNotificationCreated::class, 1);
    }

    public function test_assignment_on_creation_notifies_the_recipient_but_self_assignment_does_not(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $recipient = $this->addUser($workspace);

        $this->actingAs($owner)->postJson($this->tasksUrl($workspace, $project), [
            'title' => 'Prepare the review',
            'priority' => TaskPriority::High->value,
            'assignee_id' => $recipient->id,
        ], $this->spaHeaders())->assertCreated();

        $this->actingAs($owner)->postJson($this->tasksUrl($workspace, $project), [
            'title' => 'Owner follow-up',
            'priority' => TaskPriority::Medium->value,
            'assignee_id' => $owner->id,
        ], $this->spaHeaders())->assertCreated();

        $this->assertDatabaseCount('user_notifications', 1);
        $this->assertSame($recipient->id, UserNotification::query()->sole()->recipient_id);
    }

    public function test_comment_notifies_another_assignee_once_and_never_notifies_the_author(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $recipient = $this->addUser($workspace);
        $commenter = $this->addUser($workspace);
        $task = Task::factory()->for($project)->create(['assignee_id' => $recipient->id]);
        Event::fake([UserNotificationCreated::class]);

        $response = $this->actingAs($commenter)->postJson($this->commentsUrl($workspace, $project, $task), [
            'body' => 'The acceptance criteria are ready.',
        ], $this->spaHeaders())->assertCreated();

        $notification = UserNotification::query()->sole();
        $this->assertSame($recipient->id, $notification->recipient_id);
        $this->assertSame($commenter->id, $notification->actor_id);
        $this->assertSame(UserNotificationType::TaskCommentAdded, $notification->type);
        $this->assertStringNotContainsString('acceptance criteria', $notification->deduplication_key);

        $comment = TaskComment::query()->findOrFail($response->json('data.id'));
        app(CreateUserNotification::class)->taskCommentAdded($task, $comment, $commenter, $recipient);
        $this->assertDatabaseCount('user_notifications', 1);
        Event::assertDispatched(UserNotificationCreated::class, 1);

        $this->actingAs($recipient)->postJson($this->commentsUrl($workspace, $project, $task), [
            'body' => 'I am responding to my own assigned task.',
        ], $this->spaHeaders())->assertCreated();
        $this->assertDatabaseCount('user_notifications', 1);
    }

    public function test_removed_or_unassigned_users_do_not_receive_comment_notifications(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $removed = $this->addUser($workspace);
        $commenter = $this->addUser($workspace);
        $assignedTask = Task::factory()->for($project)->create(['assignee_id' => $removed->id]);
        $unassignedTask = Task::factory()->for($project)->create(['assignee_id' => null]);
        $removed->workspaceMemberships()->where('workspace_id', $workspace->id)->delete();

        $this->actingAs($commenter)->postJson($this->commentsUrl($workspace, $project, $assignedTask), [
            'body' => 'Former members must not receive this.',
        ], $this->spaHeaders())->assertCreated();
        $this->actingAs($commenter)->postJson($this->commentsUrl($workspace, $project, $unassignedTask), [
            'body' => 'There is no recipient for this.',
        ], $this->spaHeaders())->assertCreated();

        $this->assertDatabaseCount('user_notifications', 0);
    }

    public function test_recipient_can_paginate_and_read_only_their_notifications(): void
    {
        [$workspace, $project, $owner] = $this->projectWithUser(WorkspaceRole::Owner);
        $recipient = $this->addUser($workspace);
        $other = $this->addUser($workspace);
        $task = Task::factory()->for($project)->create();

        foreach (range(1, 25) as $number) {
            $this->notification($recipient, $owner, $project, $task, "recipient:{$number}");
        }
        $otherNotification = $this->notification($other, $owner, $project, $task, 'other:1');

        $this->actingAs($recipient)->getJson('/api/v1/notifications', $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('unread_count', 25)
            ->assertJsonMissing(['id' => $otherNotification->id])
            ->assertJsonMissingPath('data.0.recipient_id')
            ->assertJsonMissingPath('data.0.deduplication_key')
            ->assertJsonMissingPath('data.0.actor.email');

        $this->actingAs($recipient)->getJson('/api/v1/notifications?page=2', $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $first = $recipient->notifications()->firstOrFail();
        $this->actingAs($recipient)->patchJson("/api/v1/notifications/{$first->id}/read", [], $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('data.is_read', true)
            ->assertJsonPath('unread_count', 24);
        $this->assertNotNull($first->refresh()->read_at);

        $this->actingAs($recipient)->patchJson("/api/v1/notifications/{$otherNotification->id}/read", [], $this->spaHeaders())
            ->assertNotFound();

        $this->actingAs($recipient)->patchJson('/api/v1/notifications/read-all', [], $this->spaHeaders())
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
        $this->assertSame(0, $recipient->notifications()->whereNull('read_at')->count());
        $this->assertNull($otherNotification->refresh()->read_at);
    }

    public function test_notification_routes_require_authentication_and_verified_email(): void
    {
        $unverified = User::factory()->unverified()->create();

        $this->getJson('/api/v1/notifications', $this->spaHeaders())->assertUnauthorized();
        $this->actingAs($unverified)->getJson('/api/v1/notifications', $this->spaHeaders())->assertForbidden();
        $this->actingAs($unverified)->patchJson('/api/v1/notifications/read-all', [], $this->spaHeaders())->assertForbidden();
    }

    public function test_notification_channel_and_event_are_private_safe_and_recipient_scoped(): void
    {
        $recipient = User::factory()->create();
        $other = User::factory()->create();
        $unverified = User::factory()->unverified()->create();
        $this->useReverbBroadcaster();
        $payload = [
            'socket_id' => '1234.5678',
            'channel_name' => "private-user.{$recipient->id}.notifications",
        ];

        $this->postJson('/broadcasting/auth', $payload, $this->spaHeaders())->assertForbidden();
        $this->actingAs($other)->postJson('/broadcasting/auth', $payload, $this->spaHeaders())->assertForbidden();
        $this->actingAs($unverified)->postJson('/broadcasting/auth', $payload, $this->spaHeaders())->assertForbidden();
        $this->actingAs($recipient)->postJson('/broadcasting/auth', $payload, $this->spaHeaders())->assertOk();

        $event = new UserNotificationCreated($recipient->id, 99);
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        $this->assertInstanceOf(ShouldRescue::class, $event);
        $this->assertInstanceOf(PrivateChannel::class, $event->broadcastOn());
        $this->assertSame("private-user.{$recipient->id}.notifications", $event->broadcastOn()->name);
        $this->assertSame('user.notification.created', $event->broadcastAs());
        $this->assertSame(['notification_id' => 99], $event->broadcastWith());
    }

    /** @return array{Workspace, Project, User} */
    private function projectWithUser(WorkspaceRole $role): array
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->for($workspace)->create();
        $user = $this->addUser($workspace, $role);

        return [$workspace, $project, $user];
    }

    private function addUser(Workspace $workspace, WorkspaceRole $role = WorkspaceRole::Member): User
    {
        $user = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($user)->create(['role' => $role]);

        return $user;
    }

    private function notification(User $recipient, User $actor, Project $project, Task $task, string $key): UserNotification
    {
        return UserNotification::query()->create([
            'recipient_id' => $recipient->id,
            'actor_id' => $actor->id,
            'project_id' => $project->id,
            'task_id' => $task->id,
            'type' => UserNotificationType::TaskAssigned,
            'deduplication_key' => $key,
        ]);
    }

    private function tasksUrl(Workspace $workspace, Project $project): string
    {
        return "/api/v1/workspaces/{$workspace->slug}/projects/{$project->slug}/tasks";
    }

    private function taskUrl(Workspace $workspace, Project $project, Task $task): string
    {
        return $this->tasksUrl($workspace, $project)."/{$task->id}";
    }

    private function commentsUrl(Workspace $workspace, Project $project, Task $task): string
    {
        return $this->taskUrl($workspace, $project, $task).'/comments';
    }

    private function useReverbBroadcaster(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        app(BroadcastManager::class)->forgetDrivers();
        require base_path('routes/channels.php');
    }
}
