<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ProjectChatMessageCreated implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    /** @param array{id: int, body: string, sender: array{id: int, name: string, avatar_url: string|null}, created_at: string} $message */
    public function __construct(
        public readonly int $projectId,
        public readonly array $message,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("project.{$this->projectId}.collaboration");
    }

    public function broadcastAs(): string
    {
        return 'project.chat.message.created';
    }

    /** @return array{project_id: int, message: array{id: int, body: string, sender: array{id: int, name: string, avatar_url: string|null}, created_at: string}} */
    public function broadcastWith(): array
    {
        return [
            'project_id' => $this->projectId,
            'message' => $this->message,
        ];
    }
}
