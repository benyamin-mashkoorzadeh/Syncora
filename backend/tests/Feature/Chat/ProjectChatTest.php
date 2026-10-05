<?php

namespace Tests\Feature\Chat;

use App\Actions\Chat\SendProjectMessage;
use App\Enums\WorkspaceRole;
use App\Events\ProjectChatMessageCreated;
use App\Models\ChatMessage;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProjectChatTest extends TestCase
{
    use DatabaseMigrations;

    public function test_workspace_member_can_send_a_trimmed_message_with_server_derived_sender(): void
    {
        [$workspace, $project, $member] = $this->projectWithUser();
        $otherUser = User::factory()->create();
        Event::fake([ProjectChatMessageCreated::class]);

        $response = $this->actingAs($member)->postJson($this->messagesUrl($workspace, $project), [
            'body' => '  Ready for the sprint review.  ',
            'sender_id' => $otherUser->id,
            'project_id' => Project::factory()->create()->id,
        ], $this->spaHeaders())->assertCreated()
            ->assertJsonPath('data.body', 'Ready for the sprint review.')
            ->assertJsonPath('data.sender.id', $member->id)
            ->assertJsonPath('data.sender.name', $member->name)
            ->assertJsonMissingPath('data.sender.email')
            ->assertJsonMissingPath('data.sender_id')
            ->assertJsonMissingPath('data.project_id');

        $messageId = $response->json('data.id');
        $this->assertDatabaseHas('chat_messages', [
            'id' => $messageId,
            'project_id' => $project->id,
            'sender_id' => $member->id,
            'body' => 'Ready for the sprint review.',
        ]);

        Event::assertDispatched(ProjectChatMessageCreated::class, 1);
        Event::assertDispatched(ProjectChatMessageCreated::class, function (ProjectChatMessageCreated $event) use ($messageId, $member, $project): bool {
            return $event->projectId === $project->id
                && $event->message === [
                    'id' => $messageId,
                    'body' => 'Ready for the sprint review.',
                    'sender' => ['id' => $member->id, 'name' => $member->name, 'avatar_url' => null],
                    'created_at' => $event->message['created_at'],
                ];
        });
    }

    public function test_message_history_is_project_scoped_paginated_and_safe(): void
    {
        [$workspace, $project, $member] = $this->projectWithUser();
        $otherProject = Project::factory()->for($workspace)->create();
        ChatMessage::factory()->count(35)->for($project)->for($member, 'sender')->create();
        $hidden = ChatMessage::factory()->for($otherProject)->for($member, 'sender')->create();
        $newest = $project->chatMessages()->latest('id')->firstOrFail();

        $this->actingAs($member)->getJson($this->messagesUrl($workspace, $project), $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(30, 'data')
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 30)
            ->assertJsonMissing(['id' => $hidden->id])
            ->assertJsonMissingPath('data.0.sender.email')
            ->assertJsonMissingPath('data.0.sender_id')
            ->assertJsonMissingPath('data.0.project_id');

        $this->actingAs($member)->getJson($this->messagesUrl($workspace, $project).'?page=2', $this->spaHeaders())
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_chat_routes_require_authentication_verification_and_current_workspace_membership(): void
    {
        [$workspace, $project, $member] = $this->projectWithUser();
        $outsider = User::factory()->create();
        $unverified = User::factory()->unverified()->create();
        WorkspaceMembership::factory()->for($workspace)->for($unverified)->create();
        $otherWorkspace = Workspace::factory()->create();

        $this->getJson($this->messagesUrl($workspace, $project), $this->spaHeaders())->assertUnauthorized();
        $this->actingAs($unverified)->getJson($this->messagesUrl($workspace, $project), $this->spaHeaders())->assertForbidden();
        $this->actingAs($outsider)->getJson($this->messagesUrl($workspace, $project), $this->spaHeaders())->assertNotFound();
        $this->actingAs($member)->getJson($this->messagesUrl($otherWorkspace, $project), $this->spaHeaders())->assertNotFound();
        $this->actingAs($outsider)->postJson($this->messagesUrl($workspace, $project), [
            'body' => 'Must not be stored.',
        ], $this->spaHeaders())->assertNotFound();

        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_message_body_is_required_trimmed_and_bounded(): void
    {
        [$workspace, $project, $member] = $this->projectWithUser();

        $this->actingAs($member)->postJson($this->messagesUrl($workspace, $project), [
            'body' => '   ',
        ], $this->spaHeaders())->assertUnprocessable()->assertJsonValidationErrors('body');

        $this->actingAs($member)->postJson($this->messagesUrl($workspace, $project), [
            'body' => str_repeat('x', 4001),
        ], $this->spaHeaders())->assertUnprocessable()->assertJsonValidationErrors('body');

        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_chat_event_uses_the_existing_presence_channel_and_safe_contract(): void
    {
        [$workspace, $project, $member] = $this->projectWithUser();
        Event::fake([ProjectChatMessageCreated::class]);
        $message = app(SendProjectMessage::class)->handle($project, $member, 'A durable message.');
        $event = new ProjectChatMessageCreated($project->id, [
            'id' => $message->id,
            'body' => $message->body,
            'sender' => ['id' => $member->id, 'name' => $member->name, 'avatar_url' => null],
            'created_at' => $message->created_at->toISOString(),
        ]);

        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        $this->assertInstanceOf(ShouldRescue::class, $event);
        $this->assertInstanceOf(PresenceChannel::class, $event->broadcastOn());
        $this->assertSame("presence-project.{$project->id}.collaboration", $event->broadcastOn()->name);
        $this->assertSame('project.chat.message.created', $event->broadcastAs());
        $this->assertSame(['project_id', 'message'], array_keys($event->broadcastWith()));
        $this->assertSame(['id', 'body', 'sender', 'created_at'], array_keys($event->broadcastWith()['message']));
        $this->assertArrayNotHasKey('email', $event->broadcastWith()['message']['sender']);
    }

    /** @return array{Workspace, Project, User} */
    private function projectWithUser(WorkspaceRole $role = WorkspaceRole::Member): array
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->for($workspace)->create();
        $user = User::factory()->create();
        WorkspaceMembership::factory()->for($workspace)->for($user)->create(['role' => $role]);

        return [$workspace, $project, $user];
    }

    private function messagesUrl(Workspace $workspace, Project $project): string
    {
        return "/api/v1/workspaces/{$workspace->slug}/projects/{$project->slug}/chat/messages";
    }
}
