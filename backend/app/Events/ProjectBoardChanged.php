<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ProjectBoardChanged implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $projectId,
        public readonly int $taskId,
        public readonly string $change,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("project.{$this->projectId}.collaboration");
    }

    public function broadcastAs(): string
    {
        return 'project.board.changed';
    }

    /** @return array{project_id: int, task_id: int, change: string} */
    public function broadcastWith(): array
    {
        return [
            'project_id' => $this->projectId,
            'task_id' => $this->taskId,
            'change' => $this->change,
        ];
    }
}
