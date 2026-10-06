<?php

namespace App\Actions\Demo;

use App\Enums\SprintStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Models\Sprint;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProvisionDemoEnvironment
{
    public const USER_EMAIL = 'explore@demo.syncora.invalid';

    public const WORKSPACE_SLUG = 'syncora-demo';

    public const WORKSPACE_NAME = 'Northstar Product Studio';

    private const COLLABORATORS = [
        ['name' => 'Maya Chen', 'email' => 'maya@demo.syncora.invalid'],
        ['name' => 'Jordan Lee', 'email' => 'jordan@demo.syncora.invalid'],
    ];

    public function handle(bool $reset = false): Workspace
    {
        return DB::transaction(function () use ($reset): Workspace {
            $owner = $this->demoUser(self::USER_EMAIL, 'Alex Morgan');
            $collaborators = collect(self::COLLABORATORS)
                ->map(fn (array $identity): User => $this->demoUser($identity['email'], $identity['name']));

            $workspace = Workspace::query()->firstOrCreate(
                ['slug' => self::WORKSPACE_SLUG],
                ['name' => self::WORKSPACE_NAME],
            );

            $members = collect([$owner, ...$collaborators]);
            $workspace->memberships()->updateOrCreate(
                ['user_id' => $owner->id],
                ['role' => WorkspaceRole::Owner],
            );
            foreach ($collaborators as $collaborator) {
                $workspace->memberships()->updateOrCreate(
                    ['user_id' => $collaborator->id],
                    ['role' => WorkspaceRole::Member],
                );
            }

            if ($reset) {
                $workspace->memberships()->whereNotIn('user_id', $members->pluck('id'))->delete();
                $workspace->projects()->get()->each->delete();
            }

            $this->provisionProject($workspace, $owner, $collaborators->values()->all());

            return $workspace->refresh();
        });
    }

    private function demoUser(string $email, string $name): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            return $user;
        }

        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(64)),
        ])->save();

        return $user;
    }

    /** @param list<User> $collaborators */
    private function provisionProject(Workspace $workspace, User $owner, array $collaborators): void
    {
        $project = $workspace->projects()->updateOrCreate(
            ['slug' => 'guided-onboarding'],
            [
                'name' => 'Guided Onboarding',
                'description' => 'A focused release initiative to help new teams reach their first shared win with clarity and confidence.',
            ],
        );

        $today = Carbon::today();
        $activeSprint = $project->sprints()->updateOrCreate(
            ['name' => 'Sprint 07 — Confident first run'],
            [
                'goal' => 'Turn the first workspace session into a clear, collaborative path to value.',
                'start_date' => $today->copy()->subDays(3),
                'end_date' => $today->copy()->addDays(8),
                'status' => SprintStatus::Active,
            ],
        );
        $completedSprint = $project->sprints()->updateOrCreate(
            ['name' => 'Sprint 06 — Product foundations'],
            [
                'goal' => 'Establish the dependable planning and collaboration foundation for launch.',
                'start_date' => $today->copy()->subDays(18),
                'end_date' => $today->copy()->subDays(5),
                'status' => SprintStatus::Completed,
            ],
        );

        [$maya, $jordan] = $collaborators;
        $definitions = [
            ['title' => 'Validate guided workspace entry', 'description' => 'Walk through the zero-setup demo path and make the first project context immediately understandable.', 'status' => TaskStatus::Todo, 'priority' => TaskPriority::High, 'position' => 0, 'sprint' => $activeSprint, 'assignee' => $owner],
            ['title' => 'Polish responsive empty states', 'description' => 'Keep loading, empty, and recovery states calm and useful across narrow and wide screens.', 'status' => TaskStatus::Todo, 'priority' => TaskPriority::Medium, 'position' => 1, 'sprint' => $activeSprint, 'assignee' => $maya],
            ['title' => 'Prepare release readiness checklist', 'description' => 'Capture the final operational checks for sessions, Reverb, storage, backups, and application health.', 'status' => TaskStatus::Todo, 'priority' => TaskPriority::Urgent, 'position' => 2, 'sprint' => null, 'assignee' => $jordan],
            ['title' => 'Instrument onboarding feedback', 'description' => 'Define the focused signals that show whether a new team understands its next action.', 'status' => TaskStatus::InProgress, 'priority' => TaskPriority::High, 'position' => 0, 'sprint' => $activeSprint, 'assignee' => $maya],
            ['title' => 'Refine collaboration presence', 'description' => 'Keep live teammate context visible without allowing presence to crowd the work itself.', 'status' => TaskStatus::InProgress, 'priority' => TaskPriority::Medium, 'position' => 1, 'sprint' => $activeSprint, 'assignee' => $jordan],
            ['title' => 'Map the first-run journey', 'description' => 'Align the team on the shortest useful path from arrival to a shared project plan.', 'status' => TaskStatus::Done, 'priority' => TaskPriority::Medium, 'position' => 0, 'sprint' => $completedSprint, 'assignee' => $owner],
            ['title' => 'Ship persistent project chat', 'description' => 'Keep delivery decisions alongside project work with authoritative history and live updates.', 'status' => TaskStatus::Done, 'priority' => TaskPriority::High, 'position' => 1, 'sprint' => $completedSprint, 'assignee' => $maya],
            ['title' => 'Align accessible interaction states', 'description' => 'Confirm focus, contrast, keyboard movement, and reduced-motion behavior across core workflows.', 'status' => TaskStatus::Done, 'priority' => TaskPriority::Low, 'position' => 2, 'sprint' => $activeSprint, 'assignee' => $jordan],
        ];

        $tasks = collect($definitions)->mapWithKeys(function (array $definition) use ($project, $owner): array {
            /** @var Sprint|null $sprint */
            $sprint = $definition['sprint'];
            /** @var User $assignee */
            $assignee = $definition['assignee'];
            $task = $project->tasks()->updateOrCreate(
                ['title' => $definition['title']],
                [
                    'description' => $definition['description'],
                    'status' => $definition['status'],
                    'priority' => $definition['priority'],
                    'position' => $definition['position'],
                    'sprint_id' => $sprint?->id,
                    'assignee_id' => $assignee->id,
                ],
            );
            $task->activities()->updateOrCreate(
                ['actor_id' => $owner->id, 'action' => 'task.created'],
                ['metadata' => ['title' => $task->title]],
            );

            if ($task->status !== TaskStatus::Todo) {
                $task->activities()->updateOrCreate(
                    ['actor_id' => $owner->id, 'action' => 'task.status_changed'],
                    ['metadata' => [
                        'field' => 'status',
                        'from' => ['value' => TaskStatus::Todo->value, 'label' => 'To Do'],
                        'to' => ['value' => $task->status->value, 'label' => $task->status === TaskStatus::Done ? 'Done' : 'In Progress'],
                    ]],
                );
            }

            return [$task->title => $task];
        });

        foreach ([
            [$tasks['Validate guided workspace entry'], $maya, 'The entry flow feels clear. I would keep the project context visible as soon as the workspace opens.'],
            [$tasks['Instrument onboarding feedback'], $jordan, 'I added a concise measurement plan so the team can review useful signals without dashboard noise.'],
            [$tasks['Ship persistent project chat'], $owner, 'History and live delivery are both verified. Reconnect recovery remains backed by the REST API.'],
        ] as [$task, $author, $body]) {
            $comment = $task->comments()->updateOrCreate([
                'author_id' => $author->id,
                'body' => $body,
            ]);
            $task->activities()->updateOrCreate(
                ['actor_id' => $author->id, 'action' => 'comment.added'],
                ['metadata' => ['comment_id' => $comment->id]],
            );
        }

        foreach ([
            [$owner, 'Welcome to the launch room. Let’s keep decisions close to the work and leave the board clearer than we found it.'],
            [$maya, 'Responsive review is complete. I’m tightening the final empty and recovery states today.'],
            [$jordan, 'Reverb, session, and storage checks are captured in the release-readiness task.'],
            [$owner, 'Perfect. Once the onboarding feedback task lands, Sprint 07 is ready for review.'],
        ] as [$sender, $body]) {
            $project->chatMessages()->updateOrCreate(
                ['sender_id' => $sender->id, 'body' => $body],
            );
        }
    }
}
