<?php

namespace App\Actions\Chat;

use App\Events\ProjectChatMessageCreated;
use App\Models\ChatMessage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SendProjectMessage
{
    public function handle(Project $project, User $sender, string $body): ChatMessage
    {
        return DB::transaction(function () use ($project, $sender, $body): ChatMessage {
            $message = $project->chatMessages()->create([
                'sender_id' => $sender->id,
                'body' => trim($body),
            ])->load('sender:id,name,avatar_path');

            event((new ProjectChatMessageCreated($project->id, [
                'id' => $message->id,
                'body' => $message->body,
                'sender' => [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'avatar_url' => $message->sender->avatarUrl(),
                ],
                'created_at' => $message->created_at->toISOString(),
            ]))->dontBroadcastToCurrentUser());

            return $message;
        });
    }
}
