<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class UserNotificationCreated implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $recipientId,
        public readonly int $notificationId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->recipientId}.notifications");
    }

    public function broadcastAs(): string
    {
        return 'user.notification.created';
    }

    /** @return array{notification_id: int} */
    public function broadcastWith(): array
    {
        return ['notification_id' => $this->notificationId];
    }
}
